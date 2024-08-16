<?php

namespace PulseFrame\Http\Handlers;

use PulseFrame\Facades\View;
use PulseFrame\Facades\Env;
use PulseFrame\Facades\Log;
use PulseFrame\Facades\Config;
use PulseFrame\Facades\Response;
use PulseFrame\Facades\Translation;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Class exceptionHandler
 * 
 * @category handlers
 * @name exceptionHandler
 * 
 * This class is responsible for handling application errors and exceptions. It captures exceptions using Sentry, 
 * and displays appropriate error views. It covers various types of exceptions and provides methods to 
 * initialize Sentry and render error views.
 */
class ExceptionHandler
{
  public static $ErrorCode = "";

  public static function initialize()
  {
    self::initializeSentry(Env::get('sentry_dsn'));

    self::$ErrorCode = substr(bin2hex(random_bytes(6)), 0, 8);

    // Set the exception and error handlers
    set_exception_handler([self::class, 'handle']);
    set_error_handler([self::class, 'handleErrors']);
  }

  /**
   * Initialize Sentry for error tracking.
   *
   * @category handlers
   * 
   * @param string $dsn The Sentry DSN (Data Source Name).
   * 
   * This static function initializes Sentry with the provided DSN and sets the sample rates for traces and profiles.
   * 
   * Example usage:
   * errorHandler::initializeSentry('your-sentry-dsn');
   */
  public static function initializeSentry($dsn)
  {
    \Sentry\init([
      'dsn' => $dsn,
      'traces_sample_rate' => 1.0,
      'profiles_sample_rate' => 1.0,
    ]);
  }

  /**
   * Handle all types of exceptions and errors.
   *
   * @category handlers
   * 
   * @param \Throwable $e The thrown exception or error.
   * 
   * This static function captures exceptions using Sentry and renders appropriate error views based on the type of exception.
   * 
   * Example usage:
   * errorHandler::handle(new \Exception('Something went wrong!'));
   */
  public static function handle(\Throwable $e)
  {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($e): void {
      $scope->setTag('error_code', self::$ErrorCode);

      \Sentry\captureException($e);
    });

    Log::Exception($e);

    if ($e instanceof HttpExceptionInterface) {
      self::renderErrorView(
        $e->getStatusCode(),
        $e->getMessage(),
        $e,
      );
    } else {
      self::renderErrorView(Response::HTTP_INTERNAL_SERVER_ERROR, Translation::key('errors.error-1'), $e);
    }
  }

  /**
   * Handle PHP errors.
   *
   * @category handlers
   * 
   * @param int $severity The severity of the error.
   * @param string $message The error message.
   * @param string $file The filename where the error occurred.
   * @param int $line The line number where the error occurred.
   * @return bool Always returns false to indicate that standard PHP error handling should proceed.
   * 
   * This static function converts PHP errors into ErrorException and captures them using Sentry.
   * 
   * Example usage:
   * set_error_handler([errorHandler::class, 'handleErrors']);
   */
  public static function handleErrors($severity, $message, $file, $line)
  {
    if (!(error_reporting() & $severity)) {
      return false;
    }

    $e = new \ErrorException($message, 0, $severity, $file, $line);
    \Sentry\captureException($e);
    Log::Exception($e);

    throw $e;
  }

  /**
   * Render an error view.
   *
   * @category handlers
   * 
   * @param int $statusCode The HTTP status code.
   * @param string $message The error message.
   * @param mixed $exception Additional exception details (optional).
   * 
   * This protected static function renders an error view using the View facade. It catches and handles exceptions 
   * that might occur while rendering the error page.
   * 
   * Example usage:
   * errorHandler::renderErrorView(500, 'Internal Server Error');
   */
  public static function renderErrorView($statusCode, $message, $exception = null)
  {
    while (ob_get_level()) {
      ob_end_clean();
    }

    ob_start();

    try {
      http_response_code($statusCode);

      if ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR) {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($exception): void {
          $scope->setTag('error_code', self::$ErrorCode);

          \Sentry\captureException($exception);
        });
      }

      $ErrorCode = $statusCode === Response::HTTP_INTERNAL_SERVER_ERROR ? self::$ErrorCode : null;

      new View();

      if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (Env::get('app.settings.debug')) {
          if ($ErrorCode) {
            $page = 'error';
          } else {
            $page = View::$errorPage;
          }
        } else {
          $page = View::$errorPage;
        }

        echo View::render($page, [
          'status' => $statusCode,
          'message' => $message,
          'exception' => $exception,
          'code' => $ErrorCode
        ]);
      } else {
        $message = Config::get('app', 'stage') === "development" ? $exception->getMessage() : Translation::key('errors.error-0');
        echo Response::JSON('error', $message, self::$ErrorCode);
      }
    } catch (\Exception $e) {
      return "An error occurred while rendering the error page.\n" . $e->getMessage();
    }

    exit;
  }
}
