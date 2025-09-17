<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\Request;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        return response()->json([
            'error' => $exception->getMessage(),
            'type' => get_class($exception),
        ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
    }
}
