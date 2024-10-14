<?php

namespace PulseFrame\Methods\Static\Routing;

use PulseFrame\Methods\Routing;

/**
 * Class group
 * 
 * Provides a static method to register a group of routes with shared attributes such as prefix and middleware.
 * Extends the Routing class to utilize its routing functionality.
 *
 * @classname PulseFrame\Methods\Static\Routing\group
 * @category methods
 * @package PulseFrame\Routing\Methods\Static\Routing
 */
class group extends Routing
{
  /**
   * Handles the registration of a group of routes with shared attributes.
   * 
   * This method allows defining a group of routes that share a common URI prefix and middleware.
   * The routes defined within the callback will be registered with the specified prefix and middleware.
   * 
   * @param array $attributes Attributes for the route group, including:
   *     - string 'prefix': The URI prefix to be added to each route in the group
   *     - array|string 'middleware': Middleware to be applied to each route in the group
   * 
   * @param callable $callback The callback function where routes are defined for this group. The callback will receive the current router instance.
   * 
   * @return $this The current instance of the class, allowing for method chaining
   * 
   * @throws \RuntimeException If the router instance is not initialized
   */
  public function handle(array $attributes, callable $callback)
  {
    // Get the router instance
    $instance = self::$instance;

    // Check if the router instance is initialized
    if (!$instance) {
      throw new \RuntimeException('Router instance is not initialized.');
    }

    // Extract and normalize the prefix and middleware from the attributes
    $prefix = $attributes['prefix'] ?? '';
    $middleware = $attributes['middleware'] ?? [];

    if (!is_array($middleware)) {
      $middleware = [$middleware];
    }

    // Resolve middleware aliases to actual middleware classes
    $middleware = $instance->resolveMiddlewareAliases($middleware);

    // Save the current state of the router
    $previousState = [
      'prefix' => $instance->groupPrefix,
      'middleware' => $instance->groupMiddleware
    ];

    // Set the new state for the group
    $instance->groupPrefix = $prefix;
    $instance->groupMiddleware = $middleware;

    // Temporarily store current routes and clear them for the group setup
    $instance->routeGroups[] = $instance->routes;
    $instance->routes = [];

    // Execute the callback to register routes for this group
    $callback($instance);

    // Retrieve the newly defined routes
    $newRoutes = $instance->routes;
    // Restore previous routes
    $instance->routes = array_pop($instance->routeGroups);

    // Add the prefix and middleware to each route in the group
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

    // Restore the previous state of the router
    $instance->groupPrefix = $previousState['prefix'];
    $instance->groupMiddleware = $previousState['middleware'];

    // Return the current instance for method chaining
    return $this;
  }
}
