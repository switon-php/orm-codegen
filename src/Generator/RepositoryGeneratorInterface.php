<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Generates Repository class code for one Entity.
 *
 * Guidance: use this after the entity class name and namespace are known so repository generation stays deterministic.
 *
 * @see \Switon\OrmCodegen\Generator\RepositoryGenerator
 */
interface RepositoryGeneratorInterface
{
    /**
     * @param string $namespace Repository namespace
     * @param string $repositoryClass Repository class name
     * @param string $entityNamespace Entity namespace
     * @param string $entityClass Entity class name
     *
     * @return string Generated Repository class code
     */
    public function generate(
        string $namespace,
        string $repositoryClass,
        string $entityNamespace,
        string $entityClass
    ): string;
}
