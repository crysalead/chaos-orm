# Chaos
— A Data Abstraction Layer Implementation In PHP 5.5+ —

Chaos is a rewrite of the [li3](http://li3.me/) model layer in PHP. If the syntax is not fully compatible with its predecessor, some effort has been made to keep the same beautiful and clean syntax.

Contrary to classic ORM approaches (i.e where the model layer is pilled up on top of a datasource/database abstraction layer), the root level of abstraction in Chaos is the model layer.

The model layer in Chaos has been built around the 3 following concepts:

 * The **model** which define a schema and the logic around entities.
 * The **entity** which is an instance of `Model`.
 * The **schema** which contains fields and relations to others models.

Chaos doesn't aim to provide an API compatible with all datasources (which is not realistic), it just make easier to take benefits of any kind of datasources by simply extending the `Schema` class to have it supported. All the lazy loading, eager loading or embbeding will work out of the box because implemented at the model layer.

And the fact that the models are directly connected to their schema make it easier to build a base model class which support some non-CRUD actions (e.g. the `findAndModify` in Mongo, or a Github starring action through your Github Schema class).

So if the datasource you envisionned to use:

 * is able to fetch a record/document thanks to an identifier.

You should be able to extends the `Schema` class to make Chaos to work with your datasource.

## Community

To ask questions, provide feedback or otherwise communicate with the team, join us on `#chaos` on Freenode.

## Requirements

 * PHP 5.5+

## Main Features

* Support eager/lazy loading
* Support custom finders
* Support nested saving
* Support nested validations
* Support external & embedded relationship
* Support custom types & auto entity's field casting
* Built-in MySQL & PostgreSQL database adapter

## Download

[Download Chaos on Github](https://github.com/crysalead/chaos)

## Documentation

Important: in the following documentation the [chaos-database](https://github.com/crysalead/chaos-database) library will be used to illustrate examples with a concrete datasource driver implementation. So don't forget to run `composer require crysalead/chaos-database` before poking around.

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
