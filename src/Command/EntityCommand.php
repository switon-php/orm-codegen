<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Command;

use Switon\Command\Attribute\Hidden;
use Switon\Command\Attribute\Tool;
use Switon\Console\Colors;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassName;
use Switon\Core\ConsoleInterface;
use Switon\Core\Exception;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Db\ClientInterface;
use Switon\Db\TableScannerInterface;
use Switon\Di\ContainerInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Orm\NamingStrategy\CamelNamingStrategy;
use Switon\Orm\NamingStrategy\DefaultNamingStrategy;
use Switon\Orm\NamingStrategyInterface;
use Switon\OrmCodegen\EntityScannerInterface;
use Switon\OrmCodegen\Generator\EntityClassResolverInterface;
use Switon\OrmCodegen\Generator\EntityGeneratorInterface;
use Switon\OrmCodegen\Generator\FilePathGeneratorInterface;
use Switon\OrmCodegen\Generator\OutputDirectoryResolverInterface;
use Switon\OrmCodegen\Generator\RepositoryGeneratorInterface;
use Throwable;

use function in_array;
use function strlen;

/**
 * CLI entry for generating entity and repository classes from database tables.
 *
 * Road-signs:
 * - makeAction is the generation entry
 * - table scanning comes from db metadata
 * - existing entities are scanned for mapping
 * - generators write entity and repository output
 * - output paths resolve through aliases and mode
 *
 * Guidance: Confirm connection, naming mode, and output target before generation so files land in the intended tree.
 *
 * @see \Switon\Db\TableScannerInterface
 * @see \Switon\OrmCodegen\EntityScannerInterface
 * @see \Switon\OrmCodegen\Generator\EntityGeneratorInterface
 * @see \Switon\OrmCodegen\Generator\RepositoryGeneratorInterface
 */
class EntityCommand
{
    #[Autowired] protected ConsoleInterface $console;
    /** @var NamedLookupInterface<ClientInterface> */
    #[Autowired] protected NamedLookupInterface $namedLookup;
    #[Autowired] protected TableScannerInterface $tableScanner;
    #[Autowired] protected EntityScannerInterface $entityScanner;
    #[Autowired] protected EntityGeneratorInterface $entityGenerator;
    #[Autowired] protected RepositoryGeneratorInterface $repositoryGenerator;
    #[Autowired] protected FilePathGeneratorInterface $filePathGenerator;
    #[Autowired] protected EntityClassResolverInterface $entityClassResolver;
    #[Autowired] protected OutputDirectoryResolverInterface $outputDirectoryResolver;
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;

    /**
     * Generates entity and repository classes for one table or every table on the selected connection.
     *
     * @param string|null $table Table name or null for all tables
     * @param string $connection Database connection name
     * @param string $naming Naming strategy: camel or empty for snake_case
     */
    public function makeAction(
        ?string $table = null,
        string  $connection = '',
        string  $naming = ''
    ): void {
        // Get connection name (default: 'default')
        if ($connection === '') {
            $connection = 'default';
        }

        // Verify connection exists
        $availableConnections = $this->namedLookup->names(ClientInterface::class);
        if (!in_array($connection, $availableConnections, true)) {
            Exception::raise('Unknown database connection "{connection}", available: {available}', ['connection' => $connection, 'available' => implode(', ', $availableConnections)]);
        }

        // Get naming strategy name (CLI parameter has highest priority, fallback to empty string)
        $namingStrategyName = $naming;

        // Get naming strategy instance for table-to-class name conversion
        $namingStrategy = $this->getNamingStrategyInstance($namingStrategyName);

        // Determine output directory
        $outputDir = $this->outputDirectoryResolver->resolve();

        // Get tables to process
        $tables = $this->getTablesToProcess($table, $connection);

        // Get existing Entity mappings
        $entityMapping = $this->entityScanner->scan();

        if (empty($tables)) {
            $this->console->writeLn('No tables found to generate.');
            return;
        }

        $this->console->writeLn("Generating Entity and Repository classes for connection: $connection");
        $this->console->writeLn("Output directory: $outputDir");
        $this->console->writeLn('Naming strategy: ' . ($namingStrategyName ?: 'original'));
        $this->console->writeLn();

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($tables as $tableName) {
            try {
                // Check if Entity already exists
                $existingEntityClass = $entityMapping[$connection][$tableName] ?? null;

                if ($existingEntityClass !== null) {
                    // Entity exists - use existing class name
                    $entityInfo = ClassName::split($existingEntityClass);
                    $entityNamespace = $entityInfo['namespace'];
                    $entityClass = $entityInfo['className'];
                } else {
                    // Entity does not exist - infer namespace and class name
                    // Pass all entities from all connections for prefix matching
                    $fullClassName = $this->entityClassResolver->resolve($tableName, $entityMapping);
                    $entityInfo = ClassName::split($fullClassName);
                    $entityNamespace = $entityInfo['namespace'];
                    $entityClass = $entityInfo['className'];
                }

                // Generate Entity
                $entityCode = $this->entityGenerator->generate(
                    $connection,
                    $tableName,
                    $entityNamespace,
                    $entityClass,
                    $namingStrategyName
                );

                // Generate Repository
                $repoInfo = $this->filePathGenerator->getRepositoryNamespace($entityNamespace, $entityClass);
                $repositoryCode = $this->repositoryGenerator->generate(
                    $repoInfo['namespace'],
                    $repoInfo['className'],
                    $entityNamespace,
                    $entityClass
                );

                // Determine file paths
                $entityPath = $this->filePathGenerator->getEntityPath($outputDir, $entityNamespace, $entityClass);
                $repositoryPath = $this->filePathGenerator->getRepositoryPath($outputDir, $entityNamespace, $entityClass);

                // Ensure directories exist
                $this->filePathGenerator->ensureDirectory($entityPath);
                $this->filePathGenerator->ensureDirectory($repositoryPath);

                // Write files
                $this->filesystem->write($entityPath, $entityCode);
                $this->filesystem->write($repositoryPath, $repositoryCode);

                $this->console->writeLn(
                    $this->console->colorize("✓", Colors::FC_GREEN) . " Generated: $tableName"
                );
                $generatedCount++;
                $this->updateEntitiesDoc($tableName, $entityNamespace . '\\' . $entityClass);
            } catch (Throwable $e) {
                $this->console->writeLn(
                    $this->console->colorize("✗", Colors::FC_RED) . " Failed: $tableName - " . $e->getMessage()
                );
                $skippedCount++;
            }
        }

        $this->console->writeLn();
        $this->console->writeLn("Generated: $generatedCount, Skipped: $skippedCount");
    }

