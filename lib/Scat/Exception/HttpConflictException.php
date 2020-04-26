<?php
namespace Scat\Exception;

class HttpConflictException extends \Slim\Exception\HttpException
{
    protected $code= 409;
    protected $message= 'Conflict.';
    protected $title= '409 Conflict';
    protected $description= 'The request could not be completed due to a conflict with the current state of the target resource.';
}
