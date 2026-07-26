<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Fixtures;

use Switon\Core\ConsoleInterface;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Db\TableScannerInterface;
use Switon\Di\ContainerInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Orm\NamingStrategyInterface;
use Switon\OrmCodegen\Command\EntityCommand;
use Switon\OrmCodegen\EntityScannerInterface;
use Switon\OrmCodegen\Generator\EntityClassResolverInterface;
use Switon\OrmCodegen\Generator\EntityGeneratorInterface;
use Switon\OrmCodegen\Generator\FilePathGeneratorInterface;
use Switon\OrmCodegen\Generator\OutputDirectoryResolverInterface;
use Switon\OrmCodegen\Generator\RepositoryGeneratorInterface;

class EntityCommandProbe extends EntityCommand
{
    public function assignDependenciesForTest(
        ConsoleInterface $console,
        EntityScannerInterface $entityScanner,
        TableScannerInterface $tableScanner,
        ContainerInterface $container,
        FilesystemInterface $filesystem,
        PathAliasInterface $pathAlias,
        NamedLookupInterface $namedLookup,
        OutputDirectoryResolverInterface $outputDirectoryResolver,
        EntityGeneratorInterface $entityGenerator,
        RepositoryGeneratorInterface $repositoryGenerator,
        FilePathGeneratorInterface $filePathGenerator,
        EntityClassResolverInterface $entityClassResolver,
    ): void {
        $this->console = $console;
        $this->entityScanner = $entityScanner;
        $this->tableScanner = $tableScanner;
        $this->container = $container;
        $this->filesystem = $filesystem;
        $this->pathAlias = $pathAlias;
        $this->namedLookup = $namedLookup;
        $this->outputDirectoryResolver = $outputDirectoryResolver;
        $this->entityGenerator = $entityGenerator;
        $this->repositoryGenerator = $repositoryGenerator;
        $this->filePathGenerator = $filePathGenerator;
        $this->entityClassResolver = $entityClassResolver;
    }

    /**
     * @param list<string> $tables
     *
     * @return list<string>
     */
    public function filterTablesBySpecForTest(array $tables, string $filter): array
    {
        return $this->filterTablesBySpec($tables, $filter);
    }

    /**
     * @return list<string>
     */
    public function getTablesToProcessForTest(?string $table, string $connection): array
    {
        return $this->getTablesToProcess($table, $connection);
    }

    public function getNamingStrategyInstanceForTest(string $strategyName): NamingStrategyInterface
    {
        return $this->getNamingStrategyInstance($strategyName);
    }

    public function updateEntitiesDocForTest(string $tableName, string $entityClass): void
    {
        $this->updateEntitiesDoc($tableName, $entityClass);
    }
}
