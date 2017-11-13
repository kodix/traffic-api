<?php


namespace Kodix\Traffic\Exceptions;

class ErrorResponseException extends TrafficException
{
    /**
     * @var array
     */
    protected $response;

    public function __construct(array $response, $code = 0, \Throwable $previous = null)
    {
        $this->response = $response;

        parent::__construct('Response to Traffic CRM has an error.' . json_encode($response, JSON_UNESCAPED_UNICODE), $code, $previous);
    }

    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}