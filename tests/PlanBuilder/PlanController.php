<?php

namespace Test\ICanBoogie\Routing\PlanBuilder;

use ICanBoogie\HTTP\File;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Body;
use ICanBoogie\Routing\Attribute\ElementType;
use ICanBoogie\Routing\Attribute\Part;
use ICanBoogie\Routing\Attribute\Path;
use ICanBoogie\Routing\Attribute\Query;
use ICanBoogie\Routing\Attribute\Upload;
use ICanBoogie\Routing\IntoResponse;
use ICanBoogie\Routing\Route;
use SplFileObject;
use Test\ICanBoogie\Routing\Decoder\Article;
use Test\ICanBoogie\Routing\Decoder\NotADecoder;
use Test\ICanBoogie\Routing\Decoder\Probe;
use Test\ICanBoogie\Routing\Decoder\Slug;
use Test\ICanBoogie\Routing\Decoder\SlugDecoder;
use Test\ICanBoogie\Routing\Decoder\Unrelated;
use Test\ICanBoogie\Routing\Decoder\Wide;
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

    public function path_attribute(#[Path] int $id): Response
    {
        return new Response();
    }

    public function path_renamed(#[Path('id')] int $article_id): Response
    {
        return new Response();
    }

    public function query_parameter(#[Query] int $page = 1): Response
    {
        return new Response();
    }

    public function query_renamed(#[Query('q')] string $search): Response
    {
        return new Response();
    }

    public function query_enum(
        #[Query] StringBackedEnum $status,
        #[Query] ?bool $archived
    ): Response {
        return new Response();
    }

    public function part_parameter(#[Part] string $title): Response
    {
        return new Response();
    }

    public function part_renamed(#[Part('author_name')] ?string $author): Response
    {
        return new Response();
    }

    public function array_parameters(
        #[Part] array $items,
        #[Part(of: ElementType::Int)] array $category_ids,
        #[Query('s', of: StringBackedEnum::class)] ?array $statuses,
        #[Query(of: ElementType::String)] array $tags = [],
    ): Response {
        return new Response();
    }

    public function array_of_not_an_enum(#[Part(of: Slug::class)] array $items): Response
    {
        return new Response();
    }

    public function of_without_array(#[Part(of: ElementType::Int)] int $id): Response
    {
        return new Response();
    }

    public function array_with_decoder(#[Part(using: SlugDecoder::class)] array $items): Response
    {
        return new Response();
    }

    public function array_path(array $items): Response
    {
        return new Response();
    }

    public function upload_parameter(#[Upload] File $image): Response
    {
        return new Response();
    }

    public function upload_renamed(#[Upload('photo')] ?File $image): Response
    {
        return new Response();
    }

    public function upload_inferred(File $image, ?File $thumbnail): Response
    {
        return new Response();
    }

    public function all_sources(
        Request $request,
        #[Path] int $id,
        #[Query] int $page = 1,
        #[Part] string $title = ''
    ): Response {
        return new Response();
    }

    // body

    public function body_parameter(#[Body] SplFileObject $body): Response
    {
        return new Response();
    }

    public function body_nullable(#[Body] ?SplFileObject $body): Response
    {
        return new Response();
    }

    public function decoded_body(#[Body(using: Probe::class)] Slug $payload): Response
    {
        return new Response();
    }

    public function decoded_self_body(#[Body] Article $article): Response
    {
        return new Response();
    }

    public function body_and_others(
        #[Path] int $id,
        #[Query] int $page = 1,
        #[Body] ?SplFileObject $body = null
    ): Response {
        return new Response();
    }

    public function two_bodies(#[Body] SplFileObject $a, #[Body] SplFileObject $b): Response
    {
        return new Response();
    }

    public function body_string(#[Body] string $body): Response
    {
        return new Response();
    }

    public function body_untyped(#[Body] $body): Response
    {
        return new Response();
    }

    public function body_and_query(#[Query] #[Body] SplFileObject $body): Response
    {
        return new Response();
    }

    // decoders

    public function decoded_path(#[Path(using: SlugDecoder::class)] Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_path_renamed(
        #[Path('slug', using: SlugDecoder::class)] Slug $article_slug
    ): Response {
        return new Response();
    }

    public function decoded_implicit_path(Article $article): Response
    {
        return new Response();
    }

    public function decoded_query(#[Query(using: SlugDecoder::class)] ?Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_part(#[Part('s', using: SlugDecoder::class)] Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_self_query(#[Query] Article $article): Response
    {
        return new Response();
    }

    public function decoded_object(#[Query(using: Wide::class)] object $value): Response
    {
        return new Response();
    }

    public function decoded_with_default(
        #[Query(using: SlugDecoder::class)] ?Slug $slug = null
    ): Response {
        return new Response();
    }

    public function decoded_not_a_decoder(#[Query(using: NotADecoder::class)] Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_not_a_class(#[Query(using: 'Nope\\Missing')] Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_wide(#[Query(using: Wide::class)] Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_unrelated(#[Query(using: Unrelated::class)] Slug $slug): Response
    {
        return new Response();
    }

    public function decoded_scalar(#[Query(using: SlugDecoder::class)] string $slug): Response
    {
        return new Response();
    }

    public function decoded_union(#[Query(using: SlugDecoder::class)] Slug|Article $slug): Response
    {
        return new Response();
    }

    public function decoded_object_default(
        #[Query(using: SlugDecoder::class)] Slug $slug = new Slug('x')
    ): Response {
        return new Response();
    }

    public function decoded_missing_placeholder(
        #[Path(using: SlugDecoder::class)] Slug $slug
    ): Response {
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

    public function two_sources(#[Query] #[Part] int $x): Response
    {
        return new Response();
    }

    public function path_and_query(#[Path] #[Query] int $x): Response
    {
        return new Response();
    }

    public function attribute_on_request(#[Query] Request $request): Response
    {
        return new Response();
    }

    public function attribute_on_route(#[Part] Route $route): Response
    {
        return new Response();
    }

    public function part_class(#[Part] \stdClass $payload): Response
    {
        return new Response();
    }

    public function upload_string(#[Upload] string $image): Response
    {
        return new Response();
    }

    /**
     * @param mixed $image
     */
    public function upload_untyped(#[Upload] $image): Response
    {
        return new Response();
    }

    public function upload_union(#[Upload] File|string $image): Response
    {
        return new Response();
    }

    public function upload_and_part(#[Upload] #[Part] File $image): Response
    {
        return new Response();
    }

    public function file_and_query(#[Query] File $image): Response
    {
        return new Response();
    }

    public function path_renamed_missing(#[Path('slug')] int $id): Response
    {
        return new Response();
    }

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
