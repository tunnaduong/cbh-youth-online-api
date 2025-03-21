<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected function invalidJson($request, \Illuminate\Validation\ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    public function render($request, Throwable $exception)
    {
        // If the request is for an API route
        if ($request->is('v1.0/*')) {
            // Check if the exception is a validation exception
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                // Get the first error message
                $firstErrorMessage = collect($exception->errors())->flatten()->first();
                return response()->json([
                    'message' => $firstErrorMessage,
                    'errors' => $exception->errors(), // This method exists only for ValidationException
                ], $exception->status);
            }

            // Check if the exception is an AuthenticationException
            if ($exception instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // For other types of exceptions, return a generic error message
            return response()->json([
                'message' => $exception->getMessage(),
            ], $this->isHttpException($exception) ? $exception->getStatusCode() : 500);
        }

        return parent::render($request, $exception);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
