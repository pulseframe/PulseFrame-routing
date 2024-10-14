<?php

namespace PulseFrame\Methods;

use PulseFrame\Facades\Route;

/**
 * Class Routing
 * 
 * Provides methods for managing route attributes such as middleware, names, and constraints.
 * This is an abstract class meant to be extended by specific routing method classes.
 * 
 * @classname PulseFrame\Methods\Routing
 * @category routing
 * @package PulseFrame\Routing\Methods
 */
abstract class Routing
{
  /**
   * @var Route|null Holds the instance of the Route class.
   */
  public static $instance;

  /**
   * Routing constructor.
   * 
   * Initializes the static instance property with the Route instance if not already set.
   */
  public function __construct()
  {
    if (!self::$instance) {
      self::$instance = Route::$instance;
    }
  }

  /**
   * Assigns middleware to the current route.
   * 
   * Middleware can be provided as a single class name, an array of class names, or a combination of both.
   * The middleware is merged with any existing middleware for the current route.
   * 
   * @param array|string $middleware Middleware to be applied to the current route
   * 
   * @return $this The current instance of the class, allowing for method chaining
   * 
   * @throws \InvalidArgumentException If no current route is defined
   */
  public function middleware($middleware = [])
  {
    $instance = self::$instance;

    if (!is_array($middleware)) {
      $middleware = [$middleware];
    }

    // Retain current route details
    $instance->currentRoute = $instance->currentRoute;

    // Resolve middleware aliases to actual middleware classes
    $middleware = $instance->resolveMiddlewareAliases($middleware);

    if ($instance->currentRoute) {
      $method = $instance->currentRoute['method'];
      $uri = $instance->currentRoute['uri'];
      $action = $instance->currentRoute['action'];
      $constraints = $instance->constraints[$uri] ?? [];

      // Update the route with the new middleware
      $instance->routes[$method][$uri] = [
        'action' => $action,
        'name' => $instance->routes[$method][$uri]['name'] ?? null,
        'constraints' => $constraints,
        'middleware' => array_merge($instance->routes[$method][$uri]['middleware'] ?? [], $middleware),
      ];

      $instance->currentRoute = null;

      return $instance;
    } else {
      throw new \InvalidArgumentException('No current route defined.');
    }
  }

  /**
   * Assigns a name to the current route.
   * 
   * The route name is used for generating URLs or referencing routes in other parts of the application.
   * 
   * @param string $name The name to assign to the current route
   * 
   * @return $this The current instance of the class, allowing for method chaining
   * 
   * @throws \InvalidArgumentException If no current route is defined
   */
  public function name($name)
  {
    $instance = self::$instance;

    if ($instance->currentRoute) {
      $method = $instance->currentRoute['method'];
      $uri = $instance->currentRoute['uri'];

      // Assign the name to the route and update the route names
      $instance->routes[$method][$uri]['name'] = $name;
      $instance->routeNames[$name] = $uri;

      $instance->currentRoute = null;
    } else {
      throw new \InvalidArgumentException('No current route defined.');
    }

    return $instance;
  }

  /**
   * Defines constraints for route parameters.
   * 
   * Constraints specify the valid patterns for route parameters and are applied to the current route.
   * 
   * @param string $parameter The name of the route parameter to constrain
   * @param string $pattern The regular expression pattern to match against the parameter
   * 
   * @return $this The current instance of the class, allowing for method chaining
   * 
   * @throws \InvalidArgumentException If no current route is defined
   */
  public function where($parameter, $pattern)
  {
    $instance = self::$instance;

    if ($instance->currentRoute) {
      $uri = $instance->currentRoute['uri'];
      $instance->constraints[$uri][$parameter] = $pattern;

      // Update constraints for the route
      foreach ($instance->routes as $method => $routes) {
        if (isset($routes[$uri])) {
          $instance->routes[$method][$uri]['constraints'] = $instance->constraints[$uri];
        }
      }

      $instance->currentRoute = null;
    } else {
      throw new \InvalidArgumentException('No current route defined.');
    }

    return $instance;
  }
}
