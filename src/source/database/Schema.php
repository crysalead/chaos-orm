<?php
namespace chaos\source\database;

use chaos\SourceException;

class Schema extends \chaos\model\Schema
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'collector'      => 'chaos\model\Collector',
        'relationship'   => 'chaos\model\Relationship',
        'belongsTo'      => 'chaos\model\relationship\BelongsTo',
        'hasOne'         => 'chaos\model\relationship\HasOne',
        'hasMany'        => 'chaos\model\relationship\HasMany',
        'hasManyThrough' => 'chaos\model\relationship\HasManyThrough',
        'query'          => 'chaos\source\database\Query'
    ];

    /**
     * Returns a query to retrieve data from the connected data source.
     *
     * @param  array  $options Query options.
     * @return object          An instance of `Query`.
     */
    public function query($options = [])
    {
        $options += [
            'connection' => $this->connection(),
            'model' => $this->model()
        ];
        $query = $this->_classes['query'];
        if (!$options['model']) {
            throw new SourceException("Missing model for this schema, can't create a query.");
        }
        return new $query($options);
    }

    /**
     * Create the schema.
     *
     * @param  array   $options An array of options.
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function create($options = [])
    {
        $defaults = [
            'soft' => true
        ];
        $options += $defaults;

        if (!isset($this->_source)) {
            throw new SourceException("Missing table name for this schema.");
        }

        $query = $this->connection()->dialect()->statement('create table');
        $query
            ->ifNotExists($options['soft'])
            ->table($this->_source)
            ->columns($this->fields())
            ->constraints($this->meta('constraints'))
            ->meta($this->meta('table'));

        return $this->connection()->query($query->toString());
    }

    /**
     * Update multiple records or documents with the given data, restricted by the given set of
     * criteria (optional).
     *
     * @param  mixed $data       Typically an array of key/value pairs that specify the new data with which
     *                           the records will be updated. For SQL databases, this can optionally be
     *                           an SQL fragment representing the `SET` clause of an `UPDATE` query.
     * @param  mixed $conditions An array of key/value pairs representing the scope of the records
     *                           to be updated.
     * @param  array $options    Any database-specific options to use when performing the operation. See
     *                           the `delete()` method of the corresponding backend database for
     *                           available options.
     * @return boolean           Returns `true` if the update operation succeeded, otherwise `false`.
     */
    public function update($data, $conditions = [], $options = [])
    {
        $update = $this->connection()->dialect()->statement('update');

        $update->table($this->source())
            ->where($conditions)
            ->values($data);

        return $this->connection()->execute($update->toString());
    }

    /**
     * Remove multiple documents or records based on a given set of criteria. **WARNING**: If no
     * criteria are specified, or if the criteria (`$conditions`) is an empty value (i.e. an empty
     * array or `null`), all the data in the backend data source (i.e. table or collection) _will_
     * be deleted.
     *
     * @param mixed $conditions An array of key/value pairs representing the scope of the records or
     *              documents to be deleted.
     * @param array $options Any database-specific options to use when performing the operation. See
     *              the `delete()` method of the corresponding backend database for available
     *              options.
     * @return boolean Returns `true` if the remove operation succeeded, otherwise `false`.
     * @filter
     */
    public function remove($conditions = [], $options = [])
    {
        $statement = $this->connection()->dialect()->statement('delete');

        $update->table($this->source())
            ->where($conditions);

        return $this->connection()->execute($statement->toString());
    }

    /**
     * Drop the schema
     *
     * @param  array   $options An array of options.
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function drop($options = [])
    {
        $defaults = [
            'soft'     => true,
            'cascade'  => false,
            'restrict' => false
        ];
        $options += $defaults;

        if (!isset($this->_source)) {
            throw new SourceException("Missing table name for this schema.");
        }
        $query = $this->connection()->dialect()->statement('drop table');
        $query
            ->ifExists($options['soft'])
            ->table($this->_source)
            ->cascade($options['cascade'])
            ->restrict($options['restrict']);

        return $this->connection()->query($query->toString());
    }

    /**
     * An instance method (called on record and document objects) to create or update the record or
     * document in the database.
     *
     * For example, to create a new record or document:
     * {{{
     * $post = Posts::create(); // Creates a new object, which doesn't exist in the database yet
     * $post->title = "My post";
     * $success = $post->save();
     * }}}
     *
     * It is also used to update existing database objects, as in the following:
     * {{{
     * $post = Posts::first($id);
     * $post->title = "Revised title";
     * $success = $post->save();
     * }}}
     *
     * By default, an object's data will be checked against the validation rules of the model it is
     * bound to. Any validation errors that result can then be accessed through the `errors()`
     * method.
     *
     * {{{
     * if (!$post->save($someData)) {
     *     return array('errors' => $post->errors());
     * }
     * }}}
     *
     * To override the validation checks and save anyway, you can pass the `'validate'` option:
     *
     * {{{
     * $post->title = "We Don't Need No Stinkin' Validation";
     * $post->body = "I know what I'm doing.";
     * $post->save(null, array('validate' => false));
     * }}}
     *
     * @param array $data    Any data that should be assigned to the record before it is saved.
     * @param array $options Options:
     *                       - `'whitelist'` _array_:   An array of fields that are allowed to
     *                          be saved to this record.
     *                       - `'locked'`    _boolean_: Lock data to the schema fields.
     *                       - `'with'`      _boolean_: List of relations to save.
     * @return boolean       Returns `true` on a successful save operation, `false` on failure.
     */
    public function save($entity, $options = [])
    {
        $defaults = [
            'whitelist' => null,
            'locked' => $this->_locked,
            'with' => false
        ];
        $options += $defaults;

        // TODO
        // if (!$entity->modified()) {
        //     return true;
        // }

        if (!$this->_save($entity, 'belongsTo', $options)) {
            return false;
        }

        if (($whitelist = $options['whitelist']) || $options['locked']) {
            $whitelist = $whitelist ?: array_keys($this->fields());
        }

        $exclude = array_diff($this->relations(), array_keys($this->fields()));

        $connection = $this->connection();
        $source = $this->source();

        if ($entity->exists()) {
            $statement = $connection->dialect()->statement('update');
            $statement->table($source);
        } else {
            $statement = $connection->dialect()->statement('insert');
            $statement->into($source);
        }
        $statement->values(array_diff_key($entity->data(), array_fill_keys($exclude, true)));
        $result = $connection->query($statement->toString());

        if (!$entity->exists()) {
            $entity->sync($connection->lastInsertId(), [], ['exists' => true]);
        }

        $hasRelations = ['hasManyThrough', 'hasMany', 'hasOne'];

        if (!$this->_save($entity, $hasRelations, $options)) {
            return false;
        }
        return $result;
    }

    /**
     * Save relations helper.
     *
     * @param array $types Type of relations to save.
     */
    protected function _save($entity, $types, $options = [])
    {
        $defaults = ['with' => false];
        $options += $defaults;

        if (!$with = $this->with($options['with'])) {
            return true;
        }

        $types = (array) $types;
        foreach ($types as $type) {
            foreach ($with as $relName => $value) {
                if (!($rel = $this->relation($relName)) || $rel->type() !== $type) {
                    continue;
                }
                if (!$rel->save($entity, ['with' => $value] + $options)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Deletes the data associated with the current `Model`.
     *
     * @param object $entity Entity to delete.
     * @param array $options Options.
     * @return boolean Success.
     * @filter
     */
    public function delete($entity, $options = [])
    {
        //Adds entity conditions
        $statement = $this->connection()->dialect()->statement('delete');
        $result = $this->connection()->execute($statement->toString());
    }
}