    /**
     * Prints machine-readable entity specs derived from database metadata.
     *
     * @param string|null $connection Connection name or null for all
     * @param bool $plain Compact one-line JSON
     * @param string|null $table Omit for all; comma list or glob pattern
     */
    #[Hidden]
    public function specAction(?string $connection = null, bool $plain = false, ?string $table = null): void
    {
        $availableConnections = $this->namedLookup->names(ClientInterface::class);
        $allConnections = ($connection === null || $connection === '');
        if (!$allConnections && !in_array($connection, $availableConnections, true)) {
            Exception::raise('Unknown connection "{connection}", available: {available}', [
                'connection' => $connection,
                'available' => implode(', ', $availableConnections),
            ]);
        }

        $entityMapping = $this->entityScanner->scan();
        $result = [];
        $connectionsToUse = $allConnections ? $availableConnections : [$connection];

        foreach ($connectionsToUse as $conn) {
            $tables = $this->getTablesToProcess(null, $conn);
            if ($table !== null && $table !== '') {
                $tables = $this->filterTablesBySpec($tables, $table);
                if (!$allConnections && $tables === []) {
                    Exception::raise('No tables match filter "{table}" in connection "{connection}"', ['table' => $table, 'connection' => $conn]);
                }
            }
            $list = [];
            foreach ($tables as $tableName) {
                $raw = $this->entityGenerator->getTableSpec($conn, $tableName);
                $list[] = [
                    'table' => $tableName,
                    'class' => $this->resolveSpecEntityClass($conn, $tableName, $entityMapping),
                    'primaryKey' => $raw['primaryKey'],
                    'properties' => $raw['properties'],
                ];
            }
            $result[$conn] = $list;
        }

        if ($table !== null && $table !== '' && $allConnections) {
            $total = array_sum(array_map('count', $result));
            if ($total === 0) {
                Exception::raise('No tables match filter "{table}"', ['table' => $table]);
            }
        }

        $flags = JSON_UNESCAPED_UNICODE | ($plain ? 0 : JSON_PRETTY_PRINT);
        $this->console->write(json_encode($result, $flags));
    }

