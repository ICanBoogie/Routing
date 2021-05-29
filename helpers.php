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
 */
function contextualize(string $pathname): string
{
	return Helpers::contextualize($pathname);
}

/**
 * Decontextualize a pathname.
 */
function decontextualize(string $pathname): string
{
	return Helpers::decontextualize($pathname);
}

/**
 * Absolutize a URL.
 */
function absolutize_url(string $url): string
{
	return Helpers::absolutize_url($url);
}
