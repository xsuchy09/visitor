# Visitor

PHP library to save info about visitors of web page into DB.
PHP 7.1 is required.

Authors:
 - Petr Suchy (xsuchy09 - www.wamos.cz)

## Overview

Visitor saves info about visitor of web page into db and uses cookie to identify same visitor. Lifetime of that cookie is configurable - default is 10 years. It is using [UtmCookie](https://github.com/xsuchy09/utm-cookie) to save UTMs too and [Hashids](https://github.com/ivanakimov/hashids.php) to get unique hash for every single visitor (for example in JavaScript it is not safe to use directly ID from DB).

## Installation (via composer)

[Get composer](http://getcomposer.org/doc/00-intro.md) and add this in your requires section of the composer.json:

```
{
    "require": {
        "xsuchy09/visitor": "*"
    }
}
```

and then

```
composer install
```

## Usage

### Basic Example

```php
$pdo = $PDO = new PDO(sprintf('pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s', 'localhost', 5432, 'db_name', 'username', 'password'));
$visitor = new Visitor($pdo, 'HashidsKey'); // it needs pdo instance in constructor and key for Hashids (use your own for your security). You can use others optionally params as you need.
$visitor->addVisit(); // set last visit of of visitor if he is identified or just create the new one with first visit
```

More examples can be found in the examples/ directory.