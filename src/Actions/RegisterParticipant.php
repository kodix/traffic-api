<?php


namespace Kodix\Traffic\Actions;

use Kodix\Traffic\LoggingAction;
use Log;
use Kodix\Traffic\Contracts\ExternalEntity;

class RegisterParticipant extends LoggingAction
{
    public function __construct(ExternalEntity $entity)
    {
        parent::__construct($entity->newEntity());
    }

    /**
     * @param array $response
     * @param string $xml
     */
    public function onError(array $response = [], string $xml = ''): void
    {
        parent::onError($response, $xml);
        Log::error('Не удалось добавить участника в CRM.' . json_encode($response, JSON_UNESCAPED_UNICODE));
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