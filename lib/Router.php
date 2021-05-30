<?php

namespace ICanBoogie\Routing;

use Closure;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\ResponderClosure;
use ICanBoogie\HTTP\Response;

/**
 * Configures routes and controller providers at runtime.
 */
class Router
{
	static private int $anonymous_action_count = 0;

	/**
	 * Generates an anonymous respond identifier.
	 */
	static private function generate_anonymous_action(): string
	{
		return '__anonymous_action_' . ++self::$anonymous_action_count;
	}

	public function __construct(
		private MutableRouteProvider $routes,
		private MutableResponderProvider $responders,
	) {
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function any(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_ANY, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function connect(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_CONNECT, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function delete(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_DELETE, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function get(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_GET, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function head(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_HEAD, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function options(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_OPTIONS, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function patch(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_PATCH, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function post(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_POST, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function put(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_PUT, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	public function trace(string $pattern, Closure $closure): self
	{
		return $this->method(Request::METHOD_TRACE, $pattern, $closure);
	}

	/**
	 * @param Closure(Request):Response $closure
	 *
	 * @return $this
	 */
	private function method(string $method, string $pattern, Closure $closure): self
	{
		$action = self::generate_anonymous_action();

		$this->routes->add_route(new Route($pattern, $action, $method));
		$this->responders->add_responder($action, new ResponderClosure($closure));

		return $this;
	}
}
