<?php
/**
 * /tests/bootstrap.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
// The tests run from the command line only, so a web request to this file (on an install made with git clone) does
// nothing.
if (PHP_SAPI !== 'cli') exit;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The tests' own classes (the DomainMOD\Tests namespace) live in this folder.
spl_autoload_register(function ($class) {
    $prefix = 'DomainMOD\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

// The same path constants as _includes/init.inc.php. DEFAULT_LANGUAGE is left undefined on purpose: a test that needs
// a language defines it itself, in a separate process.
define('DIR_ROOT', dirname(__DIR__));
const DIR_INC = DIR_ROOT . '/_includes';
const DIR_LOCALES = DIR_ROOT . '/locales';

date_default_timezone_set('UTC');
