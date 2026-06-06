<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Switon\Core\ConsoleInterface;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Db\ClientInterface;
use Switon\Db\TableScannerInterface;
use Switon\Di\ContainerInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Orm\NamingStrategy\CamelNamingStrategy;
use Switon\Orm\NamingStrategy\DefaultNamingStrategy;
use Switon\OrmCodegen\Command\EntityCommand;
use Switon\OrmCodegen\EntityScannerInterface;
use Switon\OrmCodegen\Generator\EntityClassResolverInterface;
use Switon\OrmCodegen\Generator\EntityGeneratorInterface;
use Switon\OrmCodegen\Generator\FilePathGeneratorInterface;
use Switon\OrmCodegen\Generator\OutputDirectoryResolverInterface;
use Switon\OrmCodegen\Generator\RepositoryGeneratorInterface;
use ReflectionClass;
use RuntimeException;

use function json_decode;

#[AllowMockObjectsWithoutExpectations]
class EntityCommandTest extends TestCase
{
    protected EntityCommand $command;
    protected ConsoleInterface&MockObject $console;
    protected EntityScannerInterface&MockObject $entityScanner;
    protected TableScannerInterface&MockObject $tableScanner;
    protected ContainerInterface&MockObject $container;
    protected FilesystemInterface&MockObject $filesystem;
    protected PathAliasInterface&MockObject $pathAlias;
    protected NamedLookupInterface&MockObject $namedLookup;
    protected OutputDirectoryResolverInterface&MockObject $outputDirectoryResolver;
    protected EntityGeneratorInterface&MockObject $entityGenerator;
    protected RepositoryGeneratorInterface&MockObject $repositoryGenerator;
    protected FilePathGeneratorInterface&MockObject $filePathGenerator;
    protected EntityClassResolverInterface&MockObject $entityClassResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new EntityCommand();
        $this->console = $this->createMock(ConsoleInterface::class);
        $this->entityScanner = $this->createMock(EntityScannerInterface::class);
        $this->tableScanner = $this->createMock(TableScannerInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->filesystem = $this->createMock(FilesystemInterface::class);
        $this->pathAlias = $this->createMock(PathAliasInterface::class);
        $this->namedLookup = $this->createMock(NamedLookupInterface::class);
        $this->outputDirectoryResolver = $this->createMock(OutputDirectoryResolverInterface::class);
        $this->entityGenerator = $this->createMock(EntityGeneratorInterface::class);
        $this->repositoryGenerator = $this->createMock(RepositoryGeneratorInterface::class);
        $this->filePathGenerator = $this->createMock(FilePathGeneratorInterface::class);
        $this->entityClassResolver = $this->createMock(EntityClassResolverInterface::class);

        $this->injectProperty($this->command, 'console', $this->console);
        $this->injectProperty($this->command, 'entityScanner', $this->entityScanner);
        $this->injectProperty($this->command, 'tableScanner', $this->tableScanner);
        $this->injectProperty($this->command, 'container', $this->container);
        $this->injectProperty($this->command, 'filesystem', $this->filesystem);
        $this->injectProperty($this->command, 'pathAlias', $this->pathAlias);
        $this->injectProperty($this->command, 'namedLookup', $this->namedLookup);
        $this->injectProperty($this->command, 'outputDirectoryResolver', $this->outputDirectoryResolver);
        $this->injectProperty($this->command, 'entityGenerator', $this->entityGenerator);
        $this->injectProperty($this->command, 'repositoryGenerator', $this->repositoryGenerator);
        $this->injectProperty($this->command, 'filePathGenerator', $this->filePathGenerator);
        $this->injectProperty($this->command, 'entityClassResolver', $this->entityClassResolver);
    }

    public function testListActionOutputsEmptyEntitiesJsonWhenNoMappingsFound(): void
    {
        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with('{"entities":[]}');

        $this->command->listAction(true);
    }

    public function testListActionOutputsEntitiesInJsonMode(): void
    {
        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([
                'default' => [
                    'users' => 'App\\Entity\\User',
                    'orders' => 'App\\Entity\\Order',
                ],
                'audit' => [
                    'logs' => 'App\\Entity\\AuditLog',
                ],
            ]);

        $captured = null;
        $this->console->expects($this->once())
            ->method('writeLn')
            ->willReturnCallback(static function (string $line) use (&$captured): void {
                $captured = json_decode($line, true);
            });

        $this->command->listAction(true);

        $this->assertSame(
            [
                ['entity' => 'App\\Entity\\User', 'connection' => 'default', 'table' => 'users'],
                ['entity' => 'App\\Entity\\Order', 'connection' => 'default', 'table' => 'orders'],
                ['entity' => 'App\\Entity\\AuditLog', 'connection' => 'audit', 'table' => 'logs'],
            ],
            $captured['entities']
        );
    }

