<?php

namespace PulseFrame\Methods\Static\Routing;

use PulseFrame\Methods\Routing;

class options extends Routing
{
  public function handle($uri, $action)
  {
    $instance = self::$instance;

    if (!$instance) {
      throw new \RuntimeException('Router instance is not initialized.');
    }

    $instance->addRoute('OPTIONS', $uri, $action);

    return $this;
  }
}
