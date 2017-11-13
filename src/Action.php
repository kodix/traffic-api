<?php


namespace Kodix\Traffic;


use Log;

abstract class Action
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $data = [];

    public function __construct(array $data = [], string $name = null)
    {
        $this->data = $data;
        $this->initializeAction($name);
    }

    /**
     * @param string|null $name
     */
    protected function initializeAction(string $name = null): void
    {
        $this->name = $name ? ucfirst(camel_case($name)) : class_basename(static::class);
    }

    /**
     * @param array $data
     *
     * @return \Kodix\Traffic\Action
     */
    public function setData(array $data = []): Action
    {
        $this->data = $data;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function rootData(): array
    {
        return [];
    }

    public function onStart(string $xml): void
    {
        // do logic on execution start
    }

    /**
     * @param array $response
     * @param string $xml
     */
    public function onSuccess(array $response = [], string $xml = ''): void
    {
        Log::info(
            sprintf('Событие %s было успешно добавлено в CRM Traffic.', $this->name)
        );
    }

    /**
     * @param array $response
     * @param string $xml
     */
    public function onError(array $response = [], string $xml): void
    {
        // do nothing
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return config('services.traffic.importer_api_url');
    }
}