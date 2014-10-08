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

/**
 * Contextualize a pathname.
 *
 * @param string $pathname
 *
 * @return string
 */
function contextualize($pathname)
{
	return Helpers::contextualize($pathname);
}

/**
 * Decontextualize a pathname.
 *
 * @param string $pathname
 *
 * @return string
 */
function decontextualize($pathname)
{
	return Helpers::decontextualize($pathname);
}

/**
 * Absolutize a RUL.
 *
 * @param string $url
 */
function absolutize_url($url)
{
	return Helpers::absolutize_url($url);
}