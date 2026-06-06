<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Resolves the output base directory for generated ORM classes.
 *
 * Guidance: use this before file path generation so app-vs-runtime output decisions stay centralized and predictable.
 *
 * @see \Switon\OrmCodegen\Generator\OutputDirectoryResolver
 */
interface OutputDirectoryResolverInterface
{
    /** Resolve output directory alias (`@app` or `@runtime/orm`). */
    public function resolve(): string;
}
