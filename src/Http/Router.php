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

/**
 * Class Router
 * 
 * Handles the routing of HTTP requests, including route registration, middleware application, and dispatching actions.
 * 
 * @classname PulseFrame\Http\Router
 * @category routing
 * @package PulseFrame\Routing\Http
 */
class Router
{
  /**
   * Array of registered routes.
   * 
   * @var array
   */
  public $routes = [];

  /**
   * Array of registered middleware.
   * 
   * @var array
   */
  public $middleware = [];

  /**
   * Array of route names.
   * 
   * @var array
   */
  public $routeNames = [];

  /**
   * Array of route constraints.
   * 
   * @var array
   */
  public $constraints = [];

  /**
   * The current route being processed.
   * 
   * @var array|null
   */
  public $currentRoute = null;

  /**
   * Flag indicating if the router is in a group.
   * 
   * @var bool
   */
  public $isInGroup = false;

  /**
   * Prefix for the current route group.
   * 
   * @var string
   */
  public $groupPrefix = '';

  /**
   * Middleware for the current route group.
   * 
   * @var array
   */
  public $groupMiddleware = [];

  /**
   * Array of route groups.
   * 
   * @var array
   */
  public $routeGroups = [];

  /**
   * Adds a new route to the router.
   * 
   * @param string $method HTTP method (e.g., 'GET', 'POST')
   * @param string $uri URI pattern for the route
   * @param callable|array|string $action The action to be executed for the route, can be a callable, a controller method in an array, or a string in the format 'Controller@method'
   * 
   * @return void
   */
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

  /**
   * Registers a middleware alias with a class.
   * 
   * @param string $alias The alias for the middleware
   * @param string $class The middleware class
   * 
   * @return void
   */
  public function registerMiddleware($alias, $class)
  {
    $this->middleware[$alias] = $class;
  }

  /**
   * Resolves middleware aliases to their actual class names.
   * 
   * @param array $middleware List of middleware aliases or classes
   * 
   * @return array Resolved middleware classes or closures
   * 
   * @throws InvalidArgumentException If a middleware alias or class does not exist
   */
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

  /**
   * Dispatches a request to the appropriate route.
   * 
   * @param Request $request The incoming HTTP request
   * 
   * @return mixed The response from the action
   * 
   * @throws NotFoundException If the route is not found
   * @throws MethodNotAllowedException If the HTTP method is not allowed
   * @throws BadRequestException If the request parameters do not match constraints
   */
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

  /**
   * Applies middleware to the given handler.
   * 
   * @param array $middleware List of middleware classes or closures
   * @param callable $handler The request handler to be wrapped by middleware
   * 
   * @return callable The handler with applied middleware
   * 
   * @throws InvalidArgumentException If a middleware class does not extend the Middleware class
   */
  protected function applyMiddleware(array $middleware, $handler)
  {
    $next = $handler;

    foreach (array_reverse($middleware) as $middlewareClass) {
      $next = function ($request) use ($next, $middlewareClass) {

        $reflection = new \ReflectionClass($middlewareClass);

        if (class_exists($middlewareClass) && $reflection->isSubclassOf(Middleware::class)) {
          return (new $middlewareClass)->handle($request, $next);
        } else {
          throw new InvalidArgumentException("Middleware class [$middlewareClass] does not extend PulseFrame/Middleware.");
        }

        throw new InvalidArgumentException("Middleware class [$middlewareClass] not found.");
      };
    }

    return $next;
  }

  /**
   * Validates request parameters against route constraints.
   * 
   * @param Request $request The incoming HTTP request
   * @param array $constraints The route constraints
   * 
   * @return bool True if constraints are met, false otherwise
   */
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

  /**
   * Extracts parameters from the URI.
   * 
   * @param string $uri The URI with parameter placeholders
   * 
   * @return array Associative array of parameters
   */
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

  /**
   * Extracts a parameter value from the URI (placeholder implementation).
   * 
   * @param string $parameter The parameter name
   * 
   * @return string The extracted parameter value
   */
  protected function extractParameterFromUri($parameter)
  {
    return '';
  }

  /**
   * Calls the action for the route.
   * 
   * @param callable|array|string $action The action to be executed
   * @param Request $request The incoming HTTP request
   * @param array $matches Route parameters
   * 
   * @return mixed The result of the action
   * 
   * @throws NotFoundException If the controller or method does not exist
   * @throws InvalidArgumentException If the action format is invalid
   */
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
