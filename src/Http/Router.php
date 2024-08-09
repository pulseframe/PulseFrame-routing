<?php

namespace PulseFrame\Http;

use PulseFrame\Facades\Config;
use PulseFrame\Facades\Request;
use PulseFrame\Exceptions\NotFoundException;
use PulseFrame\Exceptions\MethodNotAllowedException;
use PulseFrame\Exceptions\BadRequestException;
use PulseFrame\Middleware;
use InvalidArgumentException;
use Closure;

class Router
{
  public $routes = [];
  public $middleware = [];
  public $routeNames = [];
  public $constraints = [];
  public $currentRoute = null;
  public $isInGroup = false;
  public $groupPrefix = '';
  public $groupMiddleware = [];
  public $routeGroups = [];

  public function addRoute($method, $uri, $action)
  {
    $this->currentRoute = [
      'method' => $method,
      'uri' => $uri,
      'action' => $action
    ];

    $this->routes[$method][$uri] = [
      'action' => $action,
      'name' => $this->currentRoute['name'] ?? null,
      'constraints' => $this->constraints[$uri] ?? [],
      'middleware' => $this->currentRoute['middleware'] ?? []
    ];
  }

  public function registerMiddleware($alias, $class)
  {
    $this->middleware[$alias] = $class;
  }

  public function resolveMiddlewareAliases(array $middleware)
  {
    $middlewareConfig = Config::get('app', 'middleware');
    $resolvedMiddleware = [];

    foreach ($middleware as $alias) {
      if (is_string($alias)) {
        if (isset($middlewareConfig[$alias])) {
          $resolvedMiddleware[] = $middlewareConfig[$alias];
        } elseif (class_exists($alias)) {
          $resolvedMiddleware[] = $alias;
        } else {
          throw new InvalidArgumentException("Middleware alias or class [$alias] not defined or does not exist.");
        }
      } elseif ($alias instanceof Closure) {
        $resolvedMiddleware[] = $alias;
      } else {
        throw new InvalidArgumentException("Middleware should be a class name or Closure.");
      }
    }

    return $resolvedMiddleware;
  }

  // in your Router class
  public function dispatch(Request $request)
  {
    $method = $request->getMethod();
    $uri = $request->getPathInfo();

    $allowedMethods = array_keys($this->routes);

    $matches = [];

    foreach ($this->routes[$method] as $routeUri => $routeData) {
      $regex = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($routeData) {
        $parameter = $matches[1];
        $pattern = $routeData['constraints'][$parameter] ?? '[^/]+';
        return "(?P<$parameter>$pattern)";
      }, $routeUri);

      if (preg_match("#^$regex$#", $uri, $matches)) {
        if (strpos($routeUri, '{') !== false) {
          array_shift($matches);
        }
        break;
      } else {
        $matches = [];
      }
    }

    if (empty($matches)) {
      $otherMethods = array_diff($allowedMethods, [$method]);
      foreach ($otherMethods as $otherMethod) {
        if (isset($this->routes[$otherMethod][$uri])) {
          throw new MethodNotAllowedException('Method not allowed for this resource.');
        }
      }
      throw new NotFoundException('The requested resource was not found!');
    }

    $route = $this->routes[$method][$routeUri];

    $action = $route['action'];
    $constraints = $route['constraints'] ?? [];

    if (!$this->validateConstraints($request, $constraints)) {
      throw new BadRequestException('The request parameters do not match the required constraints.');
    }

    $middleware = $route['middleware'];

    try {
      $handler = $this->applyMiddleware($middleware, function () use ($action, $request, $matches) {
        return $this->callAction($action, $request, $matches);
      });

      return $handler($request);
    } catch (\Throwable $e) {
      throw $e;
    }
  }

  protected function applyMiddleware(array $middleware, $handler)
  {
    $next = $handler;

    foreach (array_reverse($middleware) as $middlewareClass) {
      $next = function ($request) use ($next, $middlewareClass) {

        $reflection = new \ReflectionClass($middlewareClass);

        if (class_exists($middlewareClass) && $reflection->isSubclassOf(Middleware::class)) {
          return (new $middlewareClass)->handle($request, $next);
        } else {
          throw new InvalidArgumentException("Middleware class [$middlewareClass] does not extend to PulseFrame/Middleware.");
        }

        throw new InvalidArgumentException("Middleware class [$middlewareClass] not found.");
      };
    };

    return $next;
  }

  protected function validateConstraints(Request $request, array $constraints)
  {
    $parameters = $this->extractParameters($request->getPathInfo());

    foreach ($constraints as $parameter => $pattern) {
      if (isset($parameters[$parameter]) && !preg_match('/' . $pattern . '/', $parameters[$parameter])) {
        return false;
      }
    }

    return true;
  }

  protected function extractParameters($uri)
  {
    $parameters = [];
    $matches = [];
    preg_match_all('/\{(\w+)\}/', $uri, $matches);

    foreach ($matches[1] as $parameter) {
      $parameters[$parameter] = $this->extractParameterFromUri($parameter);
    }

    return $parameters;
  }

  protected function extractParameterFromUri($parameter)
  {
    return '';
  }

  protected function callAction($action, $request, $matches)
  {
    try {
      if (is_callable($action)) {
        return call_user_func_array($action, array_values($matches));
      }

      if (is_array($action) && isset($action[0], $action[1])) {
        list($controller, $method) = $action;
        if (class_exists($controller) && method_exists($controller, $method)) {
          $controllerInstance = new $controller();
          return call_user_func_array([$controllerInstance, $method], array_values($matches));
        } else {
          throw new NotFoundException("Controller [{$controller}] or its method [{$method}] not found.");
        }
      }

      if (is_string($action) && strpos($action, '@') !== false) {
        list($controller, $method) = explode('@', $action);
        if (class_exists($controller) && method_exists($controller, $method)) {
          $controllerInstance = new $controller();
          return call_user_func_array([$controllerInstance, $method], array_values($matches));
        } else {
          throw new NotFoundException("Controller [{$controller}] or its method [{$method}] not found.");
        }
      }

      throw new InvalidArgumentException('Invalid action format.');
    } catch (\Throwable $e) {
      throw $e;
    }
  }
}
