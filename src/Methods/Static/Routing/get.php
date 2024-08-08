<?php

namespace PulseFrame\Methods\Static\Routing;

use PulseFrame\Methods\Routing;

class get extends Routing
{
  public function handle($uri, $action)
  {
    $instance = self::$instance;

    if (!$instance) {
      throw new \RuntimeException('Router instance is not initialized.');
    }

    $instance->addRoute('GET', $uri, $action);

    return $this;
  }
}
