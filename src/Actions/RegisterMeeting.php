<?php


namespace Kodix\Traffic\Actions;


use Kodix\Traffic\Contracts\ExternalEntity;
use Kodix\Traffic\Contracts\HasExternalId;
use Kodix\Traffic\Entities\MeetingEntity;
use Log;
use Kodix\Traffic\LoggingAction;

class RegisterMeeting extends LoggingAction
{
    /**
     * RegisterMeeting constructor.
     *
     * @param \Kodix\Traffic\Contracts\HasExternalId $dealer
     * @param \Kodix\Traffic\Contracts\HasExternalId $car
     * @param array $data
     */
    public function __construct(HasExternalId $dealer, HasExternalId $car, array $data = [])
    {
        $data = array_merge((new MeetingEntity($data))->newEntity(), [
            'dealer_id' => $dealer->externalId(),
            'car_id' => $car->externalId()
        ]);

        parent::__construct($data);
    }

    public function onSuccess(array $response = [], string $xml = ''): void
    {
        parent::onSuccess($response, $xml);

        Log::info('Встреча была успешно зарегистрированна. ' . json_encode($this->getData(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return string
     */
    protected function getLogFilename(): string
    {
        /** @noinspection NonSecureUniqidUsageInspection */
        return uniqid(array_get($this->getData(), 'participant_id') . '_') . '.xml';
    }

    public function onError(array $response = [], string $xml): void
    {
        parent::onError($response, $xml);

        Log::error('Ошибка регистрации встречи' . PHP_EOL .
            'Запрос - ' . json_encode($this->getData(), JSON_UNESCAPED_UNICODE) . PHP_EOL .
            'Данные - ' . json_encode($response, JSON_UNESCAPED_UNICODE)
        );
    }
}