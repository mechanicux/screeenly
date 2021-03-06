<?php

namespace Screeenly\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Log;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        TooManyRequestsHttpException::class,
        HostNotFoundException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Exception $e
     *
     * @return void
     */
    public function report(Exception $e)
    {
        // Copied from Bugsnag\BugsnagLaravel\BugsnagExceptionHandler::class
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return parent::report($e);
            }
        }

        if (app()->bound('bugsnag')) {
            app('bugsnag')->notifyException($e, null, 'error');
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception               $e
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if (method_exists($e, 'getHeaders')) {
            $headers = $e->getHeaders();
        }

        $code = $this->getCode($e);

        /*
         * Handle API Errors
         */
        if ($request->is('api/v1/*') && $request->isMethod('post')) {
            $headers['Access-Control-Allow-Origin'] = '*';

            $returnMessage = [
                'title'   => 'An error accoured',
                'message' => $e->getMessage(),
            ];

            if ($code < 100) {
                $code = 400;
            }

            return response()->json($returnMessage, $code, $headers);
        }

        /*
         * Global Exception Handler for API v2. If everything fails, respond
         * with a simple message.
         */
        if ($request->is('api/v2/*') && ! $e instanceof HttpResponseException) {
            $code = 500;
            if ($e->getCode() >= 400) {
                $code = $e->getCode();
            }

            $message = $e->getMessage();
            if (empty($message)) {
                $message = 'Oops. An internal server error accoured';
            }

            return response()->json(
                [
                    'error' => [
                        [
                            'title'  => 'Application Error',
                            'detail' => $message,
                            'code'   => $e->getCode(),
                            'meta'   => [
                                'type'    => (new \ReflectionClass($e))->getShortName(),
                            ],
                        ],
                    ],
                ],
                $code,
                []
            );
        }

        return parent::render($request, $e);
    }

    /**
     * Return HTTP Status Code from given Exception.
     *
     * @param mixed $e
     *
     * @return itn
     */
    private function getCode($e)
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        } elseif (method_exists($e, 'getCode')) {
            return $e->getCode();
        }

        return 400;
    }
}
