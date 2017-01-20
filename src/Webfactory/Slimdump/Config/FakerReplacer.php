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

    private $replacementOptions = [
        self::PREFIX . 'NAME' => 'generateFullNameReplacement',
        self::PREFIX . 'FIRSTNAME' => 'generateFirstNameReplacement',
        self::PREFIX . 'LASTNAME' => 'generateLastNameReplacement',
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
        $this->validateReplacementConfigured($replacementId);
        return $this->getReplacementById($replacementId);
    }

    /**
     * validates if this type of replacement was configured
     *
     * @param string $replacementId
     * @throws \Webfactory\Slimdump\Exception\InvalidReplacementOptionException if not configured in $this->replacementOptions
     */
    private function validateReplacementConfigured($replacementId)
    {
        if (!array_key_exists($replacementId, $this->replacementOptions)) {
            throw new InvalidReplacementOptionException($replacementId . ' is no valid faker replacement');
        }
    }

    /**
     * @param $replacementId
     * @return string
     */
    private function getReplacementById($replacementId)
    {
        $methodName = $this->replacementOptions[$replacementId];
        return $this->$methodName();
    }

    /**
     * returns first and lastname separated by space
     * Please note: This method might be marked as unused in your IDE.
     * Method is called by wrapper method getReplacementById
     * @return string
     */
    private function generateFullNameReplacement()
    {
        return $this->faker->firstName() . ' ' . $this->faker->lastName();
    }

    /**
     * returns random first name
     * Please note: This method might be marked as unused in your IDE.
     * Method is called by wrapper method getReplacementById
     * @return string
     */
    private function generateFirstNameReplacement()
    {
        return $this->faker->firstName();
    }

    /**
     * returns random last name
     * Please note: This method might be marked as unused in your IDE.
     * Method is called by wrapper method getReplacementById
     * @return string
     */
    private function generateLastNameReplacement()
    {
        return $this->faker->lastName();
    }
}
