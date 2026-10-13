<?php

namespace ICanBoogie\Routing;

use ICanBoogie\StaticInflector;

use function str_ends_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Infers the action of a method when it is not specified.
 *
 * The action is made of the name of the controller class and the name of the method, which is
 * used as is: `PhotosController::show` is `photos:show`, `PostCategoryController::list` is
 * `post-categories:list`. The name of the class is stripped of its namespace and of the suffix
 * `Controller`, hyphenated, and pluralized.
 */
final readonly class ActionResolver
{
    private const string CONTROLLER_SUFFIX = 'Controller';

    /**
     * @param class-string $class
     * @param non-empty-string $method
     *
     * @return non-empty-string
     */
    public static function resolve(string $class, string $method): string
    {
        $position = strrpos($class, '\\');
        $name = $position === false ? $class : substr($class, $position + 1);

        if ($name !== self::CONTROLLER_SUFFIX && str_ends_with($name, self::CONTROLLER_SUFFIX)) {
            $name = substr($name, 0, -strlen(self::CONTROLLER_SUFFIX));
        }

        return StaticInflector::pluralize(StaticInflector::hyphenate($name)) . Route::ACTION_SEPARATOR . $method;
    }
}