    /**
     * Lists existing entity classes and their mapped connection and table pairs.
     *
     * @param bool $json Output machine-readable JSON
     */
    #[Hidden, Tool('Returns JSON: entities[] → entity, connection, table.')]
    public function listAction(bool $json = true): void
    {
        $mapping = $this->entityScanner->scan();
        $rows = [];
        foreach ($mapping as $connName => $tableToEntity) {
            foreach ($tableToEntity as $tableName => $entityClass) {
                $rows[] = [$entityClass, $connName, $tableName];
            }
        }
        if ($rows === []) {
            if ($json) {
                $this->console->writeLn('{"entities":[]}');
                return;
            }
            $this->console->writeLn('No entities found.');
            return;
        }
        if ($json) {
            $out = array_map(static fn ($r) => ['entity' => $r[0], 'connection' => $r[1], 'table' => $r[2]], $rows);
            $this->console->writeLn(json_encode(['entities' => $out], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        $this->console->table(['entity', 'connection', 'table'], $rows);
    }

    /**
     * Filters the scanned table list using a comma list or one glob-style pattern.
     *
     * @param list<string> $tables
     *
     * @return list<string>
     * Comma = explicit list; no comma = glob (auto-append * if none, so "rbac" → "rbac*").
     */
    protected function filterTablesBySpec(array $tables, string $filter): array
    {
        $filter = trim($filter);
        if (str_contains($filter, ',')) {
            $want = array_map('trim', explode(',', $filter));
            return array_values(array_intersect($tables, $want));
        }
        if (!str_contains($filter, '*')) {
            $filter .= '*';
        }
        return array_values(array_filter($tables, static fn (string $t) => fnmatch($filter, $t)));
    }

    /**
     * Resolves the entity class for spec output within the current connection scope.
     *
     * @param string $connection Connection name currently being processed
     * @param string $tableName Table name
     * @param array<string, array<string, string>> $entityMapping Mapping from EntityScanner::scan()
     *
     * @return string Full entity class name
     */
    protected function resolveSpecEntityClass(string $connection, string $tableName, array $entityMapping): string
    {
        if (isset($entityMapping[$connection][$tableName])) {
            return $entityMapping[$connection][$tableName];
        }

        // Keep inference isolated to current connection to avoid cross-connection bleed for same table names.
        $scopedMapping = isset($entityMapping[$connection]) ? [$connection => $entityMapping[$connection]] : [];
        return $this->entityClassResolver->resolve($tableName, $scopedMapping);
    }

    /**
     * Resolves the final table list to process based on CLI arguments.
     *
     * @param string|null $table Table name (if specified)
     * @param string $connection Connection name
     *
     * @return list<string> List of table names
     */
    protected function getTablesToProcess(?string $table, string $connection): array
    {
        // Get tables from TableScanner (already filtered by blacklist)
        $tablesByConnection = $this->tableScanner->scan($connection);
        $tables = $tablesByConnection[$connection] ?? [];

        if ($table !== null) {
            // Single table specified - verify it exists
            if (!in_array($table, $tables, true)) {
                Exception::raise('Table "{table}" not found in connection "{connection}"', ['table' => $table, 'connection' => $connection]);
            }
            return [$table];
        }

        // Return all tables (already filtered by TableScanner)
        return $tables;
    }

    /**
     * Get naming strategy instance based on strategy name.
     *
     * @param string $strategyName Strategy name: 'camel' or '' for default
     *
     * @return NamingStrategyInterface Naming strategy instance (never null)
     */
    protected function getNamingStrategyInstance(string $strategyName): NamingStrategyInterface
    {
        if ($strategyName === 'camel') {
            return $this->container->get(CamelNamingStrategy::class);
        }

        // Default: use DefaultNamingStrategy
        return $this->container->get(DefaultNamingStrategy::class);
    }

    /**
     * Ensure docs/entities.md has a row for this table (per entity skill).
     * Tries @root/docs/entities.md; creates file with standard header if missing.
     *
     * @param string $tableName Table name
     * @param string $entityClass Full entity class name (e.g. App\Entity\Admin)
     */
    protected function updateEntitiesDoc(string $tableName, string $entityClass): void
    {
        try {
            $docPath = $this->pathAlias->resolve('@root/docs/entities.md');
        } catch (Throwable $e) {
            return;
        }

        $shortName = ClassName::short($entityClass);
        $row = "| $tableName | $shortName | |\n";

        if (!$this->filesystem->exists($docPath)) {
            $dir = dirname($docPath);
            if (!$this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir);
            }
            $content = "# Table and entity list\n\n"
                . "- Table ↔ entity.\n"
                . "- Read before create/change. `—` = no entity.\n"
                . "- Update when adding or removing.\n\n"
                . "| Table | Entity | Notes |\n"
                . "| --------------- | ------------- | -------------------- |\n"
                . $row;
            $this->filesystem->write($docPath, $content);
            return;
        }

        $content = $this->filesystem->read($docPath);
        if ($content === '') {
            return;
        }
        // Already has this table?
        if (preg_match('/^\|\s*' . preg_quote($tableName, '/') . '\s\|/m', $content) === 1) {
            return;
        }
        // Insert row after the last table data row (line like | x | y | z |)
        if (preg_match('/^(\| [^|]+ \| [^|]+ \| [^|]* \|)\s*$/m', $content, $m, PREG_OFFSET_CAPTURE)) {
            $last = $m[0];
            $pos = $last[1] + strlen($last[0]);
            $content = substr($content, 0, $pos) . "\n" . trim($row) . "\n" . substr($content, $pos);
        } else {
            $content .= $row;
        }
        $this->filesystem->write($docPath, $content);
    }

}
