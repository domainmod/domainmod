# MySQL Importer

 ![ViewCount](https://views.whatilearened.today/views/github/thamaraiselvam/mysql-import.svg)

<img alt="Packagist" src="https://img.shields.io/packagist/dd/thamaraiselvam/mysql-import.svg?style=for-the-badge"> <img alt="Packagist" src="https://img.shields.io/packagist/dm/thamaraiselvam/mysql-import.svg?style=for-the-badge"> <img alt="Packagist" src="https://img.shields.io/packagist/dt/thamaraiselvam/mysql-import.svg?style=for-the-badge"> <img alt="GitHub tag (latest SemVer)" src="https://img.shields.io/github/tag/thamaraiselvam/mysql-import.svg?style=for-the-badge"> <img alt="GitHub" src="https://img.shields.io/github/license/thamaraiselvam/mysql-import.svg?style=for-the-badge">
<img alt="Travis" src="https://img.shields.io/travis/thamaraiselvam/mysql-import?style=for-the-badge">

<a href="https://www.buymeacoffee.com/R8Nc2vn" target="_blank"><img src="https://www.buymeacoffee.com/assets/img/custom_images/yellow_img.png" alt="Buy Me A Coffee"></a>

Import MySQL database backup files easily with <strong>MySQL Import</strong>

## Development

Run:

```sh
$ git clone https://github.com/thamaraiselvam/mysql-import.git
$ cd mysql-import
$ composer install
```

This will setup the library dependencies for you.

To run tests, run

```sh
$ composer phpunit
```

To ensure your code is following the coding style, run

```sh
$ composer phpcs
```

### Install

Using composer include the repository by typing the following into a terminal

```
composer require thamaraiselvam/mysql-import
```

### Usage

Include the composer autoloader, import the Import namespace.

```
<?php
require('vendor/autoload.php');

use Thamaraiselvam\MysqlImport\Import;

$filename = 'database.sql';
$username = 'root';
$password = '';
$database = 'sampleproject';
$host = 'localhost';
new Import($filename, $username, $password, $database, $host);
```
