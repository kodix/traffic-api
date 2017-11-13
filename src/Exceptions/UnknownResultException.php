<?php

namespace Kodix\Traffic\Exceptions;

class UnknownResultException extends TrafficException
{
    protected $response;

    public function __construct(array $response, $code = 0, \Throwable $previous = null)
    {
        $this->response = $response;

        parent::__construct('В ответе получен неизвестный результат - ' . array_get($response, 'result'), $code, $previous);
    }

    public function getResponse()
    {
        return $this->response;
    }
}