    public function testListActionRendersTableInNonJsonMode(): void
    {
        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([
                'default' => [
                    'users' => 'App\\Entity\\User',
                ],
            ]);

        $this->console->expects($this->once())
            ->method('table')
            ->with(
                ['entity', 'connection', 'table'],
                [['App\\Entity\\User', 'default', 'users']],
                8
            );

        $this->command->listAction(false);
    }

    public function testFilterTablesBySpecSupportsCommaList(): void
    {
        $result = $this->invokeProtected('filterTablesBySpec', [
            ['users', 'orders', 'posts'],
            'users, posts',
        ]);

        $this->assertSame(['users', 'posts'], $result);
    }

    public function testFilterTablesBySpecAppendsWildcardWhenMissing(): void
    {
        $result = $this->invokeProtected('filterTablesBySpec', [
            ['rbac_users', 'rbac_roles', 'audit_logs'],
            'rbac',
        ]);

        $this->assertSame(['rbac_users', 'rbac_roles'], $result);
    }

    public function testGetTablesToProcessReturnsRequestedTable(): void
    {
        $this->tableScanner->expects($this->once())
            ->method('scan')
            ->with('default')
            ->willReturn(['default' => ['users', 'orders']]);

        $result = $this->invokeProtected('getTablesToProcess', ['users', 'default']);

        $this->assertSame(['users'], $result);
    }

    public function testGetTablesToProcessThrowsWhenRequestedTableNotFound(): void
    {
        $this->tableScanner->expects($this->once())
            ->method('scan')
            ->with('default')
            ->willReturn(['default' => ['users']]);

        $this->expectException(\Switon\Core\Exception::class);
        $this->expectExceptionMessage('Table "orders" not found in connection "default"');

        $this->invokeProtected('getTablesToProcess', ['orders', 'default']);
    }

    public function testGetNamingStrategyInstanceReturnsCamelStrategy(): void
    {
        $camel = $this->createMock(CamelNamingStrategy::class);

        $this->container->expects($this->once())
            ->method('get')
            ->with(CamelNamingStrategy::class)
            ->willReturn($camel);

        $result = $this->invokeProtected('getNamingStrategyInstance', ['camel']);

        $this->assertSame($camel, $result);
    }

    public function testGetNamingStrategyInstanceReturnsDefaultStrategy(): void
    {
        $default = $this->createMock(DefaultNamingStrategy::class);

        $this->container->expects($this->once())
            ->method('get')
            ->with(DefaultNamingStrategy::class)
            ->willReturn($default);

        $result = $this->invokeProtected('getNamingStrategyInstance', ['']);

        $this->assertSame($default, $result);
    }

    public function testUpdateEntitiesDocReturnsWhenPathResolveFails(): void
    {
        $this->pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@root/docs/entities.md')
            ->willThrowException(new RuntimeException('no alias'));

        $this->filesystem->expects($this->never())->method('write');

        $this->invokeProtected('updateEntitiesDoc', ['users', 'App\\Entity\\User']);
    }

    public function testUpdateEntitiesDocCreatesFileWhenMissing(): void
    {
        $docPath = '/tmp/project/docs/entities.md';
        $docDir = '/tmp/project/docs';

        $this->pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@root/docs/entities.md')
            ->willReturn($docPath);

        $this->filesystem->expects($this->exactly(2))
            ->method('exists')
            ->willReturnMap([
                [$docPath, false],
                [$docDir, false],
            ]);

        $this->filesystem->expects($this->once())
            ->method('mkdir')
            ->with($docDir);

        $this->filesystem->expects($this->once())
            ->method('write')
            ->with(
                $docPath,
                $this->callback(static function (string $content): bool {
                    return str_contains($content, '| users | User | |');
                })
            );

        $this->invokeProtected('updateEntitiesDoc', ['users', 'App\\Entity\\User']);
    }

    public function testSpecActionThrowsForUnknownConnection(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default']);

        $this->expectException(\Switon\Core\Exception::class);
        $this->expectExceptionMessage('Unknown connection "audit", available: default');

        $this->command->specAction('audit');
    }

