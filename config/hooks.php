<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

$hooks = __NAMESPACE__ . '\Hooks::';

return [

	'prototypes' => [

		'ICanBoogie\Core::get_routes' => $hooks . 'core_get_routes'

	]

];
