<?php


namespace Kodix\Traffic;


use File;
use DOMDocument;
use Kodix\Traffic\Exceptions\TrafficException;

/**
 * Class LoggingAction
 * @package Kodix\Traffic\Actions
 */
abstract class LoggingAction extends Action
{
    protected $xml;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var string
     */
    protected $filename;

    public function __construct(array $data = [], $name = null)
    {
        parent::__construct($data, $name);

        $this->filePath = storage_path('traffic-logs/' . $this->getName());
        $this->filename = preg_replace('/[^\w\.]/', '', $this->getLogFilename());

        if (!File::isDirectory($this->filePath) && !File::makeDirectory($this->filePath, 493, true)) {
            throw new TrafficException("Failed creating {$this->filePath} directory");
        }

        $this->xml = new DOMDocument();
        $this->xml->loadXML('<?xml version="1.0" encoding="utf-8"?><requestsLog time="' . date('d.m.Y H:i:s') . '"/>');
    }

    public function onStart(string $xml): void
    {
        parent::onStart($xml);

        $this->addToLog('requestStarted', $xml);

        $this->writeToFile($this->xml->saveXML());
    }

    public function onSuccess(array $response = [], string $xml = ''): void
    {
        parent::onSuccess($response, $xml);

        $this->addToLog('requestSucceed', $xml);

        $this->writeToFile($this->xml->saveXML());
    }

    /**
     * @param $name
     * @param $xml
     */
    protected function addToLog($name, $xml)
    {
        $time = time();

        $fragment = $this->xml->createDocumentFragment();
        $fragment->appendXML("<{$name} at=\"{$time}\">{$this->withoutXmlVersion($xml)}</{$name}>");

        $this->xml->documentElement->appendChild($fragment);
    }

    /**
     * @param string $xml
     *
     * @return string
     */
    protected function withoutXmlVersion(string $xml): string
    {
        return preg_replace('/\<\?xml.+?\?\>/', '', $xml);
    }

    /**
     * @param array $response
     * @param string $xml
     */
    public function onError(array $response = [], string $xml): void
    {
        parent::onError($response, $xml);

        $this->addToLog('requestFailed', trim($xml) ? $xml : 'Empty');

        $this->writeToFile($this->xml->saveXML());
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function writeToFile(string $content): string
    {
        return file_put_contents($this->filePath . DIRECTORY_SEPARATOR . $this->filename, $content);
    }

    /**
     * @return string
     */
    abstract protected function getLogFilename(): string;
}