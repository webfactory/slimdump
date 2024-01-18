<?php

namespace Webfactory\Slimdump\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

class DummyType extends Type
{
    public const NAME = 'dummy_type';

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        throw new LogicException('this should not be called in the first place');
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        throw new LogicException('this should not be called in the first place');
    }

    public function getName()
    {
        return self::NAME;
    }
}
