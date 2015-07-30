## Connections

Connections need to be configured at a bootstrap level and will be used by the `Schema` instances to communicate with the datasource.

First we need to create a connection, for example let's create a PostgreSql connection:

```php
use chaos\database\adapter\PostgreSql;

$connection = new PostgreSql([
    'database' => 'mydatabase',
    'username' => 'mylogin',
    'password' => 'mypassword'
]);
```

Then the `::connection()` method of model class will be used on a base model to set the connection:

```php
use chaos\Model;

Model::connection($connection);
```

In the example above all models extending from `chaos\Model` will now use the `PostgreSql` connection instance.

> Note: this connection will be passed to `Schema` instances (at least when `::schema()` will be called on a specific model).

Sometimes you will probably need many datasources for your models. So to be able to attach different connections on your models, you will need to create as many base model as you need different connections.

To illustrate this point, let's create a base class that allow to set a dedicated connection:

```php
namespace myproject\model;

class ModelCustom extends \chaos\Model
{
    /**
     * MUST BE re-defined to be able to attach a specific connection on it.
     */
    protected static $_connection = null;
}
```

Now all models extending `ModelCustom` can be connected using the following syntax:

```php
use chaos\Model;
use myproject\http\MyApi; // A example of custom HTTP based connection

$connection = new MyApi([
    'scheme' => 'http',
    'host' => 'my.api.com',
    'socket' => 'Curl',
    'username' => 'mylogin',
    'password' => 'mypassword'
]);
ModelCustom::connection($connection);
```
