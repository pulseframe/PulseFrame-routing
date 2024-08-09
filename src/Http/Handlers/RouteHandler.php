<?php

namespace PulseFrame\Http\Handlers;

use PulseFrame\Facades\View;
use PulseFrame\Facades\Config;
use PulseFrame\Facades\Log;
use PulseFrame\Facades\Response;
use PulseFrame\Http\Handlers\ExceptionHandler;
use PulseFrame\Http\Router;
use PulseFrame\Facades\Request;

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

  public static function getInstance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  protected function initializeRouter()
  {
    self::loadMiddlewaresFromConfig(Config::get('app'));

    $this->router = new Router();

    foreach (self::$middlewares as $alias => $class) {
      $this->router->registerMiddleware($alias, $class);
    }
  }

  protected static function loadMiddlewaresFromConfig(array $config)
  {
    self::$middlewares = $config['middleware'] ?? [];
  }

  protected function loadRoutesFromFile($filePath)
  {
    include_once $filePath;

    $routeData = [];

    $routeCollection = $this->router->routes;

    foreach ($routeCollection as $methodRoutes) {
      foreach ($methodRoutes as $uri => $route) {
        $name = $route['name'] ?? null;
        $fullUri = $uri;

        if ($name) {
          $routeData[] = ['name' => $name, 'url' => $fullUri];
        }
      }
    }

    self::$routeNames = $routeData;
  }

  public function loadRoutes()
  {
    $this->loadRoutesFromFile(__DIR__ . '/../../InternalRoutes.php');
    $this->loadRoutesFromFile(ROOT_DIR . '/routes/index.php');
  }

  public function getRouter()
  {
    return $this->router;
  }

  public function handleRequest($loader = null)
  {
    $request = Request::capture();

    $output = '';

    ob_start();

    try {
      if (!$loader) {
        throw new \RuntimeException('Loader not set');
      }

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

  protected function handleException(\Throwable $e)
  {
    Log::Exception($e);

    $statusCode = $e->getCode();

    switch ($statusCode) {
      case 404:
        ExceptionHandler::renderErrorView($statusCode, 'The page you are looking for could not be found.');
        break;
      case 405:
        ExceptionHandler::renderErrorView($statusCode, 'The method you are using is not supported.');
        break;
      case 403:
        ExceptionHandler::renderErrorView($statusCode, 'Access denied.');
        break;
      case 401:
        ExceptionHandler::renderErrorView($statusCode, 'Unauthorized access.');
        break;
      case 400:
        ExceptionHandler::renderErrorView($statusCode, 'Bad request.');
        break;
      case 429:
        ExceptionHandler::renderErrorView($statusCode, 'Rate limit exceeded.');
        break;
      case 500:
        ExceptionHandler::renderErrorView($statusCode, 'An internal server error occurred.', $e);
        break;
      default:
        ExceptionHandler::renderErrorView(500, 'An unexpected error occurred.', $e);
        break;
    }
  }

  public function __call($method, $arguments)
  {
    if (isset($arguments[1]) && is_string($arguments[1]) && strpos($arguments[1], '@') !== false) {
      $arguments[1] = $this->resolveControllerAction($arguments[1]);
    }

    return call_user_func_array([$this->router, $method], $arguments);
  }

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

  public static function initialize()
  {
    $instance = self::getInstance();
    $instance->loadRoutes();
    $instance->handleRequest((new View())::$twig);
  }
}
