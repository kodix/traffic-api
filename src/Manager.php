<?php


namespace Kodix\Traffic;


use Log;
use Throwable;
use GuzzleHttp\Client;
use InvalidArgumentException;
use SoapBox\Formatter\Formatter;
use Kodix\Traffic\Actions\BaseAction;
use GuzzleHttp\Exception\ClientException;
use Kodix\Traffic\Contracts\HasExternalId;
use Kodix\Traffic\Actions\RegisterMeeting;
use Kodix\Traffic\Contracts\ExternalEntity;
use Kodix\Traffic\Actions\UpdateParticipant;
use Kodix\Traffic\Actions\RegisterParticipant;
use Kodix\Traffic\Exceptions\ErrorResponseException;
use Kodix\Traffic\Exceptions\UnknownResultException;
use Kodix\Traffic\Exceptions\ResponseStatusException;
use Kodix\Traffic\Exceptions\ParticipantAlreadyRegistered;

class Manager
{
    /**
     * @var string
     */
    private $login;

    /**
     * @var string
     */
    private $password;

    /**
     * Ключ используемого проекта.
     *
     * @var string
     */
    private $secret;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Если true, то логирует успешную отправку запросов в CRM.
     *
     * @var bool
     */
    protected $successLogEnabled = true;

    const ERROR_STATUSES = [2, 3, 4, 5];

    const RESULT_RESPONSE_SUCCESS = 1;

    const RESULT_RESPONSE_DUPLICATE = 2;

    const RESULT_RESPONSE_NO_DATA = 2;

    /**
     * Канал запроса.
     * S - сайт, MOBILE - приложение.
     */
    const CHANNEL = 'S';

    public function __construct(string $login, string $password, string $key)
    {
        $this->login = $login;
        $this->password = $password;
        $this->secret = trim($key);

        $this->client = new Client(['base_uri' => config('services.traffic.api_url')]);
    }

    /**
     * Отправляет запрос в crm с указанным действием и данными.
     *
     * @param \Kodix\Traffic\Action|string $action действие, которое необходимо выполнить в системе.
     * @param array $data
     *
     * @return array
     * @throws \Kodix\Traffic\Exceptions\ResponseStatusException
     * @throws \Throwable
     */
    public function send($action, array $data = []): array
    {
        if (!($action instanceof Action)) {
            if (!is_string($action)) {
                throw new InvalidArgumentException(sprintf('Action must be string or instance of %s.', Action::class));
            }

            $action = new BaseAction($data, $action);
        }

        // Формируем список данных для отправки
        $data = array_merge($action->rootData(), [
            'action' => $action->getName(),
            'secretcode' => $this->secret,
            'params' => $action->getData()
        ]);

        try {
            // Создаем xml строку из массива данных
            $xml = Formatter::make($data, Formatter::ARR)->toXml('request');

            $action->onStart($xml);

            $response = $this->client->post($action->getUri() ?? '/', [
                // XML отправляем в теле запроса
                'body' => $xml,
                // Basic Auth идет по документации CRM
                'auth' => [$this->login, $this->password]
            ]);
        } catch (ClientException|Throwable $exception) {
            // Лоавим базовые исключения запроса. ClientException может произойти в случае 401 ошибки сервера.
            Log::critical("Не удалось сделать запрос в Traffic систему.\n\r{$exception->getMessage()}");

            throw $exception;
        }

        // Делаем из ответа xml массив
        $originalContent = $response->getBody()->getContents();
        $responseArray = (array)Formatter::make($originalContent, Formatter::XML)->toArray();

        if (!($status = array_get($responseArray, 'error'))) {
            // В ответах от сервера всегда будет приходить как минимум ключ error. Поэтому если он отсутствует, то
            // ответ нестандартный и что-то пошло не так.
            $this->callSilentActionFail($action, $responseArray, $originalContent);

            throw new ResponseStatusException($response);
        }

        if (static::isStatusFails($status)) {
            $transMessage = trans("services.skoda_traffic.{$status}");
            // Дописываем языковое сообщение в массив ответа с ошибкой и логируем её
            $responseArray['error_msg_trans'] = $transMessage;
            Log::error('Не удалось отправить событие в Traffic. Статус ответа - ' . $transMessage);

            $this->callActionFail($action, $responseArray, $originalContent);
        }

        try {
            $this->checkResultStatusSuccess($responseArray);
        } catch (UnknownResultException $exception) {
            $this->callSilentActionFail($action, $responseArray, $originalContent);

            throw $exception;
        }

        $action->onSuccess($responseArray, $originalContent);

        return $responseArray;
    }

    /**
     * @param \Kodix\Traffic\Action $action
     * @param array $response
     * @param $content
     *
     * @internal param $data
     */
    private function callSilentActionFail(Action $action, array $response, $content): void
    {
        try {
            $action->onError($response, $content);
        } catch (Throwable $e) {
        }
    }

