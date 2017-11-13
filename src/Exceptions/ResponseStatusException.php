<?php

namespace Kodix\Traffic\Exceptions;

use Psr\Http\Message\ResponseInterface;

class ResponseStatusException extends TrafficException
{
    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    public function __construct(ResponseInterface $response, $code = 0, \Throwable $previous = null)
    {
        $this->response = $response;

        parent::__construct('Не удалось распознать статус ответа. Данные - ' . json_encode($response, JSON_UNESCAPED_UNICODE), $code, $previous);
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}