<?php

namespace Webfactory\Slimdump\Enum;

class ReplacementStrategy
{
    public const PASSTHROUGH = 'passthrough';
    public const EQ = 'eq';
    public const NEQ = 'neq';
    public const REGEX = 'regex';
}
