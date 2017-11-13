<?php


namespace Kodix\Traffic\Entities;


use Kodix\Traffic\Manager;
use Kodix\Traffic\Contracts\ExternalEntity;

class MeetingEntity implements ExternalEntity
{
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