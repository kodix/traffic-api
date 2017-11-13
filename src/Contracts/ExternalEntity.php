<?php


namespace Kodix\Traffic\Contracts;


interface ExternalEntity
{
    /**
     * @return array
     */
    public function newEntity(): array;

    /**
     * @param array $attributes
     *
     * @return array
     */
    public function updateEntity(array $attributes = []): array;
}