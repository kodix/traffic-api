<?php


use Illuminate\Config\Repository;
use Mockery as M;
use GuzzleHttp\Client;
use Kodix\Traffic\Manager;
use Orchestra\Testbench\TestCase;
use GuzzleHttp\Exception\ClientException;
use Kodix\Traffic\Contracts\HasExternalId;
use Kodix\Traffic\Contracts\ExternalEntity;
use Kodix\Traffic\Exceptions\ErrorResponseException;
use Kodix\Traffic\Exceptions\ResponseStatusException;
use Kodix\Traffic\Exceptions\ParticipantAlreadyRegistered;

/**
 * This test can be called only manually because of auth data, which is given by manager of CRM.
 * There is no api for requesting new tokens or something like that.
 *
 * Class TrafficManagerTest
 */
class TrafficManagerTest extends TestCase
{
    /**
     * @var \Kodix\Traffic\Manager
     */
    protected $manager;

    /**
     * @var array
     */
    protected $dummyData;

    /**
     * @var Manager
     */
    protected $invalidManager;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        if (!env('login') || !env('password') || !env('secret')) {
            fwrite(STDERR, "\033[0;31mTo run Traffic CRM Tests you need to provide login, password and secret for api. 
                \rPass it to ENV before phpunit execution.\033[0m" . PHP_EOL);

            exit(1);
        }

        parent::__construct($name, $data, $dataName);
    }

    public function setUp()
    {
        parent::setUp();

        $this->dummyData = [
            'mobilephone' => '+7(999)999-99-99',
            'password' => hash('md5', '+7(999)999-99-99'),
            'email' => 'test@kodix.ru',
            'channel' => 'S',
            'isrulesagreed' => 'Y',
            'ismailingagreed' => 'Y',
            'ispdagreed' => 'Y',
            'lastname' => 'Test',
            'firstname' => 'Test',
            'vin' => '4T1BG28K0XU320066'
        ];

        $this->manager = new Manager(
            env('login'),
            env('password'),
            env('secret')
        );

        $this->invalidManager = new Manager(
            env('login'),
            env('password'),
            'invalid_token'
        );
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('services.traffic', include __DIR__ . '/config/service.php');
    }

    /**
     * @covers Manager::client()
     */
    public function test_manager_has_correct_client()
    {
        $this->assertInstanceOf(Client::class, $this->manager->client());
    }

    /**
     * @covers Manager::send()
     */
    public function test_it_throws_status_exception_when_token_is_invalid()
    {
        $this->expectException(ErrorResponseException::class);

        $this->invalidManager->getCars();
    }

    /**
     * @covers Manager::send()
     */
    public function test_it_fails_request_with_invalid_user_data()
    {
        $manager = new Manager('fake_user', 'fake_password', 'test_invalid_data');

        $this->expectException(ClientException::class);
        $manager->getCars();
    }

    /**
     * @covers Manager::send()
     */
    public function test_it_get_response_with_error_if_invalid_action_given()
    {
        $this->expectException(ResponseStatusException::class);
        $this->manager->send('NotExistingAction');
    }

    /**
     * @covers Manager::send()
     */
    public function test_response_exception_has_array_of_response()
    {
        try {
            $this->invalidManager->getCars();
        } catch (ErrorResponseException $exception) {
            $this->assertInternalType('array', $exception->getResponse());

            $keys = array_flip(['error', 'error_msg', 'error_msg_trans']);
            $diff = array_diff_key($keys, $exception->getResponse());
            $this->assertCount(0, $diff, 'Missing keys in response: ' . implode(', ', array_keys($diff)));
        }
    }

    /**
     * Метод getCars тестируем на наличие элементов в массиве, т.к. подразумевается, что машины в системе
     * всегда имеются.
     *
     * @covers Manager::getCars()
     */
    public function test_it_returns_array_of_cars()
    {
        $this->assertInternalType('array', $cars = $this->manager->getCars());

        $this->assertNotEmpty($cars, 'Cars list is empty!');
    }

    /**
     * @covers Manager::getMeetings()
     */
    public function test_it_returns_array_of_meetings()
    {
        $this->assertInternalType('array', $this->manager->getMeetings());
    }

    /**
     * Метод getDealers тестируем на наличие элементов в массиве, т.к. подразумевается, что дилеры в системе
     * всегда имеются.
     *
     * @covers Manager::getDealers()
     */
    public function test_it_returns_not_empty_array_of_dealers()
    {
        $this->assertInternalType('array', $dealers = $this->manager->getDealers());
        $this->assertNotEmpty(count($dealers) > 0, 'Dealers list is empty!');
    }

    /**
     * @covers Manager::registerParticipant()
     */
    public function test_it_fails_to_create_existed_participant_and_throws_the_exception()
    {
        $this->expectException(ParticipantAlreadyRegistered::class);
        $this->manager->registerParticipant($this->getDummyParticipant());
    }

    /**
     * @covers Manager::registerParticipant()
     */
    public function test_it_creates_participant_or_updates_it_if_such_participant_already_exists()
    {
        $dummyRequest = $this->getDummyParticipant();
        $dummyRequest->shouldReceive('updateEntity')->andReturnUsing(function ($arguments) {
            return array_merge(array_except($this->dummyData, ['password']), $arguments);
        });

        $response = $this->manager->saveParticipant($dummyRequest);

        $this->assertArrayHasKey('participant_id', $response);
    }

    /**
     * @covers Manager::registerMeeting()
     */
    public function test_it_creates_meeting()
    {
        $dummyRequest = $this->getDummyParticipant();
        $dummyRequest->shouldReceive('updateEntity')->andReturnUsing(function ($arguments) {
            return array_merge(array_except($this->dummyData, ['password']), $arguments);
        });
        collect($this->manager->getDealers())->first();

        $externalDealer = collect($this->manager->getDealers())->first();
        $externalCar = collect($this->manager->getCars())->first();
        $dealerMock = M::mock(HasExternalId::class);
        $carMock = M::mock(HasExternalId::class);
        $dealerMock->shouldReceive('externalId')->once()->andReturn($externalDealer['rsecode']);
        $carMock->shouldReceive('externalId')->once()->andReturn($externalCar['id']);

        // Сначала получим id участника, путем его обновления/создания
        $participant = $this->manager->saveParticipant($dummyRequest);

        $response = $this->manager->registerMeeting($dealerMock, $carMock, array_only($participant, ['participant_id']));

        $this->assertArraySubset(['error' => 1, 'result' => 1], $response);
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function getDummyParticipant()
    {
        $requestFormatted = M::mock(ExternalEntity::class);

        $requestFormatted->shouldReceive('newEntity')->andReturn($this->dummyData);

        return $requestFormatted;
    }

    protected function getPackageAliases($app)
    {
        return [
            'config' => Repository::class,
            'path.storage' => $app->storagePath()
        ];
    }
}