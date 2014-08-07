<?php
namespace chaos\source\mongo;

use chaos\SourceException;

/**
 * A data source adapter which allows you to connect to the MongoDB database engine.
 */
class MongoDb
{
    /**
     * Classes used by this class.
     *
     * @var array
     */
    protected $_classes = [
        'server'   => 'MongoClient',
        'mapper'   => 'chaos\source\mongo\Mapper',
        'response' => 'chaos\source\mongo\Response'
    ];

    /**
     * The Mongo class instance.
     *
     * @var object
     */
    public $_server = null;

    /**
     * The MongoDB object instance.
     *
     * @var object
     */
    public $_connection = null;

    /**
     * The Mapper object instance.
     *
     * @var object
     */
    public $mapper = null;

    /**
     * Instantiates the MongoDB adapter with the default connection information.
     *
     * @link http://php.net/manual/en/mongo.construct.php PHP Manual: MongoClient::__construct()
     * @param array $config All information required to connect to the database, including:
     *                      - `'host'` _string_: The IP or machine name where Mongo is running,
     *                        followed by a colon, and the port number. Defaults to `'localhost:27017'`.
     *                      - `'database'` _string_: The name of the database to connect to. Defaults to `null`.
     *                      - `'persistent'` _mixed_ : Determines a persistent connection to attach to.
     *                        See the `$options` parameter of
     *                        [`Mongo::__construct()`](http://www.php.net/manual/en/mongo.construct.php) for
     *                        more information. Defaults to `false`, meaning no persistent connection is made.
     *                      - `'login'` _string_: The database login.
     *                      - `'password'` _string_: The database password.
     *                      - `'timeout'` _integer_: The number of milliseconds a connection attempt will wait
     *                        before timing out and throwing an exception. Defaults to `100`.
     *                      - `'gridPrefix'` _string_: The default prefix for MongoDB's `chunks` and `files`
     *                        collections. Defaults to `'fs'`.
     *                      - `'replicaSet'` _string_: See the documentation for `Mongo::__construct()`. Defaults
     *                        to `false`.
     *                      - `'readPreference'` _mixed_: May either be a single value such as Mongo::RP_NEAREST,
     *                        or an array containing a read preference and a tag set such as:
     *                        array(Mongo::RP_SECONDARY_PREFERRED, array('dc' => 'east) See the documentation for
     *                        `Mongo::setReadPreference()`. Defaults to null.
     *                        Typically, these parameters are set in `Connections::add()`, when adding the
     *                        adapter to the list of active connections.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'host'           => 'localhost:27017',
            'database'       => null,
            'persistent'     => false,
            'login'          => null,
            'password'       => null,
            'timeout'        => 100,
            'gridPrefix'     => 'fs',
            'replicaSet'     => false,
            'w'              => 1,
            'wTimeoutMS'     => 10000,
            'readPreference' => null,
            'connect'        => true,
            'autoConnect'    => false,
            'classes'        => [],
            'server'         => null,
            'connection'     => null
        ];

        $this->_config = $config + $defaults;

        $this->_server = $this->_config['server'];
        $this->_connection = $this->_config['connection'];
        $this->_classes = $this->_config['classes'] + $this->_classes;

        $this->_operators += ['like' => function($key, $value) {
            return new MongoRegex($value);
        }];

        $mapper = $this->_classes['mapper'];

        $this->_mapper = new $mapper;

        if ($this->_config['autoConnect']) {
            $this->connect();
        }
    }

    /**
     * Ensures that the server connection is closed and resources are freed when the adapter
     * instance is destroyed.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->_isConnected) {
            $this->disconnect();
        }
    }

    /**
     * Connects to the Mongo server. Matches up parameters from the constructor to create a Mongo
     * database connection.
     *
     * @see lithium\data\source\MongoDb::__construct()
     * @link http://php.net/manual/en/mongo.construct.php PHP Manual: Mongo::__construct()
     * @return boolean Returns `true` the connection attempt was successful, otherwise `false`.
     */
    public function connect()
    {
        if ($this->_server && $this->_server->connected && $this->_connection) {
            return $this->_isConnected = true;
        }

        $config = $this->_config;
        $this->_isConnected = false;

        $host = is_array($config['host']) ? join(',', $config['host']) : $config['host'];
        $login = $config['login'] ? "{$config['login']}:{$config['password']}@" : '';
        $connection = "mongodb://{$login}{$host}" . ($login ? "/{$config['database']}" : '');

        $options = [
            'connect' => true,
            'connectTimeoutMS' => $config['timeout'],
            'replicaSet' => $config['replicaSet']
        ];

        try {
            if ($persist = $config['persistent']) {
                $options['persist'] = $persist === true ? 'default' : $persist;
            }
            $server = $this->_classes['server'];
            $this->_server = new $server($connection, $options);

            if ($this->_connection = $this->_server->{$config['database']}) {
                $this->_isConnected = true;
            }

            if ($prefs = $config['readPreference']) {
                $prefs = !is_array($prefs) ? [$prefs, []] : $prefs;
                $this->_server->setReadPreference($prefs[0], $prefs[1]);
            }
        } catch (Exception $e) {
            throw new SourceException("Could not connect to the database.", 503, $e);
        }
        return $this->_isConnected;
    }

    /**
     * Return formatted identifiers for fields.
     *
     * @param  array $fields Fields to be parsed.
     * @return array         Parsed fields array.
     */
    protected function execute($request)
    {
        $query = $this->_mapper->map($request->get());

        $this->_connection->command($query, null, $hash);

        $cursor = MongoCommandCursor::createFromDocument($this->_server, $hash, $cursor);

        $response = $this->_classes['response'];

        return new $response(compact('cursor'));
    }

    /**
     * Disconnect from the Mongo server.
     *
     * @return boolean True
     */
    public function disconnect()
    {
        if ($this->_server && $this->_server->connected) {
            $this->_isConnected = false;
            unset($this->connection, $this->_server);
        }
        return true;
    }

}
