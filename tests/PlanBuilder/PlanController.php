<?php

namespace Test\ICanBoogie\Routing\PlanBuilder;

use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\IntoResponse;
use ICanBoogie\Routing\Route;
use Test\ICanBoogie\Routing\Parameter\IntBackedEnum;
use Test\ICanBoogie\Routing\Parameter\StringBackedEnum;

/**
 * Each method is a case for the plan builder, the name of the method says what it is about.
 */
final class PlanController
{
    // valid

    public function no_parameters(): Response
    {
        return new Response();
    }

    public function path_parameter(int $id): Response
    {
        return new Response();
    }

    public function all_scalars(int $a, float $b, string $c, bool $d): Response
    {
        return new Response();
    }

    public function request_and_route(Request $request, Route $route, string $slug): Response
    {
        return new Response();
    }

    public function nullable_request(?Request $request): Response
    {
        return new Response();
    }

    public function enums(StringBackedEnum $status, IntBackedEnum $rank): Response
    {
        return new Response();
    }

    public function enum_with_default(StringBackedEnum $status = StringBackedEnum::DRAFT): Response
    {
        return new Response();
    }

    public function with_default(int $page = 1): Response
    {
        return new Response();
    }

    public function nullable(?int $page): Response
    {
        return new Response();
    }

    public function nullable_with_default(?int $page = null): Response
    {
        return new Response();
    }

    public function catch_all(string $all): Response
    {
        return new Response();
    }

    public function returns_response_subclass(): RedirectResponse
    {
        return new RedirectResponse('/');
    }

    public function returns_into_response(): Wrapper
    {
        return new Wrapper();
    }

    public function returns_into_response_interface(): IntoResponse
    {
        return new Wrapper();
    }

    public function returns_union(): Response|IntoResponse
    {
        return new Response();
    }

    public function returns_static(): static
    {
        return $this;
    }

    // not valid methods

    private function not_public(): Response
    {
        return new Response();
    }

    public static function is_static(): Response
    {
        return new Response();
    }

    public function no_return_type()
    {
        return new Response();
    }

    public function returns_void(): void
    {
    }

    public function returns_string(): string
    {
        return '';
    }

    public function returns_mixed(): mixed
    {
        return new Response();
    }

    public function returns_nullable(): ?Response
    {
        return null;
    }

    public function returns_union_with_null(): Response|null
    {
        return null;
    }

    public function returns_union_with_string(): Response|string
    {
        return new Response();
    }

    public function returns_unrelated_class(): \stdClass
    {
        return new \stdClass();
    }

    public function returns_intersection(): Response&IntoResponse
    {
        throw new \LogicException();
    }

    // not valid parameters

    public function untyped($x): Response
    {
        return new Response();
    }

    public function union_type(int|string $x): Response
    {
        return new Response();
    }

    public function array_type(array $x): Response
    {
        return new Response();
    }

    public function mixed_type(mixed $x): Response
    {
        return new Response();
    }

    public function unrelated_class(\stdClass $x): Response
    {
        return new Response();
    }

    public function unit_enum(PlainEnum $x): Response
    {
        return new Response();
    }

    public function variadic(int ...$ids): Response
    {
        return new Response();
    }
}
