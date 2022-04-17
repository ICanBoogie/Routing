<?php

namespace ICanBoogie\Routing\RouteMaker;

use ICanBoogie\HTTP\RequestMethod;

/**
 * Defines pattern and methods of an action.
 */
final class Basics
{
	public const PLACEHOLDER_NAME = '{name}';
	public const PLACEHOLDER_ID = '{id}';

	public const PATTERN_LIST = '/{name}';
	public const PATTERN_NEW = '/{name}/new';
	public const PATTERN_CREATE = '/{name}';
	public const PATTERN_SHOW = '/{name}/{id}';
	public const PATTERN_EDIT = '/{name}/{id}/edit';
	public const PATTERN_UPDATE = '/{name}/{id}';
	public const PATTERN_DELETE = '/{name}/{id}';

	public const METHODS_LIST = RequestMethod::METHOD_GET;
	public const METHODS_NEW = RequestMethod::METHOD_GET;
	public const METHODS_CREATE = RequestMethod::METHOD_POST;
	public const METHODS_SHOW = RequestMethod::METHOD_GET;
	public const METHODS_EDIT = RequestMethod::METHOD_GET;
	public const METHODS_UPDATE = [ RequestMethod::METHOD_PUT, RequestMethod::METHOD_PATCH ];
	public const METHODS_DELETE = RequestMethod::METHOD_DELETE;

	/**
	 * @param RequestMethod|RequestMethod[] $methods
	 */
	public function __construct(
		public string $pattern,
		public RequestMethod|array $methods
	) {
	}
}
