# Chaos
— A Domain Modeling Library —

Chaos is an independent, persistence-agnostic layer responsible for defining entities' business logic and relationships. It allows to describe a [Domain Model](https://en.wikipedia.org/wiki/Domain_model) without any assumption about the persitence layer.

Already available persistant layers:
  * [chaos-database](https://github.com/crysalead/chaos-database): supports MySQL, PostgreSQL and SQLite.

Since Chaos contains all persistence-agnostic logic like relationships, eager/lazy loading, validations, etc. it dramatically simplify the developpment of datasources libraries.

As long as the datasource you envisionned to use is able to fetch a record/document thanks to a unique identifier (i.e no composite primary key), creating a persistant layer for Chaos will be trivial.

> Note: The Chaos syntax is derived from [li3](http://li3.me/). If the syntax is not fully compatible with its predecessor, some effort has been made to keep the same clean and beautiful syntax.

## Requirements

 * PHP 5.5+

## Main Features

* Support eager/lazy loading
* Support custom finders
* Support nested saving
* Support nested validations
* Support external & embedded relationship
* Support custom types & entities' field casting

## Example of syntax:

```php
use My\Project\Model\Image;

// Adding a many-to-many relation
$image = Image::load(123);
$image->tags[] = ['name'  =>  'Landscape'];
$image->save();

foreach($image->tags as $tag) { // Echoes: 'Montain', 'Black&White', 'Landscape'
    echo $tag->name;
}
```

## Download

[Download Chaos on Github](https://github.com/crysalead/chaos)

## Documentation

Important: in the following documentation [chaos-database](https://github.com/crysalead/chaos-database) is used to illustrate examples. So don't forget to run `composer require crysalead/chaos-database` in your project before poking around examples.

* [Connections](connections.md)
* [Models](models.md)
  * [Definition](models.md#definition)
  * [Schema](models.md#schema)
  * [Entities](models.md#entities)
  * [Validations](models.md#validations)
  * [Querying](models.md#Querying)
    * [Querying methods](models.md#querying_methods)
    * [Fetching methods](models.md#fetching_methods)
    * [Scopes](models.md#Scopes)
    * [Global scope](models.md#global_scope)
    * [Querying shortcuts](models.md#querying_shortcuts)
  * [Getters/Setters](models.md#getters_getters)
* [Entities](entities.md)
  * [Creation](entities.md#creation)
  * [CRUD Actions](entities.md#crud)
  * [Additional Methods](entities.md#methods)
* [Schemas](schemas.md)
  * [Overview](schemas.md#overview)
  * [Fields](schemas.md#fields)
  * [Relations](schemas.md#relations)
  * [Formatters](schemas.md#formatters)
  * [Custom types](schemas.md#types)
  * [Additionnal Methods](schemas.md#methods)
