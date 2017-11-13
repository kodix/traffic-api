<?php

namespace Kodix\Traffic\Exceptions;

class ParticipantAlreadyRegistered extends TrafficException
{
    /**
     * ID участника, который был зарегистрирован.
     *
     * @var string
     */
    protected $participantId;

    public function __construct($id, $code = 0, \Throwable $previous = null)
    {
        $this->participantId = $id;

        parent::__construct(sprintf('Участник с id %s уже был зарегистрирован', $id), $code, $previous);
    }

    public function getParticipantId()
    {
        return $this->participantId;
    }
}