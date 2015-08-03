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

The main purpose of models is to abstract business logic and datasources operations from higher levels. The in-memory representation of data are represented by models instances (i.e entities). And the datasources operations are delegated to the `Schema` instance attached to a model.

In Chaos the built-in `Schema` class for all PDO compatible databases is `chaos\database\Schema`. For example to create a `Gallery` model which uses the PDO related `Schema` class you can write:

```php
namespace myproject\model;

class Gallery extends \chaos\Model
{
    protected static $_schema = 'chaos\database\Schema';

    ...
}
```

And a complete model definition could be the following:

```php
namespace myproject\model;

class Gallery extends \chaos\Model
{

    protected static $_schema = 'chaos\database\Schema';

    protected static function _schema($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->bind('images', [
            'relation' => 'hasMany',
            'to'       => 'myproject\model\Image',
            'keys'     => ['id' => 'gallery_id']
        ]);
    }
}
```

The model definition is pretty straightforward. A "blank" schema instance is injected to the `::_schema()` method and can be configured right there to fit the Domain Model.

By default the injected schema instance is pre-configured with a source name and a primary key field name through a `Conventions` instance used to extract correct values. However in Chaos you can either set your own `Conventions` instance or manually set specific values like the following:

```php
// Sets a custom prefixed table name
$schema->source('prefixed_gallery');

// Uses 'uuid' as field name for primary key instead of 'id'
$schema->primaryKey('uuid');
```

