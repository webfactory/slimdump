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
     * wrapper method which removes the prefix calls the defined faker method
     * @param string $replacementId
     * @return string
     */
    public function generateReplacement($replacementId)
    {
        $replacementMethodName = str_replace(self::PREFIX, '', $replacementId);

        if (strpos($replacementMethodName,'->') !== false) {

            $modifierAndMethod = explode('->',$replacementMethodName);
            $modifierName = $modifierAndMethod[0];
            $replacementMethodName = $modifierAndMethod[1];

            $this->validateReplacementConfiguredModifier($modifierName, $replacementMethodName);

            return (string)$this->faker->$modifierName->$replacementMethodName;
        } else {
            $this->validateReplacementConfigured($replacementMethodName);
            return (string)$this->faker->$replacementMethodName;
        }
    }

    /**
     * validates if this type of replacement was configured
     *
     * @param string $replacementName
     * @throws \Webfactory\Slimdump\Exception\InvalidReplacementOptionException if not a faker method
     */
    private function validateReplacementConfigured($replacementName)
    {
        try {
            $this->faker->__get($replacementName);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidReplacementOptionException($replacementName . ' is no valid faker replacement');
        }
    }
    private function validateReplacementConfiguredModifier($replacementModifier,$replacementName)
    {
        try {
            $this->faker->__get($replacementModifier)->__get($replacementName);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidReplacementOptionException($replacementModifier . "->" . $replacementName . ' is no valid faker replacement');
        }
    }
}
