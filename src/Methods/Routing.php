<?php

namespace PulseFrame\Methods;

use PulseFrame\Facades\Route;

abstract class Routing
{
  public static $instance;

  public function __construct()
  {
    if (!self::$instance) {
      self::$instance = Route::$instance;
    }
  }

  public function middleware($middleware = [])
  {
    $instance = self::$instance;

    if (!is_array($middleware)) {
      $middleware = [$middleware];
    }

    $instance->currentRoute = $instance->currentRoute;

    $middleware = $instance->resolveMiddlewareAliases($middleware);

    if ($instance->currentRoute) {
      $method = $instance->currentRoute['method'];
      $uri = $instance->currentRoute['uri'];
      $action = $instance->currentRoute['action'];
      $constraints = $instance->constraints[$uri] ?? [];

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

  public function name($name)
  {
    $instance = self::$instance;

    if ($instance->currentRoute) {
      $method = $instance->currentRoute['method'];
      $uri = $instance->currentRoute['uri'];

      $instance->routes[$method][$uri]['name'] = $name;
      $instance->routeNames[$name] = $uri;

      $instance->currentRoute = null;
    } else {
      throw new \InvalidArgumentException('No current route defined.');
    }

    return $instance;
  }

  public function where($parameter, $pattern)
  {
    $instance = self::$instance;

    if ($instance->currentRoute) {
      $uri = $instance->currentRoute['uri'];
      $instance->constraints[$uri][$parameter] = $pattern;

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