> Note: Composite primary keys have not been implemented in Chaos to minimize the [object-relational impedance mismatch](https://en.wikipedia.org/wiki/Object-relational_impedance_mismatch). It would add a extra overhead with non negligible performance impact otherwise.


### <a name="schema"></a>Schema

In the previous example you noticed that fields and relations have been defined using the `::_schema()` method. More informations on [how to define a schema can by found here](schemas.md)

Once done, you can retrieve the model's schema using `::schema()` or defined relations using `::relations()`:

```php
$relations = Gallery::relations(); // ['images']
```

And to get a specific relation you need to use `::relation()`:

```php
$relation = Gallery::relation('images'); // A `HasMany` instance
```

It's also possible to check the availability of a specific relation using `::hasRelation()`:

```php
$relation = Gallery::hasRelation('images'); // A boolean
```

> Note: under the hood, `::relations()`, `::relation()` and `::hasRelation()` are simple shorcuts on `::schema()->relations()`, `::schema()->relation()` and `::schema()->hasRelation()`.

### <a name="entities"></a>Entities

Once a model has been defined it's possible to create entity instances using the `::create()` method.

```php
$gallery = Gallery::create(['name' => 'MyGallery']);
$gallery->name = 'MyAwesomeGallery';
```

Note: while this method creates a new entity, there is no effect on the datasource until the `->save()` method is called.


### <a name="validations"></a>Validations

Validation rules can be defined at the model level using the following syntax:

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

> You can check the [validation documentation](https://github.com/crysalead/validator) for more detail about how rules can be defined.

Then, with the `Gallery` definition above we can do the following:

```php
$gallery = Gallery::create();
$gallery->name = '';
$gallery->validate(); // `false`
$gallery->errors();   // ['name' => ['must not be a empty']]
```

#### Nested Validations

Validation also work in a nested way. To illustrate this feature, let's take as an example the following `Image` class:

```php
namespace myproject\model;

class Image extends \chaos\Model
{
    protected static function _schema($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->bind('gallery', [
            'relation' => 'belongsTo',
            'to'       => 'myproject\model\Gallery',
            'keys'     => ['gallery_id' => 'id']
        ]);
    }

    protected static function _rules($validator)
    {
        $validator->rule('name', 'not:empty');
    }
}
```

Now we can write the following to perform some nested validations:

```php
$gallery = Gallery::create();
$gallery->name = 'new gallery';
$gallery->images[] = Image::create(['name' => 'image1']);
$gallery->images[] = Image::create();
$gallery->validate(['with' => 'images']); // `false`
$gallery->errors(['with' => 'images']);   // ['images' => [ 1 => ['must not be a empty']]
```

### <a name="querying"></a>Querying

The model `::find()` method is used to perform queries on a datasource. By using the `chaos\database\Schema` implementation, the `::find()` will return a `Query` instance to facilitate the querying.

> Note: Under the hood the `::find()` method call the `->query()` method of the schema's instance. So the querying instance will depends on one implemented by the `Schema` class.

Let's start with a simple query for finding all entities:

```php
$galleries = Gallery::find();

foreach($galleries as $gallery) {
    echo $gallery->name;
}
```

Make no mistake here, the `$galleries` variable above is **not a collection** but an instance of `Query` which is lazily resolved when the `foreach` is executed. To make it more clear it could have been rewrited like the following:

```php
foreach($galleries->all() as $gallery) {
    echo $gallery->name;
}
```

#### <a name="querying_methods"></a>Querying methods

With the database schema, it's possible to use the following methods to configure your query on the `Query` instance:

* `where()` or `conditions()` : the `WHERE` conditions
* `group()`  : the `GROUP BY` parameter
* `having()` : the `HAVING` conditions
* `order()`  : the `ORDER BY` parameter
* `with()`   : the relations to include
* `has()`    : some conditions on relations

> Note: `'conditions'` is the generic naming for setting conditions. However for RDBMS databases, you can also use `'where'` which is supported as an alias of `'conditions'` to match more closely the SQL API.

So for example, we can write the following query:

```php
$galleries = Gallery::find();
$galleries->where(['name' => 'MyGallery'])
          ->with(['images']); // Eager load related images

foreach($galleries as $gallery) {
    echo $gallery->name;
    foreach ($gallery->images as $image) {
        echo $images->name;
    }
}
```

The `with()` method allows to eager load relations to minimize the number of queries. In the example above for example, only two queries are executed.

Isn't `->with()` supposed to take care of all the joins automatically ? I followed this approach in [li3](http://li3.me/). If I was able to achieve something decent, it leads to a lot of problems to solve like table aliases, redundant data, column references disambiguation, raw references disambiguation. Chaos follows a more straightforward approach and performes multiple queries instead of trying to deal with a massive and inadapted `JOIN` result set. It's also the approch choosen by most popular ORM around.

To deal with JOINs, the `->has()` method is available to RDBMS compatible `Query` instance.

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

In the example above three queries are executed. The first one is a `SELECT` on the gallery table with the necessary `JOIN`s to fit the `->has()` condition and return galleries which contain at least an image having the computer tag. The images and the tags will then be embedded using two extra queries.

> Note: In the example above, all images' tags will be loaded (i.e not only the `'computer'` tag). The `->has()` method added the constraint at the gallery level only.

#### <a name="fetching_methods"></a>Fetching methods

On the `Query` instance it's also possible to use some different getter to retreive the records, call:

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

All different representations can be mixed with the `->with()` paremeter to get nested structures.

#### <a name="finders"></a>Finders

At a model level you can define different custom finders. For example you could create a custom finder method that packages some specified conditions into a finder.

You can for example set two finders `->active()` and `->recent()` instead of repeatedly adding some often used conditions to your queries.

Scope are setted like the following in your model class:

```php
namespace myproject\model;

class Gallery extends \chaos\Model
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
$galleries = Gallery::find();
$galleries->active()->published();
```

#### <a name="global_scope"></a>Global scope

In cases where you always want finders results to be constrained to some conditions by default, some global query options can be setted at a model level. Default options can be defined by the `static $_query` property inside the model class or by using the `::query()` method like the following:

```php
Galleries::query([
    'conditions' => ['published' => true],
    'limit' => 4
]);
```

This way all finds will now be scoped to `static $_query` constraints.

#### <a name="querying_shortcuts"></a>Querying shortcuts

##### ::first()

Gets the first entity:

```php
$gallery = $galleries::first();

// Similar to
$gallery = $galleries::find()->first();
```

##### ::all()

Gets all entities:

```php
$gallery = $galleries::all();

// Similar to
$gallery = $galleries::find()->all();
```

##### ::id()

Gets an entity of a specific id:

```php
$gallery = $galleries::id(123);

// Similar to
$gallery = $galleries::find()->where(['id' => 123])->first();
```

### <a name="getters_getters"></a>Getters/Setters

#### ::connection()

This method allows you to get/set the model's connection.

#### ::conventions()

This method allows you to get/set the model's conventions.

#### ::schema()

This method allows you to get/set the model's schema.

#### ::validator()

This method allows you to get/set the model's validator instance used to validate entities.
