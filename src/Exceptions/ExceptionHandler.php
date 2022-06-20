<?php

namespace Faridibin\LaravelApiResponse\Exceptions;

use Faridibin\LaravelApiResponse\Traits\HasApiResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * ApiResponse represents an HTTP response in JSON format.
 *
 * Note that this class does not force the returned JSON content to be an
 * object. It is however recommended that you do return an object as it
 * protects yourself against XSSI and JSON-JavaScript Hijacking.
 *
 * @see https://github.com/faridibin/laravel-api-json-response/blob/master/README.md
 *
 * @author Farid Adam <me@faridibin.tech>
 */

class ExceptionHandler extends \Exception
{
    use HasApiResponse;

    /**
     * The exception from \Faridibin\LaravelApiResponse\ApiResponse.
     *
     * @var \Exception
     */
    protected $exception;

    /**
     * The status code to use for the exception.
     *
     * @var int
     */
    protected $statusCode;

    /**
     * The message for the exception.
     *
     * @var string
     */
    protected $message;

    /**
     * The errors for the exception.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Whether or not the handler failed.
     *
     * @var bool
     */
    private $failed = true;

    /**
     * Constructor.
     *
     * @param null|\Exception $exception The Exception from \Faridibin\LaravelApiResponse\ApiResponse.
     */
    public function __construct(\Exception $exception = null, int $statusCode = null)
    {
        $this->exceptions = config(LARAVEL_API_RESPONSE_CONFIG . '.exceptions', []);
        $this->exception = $exception;

        if (isset($statusCode)) {
            $this->setStatusCode($statusCode);
        }

        // Check for exception and handle it.
        if ($this->exception) {
            if (isset($this->exception->status)) {
                $this->setStatusCode($this->exception->status);
            }

            if (isset($this->exception->message)) {
                $this->setMessage($this->exception->message);
            }

            $this->failed = $this->handle();
        }
    }

    /**
     * Handles the exceptions.
     * Sets whether or not the handler failed and allows
     * for makeJsonResponse() to run.
     *
     * @return bool
     */
    public function handle(): bool
    {
        // foreach ($exceptions as $exception => $case) {

        //     if (!is_a($this->exception, $exception)) {
        //         continue;
        //     }

        //     $ran = true;

        //     if (is_array($case) && isset($case['error'])) {

        //         foreach ($case as $key => $value) {
        //             if (!is_callable([$this->json(), $key])) {
        //                 continue;
        //             }
        //             call_user_func_array([$this->json(), $key], is_array($value) ? $value : [$value]);
        //         }
        //     } elseif (is_array($case)) {

        //         $this->json()->error(...$case);
        //     } elseif (is_callable($case)) {

        //         if ($case($this->exception, $this->json())) {
        //             $result = true;
        //         }
        //     } else {

        //         $this->json()->error($case);
        //     }
        // }

        foreach ($this->exceptions as $exception => $case) {
            if (!is_a($this->exception, $exception)) {
                continue;
            }

            if (is_array($case)) {
                foreach ($case as $key => $value) {
                    if (is_callable([$this, $key])) {
                        $this->$key($value);
                    }
                }
            }

            if (is_callable($case)) {
                $case($this->exception, $this);
            }
        }

        // Handle custom methods created.
        $method = Str::camel('handle_' . $this->getExceptionShortName());

        if (method_exists($this, $method)) {
            $this->$method($this->exception);
        }

        // TODO: Handle trace.
        // if (env('APP_DEBUG')) {
        //     $this->mergeErrors($this->exception->getTrace());
        // }

        return false;
    }

    /**
     * Handles the api response error.
     *
     * @param ApiResponseErrorException $e
     *
     * @return void
     */
    public function handleApiResponseErrorException(ApiResponseErrorException $e)
    {
        $this
            ->mergeErrors($e->getErrors())
            ->setMessage($e->getMessage())
            ->setStatusCode($e->statusCode ?? Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Sets the HTTP status code to be used for the response.
     *
     * @param  int  $statusCode
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Sets the Exception message.
     *
     * @param  string  $message
     * @return $this
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Retrieves the status code for the current web response.
     *
     * @final
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Retrieves the errors for the current web response.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Returns the short name of the exception class.
     *
     * @return string
     */
    public function getExceptionShortName()
    {
        return (new \ReflectionClass($this->exception))->getShortName();
    }

    /**
     * Gets the exception class.
     *
     * @return string
     */
    public function getExceptionClass()
    {
        return get_class($this->exception);
    }

    /**
     * Returns whether or not the handler failed
     *
     * @return bool
     */
    public function failed()
    {
        return $this->failed;
    }
}