<?php


use Illuminate\Support\Facades\Facade;

class TrafficFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        return 'kodix.traffic';
    }
}