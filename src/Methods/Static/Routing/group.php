<?php

namespace PulseFrame\Methods\Static\Routing;

use PulseFrame\Methods\Routing;

class group extends Routing
{
  public function handle(array $attributes, callable $callback)
  {
    $instance = self::$instance->router;

    if (!$instance) {
      throw new \RuntimeException('Router instance is not initialized.');
    }

    $prefix = $attributes['prefix'] ?? '';
    $middleware = $attributes['middleware'] ?? [];

    if (!is_array($middleware)) {
      $middleware = [$middleware];
    }

    $middleware = $instance->resolveMiddlewareAliases($middleware);

    $previousState = [
      'prefix' => $instance->groupPrefix,
      'middleware' => $instance->groupMiddleware
    ];

    $instance->groupPrefix = $prefix;
    $instance->groupMiddleware = $middleware;

    $instance->routeGroups[] = $instance->routes;
    $instance->routes = [];

    $callback($instance);

    $newRoutes = $instance->routes;
    $instance->routes = array_pop($instance->routeGroups);

    foreach ($newRoutes as $method => $routes) {
      foreach ($routes as $uri => $route) {
        $newUri = $prefix . $uri;
        $instance->routes[$method][$newUri] = $route;
        $instance->routes[$method][$newUri]['middleware'] = array_merge(
          $instance->groupMiddleware,
          $route['middleware'] ?? []
        );
        $instance->constraints[$newUri] = $instance->constraints[$uri] ?? [];
      }
    }

    $instance->groupPrefix = $previousState['prefix'];
    $instance->groupMiddleware = $previousState['middleware'];

    return $this;
  }
}
