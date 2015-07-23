## Schemas

* [Overview](#overview)
* [Fields](#fields)
* [Relations](#relations)
* [Formatters](#formatters)
* [Custom types](#types)
* [Additionnal Methods](#methods)

### <a name="overview"></a>Overview

The Schema abstraction is the central point of the Chaos data abstraction layer. Long story short, it corresponds to the mapper part of the DataMapper pattern. Schema instnces are bridges between in-memory entities representation and datasources storage.

Schemas can be compounded by:
* fields
* and relationships (through the model references).

#### <a name="fields"></a>Fields

Schemas are like tables and contain typed fields (like `string`, `integer`, etc.). To define a schema's field we need to use the `->set()` method.

Example:

```php
use chaos\model\Schema;

$schema = new Schema();
$schema->set('id',   ['type' => 'serial']);
$schema->set('name', ['type' => 'string']);
```

The `'type'` define an abstract representation of type which essentially depends on the used datasource. For example `'type' => 'serial'` will mean `INT NOT NULL AUTO_INCREMENT` for a MySQL connection and `SERIAL` for PostgreSQL.

With the RDBMS schema implementation, you can rely on the following `'type'` definitions:

* `'id'`
* `'serial'`
* `'string'`
* `'text'`
* `'integer'`
* `'boolean'`
* `'float'`
* `'decimal'`
* `'date'`
* `'time'`
* `'datetime'`
* `'binary'`
* `'uuid'`

And each type above will be matched to a RDBMS type definition through the dedicaded database adapter.

However it's also possible to create you own types and also override the default ones. See the [custom types section bellow](#types) for more informations on types.

With Chaos you are not limited to "scalar" types, you can also have objects and arrays. So with PostgreSQL you should be able to support schema like:

```php
use chaos\model\Schema;

$schema = new Schema();
$schema->set('id',             ['type' => 'serial']);
$schema->set('user',           ['type' => 'object']);
$schema->set('user.firstname', ['type' => 'string']);
$schema->set('user.lastname',  ['type' => 'string']);
$schema->set('comments',       ['type' => 'id', 'array' => true]);
```

Note: 26/07/2015, the PostgreSQL database adapter doesn't support this high level feature as the time I'm writing this documentation.

Field definition can have the following options:

* `'default'`: sets the default field value.
* `'array'`: indicates if it's a collection of `'type'` or not.
* `'null'`: indicates if the null value is allowed.
* `'use'`: allows to use a specifi RDBMS type definition (e.g `['type' => 'integer', 'use' => 'int8']`).

#### <a name="relations"></a>Relations

There's two way to set up a relation:

* `->bind()`: is used to define external relation via foreign keys for example.
* `->set()`: is used to define embeded relation (same as for fields).

The first parameter of methods will be the name of the relation (which mean the name of the field name used to store the relationship data). And the second parameter is an array of options where possible values are:

* `'relation'`: The name of the relationship (i.e 'belongsTo', 'hasOne', 'hasMany' or 'hasManyThrough').
* `'to'`: The target model name, can be a fully namespaced class name or just the class name if it belongs to the same namespace of the source.
* `'keys'`: A key value array where the key is the field name of the indentifier in the source model and the value, the indentifier in the target model (i.e. `['fromId' => 'toId']`).
* `'link'`: For relational databases, the only valid value is `Relationship::LINK_KEY`, which means a foreign key. But for document-oriented and other non-relational databases, different types of linking, including key lists or even embedding.

Example:

```php
use chaos\model\Schema;

$schema = new Schema();

// Embeded relation
$schema->set('author', [
    'relation' => 'hasOne',
    'to'       => 'myproject\model\Author'
]);

// External relation
$schema->bind('images', [
    'relation' => 'hasMany',
    'to'       => 'myproject\model\Image',
    'keys'     => ['id' => 'gallery_id']
]);
```

#### <a name="formatters"></a>Formatters

Formatters are handy way to perform casting between different representations. For example when data are loaded from a database, they must be casted first to fit the schema definition, then must be casted back to the datasource format to be saved.

With a setted connection, a schema own three type of formatter handlers:
- `'cast'`: are used to load data into an entity. Data can come from the datasource or some manually setted data (e.g. `$entity->created = '2015-07-26'`).
- `'datasource'` : are used to cast entity's data back to some compatible datasource format.
- `'array'` : are used to export a entity's data into a kind of generic array structure (through `$entiy->to('array')` or `$entiy->data()`);

Let's take for example the date type in MySQL. In the RDBMS the date are stored as a "Y-m-d H:i:s" string. However it's not a really handy structure to handle and I would prefer to have them casted into `DateTime` instances (which is the actually the default behavior of the RDBMS schema implementation).

With Chaos, you can define formatters based on field type. And you can set as many formatters as you need.

Let's take a concrete example:

```php
use myproject\model\Gallery;

$schema = Gallery::schema();

// To make sure created is of type date.
$schema->set('created', ['type' => 'date']);

$entity = Gallery::create([
    'name'   => 'My Gallery',
    'created' => '2014-10-26 00:25:15'   // It's a string
]);

$entity->created;                        // It's a DateTime instance

$entity->to('datasource');               // [
                                         //     'name'   => "'My Gallery'",
                                         //     'created' => "'2014-10-26 00:25:15'"
                                         // ]);
```

As you can seen `'created'` has been initialized using string. Then internaly the casting handler attached to the `'date'` type has been executed to cast the string into an instance of `DateTime`. And then `->to('datasource')` exported all entity's data into some datasource compatible data (i.e. using quoted string).

All `'cast'` and `'datasource'` handlers of the schema instance come from the database adapter instance (i.e. the connection). So if a schema has been defined with no connection, these handlers won't be defined and `::create()` as well as `->to('datasource')` won't perform any casting and will just leave the data unchanged.

Using the above example, let's change default handlers to be able to use my `MyDateTime` class instead of the default `DateTime` one:

```php
use MyDateTime;
use myproject\model\Gallery;

$schema = Gallery::schema();

// To make sure created is of type date.
$schema->set('created', ['type' => 'date']);

// The casting handler
$schema->formatter('cast', 'date', function($value, $options = []) {
    if (is_numeric($value)) {
        return new MyDateTime('@' . $value);
    }
    if ($value instanceof MyDateTime) {
        return $value;
    }
    return MyDateTime::createFromFormat(date('Y-m-d H:i:s', strtotime($value)), $value);
});

// The datasource handler
$schema->formatter('datasource', 'date', function($value, $options = []) {
    if ($value instanceof MyDateTime) {
        $date = $value->format('Y-m-d H:i:s');
    } else {
        $date = date('Y-m-d H:i:s', is_numeric($value) ? $value : strtotime($value));
    }
    return $this->dialect()->quote((string) $date);
});

$entity = Gallery::create([
    'name'   => 'My Gallery',
    'created' => '2014-10-26 00:25:15'   // It's a string
]);

$entity->created;                        // It's a MyDateTime instance

$entity->to('datasource');               // [
                                         //     'name'   => "'My Gallery'",
                                         //     'created' => "'2014-10-26 00:25:15'"
                                         // ]);
```

The fact that the `'cast'` handler manage several types of data is necessery because the casting handlers are used to cast datasource data as well as manually setted data (e.g. `$entity->created = '2015-07-26'`).

Also, the `'datasource'` handler need to manage several types of data because `'cast'` handlers are optionnals so there's no warranty that the passed value is of a specific type.

##### Custom Formatters

It's also possible to define some custom formatters. To do so, you need to add you custom handlers using the `->formatter()` method with a specific key for example in this case `'form'` since I want this custom export for my `<form>`.

Example:

```php
use myproject\model\Gallery;

$schema = Gallery::schema();

// To make sure created is of type date.
$schema->set('created', ['type' => 'date']);

$entity = Gallery::create([
    'name'   => 'My Gallery',
    'created' => '2014-10-26 00:25:15'
]);

$schema->formatter('form', 'date', function($value, $options = []) {
    $options += ['format' => 'Y-m-d H:i:s'];
    $format = $options['format'];
    if ($value instanceof DateTime) {
        return $value->format($format);
    }
    return date($format, is_numeric($value) ? $value : strtotime($value));
});

$entity->created;                           // It's a DateTime instance

$entity->to('form', ['format' => 'Y-m-d']); // [
                                            //     'name'   => 'My Gallery',
                                            //     'created' => '2014-10-26'
                                            // ]);
```

#### <a name="types"></a>Custom types

With Chaos it's possible to add your custom data types. For example it can be interesting to add some PostgreSQL geometric types to make them casted into objects by formatters, instead of working with string values.

Adding a new type only require to set at least a `'cast'` && `'datasource'` handler for the new data type.

Example:

```php
use myproject\model\Gallery;

$schema = Gallery::schema();

$schema->formatter('cast', 'customtype', function($value, $options = []) {
    return ...; // returns the casted value
});

$schema->formatter('datasource', 'customtype', function($value, $options = []) {
    return ...; // returns a datasource compatible value
});

// Now you can use your custom type.
$schema->set('custom', ['type' => 'customtype']);
```

#### <a name="methods"></a>Additionnal Methods

One of the principal key point of Chaos is that a `Schema` can be easily adapted to take advantage of specific datasource features. So the additionnal methods can by anything you willing to implement.

In the example bellow I will use `chaos\source\database\Schema` to show how the added methods of this specific implementation can be used in practice:

```php
$schema = Gallery::schema();

$schema->create();                           // creates the table
$schema->insert(['name' => 'MyGallery']);    // inserts raw datas w/o any validation

$id = $schema->connection()->lastInsertId(); // gets the last inserted id

$schema->update([
    'name' => 'MyNewGallery'
], ['id' => $id]);                            // updates raw datas w/o any validation

$schema->remove(['id' => $id]);               // removes raw datas
$schema->drop();                              // drops the table
```

The fact there's no additional abstraction layer between models and schemas allows to take advantages of any kind of datasource features by simply extending your base models.
