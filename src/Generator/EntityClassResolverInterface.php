<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Resolves Entity class names and namespaces from table names and known mappings.
 *
 * Guidance: use this after scanning existing entities so inference can reuse established area or namespace conventions.
 *
 * @see \Switon\OrmCodegen\Generator\EntityClassResolver
 */
interface EntityClassResolverInterface
{
    /**
     * @param string $table Table name
     * @param array<string, array<string, string>> $entities Entity mapping grouped by connection
     *
     * @return string Full class name
     */
    public function resolve(string $table, array $entities): string;
}