    public function testSpecActionOutputsJsonForSingleConnection(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default']);

        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn(['default' => ['users' => 'App\\Entity\\User']]);

        $this->tableScanner->expects($this->once())
            ->method('scan')
            ->with('default')
            ->willReturn(['default' => ['users']]);

        $this->entityGenerator->expects($this->once())
            ->method('getTableSpec')
            ->with('default', 'users', '')
            ->willReturn(['primaryKey' => 'user_id', 'properties' => [['name' => 'user_id']]]);

        $this->entityClassResolver->expects($this->never())
            ->method('resolve');

        $captured = null;
        $this->console->expects($this->once())
            ->method('write')
            ->willReturnCallback(static function (string $json) use (&$captured): void {
                $captured = json_decode($json, true);
            });

        $this->command->specAction('default', false, null);

        $this->assertSame('users', $captured['default'][0]['table']);
        $this->assertSame('App\\Entity\\User', $captured['default'][0]['class']);
        $this->assertSame('user_id', $captured['default'][0]['primaryKey']);
    }

    public function testSpecActionUsesConnectionScopedEntityMappingForSameTableAcrossConnections(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default', 'audit']);

        $mapping = [
            'default' => ['users' => 'App\\Entity\\User'],
            'audit' => ['users' => 'App\\Areas\\Audit\\Entity\\User'],
        ];
        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn($mapping);

        $this->tableScanner->expects($this->exactly(2))
            ->method('scan')
            ->willReturnMap([
                ['default', ['default' => ['users']]],
                ['audit', ['audit' => ['users']]],
            ]);

        $this->entityGenerator->expects($this->exactly(2))
            ->method('getTableSpec')
            ->willReturnMap([
                ['default', 'users', '', ['primaryKey' => ['id'], 'properties' => [['name' => 'id']]]],
                ['audit', 'users', '', ['primaryKey' => ['id'], 'properties' => [['name' => 'id']]]],
            ]);

        $this->entityClassResolver->expects($this->never())
            ->method('resolve');

        $captured = null;
        $this->console->expects($this->once())
            ->method('write')
            ->willReturnCallback(static function (string $json) use (&$captured): void {
                $captured = json_decode($json, true);
            });

        $this->command->specAction(null, false, 'users');

        $this->assertSame('App\\Entity\\User', $captured['default'][0]['class']);
        $this->assertSame('App\\Areas\\Audit\\Entity\\User', $captured['audit'][0]['class']);
    }

    public function testMakeActionThrowsForUnknownConnection(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default']);

        $this->expectException(\Switon\Core\Exception::class);
        $this->expectExceptionMessage('Unknown database connection "audit", available: default');

        $this->command->makeAction(null, 'audit');
    }

    public function testMakeActionWritesNoTablesMessageWhenScannerReturnsEmpty(): void
    {
        $defaultNaming = $this->createMock(DefaultNamingStrategy::class);

        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default']);

        $this->container->expects($this->once())
            ->method('get')
            ->with(DefaultNamingStrategy::class)
            ->willReturn($defaultNaming);

        $this->outputDirectoryResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('@app/Entity');

        $this->tableScanner->expects($this->once())
            ->method('scan')
            ->with('default')
            ->willReturn(['default' => []]);

        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with('No tables found to generate.');

        $this->command->makeAction(null, 'default', '');
    }

    public function testListActionOutputsEmptyTextWhenNoMappingsFoundAndNonJson(): void
    {
        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with('No entities found.');

        $this->command->listAction(false);
    }

    public function testSpecActionThrowsWhenTableFilterNoMatchesInSingleConnection(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default']);

        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $this->tableScanner->expects($this->once())
            ->method('scan')
            ->with('default')
            ->willReturn(['default' => ['users']]);

        $this->expectException(\Switon\Core\Exception::class);
        $this->expectExceptionMessage('No tables match filter "orders" in connection "default"');

        $this->console->expects($this->never())->method('write');

        $this->command->specAction('default', false, 'orders');
    }

    public function testSpecActionThrowsWhenTableFilterNoMatchesAcrossAllConnections(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default', 'audit']);

        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $this->tableScanner->expects($this->exactly(2))
            ->method('scan')
            ->willReturnMap([
                ['default', ['default' => ['users']]],
                ['audit', ['audit' => ['logs']]],
            ]);

        $this->expectException(\Switon\Core\Exception::class);
        $this->expectExceptionMessage('No tables match filter "orders"');

        $this->console->expects($this->never())->method('write');

        $this->command->specAction(null, false, 'orders');
    }

