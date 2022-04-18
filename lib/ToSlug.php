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
 * An interface used to turn an instance into a slug.
 *
 * @see http://en.wikipedia.org/wiki/Semantic_URL#Slug
 */
interface ToSlug
{
    /**
     * Returns a slug representation of the instance.
     */
    public function to_slug(): string;
}
