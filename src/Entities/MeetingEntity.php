<?php


namespace Kodix\Traffic\Entities;


use Kodix\Traffic\Manager;
use Kodix\Traffic\Contracts\ExternalEntity;

class MeetingEntity implements ExternalEntity
{
    const TYPE_SERVICE = 'SERVICE';

    const TYPE_CONSULTATION = 'CONSULTATION';

    const TYPE_TEST_DRIVE = 'TEST_DRIVE';

    const STATUS_NEW_REQUEST = 'NEW_REQUEST_FOR_TO_OR_REPAIR';

    protected $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function newEntity(): array
    {
        return array_merge($this->data, [
            'channel' => Manager::CHANNEL,
        ]);
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    public function updateEntity(array $attributes = []): array
    {
        return array_merge($this->newEntity(), $attributes);
    }
}