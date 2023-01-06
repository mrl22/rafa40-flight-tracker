<?php

date_default_timezone_set('Europe/London');

define('DB_HOST', 'localhost');
define('DB_NAME', 'devfwd_geo');
define('DB_USER', 'devfwd_geo');
define('DB_PASS', 'wmITWdEmKUOwxaUM');
define('DB_PREFIX', '');

require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
$db = new Capsule;
$db->setFetchMode(PDO::FETCH_CLASS);
$db->addConnection([
    'driver'    => 'mysql',
    'host'      => DB_HOST,
    'database'  => DB_NAME,
    'username'  => DB_USER,
    'password'  => DB_PASS,
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => DB_PREFIX,
]);
$db->setAsGlobal();