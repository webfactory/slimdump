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
        $replacementMethodArguments = [];

        $replacementMethodName = str_replace(self::PREFIX, '', $replacementId);

        if (strpos($replacementMethodName, ':') !== false) {
            [$replacementMethodName, $replacementMethodArguments] = explode(':', $replacementMethodName, 2);
            $replacementMethodArguments = str_getcsv(strtolower($replacementMethodArguments));
        }

        if (false !== strpos($replacementMethodName, '->')) {
            [$modifierName, $replacementMethodName] = explode('->', $replacementMethodName);

            $this->validateReplacementConfiguredModifier($modifierName, $replacementMethodName, $replacementMethodArguments);

            return (string) $this->faker->$modifierName->format($replacementMethodName, $replacementMethodArguments);
        }

        $this->validateReplacementConfigured($replacementMethodName, $replacementMethodArguments);

        return (string) $this->faker->format($replacementMethodName, $replacementMethodArguments);
    }

    /**
     * validates if this type of replacement was configured.
     *
     * @param string $replacementName
     * @param array $replacementArguments
     *
     * @throws InvalidReplacementOptionException if not a faker method
     */
    private function validateReplacementConfigured($replacementName, $replacementArguments = [])
    {
        try {
            $this->faker->format($replacementName, $replacementArguments);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidReplacementOptionException($replacementName.' is no valid faker replacement');
        }
    }

    private function validateReplacementConfiguredModifier($replacementModifier, $replacementName, $replacementArguments = [])
    {
        try {
            $this->faker->__get($replacementModifier)->format($replacementName, $replacementArguments);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidReplacementOptionException($replacementModifier.'->'.$replacementName.' is no valid faker replacement');
        }
    }
}
