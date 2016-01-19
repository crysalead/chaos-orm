## Connections

Connections needs to be configured first at a bootstrap level.

Let's create a PostgreSql connection:

```php
use Chaos\Database\Adapter\PostgreSql;

$connection = new PostgreSql([
    'database' => 'mydatabase',
    'username' => 'mylogin',
    'password' => 'mypassword'
]);
```

Then te connection is bindined to our models using `Model::connection()`:

```php
use Chaos\Model;

Model::connection($connection);
```

Now all models extending `chaos\Model` will use the `PostgreSql` connection.

Note: under the hood the model don't use the connection directly but use it through its attached schema instance. So when `::schema()` is called on a specific model, the returned schema is correctly configured with the model's connection.

To be able to use different connections, you will need to create as many base model as you need different connections.

Let's illustrate this point with an example:

```php
namespace myproject\model;

class NewBaseModel extends \chaos\Model
{
    /**
     * Re-defining the `_connection` attribute in this base model class will
     * allow to attach a specific connection to it and its subclasses.
     */
    protected static $_connection = null;
}
```

Now all models extending `NewBaseModel` can be connected that way:

```php
use Chaos\Model;
use Myproject\Http\MyApi; // Example of a custom HTTP based connection

$connection = new MyApi([
    'scheme' => 'http',
    'host' => 'my.api.com',
    'socket' => 'Curl',
    'username' => 'mylogin',
    'password' => 'mypassword'
]);

NewBaseModel::connection($connection);
```
