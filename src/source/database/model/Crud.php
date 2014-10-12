<?php
namespace chaos\source\database\model;

use set\Set;
use chaos\SourceException;
use chaos\model\Relationship;

/**
 * The `Crud` trait implements all methods to perform CRUD database operations.
 */
trait Crud {

    /**
     * Default query parameters for the model finders.
     *
     * - `'conditions'`: The conditional query elements, e.g.
     *                   `'conditions' => ['published' => true]`
     * - `'fields'`    : The fields that should be retrieved. When set to `null`, defaults to
     *                   all fields.
     * - `'order'`     : The order in which the data will be returned, e.g. `'order' => 'ASC'`.
     * - `'limit'`     : The maximum number of records to return.
     * - `'page'`      : For pagination of data.
     * - `'with'`      : An array of relationship names to be included in the query.
     *
     * @return array
     */
    protected static $_query = [
        'conditions' => null,
        'fields'     => null,
        'order'      => null,
        'limit'      => null,
        'page'       => null,
        'with'       => []
    ];

    /**
     * The `find` method allows you to retrieve data from the connected data source.
     *
     * @param array $options Options for the query. By default, accepts:
     *                       - `conditions`: The conditional query elements, e.g.
     *                       `'conditions' => array('published' => true)`
     *                       - `fields`: The fields that should be retrieved. When set to `null`, defaults to
     *                       all fields.
     *                       - `order`: The order in which the data will be returned, e.g. `'order' => 'ASC'`.
     *                       - `limit`: The maximum number of records to return.
     *                       - `page`: For pagination of data.
     * @return object        A `Query` instance.
    */
    public static function find($options = [])
    {
        return new Query([
            'model'      => get_called_class(),
            'connection' => static::connection(),
            'query'      => Set::merge(static::$_query, $options)
        ]);
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
    public static function update($data, $conditions = [], $options = [])
    {
        $defaults = ['model' => get_called_class()];
        $options += $defaults;

        $statement = $this->connection()->sql()->statement('update');
        return static::connection()->execute((string) $statement);
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
    public static function remove($conditions = [], $options = [])
    {
        $defaults = ['model' => get_called_class()];
        $options += $defaults;

        $statement = $this->connection()->sql()->statement('delete');
        return static::connection()->execute((string) $statement);
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
    public function save($options = [])
    {
        $defaults = [
            'whitelist' => null,
            'locked' => $this->_meta['locked'],
            'with' => false
        ];
        $options += $defaults;

        if (!$this->_save('belongsTo', $options)) {
            return false;
        }

        if (($whitelist = $options['whitelist']) || $options['locked']) {
            $whitelist = $whitelist ?: array_keys($this->schema()->fields());
        }

        if ($entity->exists()) {
            $statement = $this->connection()->sql()->statement('update');
        } else {
            $statement = $this->connection()->sql()->statement('insert');
        }
        $result = static::connection()->execute((string) $statement);

        $hasRelations = ['hasManyThrough', 'hasMany', 'hasOne'];

        if (!$this->_save($hasRelations, $options)) {
            return false;
        }

        return $result;
    }

    /**
     * Save relations helper.
     *
     * @param array $types Type of relations to save.
     */
    protected function _save($types, $options = []) {
        $defaults = ['with' => false];
        $options += $defaults;

        if (!$with = Relationship::with($options['with'])) {
            return true;
        }

        $types = (array) $types;
        foreach ($types as $type) {
            foreach ($with as $relName => $value) {
                if (!($rel = static::schema()->relation($relName)) || $rel->type() !== $type) {
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
    public function delete($options = []) {
        $defaults = ['model' => get_called_class()];
        $options += $defaults;

        //Add entity conditions

        $statement = $this->connection()->sql()->statement('delete');
        $result = static::connection()->execute((string) $statement);
    }

}
