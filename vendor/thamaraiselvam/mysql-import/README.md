# php Import SQL
mysqli import sql from a .sql file

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