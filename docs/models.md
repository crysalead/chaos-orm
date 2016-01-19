## Models

* [Definition](#definition)
* [Schema](#schema)
* [Entities](#entities)
* [Validations](#validations)
* [Querying](#Querying)
  * [Querying methods](#querying_methods)
  * [Fetching methods](#fetching_methods)
  * [Scopes](#Scopes)
  * [Global scope](#global_scope)
  * [Querying shortcuts](#querying_shortcuts)
* [Getters/Setters](#getters_getters)

### <a name="definition"></a>Definition

The main purpose of models is to abstract business logic and datasources operations from a higher level. The in-memory representation of data are represented by models instances (i.e entities). And the datasources operations are delegated to the `Schema` instance attached to a model.

In Chaos the built-in `Schema` class for all PDO compatible databases is `Chaos\Database\Schema`. For example to create a `Gallery` model which uses the PDO related `Schema` class you can write:

```php
namespace My\Project\Model;

class Gallery extends \Chaos\Model
{
    protected static $_schema = 'Chaos\Database\Schema';

    ...
}
```

And a complete model definition could be the following:

```php
namespace My\Project\Model;

class Gallery extends \Chaos\Model
{

    protected static $_schema = 'Chaos\Database\Schema';

    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->hasMany('images', 'My\Project\Model\Image');
    }
}
```

The model definition is pretty straightforward. The schema instance is configured through the `::_define()` method.

By default the schema instance is pre-configured with a source name and a primary key field name extracted from the model through a `Conventions` instance. You can set your own `Conventions` instance or manually set the pre-configured values like so:

```php
// Sets a custom prefixed table name
$schema->source('prefixed_gallery');

// Uses 'uuid' as field name for primary key instead of 'id'
$schema->primaryKey('uuid');
```

Note: Composite primary keys have not been implemented in Chaos to minimize the [object-relational impedance mismatch](https://en.wikipedia.org/wiki/Object-relational_impedance_mismatch). Indeed it adds extra overhead with non negligible performance impact.

### <a name="schema"></a>Schema

In the previous example you noticed that fields and relations are defined using the `::_define()` method. More informations on [how to define a schema can by found here](schemas.md)

Once defined, model's schema is available through `::schema()` and relations through `::relations()`:

```php
$relations = Gallery::relations(); // ['images']
```

To get a specific relation use `::relation()`:

```php
$relation = Gallery::relation('images'); // A `HasMany` instance
```

It's also possible to check the availability of a specific relation using `::hasRelation()`:

```php
$relation = Gallery::hasRelation('images'); // A boolean
```

Note: under the hood, `::relations()`, `::relation()` and `::hasRelation()` are simple shorcuts on `::schema()->relations()`, `::schema()->relation()` and `::schema()->hasRelation()`.

### <a name="entities"></a>Entities

Once a model is defined it's possible to create entity instances using `::create()`.

```php
$gallery = Gallery::create(['name' => 'MyGallery']);
$gallery->name = 'MyAwesomeGallery';
```

Note: while this method creates a new entity, there is no effect on the datasource until the `->save()` method is called.

### <a name="validations"></a>Validations

Validation rules are defined at the model level using the following syntax:

```php
namespace myproject\model;

class Gallery extends \chaos\Model
{
    ...

    protected static function _rules($validator)
    {
        $validator->rule('name', 'not:empty');
    }
}
```

You can check the [validation documentation](https://github.com/crysalead/validator) for more detail about how rules can be defined.

Then, you can validate entities using `validate()`:

```php
$gallery = Gallery::create();
$gallery->name = '';
$gallery->validate(); // `false`
$gallery->errors();   // ['name' => ['must not be a empty']]
```

#### Nested Validations

Validation also work in a nested way. To illustrate this feature, let's take the following example:

```php
namespace My\Project\Model;

class Image extends \Chaos\Model
{
    protected static $_schema = 'Chaos\Database\Schema';

    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->belongsTo('gallery', 'myproject\model\Gallery');
    }

    protected static function _rules($validator)
    {
        $validator->rule('name', 'not:empty');
    }
}
```

It's then possible to perform the following:

```php
$gallery = Gallery::create();
$gallery->name = 'new gallery';
$gallery->images[] = Image::create(['name' => 'image1']);
$gallery->images[] = Image::create();
$gallery->validate(); // `false`
$gallery->errors();   // ['images' => [[], ['name' => ['must not be a empty']]]
```

### <a name="querying"></a>Querying

The model's `::find()` method is used to perform queries. Using the `Chaos\Database\Schema` implementation, the `::find()` will return a `Query` instance to facilitate the querying.

Note: Under the hood the `::find()` method calls the `->query()` method of the schema's instance. So the query instance depends on the `Schema` class implementation.

Let's start with a simple query for finding all entities:

```php
$galleries = Gallery::find();

foreach($galleries as $gallery) {
    echo $gallery->name;
}
```

Note: the `$galleries` variable above is **not a collection** but an instance of `Query` which is lazily resolved when the `foreach` is executed (i.e. it implements `IteratorAggregate`).

So the following syntax is similar:

```php
foreach($galleries->all() as $gallery) {
    echo $gallery->name;
}
```

#### <a name="querying_methods"></a>Querying methods

With the database schema, it's possible to use the following methods to configure your query on the `Query` instance:

* `conditions()` or `where()` : the `WHERE` conditions
* `group()`  : the `GROUP BY` parameter
* `having()` : the `HAVING` conditions
* `order()`  : the `ORDER BY` parameter
* `with()`   : the relations to include
* `has()`    : some conditions on relations

Note: `'conditions'` is the generic naming for setting conditions. However for RDBMS databases, you can also use `'where'` which is supported as an alias of `'conditions'` to match more closely the SQL API.

So for example, we can write the following query:

```php
$galleries = Gallery::find();
$galleries->where(['name' => 'MyGallery'])
          ->embed(['images']); // Eager load related images

foreach($galleries as $gallery) {
    echo $gallery->name;
    foreach ($gallery->images as $image) {
        echo $images->name;
    }
}
```

The `embed()` method allows to eager load relations to minimize the number of queries. In the example above for example, only two queries are executed.

Note: in Chaos `->embed()` doesn't perform any `JOIN` ? I followed the `JOIN` approach in [li3](http://li3.me/) but if I was able to achieve something decent, this strategy generates to a lot of problems to solve like table aliases, redundant data, column references disambiguation, raw references disambiguation. That's why Chaos follows a more straightforward approach and performes multiple queries instead of trying to deal with a massive and inadapted `JOIN` result set.

To deal with JOINs, the `->has()` method is available for RDBMS compatible `Query` instance.

Example:

```php
$galleries = Gallery::find();
$galleries->where(['name' => 'MyGallery'])
          ->with(['images.tags']);   // Eager load related images and tags
          ->has('images.tags', [     // Sets a conditions on the 'images.tags' relation
              'name' => 'computer'
          ]);

foreach($galleries as $gallery) {
    echo $gallery->name;
    foreach ($gallery->images as $image) {
        echo $images->name;
    }
}
```

In the example above three queries are executed. The first one is a `SELECT` on the gallery table with the necessary `JOIN`s to fit the `->has()` condition and return galleries which contain at least an image having the computer tag. The images and the tags are then embedded using two additionnal queries.

Note: In the example above, all images and tags are loaded for returned galleries (i.e not only the `'computer'` tag). The `->has()` method added a constraint at the gallery level only.

#### <a name="fetching_methods"></a>Fetching methods

On `Query` instances it's also possible to use some different getter to fetch records:

* `->all()`   : to get the full collection.
* `->first()` : to get the first entity only.
* `->count()` : to get the count value.
* `->get()`   : to get the full collection (it's an alias to `->all()`)

The response of above methods will depends on the `'return'` option value. By default the fething method will return an entity or a collection of entities. However you can switch to a different representation like in the following:

```php
// A collection of entities
$galleries = Gallery::find()->all();

// A array of stdClass
$galleries = Gallery::find()->all([
    'return' => 'object'
]);

// A array of array
$galleries = Gallery::find()->all([
    'return' => 'array'
]);
```

All different representations can be mixed with the `->embed()` parameter to get nested structures.

#### <a name="finders"></a>Finders

At a model level you can define different custom finders. For example you could create a custom finders that packages some specified conditions into a finder.

You can for example set an `->active()` and a `->recent()` finder instead of repeatedly adding some conditions to your queries.

Finders are defined like the following in model classes:

```php
namespace My\Project\Model;

class Gallery extends \Chaos\Model
{
    ...

    protected static function _finders($finders)
    {
        $finders->set('active', function($query) {
            $query->where(['active' => true]);
        });

        $finders->set('published', function($query) {
            $query->where(['published' => true]);
        });
    }
}
```

And you can use them like this:

```php
$galleries = Gallery::find()->active()->published()->all();
```

#### <a name="global_scope"></a>Global scope

You can also set some constraints at a model level to have them used in all queries. The default constraints can be defined in the `static $_query` property or by using the `::query()` method:

```php
Gallery::query([
    'conditions' => ['published' => true],
    'limit' => 4
]);
```

All futur queries on `Gallery` will be scoped according to `static $_query`.

#### <a name="querying_shortcuts"></a>Querying shortcuts

##### ::first()

Gets the first entity:

```php
$gallery = Gallery::first();

// Similar to
$gallery = Gallery::find()->first();
```

##### ::all()

Gets all entities:

```php
$gallery = Gallery::all();

// Similar to
$gallery = Gallery::find()->all();
```

##### ::id()

Gets an entity of a specific id:

```php
$gallery = Gallery::id(123);

// Similar to
$gallery = Gallery::find()->where(['id' => 123])->first();
```

### <a name="getters_getters"></a>Getters/Setters

#### ::connection()

Gets/sets the model's connection.

#### ::conventions()

Gets/sets the model's conventions.

#### ::schema()

Gets/sets the model's schema.

#### ::validator()

Gets/sets the model's validator instance used to validate entities.
