<?php

namespace PulseFrame\Methods\Static\Routing;

use PulseFrame\Methods\Routing;

/**
 * Class options
 * 
 * Provides a static method to register OPTIONS routes with the router.
 * Extends the Routing class to utilize its routing functionality.
 *
 * @classname PulseFrame\Methods\Static\Routing\options
 * @category methods
 * @package PulseFrame\Routing\Methods\Static\Routing
 */
class options extends Routing
{
  /**
   * Handles the registration of an OPTIONS route.
   * 
   * This method allows defining a route that responds to OPTIONS HTTP requests.
   * 
   * @param string $uri The URI pattern for the route
   * @param callable|array|string $action The action to be executed for the route. It can be a callable, a controller method in an array, or a string in the format 'Controller@method'
   * 
   * @return $this The current instance of the class, allowing for method chaining
   * 
   * @throws \RuntimeException If the router instance is not initialized
   */
  public function handle($uri, $action)
  {
    // Get the router instance
    $instance = self::$instance;

    // Check if the router instance is initialized
    if (!$instance) {
      throw new \RuntimeException('Router instance is not initialized.');
    }

    // Register the OPTIONS route with the router instance
    $instance->addRoute('OPTIONS', $uri, $action);

    // Return the current instance for method chaining
    return $this;
  }
}
