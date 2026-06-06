<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;

use function array_merge;

/**
 * Resolves output directory based on configuration and app/Entity status.
 *
 * Road-signs:
 * - output mode auto runtime app
 * - auto inspects @app/Entity
 * - only the shared base entity file counts as empty
 * - other business entity files or subdirs go to @runtime/orm
 * - filesystem alias-based checks
 *
 * Guidance: Keep generation output deterministic when CI or scripts depend on paths.
 *
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\EntityGenerator Typical consumer
 * @see \Switon\OrmCodegen\Generator\RepositoryGenerator Typical consumer
 * @see \Switon\OrmCodegen\Generator\FilePathGenerator Typical consumer
 * @see \Switon\Core\FilesystemInterface
 */
class OutputDirectoryResolver implements OutputDirectoryResolverInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;

    /** @var string Output mode flag. */
    #[Autowired] protected string $mode = 'auto';

    /**
     * Resolves the output directory alias based on mode and whether the app entity tree is effectively empty.
     *
     * @return string Output directory alias ('@app' or '@runtime/orm')
     */
    public function resolve(): string
    {
        // Explicitly specified runtime
        if ($this->mode === 'runtime') {
            return '@runtime/orm';
        }

        // Explicitly specified app
        if ($this->mode === 'app') {
            return '@app';
        }

        // Auto mode: treat only the shared base entity file as empty
        $appEntitiesPath = '@app/Entity';
        if ($this->filesystem->exists($appEntitiesPath)) {
            $phpFiles = $this->filesystem->glob($appEntitiesPath . '/*.php') ?: [];
            $subDirs = $this->filesystem->glob($appEntitiesPath . '/*', GLOB_ONLYDIR) ?: [];
            $files = array_merge(
                array_filter($phpFiles, static fn (string $path): bool => basename($path) !== 'Entity.php'),
                $subDirs
            );
            if (empty($files)) {
                // app/Entity only has the shared base entity, can generate to app directory
                return '@app';
            }
        } else {
            // app/Entity does not exist, treat as empty, can generate to app directory
            return '@app';
        }

        // app/Entity is not empty, generate to runtime/orm directory
        return '@runtime/orm';
    }
}
