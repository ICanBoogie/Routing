<?php

namespace ICanBoogie\Routing\RouteMaker;

use ICanBoogie\HTTP\Request;

/**
 * Defines pattern and methods of an action.
 */
final class Basics
{
	public const PLACEHOLDER_NAME = '{name}';
	public const PLACEHOLDER_ID   = '{id}';

	public const PATTERN_INDEX  = '/{name}';
	public const PATTERN_NEW    = '/{name}/new';
	public const PATTERN_CREATE = '/{name}';
	public const PATTERN_SHOW   = '/{name}/{id}';
	public const PATTERN_EDIT   = '/{name}/{id}/edit';
	public const PATTERN_UPDATE = '/{name}/{id}';
	public const PATTERN_DELETE = '/{name}/{id}';

	public const METHODS_INDEX  = Request::METHOD_GET;
	public const METHODS_NEW    = Request::METHOD_GET;
	public const METHODS_CREATE = Request::METHOD_POST;
	public const METHODS_SHOW   = Request::METHOD_GET;
	public const METHODS_EDIT   = Request::METHOD_GET;
	public const METHODS_UPDATE = [ Request::METHOD_PUT, Request::METHOD_PATCH ];
	public const METHODS_DELETE = Request::METHOD_DELETE;

	/**
	 * @param string $pattern
	 * @param string|array $methods
	 */
	public function __construct(
		public string $pattern,
		public string|array $methods
	) {
	}
}
