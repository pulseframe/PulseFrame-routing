<?php

namespace PulseFrame\Methods\Static\Routing;

use PulseFrame\Methods\Routing;

class put extends Routing
{
  public function handle($uri, $action)
  {
    $instance = self::$instance;

    if (!$instance) {
      throw new \RuntimeException('Router instance is not initialized.');
    }

    $instance->addRoute('PUT', $uri, $action);

    return $this;
  }
}
