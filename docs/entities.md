## Entities

* [Creation](#creation)
* [CRUD Actions](#crud)
* [Additional Methods](#methods)

### <a name="creation"></a>Creation

Once a model has been defined it's possible to create entity instances using the `::create()` method.

```php
$gallery = Gallery::create(['name' => 'MyGallery']);
$gallery->name = 'MyGallery';
```

The first parameter is the entity's data to set. And the second parameter takes an array of options. Principal options are:

* `'type'`: can be `'entity'` or `'set'`. `'set'` is used if the passed data represent a collection of entities. Default to `'entity'`.
* `'exists'`: corresponds whether the entity is present in the datastore or not.
* `'autoreload'`: sets the specific behavior when exists is `null`. A '`true`' value will perform a reload of the entity from the datasource. Default to `'true'`.
* `'defaults'`: indicates whether the entity needs to be populated with their defaults values on creation.
* `'model'`: the model to use for instantiating the entity. Can be useful for implementing [Single Table Inheritance](http://martinfowler.com/eaaCatalog/singleTableInheritance.html)

The meaning of the `'exists'` states can be quite confusing if you don't get its purpose correctly. `'exists'` is used to indicate that the data you are passing is a pre-existing entity from the database, without actually querying the database:

```php
$gallery = Gallery::create([
    'id' => 123, name = 'MyGallery'
], ['exists' => true]);

$gallery->name = 'MyAwesomeGallery';
$success = $gallery->save();
```

The example above generates an update query of the record having the ID equal to`123`.

However the `'exists'` can have three different states:

* `true`: means you provided the whole data of an pre-existing entity.
* `false`: means you provided the whole data of a new entity. It's the default state.
* `null`: on the contrary `null` is a kind of "undefined state" where two different scenarios can occurs:
  * coupled with `'autoreload' => true`, an attempt to reload the whole entity data from the datasource is done to be able to switch the exists attribute to `true`.
  * coupled with `'autoreload' => false`, is doesn't do nothing, and it assumes you know what you are doing.

`'exists' => null` is the common scenario when you get entity's data from `$_POST`. Indeed `$_POST` generally contain only a subset of the entity data. Using `'exists' => null` will have the entity to be reloaded from the datasource to make sure `->modified()` && `->persisted()` (i.e the "old" data stored the datasource) will be accurate.

`'exists' => null` with `'autoreload' => false` is the state you get when you try to filter out some fields from your queries. This point is really important to get. The Data Abstraction Layer purpose is to provide an accurate object oriented representation of stored data. But the accuracy is impossible if the entity's data are not complete (especially when the ID hasn't been filetered out from data). Long story short, `'exists' => null` indicates something is wrong with your datas and you can't reliably consider it as some valid representation of the datasource data.

Note: if you want to be able to think at a higher level of abstraction, I would recommand to not filter out fields on find queries. Let have them filtered out in your views only. Early optimizations are an anti-pattern.

#### Getters/Setters

There's many way to get or set a value. The simplest one is using the following syntax:

```php
$entity->name = "A name"; // Set
$entity->name;            // Get
```

But it's also possible to override the default behavior by adding some specific getter & setter function in the model class. It can be useful to make some pre/post processing or to manage virtual fields.

Let's take the following example:

```php
class User
{
    public funciton getFullname() {
        return $this->firstname . ' ' . $this->lastname;
    }
}
```

Then you can access your virtual field like the following:

```php
$user = User::create([
    'firstname' => 'Johnny',
    'lastname'  => 'Boy'
]);

$user->fullname; // Johnny Boy
```

It's also possible to set multiple values in one go using the `->set()` method:

```php
$gallery = Gallery::create();
$gallery->set([
    'name'    => 'MyAwesomeGallery',
    'updated' => time()
])
```

In a reciprocal manner using `->get()` will returns the whole entity's data array.

Although it have a different purpose `->to()` is also a useful method to get entity's data. The main purpose of ->to()` is to export them into another format using some formatters.

For example `->to('array')` (or `->data()` which is an alias to `->to('array')`) exports the entity's data using the schema `'array'` formatters. See the [schema documentation to learn more about formatters & custom types](schemas.md).

### <a name="crud"></a>CRUD Actions

If Chaos has been designed to easily support non-CRUD actions, CRUD ones are supported through the `::find()`, `->save()` and `->delete()` methods.

The `::find()` method which stand for the read action belongs to the model. And the create, update and delete actions all belongs to the entity level through the `->save()` and `->delete()` method.

#### Saving an entity

The `->save()` method perform an insert or an update query depending the entity exists state. It returns either `true` or `false` depending on the success of the save operation. Example of usage:

```php
$gallery = Gallery::create();
$gallery->name = '';

if ($gallery->save()) {
    // It passes
} else {
    // It fails
}
```

Note: the `->save()` method method also validates entity's data by default if you have any validation rules defined at the model level. More information on [validation & errors here](models.md#validations).

The `->save()` method take as first argument an array of options. Possible values are:

* `'validate'`: If false, validation will be skipped, and the record will be immediately saved. Defaults to true.
* `'events'`: A string or array defining one or more validation events. Events are different contexts in which data events can occur, and correspond to the optional 'on' key in validation rules. They will be passed to the `->validate()` method if 'validate' is not false.
* `'whitelist'`: An array of fields that are allowed to be saved. Defaults to the schema fields.

Once an entity has been saved its exists state has been set to `true` which will lead to do update queries the next `->save()` calls.

Example:

```php
$gallery = Gallery::create([
    name = 'MyGallery'
]);
$gallery->exists()            // false

$gallery->save()              // insert query
$gallery->exists()            // true

$gallery->save()              // update query
$gallery->exists()            // true
```

Note: when the exists state of an entity is `null` something probably gone wrong somewhere and you should reconsider the way you are creating/loading your entities. And the main drawback will be that `->modified()` && `->persisted()` won't be accurate will exists in a `null` state.

#### Modified and persisted data

When the exists state is not `null` you can reliably use `modified()` to check whether a field has been updated or not.

`->modified()` with a field name as argument returns `true` if the field has been modified. If no argument is given, `->modified()` retruns `true` if one of the entity's field has been modified:

```php
$entity = Gallery::create(['name' => 'old name'], ['exists' => true]);
$entity->modified('title'); // false

$entity->name = 'new name';
$entity->modified('title'); // true
$entity->modified();        // true
```

Also it's also possible to retrieve the persisted data are using `->persisted()` (i.e. the previous state of values):

```php
$entity = Gallery::create(['name' => 'old name'], ['exists' => true]);
$entity->name = 'new name';

$entity->name;               // new name
$entity->persisted('name')); // old name
```

#### Deleting an entity

Deleting entities from a datasource is pretty straightforward and can be accomplished by simply calling the `->delete()` method on entities to delete:

```php
$gallery = Gallery::create([name = 'MyGallery']);
$gallery->save();

$gallery->delete();
$gallery->exists(); // false
```

### <a name="methods"></a>Additional Methods

There's a couple of useful method which gives additionnal information about entites.

#### primaryKey()

The `->primaryKey()` method allows to return the ID value of an entity. It's for example identical to `$entity->id` if the primary key field name of your entity is `'id'`.

#### parent()

Most of the time, entites will be connected together through relations and the `->parent()` method allows to return the parent instance of an entity which can be either an entity or a collection instance.

#### rootPath()

This method is related to embedded entities. The root path indicates at which position an "embedded entity" is located in its entity. The position is represented by a dotted field name string.

This root path is requires for embedded entities to make schema casting to work. All field names will be prefixed by the entity root path to be able to match its definition in the entity's schema.
