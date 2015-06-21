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
$loader->addPsr4('ICanBoogie\\Routing\\ControllerTest\\', __DIR__ . '/ControllerTest');
$loader->addPsr4('ICanBoogie\\Routing\\Controller\ResourceTraitTest\\', __DIR__ . '/Controller/ResourceTraitTest');
$loader->addPsr4('ICanBoogie\\Routing\\Controller\ActionTraitTest\\', __DIR__ . '/Controller/ActionTraitTest');

$_SERVER['HTTP_HOST'] = 'icanboogie.org';
