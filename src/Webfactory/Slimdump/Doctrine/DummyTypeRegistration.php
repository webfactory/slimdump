<?php

namespace Webfactory\Slimdump\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

/**
 * The SchemaManager throws when it encounters a native DB column type Doctrine
 * does not map. Registers a placeholder DummyType for every native database
 * column type that Doctrine does not map by default (enum, set, geometry, …).
 */
final class DummyTypeRegistration
{
    public static function register(Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();

        if (!Type::hasType(DummyType::NAME)) {
            Type::addType(DummyType::NAME, DummyType::class);
        }

        $schema = $connection->getDatabase();
        if (null === $schema) {
            return;
        }

        $sql = 'SELECT DISTINCT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?';

        foreach ($connection->fetchAllAssociative($sql, [$schema]) as $row) {
            $dbType = strtolower((string) $row['DATA_TYPE']);

            if ('' !== $dbType && !$platform->hasDoctrineTypeMappingFor($dbType)) {
                $platform->registerDoctrineTypeMapping($dbType, DummyType::NAME);
            }
        }
    }
}
