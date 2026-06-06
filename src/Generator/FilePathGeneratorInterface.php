<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Generates output file paths for Entity and Repository classes.
 *
 * Guidance: keep all path derivation here so CLI and generators share the same app/runtime layout rules.
 *
 * @see \Switon\OrmCodegen\Generator\FilePathGenerator
 */
interface FilePathGeneratorInterface
{
    /** Generate Entity file path. */
    public function getEntityPath(string $outputDir, string $namespace, string $entityClass): string;

    /** Generate Repository file path. */
    public function getRepositoryPath(string $outputDir, string $namespace, string $entityClass): string;

    /**
     * @return array{namespace: string, className: string}
     */
    public function getRepositoryNamespace(string $entityNamespace, string $entityClass): array;

    /** Ensure target directory exists. */
    public function ensureDirectory(string $filePath): void;
}
