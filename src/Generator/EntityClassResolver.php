<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassName;
use Switon\Core\FilesystemInterface;
use Switon\Core\Naming;

use function basename;
use function in_array;
use function str_starts_with;
use function strpos;
use function strrpos;
use function substr;

/**
 * Resolves Entity class names and namespaces from table names.
 *
 * Handles Area inference and namespace resolution based on table naming patterns.
 * Uses intelligent inference with three priority levels:
 * 1. Direct match if table already has entity
 * 2. Prefix matching to inherit namespace from existing entities
 * 3. Area directory inference as fallback
 *
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\EntityScanner Typical producer of $entities mapping
 * @see \Switon\Core\FilesystemInterface
 * @see \Switon\Core\Naming
 * @see \Switon\Core\ClassName
 */
class EntityClassResolver implements EntityClassResolverInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;

    /**
     * Resolve Entity full class name from table name.
     *
     * Inference priority:
     * 1. Check if table already has entity (direct match across all connections)
     * 2. Match by table prefix to inherit namespace from existing entities (across all connections)
     * 3. Fallback to Area inference (only if Area directory exists)
     *
     * @param string $table Table name
     * @param array<string, array<string, string>> $entities Entity mapping from EntityScanner::scan() (all connections)
     *
     * @return string Full class name (e.g., 'App\Areas\Rbac\Entity\Roles' or 'App\Entity\User')
     */
    public function resolve(string $table, array $entities): string
    {
        // Step 1: Check if table already has entity (search across all connections)
        foreach ($entities as $connectionEntities) {
            if (isset($connectionEntities[$table])) {
                return $connectionEntities[$table];
            }
        }

        // Step 2: Try prefix matching (if table has underscore)
        if (($lastPos = strrpos($table, '_')) !== false) {
            // Generate at most two prefixes: longest first, then shorter if available
            $prefixes = [];
            // First attempt: use prefix up to last underscore
            $prefixes[] = substr($table, 0, $lastPos + 1); // Include trailing underscore
            // Second attempt: if there's another underscore, try shorter prefix
            if (($prevPos = strrpos(substr($table, 0, $lastPos), '_')) !== false) {
                $prefixes[] = substr($table, 0, $prevPos + 1); // Include trailing underscore
            }

            // Try each prefix from longest to shortest
            foreach ($prefixes as $prefix) {
                // Search across all connections
                foreach ($entities as $connectionEntities) {
                    // Search all entities in this connection
                    foreach ($connectionEntities as $existingTable => $existingClass) {
                        if (str_starts_with($existingTable, $prefix)) {
                            // Found matching prefix, extract namespace from existing entity
                            $namespace = ClassName::namespace($existingClass);

                            // Extract remaining part of table name (after prefix)
                            $remaining = substr($table, strlen($prefix));
                            $className = Naming::pascal($remaining);

                            return ClassName::join($namespace, $className);
                        }
                    }
                }
            }
        }

        // Step 3: Fallback to Area inference (only if Area directory exists)
        $areas = $this->getAreas();

        if (($pos = strpos($table, '_')) !== false) {
            $prefix = substr($table, 0, $pos);
            $areaName = Naming::pascal($prefix);

            if (in_array($areaName, $areas, true)) {
                // Area directory exists, use Area namespace
                $remainingTable = substr($table, $pos + 1);
                $entityClass = Naming::pascal($remainingTable);
                return "App\\Areas\\$areaName\\Entity\\$entityClass";
            }
            // If Area directory doesn't exist, fall through to default global Entity
        }

        // Default global Entity (no Area inference if directory doesn't exist)
        $entityClass = Naming::pascal($table);
        return "App\\Entity\\$entityClass";
    }

    /**
     * Get Areas list by checking directory existence.
     *
     * Returns all Area directories that exist, without checking Entities subdirectory.
     * This allows new Areas to be recognized even before Entities directory is created.
     *
     * @return array<string> List of area names
     */
    protected function getAreas(): array
    {
        $areas = [];
        $areaDirs = $this->filesystem->glob('@app/Areas/*', GLOB_ONLYDIR) ?: [];
        foreach ($areaDirs as $item) {
            $area = basename($item);
            // Just check Area directory exists, don't check Entities subdirectory
            $areas[] = $area;
        }
        return $areas;
    }
}
