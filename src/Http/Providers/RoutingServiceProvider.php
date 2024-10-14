<?php

namespace PulseFrame\Http\Providers;

use PulseFrame\Contracts\ServiceProviderInterface;
use PulseFrame\Http\Handlers\RouteHandler;

class RoutingServiceProvider implements ServiceProviderInterface
{
  public function register(): void
  {
    RouteHandler::getInstance()->loadRoutes();
  }

  public function boot(): void
  {
    RouteHandler::getInstance()->handleRequest();
  }
}
