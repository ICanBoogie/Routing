<?php

namespace ICanBoogie\Routing;

use function preg_replace;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Infers the action of a method when it is not specified.
 *
 * The action is made of the name of the controller and the name of the method, which is
 * used as is: `PhotosController::show` is `photos:show`, `PostCategoriesController::list` is
 * `post-categories:list`. The name of the controller is its class name stripped of its namespace
 * and of the suffix `Controller`, and hyphenated. It is not pluralized, `ContactController::show`
 * is `contact:show`. A name can be specified to replace it, it is then used as is.
 */
final readonly class ActionResolver
{
    private const string CONTROLLER_SUFFIX = 'Controller';

    /**
     * @param class-string $class
     * @param non-empty-string $method
     * @param non-empty-string|null $name
     *     Name of the controller, used as is instead of the name inferred from the class.
     *
     * @return non-empty-string
     */
    public static function resolve(string $class, string $method, ?string $name = null): string
    {
        return ($name ?? self::infer_name($class)) . Route::ACTION_SEPARATOR . $method;
    }

    /**
     * @param class-string $class
     */
    private static function infer_name(string $class): string
    {
        $position = strrpos($class, '\\');
        $name = $position === false ? $class : substr($class, $position + 1);

        if ($name !== self::CONTROLLER_SUFFIX && str_ends_with($name, self::CONTROLLER_SUFFIX)) {
            $name = substr($name, 0, -strlen(self::CONTROLLER_SUFFIX));
        }

        return self::hyphenate($name);
    }

    /**
     * `PostCategories` becomes `post-categories`, `HTTPServer` becomes `http-server`.
     *
     */
    private static function hyphenate(string $name): string
    {
        $hyphenated = preg_replace('/(?<=[a-z\d])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $name) ?? $name;

        return str_replace('_', '-', strtolower($hyphenated));
    }
}
