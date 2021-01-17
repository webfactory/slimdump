<?php

namespace Webfactory\Slimdump\Exception;

use RuntimeException;

class InvalidReplacementStrategy extends RuntimeException
{
    public static function missingRegexConstraint()
    {
        return new self('RegEx strategy requires a `constraint` attribute');
    }
}
