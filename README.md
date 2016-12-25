# Chaos - Domain Modeling Library

![Build Status](https://img.shields.io/badge/branch-master-blue.svg)
[![Build Status](https://travis-ci.org/crysalead/chaos-orm.png?branch=master)](https://travis-ci.org/crysalead/chaos-orm)
[![Scrutinizer Coverage Status](https://scrutinizer-ci.com/g/crysalead/chaos-orm/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/crysalead/chaos-orm/?branch=master)

Chaos is an independent, persistence-agnostic layer responsible for defining entities' business logic and relationships. It allows to describe a [Domain Model](https://en.wikipedia.org/wiki/Domain_model) without any assumption about the persistence layer.

> Note: The Chaos syntax is derived from [li3](http://li3.me/). If the syntax is not fully compatible with its predecessor, some effort has been made to keep the same clean and beautiful syntax.

Available datasources libraries:
  * [chaos-database](https://github.com/crysalead/chaos-database): supports MySQL and PostgreSQL.

Chaos dramatically simplify the developpment of a datasources libraries by providing all persistence-agnostic logic like relationships, eager/lazy loading, validations, etc. at the root level. The only requirement is the datasource you envisionned to use need to be able to fetch a record/document thanks to a unique identifier (i.e no composite primary key).

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
use My\Project\Model\Images;

// Adding a many-to-many relation
$image = Image::load(123);
$image->tags[] = ['name' => 'Landscape'];
$image->broadcast();

foreach($image->tags as $tag) { // Echoes: 'Montain', 'Black&White', 'Landscape'
    echo $tag->name;
}
```

## Documentation

See the whole [documentation here](http://chaos.readthedocs.org/en/latest).

### Testing

The spec suite can be runned with:

```
cd chaos
composer install
./bin/kahlan
```
