<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Generates Entity class code and table specs from database metadata.
 *
 * Guidance: use this after table metadata is available and class naming has already been resolved.
 *
 * @see \Switon\OrmCodegen\Generator\EntityGenerator
 */
interface EntityGeneratorInterface
{
    /**
     * @param string $connection Database connection name
     * @param string $table Table name
     * @param string $naming Naming strategy: 'camel' or '' for original
     *
     * @return array{className: string, primaryKey: array<string>, properties: list<array{name: string, phpType: string, maxLength: int|null}>}
     */
    public function getTableSpec(string $connection, string $table, string $naming = ''): array;

    /**
     * @param string $connection Database connection name
     * @param string $table Table name
     * @param string $namespace Entity namespace
     * @param string $className Entity class name
     * @param string $naming Naming strategy: 'camel' or '' for original
     *
     * @return string Generated Entity class code
     */
    public function generate(
        string $connection,
        string $table,
        string $namespace,
        string $className,
        string $naming = ''
    ): string;
}