    public function testMakeActionGeneratesEntityAndRepositoryForSingleTable(): void
    {
        $this->namedLookup->expects($this->once())
            ->method('names')
            ->with(ClientInterface::class)
            ->willReturn(['default']);

        $this->container->expects($this->once())
            ->method('get')
            ->with(DefaultNamingStrategy::class)
            ->willReturn($this->createMock(DefaultNamingStrategy::class));

        $this->outputDirectoryResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('@app');

        $this->tableScanner->expects($this->once())
            ->method('scan')
            ->with('default')
            ->willReturn(['default' => ['users']]);

        $this->entityScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $this->entityClassResolver->expects($this->once())
            ->method('resolve')
            ->with('users', [])
            ->willReturn('App\\Entity\\User');

        $this->entityGenerator->expects($this->once())
            ->method('generate')
            ->with('default', 'users', 'App\\Entity', 'User', '')
            ->willReturn('<?php // entity code');

        $this->filePathGenerator->expects($this->once())
            ->method('getRepositoryNamespace')
            ->with('App\\Entity', 'User')
            ->willReturn(['namespace' => 'App\\Repositories', 'className' => 'UserRepository']);

        $this->repositoryGenerator->expects($this->once())
            ->method('generate')
            ->with('App\\Repositories', 'UserRepository', 'App\\Entity', 'User')
            ->willReturn('<?php // repo code');

        $this->filePathGenerator->expects($this->once())
            ->method('getEntityPath')
            ->with('@app', 'App\\Entity', 'User')
            ->willReturn('/tmp/User.php');

        $this->filePathGenerator->expects($this->once())
            ->method('getRepositoryPath')
            ->with('@app', 'App\\Entity', 'User')
            ->willReturn('/tmp/UserRepository.php');

        $this->filePathGenerator->expects($this->exactly(2))
            ->method('ensureDirectory');

        $written = [];
        $this->filesystem->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(static function (string $path, string $code) use (&$written): void {
                $written[] = [$path, $code];
            });

        // Make updateEntitiesDoc early-return to avoid filesystem content assertions in this test
        $this->pathAlias->expects($this->atLeastOnce())
            ->method('resolve')
            ->willThrowException(new RuntimeException('no alias'));

        $this->console->method('colorize')
            ->willReturn('');

        $this->command->makeAction(null, 'default', '');

        $this->assertContains(['/tmp/User.php', '<?php // entity code'], $written);
        $this->assertContains(['/tmp/UserRepository.php', '<?php // repo code'], $written);
    }

    public function testUpdateEntitiesDocSkipsWhenRowAlreadyExists(): void
    {
        $docPath = '/tmp/project/docs/entities.md';

        $this->pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@root/docs/entities.md')
            ->willReturn($docPath);

        $this->filesystem->expects($this->once())
            ->method('exists')
            ->with($docPath)
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('read')
            ->with($docPath)
            ->willReturn("# Title\n\n| users | User | |\n");

        $this->filesystem->expects($this->never())
            ->method('write');

        $this->invokeProtected('updateEntitiesDoc', ['users', 'App\\Entity\\User']);
    }

    public function testUpdateEntitiesDocInsertsRowAfterFirstMatchingTableRow(): void
    {
        $docPath = '/tmp/project/docs/entities.md';

        $this->pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@root/docs/entities.md')
            ->willReturn($docPath);

        $this->filesystem->expects($this->once())
            ->method('exists')
            ->with($docPath)
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('read')
            ->with($docPath)
            ->willReturn("# Title\n\n| posts | Post | |\n");

        $this->filesystem->expects($this->once())
            ->method('write')
            ->with(
                $docPath,
                $this->callback(static function (string $content): bool {
                    return str_contains($content, "| posts | Post | |\n| users | User | |");
                })
            );

        $this->invokeProtected('updateEntitiesDoc', ['users', 'App\\Entity\\User']);
    }

    public function testUpdateEntitiesDocAppendsRowWhenNoTableRowPatternFound(): void
    {
        $docPath = '/tmp/project/docs/entities.md';

        $this->pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@root/docs/entities.md')
            ->willReturn($docPath);

        $this->filesystem->expects($this->once())
            ->method('exists')
            ->with($docPath)
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('read')
            ->with($docPath)
            ->willReturn("No table rows here.\n");

        $this->filesystem->expects($this->once())
            ->method('write')
            ->with(
                $docPath,
                $this->callback(static function (string $content): bool {
                    return str_ends_with($content, "| users | User | |\n");
                })
            );

        $this->invokeProtected('updateEntitiesDoc', ['users', 'App\\Entity\\User']);
    }

    private function injectProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setValue($object, $value);
    }

    private function invokeProtected(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass($this->command);
        $target = $reflection->getMethod($method);

        return $target->invokeArgs($this->command, $args);
    }
}
