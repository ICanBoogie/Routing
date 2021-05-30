<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ResponderProvider;
use Psr\Container\ContainerInterface;

/**
 * Provides responders from a PSR container.
 */
final class Container implements ResponderProvider
{
	public function __construct(private ContainerInterface $container)
	{
	}

	public function responder_for_action(string $action): ?Responder
	{
		if (!$this->container->has($action)) {
			return null;
		}

		return $this->container->get($action);
	}
}
