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
     * Creates the schema.
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
        $query->ifNotExists($options['soft'])
              ->table($this->_source)
              ->columns($this->fields())
              ->constraints($this->meta('constraints'))
              ->meta($this->meta('table'));

        return $this->connection()->query($query->toString());
    }

    /**
     * Inserts a records  with the given data.
     *
     * @param  mixed $data       Typically an array of key/value pairs that specify the new data with which
     *                           the records will be updated. For SQL databases, this can optionally be
     *                           an SQL fragment representing the `SET` clause of an `UPDATE` query.
     * @param  array $options    Any database-specific options to use when performing the operation.
     * @return boolean           Returns `true` if the update operation succeeded, otherwise `false`.
     */
    public function insert($data, $options = [])
    {
        $insert = $this->connection()->dialect()->statement('insert');
        $insert->into($this->source())
               ->values($data);

        return $this->connection()->query($insert->toString());
    }

    /**
     * Updates multiple records with the given data, restricted by the given set of criteria (optional).
     *
     * @param  mixed $data       Typically an array of key/value pairs that specify the new data with which
     *                           the records will be updated. For SQL databases, this can optionally be
     *                           an SQL fragment representing the `SET` clause of an `UPDATE` query.
     * @param  mixed $conditions An array of key/value pairs representing the scope of the records
     *                           to be updated.
     * @param  array $options    Any database-specific options to use when performing the operation.
     * @return boolean           Returns `true` if the update operation succeeded, otherwise `false`.
     */
    public function update($data, $conditions = [], $options = [])
    {
        $update = $this->connection()->dialect()->statement('update');
        $update->table($this->source())
               ->where($conditions)
               ->values($data);

        return $this->connection()->query($update->toString());
    }

    /**
     * Removes multiple documents or records based on a given set of criteria. **WARNING**: If no
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
     */
    public function remove($conditions = [], $options = [])
    {
        $delete = $this->connection()->dialect()->statement('delete');

        $delete->from($this->source())
               ->where($conditions);

        return $this->connection()->query($delete->toString());
    }

    /**
     * Drops the schema
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
        $query->ifExists($options['soft'])
              ->table($this->_source)
              ->cascade($options['cascade'])
              ->restrict($options['restrict']);

        return $this->connection()->query($query->toString());
    }
}
