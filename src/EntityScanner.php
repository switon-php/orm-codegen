<?php

declare(strict_types=1);

namespace Switon\OrmCodegen;

use ReflectionAttribute;
use ReflectionClass;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassScannerInterface;
use Switon\Orm\Attribute\Connection;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\EntityMetadataInterface;

/**
 * Scans entity classes and builds connection-table-to-class mappings.
 *
 * Road-signs:
 * - class discovery comes from ClassScanner
 * - Entity subclasses are filtered in
 * - Table and Connection attributes override inference
 * - output groups by connection then table
 *
 * Guidance: Keep scan paths alias-based and let metadata own table-name inference when <code>Table</code> is absent.
 *
 * @see \Switon\Core\ClassScannerInterface
 * @see \Switon\Orm\Attribute\Table
 * @see \Switon\Orm\Attribute\Connection
 * @see \Switon\OrmCodegen\Generator\EntityClassResolver
 */
class EntityScanner implements EntityScannerInterface
{
    #[Autowired] protected ClassScannerInterface $classScanner;
    #[Autowired] protected EntityMetadataInterface $entityMetadata;

    /** @var array<string, string> */
    #[Autowired] protected array $paths = [
        '@app/Entity/*.php' => 'App\\Entity\\*',
        '@app/Areas/*/Entity/*.php' => 'App\\Areas\\*\\Entity\\*',
    ];

    /**
     * Scans entity classes and builds the connection-to-table mapping used by code generation.
     *
     * Returns a mapping of connection names to table-to-entity-class mappings.
     * Structure: `['connection' => ['table' => 'FullEntityClass']]`
     *
     * @return array<string, array<string, string>> Mapping structure
     */
    public function scan(): array
    {
        $mapping = [];
        foreach ($this->classScanner->scan($this->paths, null, Entity::class) as $className) {
            $this->scanClass($className, $mapping);
        }

        return $mapping;
    }

    /**
     * Scans one candidate class and adds it to the mapping when it is a valid ORM entity.
     *
     * @param class-string $className Entity class name to inspect
     * @param array<string, array<string, string>> $mapping Mapping array to populate
     */
    protected function scanClass(string $className, array &$mapping): void
    {
        if (!is_a($className, Entity::class, true)) {
            return;
        }

        $rClass = new ReflectionClass($className);

        /** @var Table|null $tableAttribute */
        // Extract table name from #[Table] attribute or infer via NamingStrategy
        $tableAttribute = $this->getClassAttribute($rClass, Table::class);
        if ($tableAttribute !== null) {
            $table = $tableAttribute->name;
        } else {
            // No #[Table] attribute — infer table name using NamingStrategy
            $namingStrategy = $this->entityMetadata->getNamingStrategy($className);
            $table = $namingStrategy->classToTableName($rClass->getShortName());
        }

        /** @var Connection|null $connectionAttribute */
        // Extract connection name from #[Connection] attribute (default: 'default')
        $connectionAttribute = $this->getClassAttribute($rClass, Connection::class);
        $connection = ($connectionAttribute !== null) ? $connectionAttribute->name : 'default';

        // Build mapping: connection => table => entity class
        if (!isset($mapping[$connection])) {
            $mapping[$connection] = [];
        }
        $mapping[$connection][$table] = $className;
    }

    /**
     * Returns one class-level attribute instance from the reflected entity class when present.
     *
     * @template T of object
     *
     * @param ReflectionClass<Entity> $rClass Reflection class
     * @param class-string<T> $attributeClass
     *
     * @return T|null
     */
    protected function getClassAttribute(ReflectionClass $rClass, string $attributeClass): ?object
    {
        $attributes = $rClass->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
        if (($attribute = $attributes[0] ?? null) !== null) {
            return $attribute->newInstance();
        }
        return null;
    }

}
