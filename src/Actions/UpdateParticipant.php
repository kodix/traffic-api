<?php


namespace Kodix\Traffic\Actions;


use Log;
use Kodix\Traffic\LoggingAction;

class UpdateParticipant extends LoggingAction
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    /**
     * @param array $response
     * @param string $xml
     */
    public function onError(array $response = [], string $xml = ''): void
    {
        Log::error('Не удалось обновить участника в CRM.' . PHP_EOL . json_encode($response, JSON_UNESCAPED_UNICODE));

        parent::onError(...func_get_args());
    }

    /**
     * @return string
     */
    protected function getLogFilename(): string
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        return uniqid(array_get($this->getData(), 'mobilephone') . '_'). '.xml';
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return config('services.traffic.api_url');
    }
}