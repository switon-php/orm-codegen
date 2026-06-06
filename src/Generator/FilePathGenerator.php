<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;

use function dirname;
use function preg_replace;
use function str_ends_with;
use function str_replace;
use function substr;

/**
 * File path generator for Entity and Repository files.
 *
 * Generates file paths based on namespace, class name, and output directory,
 * handling both app directory and runtime directory structures.
 *
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\OutputDirectoryResolver Typical upstream input
 * @see \Switon\Core\FilesystemInterface
 */
class FilePathGenerator implements FilePathGeneratorInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;

    protected const string ENTITY_NAMESPACE_SUFFIX = '\\Entity';

    /**
     * Generate Entity file path.
     *
     * @param string $outputDir Output directory alias ('@app' or '@runtime/orm')
     * @param string $namespace Entity namespace (e.g., 'App\Entity' or 'App\Areas\Rbac\Entity')
     * @param string $entityClass Entity class name (e.g., 'User')
     *
     * @return string File path
     */
    public function getEntityPath(string $outputDir, string $namespace, string $entityClass): string
    {
        return $this->getPath($outputDir, $namespace, $entityClass, 'Entity');
    }

    /**
     * Generate Repository file path.
     *
     * @param string $outputDir Output directory alias ('@app' or '@runtime/orm')
     * @param string $namespace Entity namespace (used to derive repository namespace)
     * @param string $entityClass Entity class name (e.g., 'User')
     *
     * @return string File path
     */
    public function getRepositoryPath(string $outputDir, string $namespace, string $entityClass): string
    {
        // Convert Entity namespace to Repository namespace
        if (str_ends_with($namespace, self::ENTITY_NAMESPACE_SUFFIX)) {
            $repoNamespace = substr($namespace, 0, -strlen(self::ENTITY_NAMESPACE_SUFFIX)) . '\\Repository';
        } else {
            $repoNamespace = str_replace("\\Entity\\", "\\Repository\\", $namespace);
        }
        $repoClass = $entityClass . 'Repository';

        return $this->getPath($outputDir, $repoNamespace, $repoClass, 'Repository');
    }

    /**
     * Generate file path for a given namespace, class, and output directory.
     *
     * @param string $outputDir Output directory alias
     * @param string $namespace Full namespace
     * @param string $className Class name
     * @param string $subdir Subdirectory name ('Entities' or 'Repositories')
     *
     * @return string File path
     */
    protected function getPath(string $outputDir, string $namespace, string $className, string $subdir): string
    {
        $relativePath = str_replace('\\', '/', $namespace . '\\' . $className);

        if ($outputDir === '@app') {
            // Generate to app directory: use namespace path directly
            $relativePath = preg_replace('#^App[/\\\\]#', '', $relativePath);
            return $outputDir . '/' . $relativePath . '.php';
        }

        // Generate to runtime/orm directory: use subdirectory
        if (preg_match('#^App/' . $subdir . '/(.+)$#', $relativePath, $matches)) {
            // App\Entity\User -> Entity/User.php
            // App\Repository\UserRepository -> Repository/UserRepository.php
            return $outputDir . '/' . $subdir . '/' . $matches[1] . '.php';
        } elseif (preg_match('#^App/Areas/(.+)/' . $subdir . '/(.+)$#', $relativePath, $matches)) {
            // App\Areas\Rbac\Entity\Role -> Entity/Rbac/Role.php
            // App\Areas\Rbac\Repository\RoleRepository -> Repository/Rbac/RoleRepository.php
            return $outputDir . '/' . $subdir . '/' . $matches[1] . '/' . $matches[2] . '.php';
        }

        // Fallback: remove App\ prefix and use as-is
        $relativePath = preg_replace('#^App[/\\\\]#', '', $relativePath);
        return $outputDir . '/' . $subdir . '/' . $relativePath . '.php';
    }

    /**
     * Get repository namespace and class name from entity namespace and class.
     *
     * @param string $entityNamespace Entity namespace
     * @param string $entityClass Entity class name
     *
     * @return array{namespace: string, className: string}
     */
    public function getRepositoryNamespace(string $entityNamespace, string $entityClass): array
    {
        if (str_ends_with($entityNamespace, self::ENTITY_NAMESPACE_SUFFIX)) {
            $repositoryNamespace = substr($entityNamespace, 0, -strlen(self::ENTITY_NAMESPACE_SUFFIX)) . '\\Repository';
        } else {
            $repositoryNamespace = str_replace("\\Entity\\", "\\Repository\\", $entityNamespace);
        }
        $repositoryClass = $entityClass . 'Repository';

        return ['namespace' => $repositoryNamespace, 'className' => $repositoryClass];
    }

    /**
     * Ensure directory exists for the given file path.
     *
     * @param string $filePath File path
     */
    public function ensureDirectory(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }
    }
}
