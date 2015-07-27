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

In Chaos the built-in `Schema` class for all PDO compatible databases is `chaos\source\database\Schema`. For example to create a `Gallery` model which uses the PDO related `Schema` class you can write:

```php
namespace myproject\model;

class Gallery extends \chaos\model\Model
{
    protected static $_schema = 'chaos\source\database\Schema';

    ...
}
```

And a complete model definition could be the following:

```php
namespace myproject\model;

class Gallery extends \chaos\model\Model
{

    protected static $_schema = 'chaos\source\database\Schema';

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

The model definition is pretty straightforward. The schema is configured through `::_schema()` and validation rules through the `::_rules()` method.

By default a `Conventions` class is used to extract the table name from a model class name. In Chaos you can either set your own conventions or manually set specific values:

```php
// Sets a custom prefixed table name
$schema->source('prefixed_gallery');

// Uses 'uuid' as field name for primary key instead of 'id'
$schema->primaryKey('uuid');
```

> Note: Composite primary keys have not been implemented in Chaos out of the box for many reasons. Composite primary keys are a controversial feature and moreover doesn't fit well with REST which require to deal with unique ids to be able identify a resource. Since I probably never used this feature, I prefered to move the model layer ahead to the next generation databases instead of supporting composite primary keys. Anyway it can also be easily fixable by switching to a unique primary key.

### <a name="schema"></a>Schema

In the previous example you can notice that fields and relations are defined using the `::_schema()` method. More informations on [how to define a schema can by found here](schemas.md)

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

> Note: relations are loaded lazily on `::relation()` calls. So you can use `::relations()/hasRelations()` without any impact on perfomances to check the availability of a relation.

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

class Gallery extends \chaos\model\Model
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

class Image extends \chaos\model\Model
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

The model `::find()` method is used to perform queries on a datasource. By using the `chaos\source\database\Schema` implementation, the `::find()` will return a `Query` instance to facilitate the querying.

> Note: Under the hood the `::find()` method call the `->query()` method of the `Schema` instance. So the querying behavior will depends on one implemented by the `Schema` class.

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

On the `Query` instance it's possible to use the following methods to configure your query:

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

So what about `JOIN` isn't `->with()` supposed to take care of all the joins automatically ? I followed this approach in [li3](http://li3.me/). If I was able to achieve something decent, it has been a painful experience because relationnal databases fetching results are difficult to parse. You need to deal with table aliases, redundant data and the column references disambiguation which at least was time consuming. That's why I prefered to follow a more straightforward approach in Chaos and doing multiple queries instead of trying to deal with a massive and inadapted `JOIN` result set. By the way it's the approch choosen by most popular ORM around.

**But `JOIN` is not an options with relationnal databases, what if I want to get only galleries which has an image with a specific tags ?**

This specific case can be resolved using the `->has()` method of the `Query` instance to set some conditions on a relation.

Example:

```php
$galleries = Gallery::find();
$galleries->where(['name' => 'MyGallery'])
          ->with(['images.tags']);   // Eager load related images and tags
          ->has(['images.tags' => [  // Sets a conditions on 'images.tags'
              'name' => 'computer'
          ]]);

foreach($galleries as $gallery) {
    echo $gallery->name;
    foreach ($gallery->images as $image) {
        echo $images->name;
    }
}
```

In the example above three queries are executed. The first one is a `SELECT` on the gallery table with the necessary `JOIN`s to fit the `->has()` condition and return galleries which contain at least an image having the computer tag. The images and the tags will then be embedded using two extra queries.

> Note: the framework protects against injection attacks by quoting condition values by default.

#### <a name="fetching_methods"></a>Fetching methods

On the `Query` instance it's also possible to use some different getter to retreive the records, call:

* `->all()`   : to get the full collection.
* `->first()` : to get the first entity only.
* `->count()` : to get the count value.
* `->get()`   : to get the full collection (it's an alias to `->all()`)

The response from a query is an entity or a collection of entities by default. However you can switch to a different representation:

```php
// A collection of entities
$galleries = Gallery::find()->all();

// A array of stdClass
$galleries = Gallery::find()->all([
    return' => 'object'
]);

// A array of array
$galleries = Gallery::find()->all([
    return' => 'array'
]);
```

All different representations can be mixed with the `->with()` paremeter to get nested structures.

#### <a name="finders"></a>Finders

At a model level you can define different custom finders. For example you could create a custom finder method that packages some specified conditions into a finder.

You can for example set two finders `->active()` and `->recent()` instead of repeatedly adding some often used conditions to your queries.

Scope are setted like the following in your model class:

```php
namespace myproject\model;

class Gallery extends \chaos\model\Model
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

This method allows you gets/sets the connection instance used by a base model.

#### ::conventions()

This method allows you gets/sets the conventions instance used by a base model.

#### ::schema()

This method allows you gets/sets the schema instance used by a model.

#### ::validator()

This method allows you gets/sets the validator instance used by a model to validate entities.
