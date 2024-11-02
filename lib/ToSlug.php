<?php

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
