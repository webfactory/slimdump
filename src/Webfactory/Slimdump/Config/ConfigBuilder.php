<?php

namespace Webfactory\Slimdump\Config;

use Webfactory\Slimdump\Exception\InvalidXmlException;

class ConfigBuilder
{

    /**
     * @param string $file
     * @return Config
     */
    public static function createFromFile($file)
    {
        $xml = file_get_contents($file);

        return self::createFromXmlString($xml);
    }

    /**
     * @param string $xmlString
     * @throws InvalidXmlException
     * @return Config
     */
    public static function createFromXmlString($xmlString) {
        libxml_use_internal_errors(true);
        libxml_clear_errors(); // Cleanup old Errors.
        $xmlElement = simplexml_load_string($xmlString);

        $errors = libxml_get_errors();
        if ($errors) {
            $errorAsString = '';
            foreach ($errors as $error) {
                /** @var \libXMLError $error */
                $errorAsString .= sprintf("%s %d: %s\n", $error->file, $error->line, $error->message);
            }
            throw new InvalidXmlException("Invalid XML! Errors:\n$errorAsString");
        }

        return new Config($xmlElement);
    }

    public static function createConfigurationFromConsecutiveFiles(array $filePaths)
    {
        $xmlStrings = array();
        foreach ($filePaths as $path) {
            $xmlStrings[] = file_get_contents($path);
        }

        return self::createConfigurationFromConsecutiveXmlStrings($xmlStrings);
    }

    /**
     * @param array $xmlStrings
     * @return null|Config
     */
    public static function createConfigurationFromConsecutiveXmlStrings(array $xmlStrings)
    {
        /** @var Config|null $previous */
        $previous = null;

        // We need to flip the array, as we merge the previous
        // configuration into the current one.
        $xmlStrings = array_reverse($xmlStrings);

        while ($xml = array_shift($xmlStrings)) {
            $config = self::createFromXmlString($xml);


            if ($previous !== null) {
                // $previous is ephemeral, so we need to merge
                // the previous configuration into our current
                // one.
                $config->merge($previous);
            }

            $previous = $config;
        }

        return $previous;
    }

}