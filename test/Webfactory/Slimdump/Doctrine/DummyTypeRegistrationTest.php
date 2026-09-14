<?php

namespace Webfactory\Slimdump\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DummyTypeRegistrationTest extends TestCase
{
    #[Test]
    public function registersDummyTypeForUnknownDbTypesAndSkipsKnownOnes(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('hasDoctrineTypeMappingFor')->willReturnMap([
            ['int', true],
            ['enum', false],
        ]);
        $platform->expects($this->once())
            ->method('registerDoctrineTypeMapping')
            ->with('enum', DummyType::NAME);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getDatabase')->willReturn('my_schema');
        $connection->method('fetchAllAssociative')->willReturn([
            ['DATA_TYPE' => 'int'],
            ['DATA_TYPE' => 'enum'],
        ]);

        DummyTypeRegistration::register($connection);
    }

    #[Test]
    public function returnsEarlyWhenSchemaNameIsNull(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->never())->method('registerDoctrineTypeMapping');

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getDatabase')->willReturn(null);
        $connection->expects($this->never())->method('fetchAllAssociative');

        DummyTypeRegistration::register($connection);
    }
}
