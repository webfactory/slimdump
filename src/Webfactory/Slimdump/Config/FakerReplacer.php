<?php

namespace Webfactory\Slimdump\Config;

use Faker;
use Faker\Generator;
use InvalidArgumentException;
use Webfactory\Slimdump\Exception\InvalidReplacementOptionException;

/**
 * This class handles column configurations which have a "replacement" option.
 *
 * @see https://github.com/webfactory/slimdump#configuration for configuration tutorial
 *
 * @author franziskahahn
 */
class FakerReplacer
{
    public const PREFIX = 'FAKER_';

    /** @var Generator */
    private $faker;

    /**
     * TODO: Add functionality which makes locale configurable.
     */
    public function __construct()
    {
        $this->faker = Faker\Factory::create();
    }

    /**
     * check if replacement option is a faker option
     * static to prevent unnecessary instantiation for non-faker columns.
     *
     * @param string $replacement
     *
     * @return bool
     */
    public static function isFakerColumn($replacement)
    {
        return 0 === strpos($replacement, self::PREFIX);
    }

    /**
     * wrapper method which removes the prefix calls the defined faker method.
     *
     * @param string $replacementId
     *
     * @return string
     */
    public function generateReplacement($replacementId)
    {
        $replacementMethodName = str_replace(self::PREFIX, '', $replacementId);

        if (false !== strpos($replacementMethodName, '->')) {
            [$modifierName, $replacementMethodName] = explode('->', $replacementMethodName);

            $this->validateReplacementConfiguredModifier($modifierName, $replacementMethodName);

            return (string) $this->faker->$modifierName->$replacementMethodName;
        }

        $this->validateReplacementConfigured($replacementMethodName);

        return (string) $this->faker->$replacementMethodName;
    }

    /**
     * validates if this type of replacement was configured.
     *
     * @param string $replacementName
     *
     * @throws InvalidReplacementOptionException if not a faker method
     */
    private function validateReplacementConfigured($replacementName)
    {
        try {
            $this->faker->__get($replacementName);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidReplacementOptionException($replacementName.' is no valid faker replacement');
        }
    }

    private function validateReplacementConfiguredModifier($replacementModifier, $replacementName)
    {
        try {
            $this->faker->__get($replacementModifier)->__get($replacementName);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidReplacementOptionException($replacementModifier.'->'.$replacementName.' is no valid faker replacement');
        }
    }
}
