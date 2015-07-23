# Chaos - Data Abstraction Layer

[![Build Status](https://travis-ci.org/crysalead/chaos.png?branch=master)](https://travis-ci.org/crysalead/chaos)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/crysalead/chaos/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/crysalead/chaos/)

**WARNING, this repository is a work in progress (i.e a preview release). All features have been implemented but require some clean up before tagging a first beta release**

Chaos is a rewrite of the [li3](http://li3.me/) model layer in PHP. If the syntax is not fully compatible with its predecessor, some effort has been made to keep the same beautiful and clean syntax.

Contrary to classic ORM approaches (i.e where the model layer is pilled up on top of a datasource/database abstraction layer), in Chaos the root level of abstraction is model layer.

The model layer in Chaos has been built around the 3 following concepts:

 * The **model** which define a schema and the logic around entities.
 * The **entity** which is an instance of `Model`.
 * The **schema** which contains fields and relations to others models.

Chaos abstraction has been designed to take benefits of any kind of datasources by simply extending the `Schema` class to make it supported. And all the lazy loading, eager loading or embbeding will work out of the box because implemented at the model layer.

The fact that models are directly connected to their schema make it easier to build a base model class which support custom features like the `findAndModify` action in Mongo, or the Github API starring action which can't really be abstracted through a single `->find` or `->save()` method. This way Chaos can take advantage of any kind of non-CRUD actions but still rooted on a generic data abstraction layer.

So if the datasource you envisionned to use:

 * is able to fetch a record/document thanks to an identifier.

You should be able to extends the `Schema` class to make Chaos to work with your datasource whatever is a NoSQL datasource, an API or a classic RDBMS database.

## IRC

**chat.freenode.net** (server)
**#chaos** (channel)

## Documentation

See the whole [documentation here](http://chaos.readthedocs.org/en/latest).

## Requirements

 * PHP 5.5+

## Main Features

* Simple API
* Small code base
* Support Eager/Lazy loading
* Support Nested saving
* Support Nested validation
* Support Embedded relationship
* Support Schema casting

## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/).
Create a `composer.json` file and run `composer install` command to install it:

```json
{
    "require":
    {
        "crysalead/chaos": "dev-master"
    },
    "minimum-stability": "dev"
}
```

### Testing

Updates `kahlan-config.php` to set some valid databases configuration or remove them to run only unit tests then run the specs with:

```
cd chaos
composer install
./bin/chaos
```
