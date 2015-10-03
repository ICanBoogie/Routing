<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

/* @var $loader \Composer\Autoload\ClassLoader */

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->addPsr4('ICanBoogie\\Routing\\', __DIR__);

$_SERVER['HTTP_HOST'] = 'icanboogie.org';
