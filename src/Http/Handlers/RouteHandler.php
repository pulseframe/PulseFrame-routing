<?php

namespace PulseFrame\Http\Handlers;

use PulseFrame\Facades\Config;
use PulseFrame\Facades\Log;
use PulseFrame\Facades\Response;
use PulseFrame\Facades\View;
use PulseFrame\Http\Router;
use PulseFrame\Facades\Request;

/**
 * Class RouteHandler
 *
 * This class is responsible for handling routing within the PulseFrame framework. It manages the registration, 
 * loading, and execution of routes and their associated middlewares. It also provides exception handling for 
 * routing-related errors.
 * 
 * @classname PulseFrame\Http\Handlers\RouteHandler
 * @category Handlers
 * @package PulseFrame\Routing\Http\Handlers
 */
class RouteHandler
{
  public static $instance = null;
  public $router;
  protected static $middlewares = [];
  public static $routeNames = [];

  public $routes = [];
  public $middleware = [];
  public $constraints = [];
  public $currentRoute = null;
  public $isInGroup = false;
  public $groupPrefix = '';
  public $groupMiddleware = [];
  public $routeGroups = [];

  public function __construct()
  {
    $this->initializeRouter();
  }

  /**
   * Get the singleton instance of the RouteHandler.
   *
   * @return RouteHandler
   *
   * This method returns the singleton instance of the RouteHandler, creating it if it doesn't already exist.
   */
  public static function getInstance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Initialize the router and load middlewares.
   *
   * This method initializes the router and registers any middlewares defined in the application configuration.
   */
  protected function initializeRouter()
  {
    self::loadMiddlewaresFromConfig(Config::get('app'));

    $this->router = new Router();

    foreach (self::$middlewares as $alias => $class) {
      $this->router->registerMiddleware($alias, $class);
    }
  }

  /**
   * Load middlewares from the application configuration.
   *
   * @param array $config The application configuration array.
   *
   * This method loads the middlewares from the configuration array and stores them in the static $middlewares property.
   */
  protected static function loadMiddlewaresFromConfig(array $config)
  {
    self::$middlewares = $config['middleware'] ?? [];
  }

  /**
   * Load routes from a specified file.
   *
   * @param string $filePath The path to the file containing route definitions.
   *
   * This method loads routes from the specified file and registers them with the router.
   */
  protected function loadRoutesFromFile($filePath)
  {
    include_once $filePath;

    $routeData = [];

    $routeCollection = $this->router->routes;

    foreach ($routeCollection as $methodRoutes) {
      foreach ($methodRoutes as $uri => $route) {
        $name = $route['name'] ?? null;

        if ($name) {
          $routeData[] = ['name' => $name, 'url' => trim($uri)];
        }
      }
    }

    self::$routeNames = $routeData;
  }

  /**
   * Load all routes for the application.
   *
   * This method loads routes from both the internal and external route files.
   */
  public function loadRoutes()
  {
    $this->loadRoutesFromFile(__DIR__ . '/../../InternalRoutes.php');
    $this->loadRoutesFromFile(ROOT_DIR . '/routes/index.php');
  }

  /**
   * Get the router instance.
   *
   * @return Router
   *
   * This method returns the router instance used by the RouteHandler.
   */
  public function getRouter()
  {
    return $this->router;
  }

  /**
   * Handle the incoming request and dispatch it to the appropriate route.
   *
   * This method captures the incoming request, processes it through the router, and sends the response.
   * It also handles any exceptions that occur during the request handling process.
   */
  public function handleRequest()
  {
    $request = Request::capture();

    $output = '';

    ob_start();

    try {
      $response = $this->router->dispatch($request);

      $output = ob_get_contents();
      ob_end_clean();

      $response = new Response($response);
      $response->send();
    } catch (\Throwable $e) {
      $this->handleException($e);
    } finally {
      echo $output;
    }
  }

  /**
   * Handle exceptions that occur during request processing.
   *
   * @param \Throwable $e The exception to handle.
   *
   * This method handles exceptions by logging them and rendering an appropriate error view based on the 
   * exception's status code.
   */
  protected function handleException(\Throwable $e)
  {
    Log::Exception($e);

    $statusCode = $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR;

    $errorView = Config::get('view', 'error_page');

    return View::render(null, [], true)->error($errorView, $statusCode, "An internal server error occured.", $e);
  }

  /**
   * Magic method for handling dynamic method calls.
   *
   * @param string $method The method being called.
   * @param array $arguments The arguments passed to the method.
   * @return mixed
   *
   * This method handles dynamic calls to router methods, resolving controller actions as needed.
   */
  public function __call($method, $arguments)
  {
    if (isset($arguments[1]) && is_string($arguments[1]) && strpos($arguments[1], '@') !== false) {
      $arguments[1] = $this->resolveControllerAction($arguments[1]);
    }

    return call_user_func_array([$this->router, $method], $arguments);
  }

  /**
   * Resolve a controller action string into a callable array.
   *
   * @param string $action The controller action string (e.g., 'Controller@method').
   * @return array|false
   *
   * This method resolves a controller action string into a callable array if the controller class exists.
   */
  public function resolveControllerAction($action)
  {
    if (strpos($action, '@') !== false) {
      list($controller, $method) = explode('@', $action);
      $controller = '\\App\\Http\\Controllers\\' . $controller;
      if (class_exists($controller)) {
        return [$controller, $method];
      }
    }
    return false;
  }

  /**
   * Initialize the RouteHandler and process the incoming request.
   *
   * This static method initializes the RouteHandler, loads routes, and handles the request.
   */
  public static function initialize()
  {
    $instance = self::getInstance();
    $instance->loadRoutes();
    $instance->handleRequest();
  }
}
