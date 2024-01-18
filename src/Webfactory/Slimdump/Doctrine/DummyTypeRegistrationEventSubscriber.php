<?php

namespace Webfactory\Slimdump\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Types\Type;

class DummyTypeRegistrationEventSubscriber implements EventSubscriber
{
    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    public function __construct(AbstractSchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onSchemaColumnDefinition];
    }

    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $event): void
    {
        $tableColumn = array_change_key_case($event->getTableColumn(), \CASE_LOWER);
        $dbType = strtolower($tableColumn['type']);
        $dbType = strtok($dbType, '(), ');

        if (isset($tableColumn['comment'])) {
            $type = $this->schemaManager->extractDoctrineTypeFromComment($tableColumn['comment'], '');

            if ($type && !Type::hasType($type)) {
                Type::addType($type, DummyType::class);
            }
        }

        $databasePlatform = $this->schemaManager->getDatabasePlatform();
        if (!$databasePlatform->hasDoctrineTypeMappingFor($dbType)) {
            if (!Type::hasType(DummyType::NAME)) {
                Type::addType(DummyType::NAME, DummyType::class);
            }
            $databasePlatform->registerDoctrineTypeMapping($dbType, DummyType::NAME);
        }
    }
}
