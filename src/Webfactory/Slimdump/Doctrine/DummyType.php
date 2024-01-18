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
        throw new LogicException('This Doctrine type assumes that the types won\'t be used to convert any data – it\'s just there to allow the rest of DBAL\'s functionality to work without throwing errors');
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        throw new LogicException('This Doctrine type assumes that the types won\'t be used to convert any data – it\'s just there to allow the rest of DBAL\'s functionality to work without throwing errors');
    }

    public function getName()
    {
        return self::NAME;
    }
}
