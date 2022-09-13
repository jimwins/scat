<?php
namespace Scat\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class NoCache implements MiddlewareInterface
{
  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler): ResponseInterface
  {
    $response= $handler->handle($request);
    return $response->withHeader('Cache-control', 'private');
  }
}
