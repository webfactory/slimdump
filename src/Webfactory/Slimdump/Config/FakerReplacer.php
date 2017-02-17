<?php

namespace Webfactory\Slimdump\Config;

use Faker;
use Webfactory\Slimdump\Exception\InvalidReplacementOptionException;

/**
 * This class handles column configurations which have a "replacement" option
 * @see https://github.com/webfactory/slimdump#configuration for configuration tutorial
 *
 * @package Webfactory\Slimdump\Config
 * @author franziskahahn
 */
class FakerReplacer
{
    const PREFIX = 'FAKER_';

    /** @var \Faker\Generator */
    private $faker;

    /**
     * configuration for method which are not part of faker itself.
     * You can implement a new method "methodName" which returns for example a
     * format: 'suffix' => 'methodName',
     * @var array
     */
    private $replacementOptions = [
    ];

    /**
     * FakerReplacer constructor.
     * TODO: Add functionality which makes locale configurable
     */
    public function __construct()
    {
        $this->faker = Faker\Factory::create();
    }

    /**
     * check if replacement option is a faker option
     * static to prevent unnecessary instantiation for non-faker columns
     * @param string $replacement
     * @return bool
     */
    public static function isFakerColumn($replacement)
    {
        if (strpos($replacement, self::PREFIX) === 0) {
            return true;
        }

        return false;
    }

    /**
     * wrapper method which calls the method configured in $this->replacementOptions
     * @param string $replacementId
     * @return string
     */
    public function generateReplacement($replacementId)
    {
        $replacementMethodName = str_replace(self::PREFIX, '', $replacementId);
        $this->validateReplacementConfigured($replacementMethodName);
        return $this->getReplacementById($replacementMethodName);
    }

    /**
     * validates if this type of replacement was configured
     *
     * @param string $replacementName
     * @throws \Webfactory\Slimdump\Exception\InvalidReplacementOptionException if not configured in $this->replacementOptions
     */
    private function validateReplacementConfigured($replacementName)
    {
        $isConfiguredReplacement = array_key_exists($replacementName, $this->replacementOptions);
        $isFakerProperty = true;

        try {
            $this->faker->__get($replacementName);
        } catch (\InvalidArgumentException $exception) {
            $isFakerProperty = false;
        }

        if (!$isFakerProperty && !$isConfiguredReplacement) {
            throw new InvalidReplacementOptionException($replacementName . ' is no valid faker replacement');
        }
    }

    /**
     * @param string $replacementName
     * @return string
     */
    private function getReplacementById($replacementName)
    {
        if (array_key_exists($replacementName, $this->replacementOptions)) {
            // internal function
            $methodName = $this->replacementOptions[$replacementName];
            return $this->$methodName();
        }

        // default faker property
        return $this->faker->$replacementName;
    }
}
