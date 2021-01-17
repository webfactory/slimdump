<?php

namespace Webfactory\Slimdump\Config;

use SimpleXMLElement;
use Webfactory\Slimdump\Enum\ReplacementStrategy;
use Webfactory\Slimdump\Exception\InvalidReplacementStrategy;

class Replacement
{
    /** @var SimpleXMLElement */
    private $config;

    /** @var string */
    private $strategy;

    /** @var string|null */
    private $constraint;

    /** @var string */
    private $replacement;

    public function __construct(SimpleXMLElement $config)
    {
        $this->config = $config;

        $attributes = $config->attributes();

        [$strategy, $constraint] = $this->parseCondition($attributes->strategy, $attributes->constraint);

        $this->strategy = $strategy;
        $this->constraint = $constraint;
        $this->replacement = (string) $attributes->value;
    }

    public static function fromColumnReplacementAttr(string $replacement)
    {
        return new self(new SimpleXMLElement(
            '<?xml version="1.0" ?>
            <replacement value="' . $replacement . '" />'
        ));
    }

    public function matchesConstraint($value)
    {
        if ($this->strategy === ReplacementStrategy::REGEX) {
            return preg_match($this->constraint, $value) === 1;
        }

        if ($this->strategy === ReplacementStrategy::EQ) {
            return $this->constraint == $value;
        }

        if ($this->strategy === ReplacementStrategy::NEQ) {
            return $this->constraint != $value;
        }

        if ($this->strategy === ReplacementStrategy::PASSTHROUGH) {
            return true;
        }

        return false;
    }

    public function getReplacement(FakerReplacer $faker)
    {
        if ($faker::isFakerColumn($this->replacement)) {
            return $faker->generateReplacement($this->replacement);
        }

        return $this->replacement;
    }

    private function parseCondition(?string $strategy = null, ?string $constraint = null)
    {
        if ($strategy === ReplacementStrategy::REGEX && ! $constraint) {
            throw InvalidReplacementStrategy::missingRegexConstraint();
        }

        if ($strategy === ReplacementStrategy::REGEX) {
            return [ReplacementStrategy::REGEX, $constraint];
        }

        if ($strategy === ReplacementStrategy::EQ) {
            return [ReplacementStrategy::EQ, $constraint];
        }

        if ($strategy === ReplacementStrategy::NEQ) {
            return [ReplacementStrategy::NEQ, $constraint];
        }

        return [ReplacementStrategy::PASSTHROUGH, null];
    }
}
