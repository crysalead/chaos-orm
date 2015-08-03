## Connections

Connections comes from a datasource library. It need to be configured at a bootstrap level and will be used by the `Schema` instances to communicate with the datasource.

Let's create a PostgreSql connection:

```php
use chaos\database\adapter\PostgreSql;

$connection = new PostgreSql([
    'database' => 'mydatabase',
    'username' => 'mylogin',
    'password' => 'mypassword'
]);
```

The we need to attach it to our models using `::connection()`:

```php
use chaos\Model;

Model::connection($connection);
```

In the example above all models extending from `chaos\Model` will now use the `PostgreSql` connection instance.

> Note: under the hood this connection will be injected to models' schema instances, so when `::schema()` will be called on a specific model, the returned schema will be correctly configured with the connection.

Sometimes you will probably need different datasources for your Persistence Model. To be able to use different connections, you will need to create as many base model as you need different connections.

Let's illustrate this point with an example:

```php
namespace myproject\model;

class ModelCustom extends \chaos\Model
{
    /**
     * Re-defining the `_connection` attribute in a base model class will
     * allow to attach a specific connection to it and all its subclasses.
     */
    protected static $_connection = null;
}
```

So now all models extending `ModelCustom` can be connected that way:

```php
use chaos\Model;
use myproject\http\MyApi; // Example of a custom HTTP based connection

$connection = new MyApi([
    'scheme' => 'http',
    'host' => 'my.api.com',
    'socket' => 'Curl',
    'username' => 'mylogin',
    'password' => 'mypassword'
]);

ModelCustom::connection($connection);
```
