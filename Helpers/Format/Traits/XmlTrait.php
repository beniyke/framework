<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait provides a convenient way to convert arrays to XML strings.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format\Traits;

use RuntimeException;
use SimpleXMLElement;

trait XmlTrait
{
    public static function asXml(array $data): string
    {
        if (! extension_loaded('simplexml')) {
            throw new RuntimeException('The SimpleXML extension is required to format XML.');
        }

        $output = new SimpleXMLElement('<?xml version="1.0"?><response></response>');
        static::arrayToXML($data, $output);

        return $output->asXML();
    }

    protected static function arrayToXML(array $data, object &$output): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (! is_numeric($key)) {
                    $subnode = $output->addChild("$key");
                    static::arrayToXML($value, $subnode);
                } else {
                    $subnode = $output->addChild("item{$key}");
                    static::arrayToXML($value, $subnode);
                }
            } else {
                $output->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }
}
