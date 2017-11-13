<?php


namespace Kodix\Traffic\Contracts;


interface HasExternalId
{
    /**
     * Возвращает внешний id сущности.
     *
     * @return string|int
     */
    public function externalId();
}