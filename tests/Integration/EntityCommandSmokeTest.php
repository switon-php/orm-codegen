<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Integration;

use Switon\Core\PathAliasInterface;
use Switon\Db\ClientInterface;
use Switon\Di\Factory;
use Switon\OrmCodegen\Command\EntityCommand;
use Switon\OrmCodegen\EntityScannerInterface;
use Switon\OrmCodegen\Generator\OutputDirectoryResolverInterface;
use Switon\OrmCodegen\Tests\TestCase;

class EntityCommandSmokeTest extends TestCase
{
    protected string $tmpDir;
    protected string $outputDir;
    protected string $tableName;
    protected string $entityClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/orm-codegen-smoke-' . uniqid('', true);
        $this->outputDir = $this->tmpDir . '/generated';
        $this->tableName = 'zcodegensmoke_' . substr((string)uniqid('', false), -8);
        $this->entityClass = $this->pascalize($this->tableName);
        mkdir($this->tmpDir, 0777, true);

        $this->prepareAliasesForIsolatedDocWrite();
        $this->prepareDatabase();
        $this->prepareCommandDependencies();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
        parent::tearDown();
    }

    public function testMakeActionGeneratesEntityAndRepositoryFilesFromRealSqliteSchema(): void
    {
        $command = $this->container->get(EntityCommand::class);
        $command->makeAction($this->tableName, 'default', '');

        $entityPath = $this->outputDir . '/Entity/' . $this->entityClass . '.php';
        $repositoryPath = $this->outputDir . '/Repository/' . $this->entityClass . 'Repository.php';
        $docPath = $this->tmpDir . '/docs/entities.md';

        $this->assertFileExists($entityPath);
        $this->assertFileExists($repositoryPath);
        $this->assertFileExists($docPath);

        $entityCode = (string)file_get_contents($entityPath);
        $repositoryCode = (string)file_get_contents($repositoryPath);
        $doc = (string)file_get_contents($docPath);

        $this->assertStringContainsString('class ' . $this->entityClass, $entityCode);
        $this->assertStringContainsString("#[Table('{$this->tableName}')]", $entityCode);
        $this->assertStringContainsString('class ' . $this->entityClass . 'Repository', $repositoryCode);
        $this->assertStringContainsString("| {$this->tableName} | {$this->entityClass} | |", $doc);
    }

    protected function prepareAliasesForIsolatedDocWrite(): void
    {
        /** @var PathAliasInterface $pathAlias */
        $pathAlias = $this->container->get(PathAliasInterface::class);

        $appPath = $pathAlias->resolve('@app');
        $vendorPath = $pathAlias->resolve('@vendor');
        $runtimePath = $pathAlias->resolve('@runtime');

        $pathAlias->set('@root', $this->tmpDir);
        $pathAlias->set('@app', $appPath);
        $pathAlias->set('@vendor', $vendorPath);
        $pathAlias->set('@runtime', $runtimePath);
    }

    protected function prepareDatabase(): void
    {
        $this->container->set(ClientInterface::class, new Factory([
            'default' => ['uri' => 'sqlite::memory:'],
        ]));

        $client = $this->container->get(ClientInterface::class);
        $client->executeUpdate(
            "CREATE TABLE {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )"
        );
    }

    protected function prepareCommandDependencies(): void
    {
        $this->container->set(
            OutputDirectoryResolverInterface::class,
            new class ($this->outputDir) implements OutputDirectoryResolverInterface {
                protected string $outputDir;

                public function __construct(string $outputDir)
                {
                    $this->outputDir = $outputDir;
                }

                public function resolve(): string
                {
                    return $this->outputDir;
                }
            }
        );

        $this->container->set(
            EntityScannerInterface::class,
            new class () implements EntityScannerInterface {
                public function scan(): array
                {
                    return [];
                }
            }
        );
    }

    protected function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } elseif (is_file($child)) {
                unlink($child);
            }
        }

        rmdir($path);
    }

    protected function pascalize(string $name): string
    {
        $parts = explode('_', $name);
        $class = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $class .= ucfirst($part);
        }
        return $class;
    }
}
