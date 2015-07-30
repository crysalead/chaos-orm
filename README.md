# Chaos - Data Abstraction Layer

![Build Status](https://img.shields.io/badge/branch-master-blue.svg)
[![Build Status](https://travis-ci.org/crysalead/chaos.png?branch=master)](https://travis-ci.org/crysalead/chaos)
[![Scrutinizer Coverage Status](https://scrutinizer-ci.com/g/crysalead/chaos/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/crysalead/chaos/?branch=master)

**WARNING, this repository is a work in progress (i.e a preview release). All main core features have been implemented but requires some clean up before tagging a first beta release**

Chaos is a rewrite of the [li3](http://li3.me/) model layer in PHP. If the syntax is not fully compatible with its predecessor, some effort has been made to keep the same beautiful and clean syntax.

Contrary to classic ORM approaches (i.e where the model layer is pilled up on top of a datasource/database abstraction layer), in Chaos the root abstraction is the model layer.

The model layer in Chaos has been built around the 3 following concepts:

 * The **model** which define a schema and the logic around entities.
 * The **entity** which is an instance of `Model`.
 * The **schema** which contains fields and relations to others models.

Chaos abstraction has been designed to take benefits of any kind of datasources by simply extending the `Schema` class to make it supported through a connection driver. All high level feature like the lazy loading, eager loading or embbeding will work out of the box because has been implemented at the model layer.

The fact that in this abstraction models are directly connected to their schemas make it easier to build a base model class which support custom features like the `findAndModify` action in Mongo or any other non-CRUD actions like the Github API "starring" action. This way Chaos can take advantage of any kind of non-CRUD actions but still rooted on a generic CRUD abstraction layer.

So if the datasource you envisionned to use:

 * is able to fetch a record/document thanks to an identifier.

You should be able to extends the `Schema` class to make Chaos to work with your datasource whatever is a NoSQL datasource, an API or a classic RDBMS database.

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

## Documentation

See the whole [documentation here](http://chaos.readthedocs.org/en/latest).

### Testing

The spec suite can be runned with:

```
cd chaos
composer install
./bin/kahlan
```