    /**
     * @param \Kodix\Traffic\Action $action
     * @param array $data
     *
     * @param string $content
     *
     * @throws \Kodix\Traffic\Exceptions\ErrorResponseException
     * @throws \Throwable
     */
    protected function callActionFail(Action $action, array $data = [], string $content): void
    {
        try {
            $exceptionTriggered = false;
            // Пытаемся сообщить, что произошла ошибка
            $action->onError($data, $content);
        } catch (Throwable $exception) {
            $exceptionTriggered = true;
            // Если обработчик ошибки выбрасывает exception, то просто пробрасываем его дальше, т.к. мы и ожидаем
            // такого поведения
            throw $exception;
        } finally {
            // Если ошибки не произошло, то мы выбрасываем Exception ответа по-умолчанию
            if (!$exceptionTriggered) {
                throw new ErrorResponseException($data);
            }
        }
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function client(): \GuzzleHttp\Client
    {
        return $this->client;
    }

    /**
     * @return string
     */
    protected function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * @param $status
     *
     * @return bool
     */
    protected static function isStatusFails($status): bool
    {
        return in_array((int)$status, static::ERROR_STATUSES, true);
    }

    /**
     * @param array $filter
     *
     * @return array
     */
    public function getDealers(array $filter = []): array
    {
        $response = $this->send('GetDealersList', $filter);

        return array_get($response, 'dealers.dealer', []);
    }

    /**
     * @param array $filter
     *
     * @return array
     * @throws \Kodix\Traffic\Exceptions\UnknownResultException
     */
    public function getMeetings(array $filter = []): array
    {
        try {
            $response = $this->send('GetSubprojectMeetings', $filter);
        } catch (UnknownResultException $exception) {
            // Если код === нет результатов, то отдадим пустой массив
            if ((int)array_get($exception->getResponse(), 'result') === static::RESULT_RESPONSE_NO_DATA) {
                return [];
            }

            throw $exception;
        }

        return array_get($response, 'meetings.meeting', []);
    }

    /**
     * @param array $filter
     *
     * @return array
     */
    public function getCars(array $filter = []): array
    {
        $response = $this->send('GetCarsList', $filter);

        return array_get($response, 'cars.car', []);
    }

    /**
     * @return \Kodix\Traffic\Manager
     */
    public function enableSuccessLogs(): Manager
    {
        $this->successLogEnabled = true;

        return $this;
    }

    /**
     * @return \Kodix\Traffic\Manager
     */
    public function disableSuccessLogs(): Manager
    {
        $this->successLogEnabled = false;

        return $this;
    }

    /**
     * @param \Kodix\Traffic\Contracts\ExternalEntity $entity
     *
     * @return array
     * @throws \Throwable
     */
    public function saveParticipant(ExternalEntity $entity): array
    {
        try {
            $registered = $this->registerParticipant($entity);
        } catch (ParticipantAlreadyRegistered $exception) {
            $registered = $this->updateParticipant($entity, ['participant_id' => $exception->getParticipantId()]);
        } catch (Throwable $exception) {
            Log::error('Произошла неизвестная ошибка при сохранении участника.' . PHP_EOL . $exception);

            throw $exception;
        }

        return $registered;
    }

    /**
     * Регистрирует нового участника в CRM системе.
     *
     * @param \Kodix\Traffic\Contracts\ExternalEntity $entity
     *
     * @return array
     * @throws \Throwable
     * @throws \Kodix\Traffic\Exceptions\ResponseStatusException
     * @throws \Kodix\Traffic\Exceptions\ParticipantAlreadyRegistered
     * @throws \Kodix\Traffic\Exceptions\UnknownResultException
     */
    public function registerParticipant(ExternalEntity $entity): array
    {
        $participant = $entity->newEntity();

        try {
            $this->send(new RegisterParticipant($entity));
        } catch (UnknownResultException $exception) {
            if (!($id = array_get($exception->getResponse(), 'id'))) {
                throw $exception;
            }

            throw new ParticipantAlreadyRegistered($id);
        }

        return $participant;
    }

    /**
     * Обновляет данные зарегистрированного участника в CRM.
     *
     * @param \Kodix\Traffic\Contracts\ExternalEntity $entity
     * @param array $attributes
     *
     * @return array
     */
    public function updateParticipant(ExternalEntity $entity, array $attributes = []): array
    {
        $this->send(new UpdateParticipant($data = $entity->updateEntity($attributes)));

        return $data;
    }

    /**
     * @param \Kodix\Traffic\Contracts\HasExternalId $dealer
     * @param \Kodix\Traffic\Contracts\HasExternalId $car
     * @param array $data
     *
     * @return array
     *
     */
    public function registerMeeting(HasExternalId $dealer, HasExternalId $car, array $data = []): array
    {
        return $this->send(new RegisterMeeting($dealer, $car, $data));
    }

    /**
     * @param array $response
     *
     * @throws \Kodix\Traffic\Exceptions\UnknownResultException
     */
    private function checkResultStatusSuccess(array $response): void
    {
        $status = (int)array_get($response, 'result');

        if ($status !== static::RESULT_RESPONSE_SUCCESS) {
            throw new UnknownResultException($response);
        }
    }
}