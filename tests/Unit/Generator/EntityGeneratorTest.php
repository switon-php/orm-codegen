<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\FilesystemInterface;
use Switon\Db\Client;
use Switon\Db\ClientInterface;
use Switon\Di\NamedLookupInterface;
use Switon\OrmCodegen\Generator\EntityGenerator;
use Switon\OrmCodegen\Generator\TemplateCompilerInterface;
use Switon\OrmCodegen\Tests\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class EntityGeneratorTest extends TestCase
{
    protected EntityGenerator $generator;
    protected MockObject|FilesystemInterface $mockFilesystem;
    protected MockObject|NamedLookupInterface $mockNamedLookup;
    protected MockObject|TemplateCompilerInterface $mockCompiler;
    protected MockObject|ClientInterface $mockDbClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFilesystem = $this->createMock(FilesystemInterface::class);
        $this->mockNamedLookup = $this->createMock(NamedLookupInterface::class);
        $this->mockCompiler = $this->createMock(TemplateCompilerInterface::class);
        $this->mockDbClient = $this->createMock(ClientInterface::class);

        $this->container->set(FilesystemInterface::class, $this->mockFilesystem);
        $this->container->set(NamedLookupInterface::class, $this->mockNamedLookup);
        $this->container->set(TemplateCompilerInterface::class, $this->mockCompiler);

        $this->mockFilesystem->method('read')
            ->willReturnCallback(static function (string $path): string {
                if ($path === '/virtual/app/Entity/User.php') {
                    return <<<'PHP'
<?php
class User
{
    public const STATUS_ACTIVE = 1;
    protected const string STATUS_DISABLED = 'disabled';
}
PHP;
                }

                return 'entity-template';
            });

        $this->generator = $this->make(EntityGenerator::class, [
            'filesystem' => $this->mockFilesystem,
            'namedLookup' => $this->mockNamedLookup,
            'compiler' => $this->mockCompiler,
        ]);
    }

    public function testGenerateCreatesEntityClass(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'id' => 'INT',
                    'name' => 'VARCHAR(255)',
                    'created_at' => 'DATETIME',
                ],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\Entity',
            'TestEntity',
            ''
        );

        $this->assertIsString($code);
        $this->assertStringContainsString('<?php', $code);
        $this->assertStringContainsString('declare(strict_types=1);', $code);
        $this->assertStringContainsString('namespace App\Entity;', $code);
    }

    public function testGenerateWithCamelNaming(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('user_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'user_id' => 'INT',
                    'user_name' => 'VARCHAR(255)',
                ],
                Client::METADATA_PRIMARY_KEY => ['user_id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach ($attributes as $attribute) { echo $attribute; } ?>');

        $code = $this->generator->generate(
            'default',
            'user_table',
            'App\\Entity',
            'UserEntity',
            'camel'
        );

        $this->assertIsString($code);
        $this->assertStringContainsString('NamingStrategy', $code);
    }

    public function testGenerateWithNonDefaultConnection(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'shard1')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach ($attributes as $attribute) { echo $attribute; } ?>');

        $code = $this->generator->generate(
            'shard1',
            'test_table',
            'App\\Entity',
            'TestEntity',
            ''
        );

        $this->assertStringContainsString("Connection('shard1')", $code);
    }

    public function testGenerateWithAreaEntity(): void
    {
        $this->mockNamedLookup->method('by')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $tableAttribute; ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\\Areas\\Rbac\\Entity',
            'TestEntity',
            ''
        );

        $this->assertStringContainsString("#[Table('test_table')]", $code);
    }

    public function testDbTypeToPhpTypeConvertsIntegers(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach($fields as $field) { echo $field["phpType"]; } ?>');

        $this->mockNamedLookup->method('by')->willReturn($this->mockDbClient);
        $this->mockDbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['id' => 'INT', 'count' => 'BIGINT'],
            Client::METADATA_PRIMARY_KEY => ['id'],
        ]);
        $this->mockFilesystem->method('exists')->willReturn(false);
        $code = $this->generator->generate('default', 'test', 'App\Entity', 'Test', '');

        $this->assertStringContainsString('int', $code);
    }

    public function testDbTypeToPhpTypeConvertsStrings(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach($fields as $field) { echo $field["phpType"]; } ?>');

        $this->mockNamedLookup->method('by')->willReturn($this->mockDbClient);
        $this->mockDbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['name' => 'VARCHAR(255)', 'data' => 'TEXT'],
            Client::METADATA_PRIMARY_KEY => [],
        ]);
        $this->mockFilesystem->method('exists')->willReturn(false);
        $code = $this->generator->generate('default', 'test', 'App\Entity', 'Test', '');

        $this->assertStringContainsString('string', $code);
    }

    public function testDbTypeToPhpTypeConvertsDates(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach($fields as $field) { echo $field["phpType"]; } ?>');

        $this->mockNamedLookup->method('by')->willReturn($this->mockDbClient);
        $this->mockDbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['created_at' => 'DATETIME', 'birth_date' => 'DATE'],
            Client::METADATA_PRIMARY_KEY => [],
        ]);
        $this->mockFilesystem->method('exists')->willReturn(false);
        $code = $this->generator->generate('default', 'test', 'App\Entity', 'Test', '');

        $this->assertStringContainsString('string', $code);
    }

    public function testDbTypeToPhpTypeConvertsNumericTypes(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach($fields as $field) { echo $field["phpType"]; } ?>');

        $this->mockNamedLookup->method('by')->willReturn($this->mockDbClient);
        $this->mockDbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['price' => 'DECIMAL(10,2)', 'amount' => 'FLOAT'],
            Client::METADATA_PRIMARY_KEY => [],
        ]);
        $this->mockFilesystem->method('exists')->willReturn(false);
        $code = $this->generator->generate('default', 'test', 'App\Entity', 'Test', '');

        $this->assertStringContainsString('floatfloat', $code);
    }

    public function testDbTypeToPhpTypeHandlesUnknownTypes(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php foreach($fields as $field) { echo $field["phpType"]; } ?>');

        $this->mockNamedLookup->method('by')->willReturn($this->mockDbClient);
        $this->mockDbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['data' => 'UNKNOWN_TYPE'],
            Client::METADATA_PRIMARY_KEY => [],
        ]);
        $this->mockFilesystem->method('exists')->willReturn(false);
        $code = $this->generator->generate('default', 'test', 'App\Entity', 'Test', '');

        $this->assertStringContainsString('mixed', $code);
    }

    public function testGenerateExtendsAppEntityEntityWhenAppEntityBaseExistsAndNamespaceIsAppEntity(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'id' => 'INT',
                ],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturnCallback(static function (string $path): bool {
                return $path === '@app/Entity/Entity.php';
            });

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $baseClass; ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\Entity',
            'TestEntity',
            ''
        );

        $this->assertStringContainsString('Entity', $code);
        $this->assertStringNotContainsString('App\\Entity\\Entity', $code);
        $this->assertStringNotContainsString('Switon\\Orm\\Entity', $code);
    }

    public function testGenerateExtendsAppEntityEntityWhenAppEntityBaseExistsAndNamespaceIsNotAppEntity(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'id' => 'INT',
                ],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturnCallback(static function (string $path): bool {
                return $path === '@app/Entity/Entity.php';
            });

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $baseClass; ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\\Areas\\Rbac\\Entity',
            'TestEntity',
            ''
        );

        // Mock template only echoes $baseClass; non-App\Entity namespaces import App\Entity\Entity and extend short name "Entity".
        $tail = trim(substr($code, (int)strrpos($code, "\n") + 1));
        $this->assertSame('Entity', $tail);
        $this->assertStringNotContainsString('Switon\\Orm\\Entity', $code);
    }

    public function testGenerateExtendsSwitonOrmEntityWhenAppEntityBaseDoesNotExist(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'id' => 'INT',
                ],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $baseClass; ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\\Entity',
            'TestEntity',
            ''
        );

        $this->assertStringContainsString('Switon\\Orm\\Entity', $code);
    }

    public function testGenerateDoesNotIncludeMaxLengthWhenOnlyAuditColumnsHaveStringTypes(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'created_at' => 'VARCHAR(255)',
                    'updated_at' => 'VARCHAR(255)',
                ],
                Client::METADATA_PRIMARY_KEY => [],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo implode("|", $uses); ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\\Entity',
            'TestEntity',
            ''
        );

        $this->assertStringNotContainsString(
            'Switon\\Validating\\Attribute\\MaxLength',
            $code
        );
    }

    public function testGenerateIncludesMaxLengthWhenNonAuditColumnsHaveStringTypesWithLength(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('test_table')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'id' => 'INT',
                    'name' => 'VARCHAR(50)',
                ],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo implode("|", $uses); ?>');

        $code = $this->generator->generate(
            'default',
            'test_table',
            'App\\Entity',
            'TestEntity',
            ''
        );

        $this->assertStringContainsString(
            'Switon\\Validating\\Attribute\\MaxLength',
            $code
        );
    }

    public function testGenerateExtractsConstantsIncludingVisibilityAndTypedConst(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('users')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturnCallback(static function (string $path): bool {
                return $path === '@app/templates/generator/Entity.sword'
                    || $path === '/virtual/app/Entity/User.php';
            });
        $this->mockFilesystem->method('glob')
            ->willReturnCallback(static function (string $pattern): array {
                if ($pattern === '@app/Entity/User.php') {
                    return ['/virtual/app/Entity/User.php'];
                }
                return [];
            });
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $constants; ?>');

        $code = $this->generator->generate(
            'default',
            'users',
            'App\\Entity',
            'User',
            ''
        );

        $this->assertStringContainsString('public const STATUS_ACTIVE = 1;', $code);
        $this->assertStringContainsString("protected const string STATUS_DISABLED = 'disabled';", $code);
    }

    public function testGenerateExtractsConstantsFromRuntimeEntityFilesToo(): void
    {
        $filesystem = new class () implements FilesystemInterface {
            public function resolve(string $path): string
            {
                return $path;
            }

            public function exists(string $path): bool
            {
                return $path === '@app/templates/generator/Entity.sword'
                    || $path === '@runtime/orm/Entity/User.php';
            }

            public function size(string $file): ?int
            {
                return null;
            }

            public function delete(string $path): void
            {
            }

            public function read(string $file): string
            {
                if ($file === '@app/templates/generator/Entity.sword') {
                    return 'entity-template';
                }

                if ($file === '@runtime/orm/Entity/User.php') {
                    return <<<'PHP'
<?php
class User
{
    public const STATUS_ARCHIVED = 9;
}
PHP;
                }

                return '';
            }

            public function lines(string $file): array
            {
                return [];
            }

            public function write(string $file, string $data): void
            {
            }

            public function append(string $file, string $data): void
            {
            }

            public function appendJsonl(string $file, mixed $value): void
            {
            }

            public function writeJsonl(string $file, array $values): void
            {
            }

            public function readJsonl(string $file): array
            {
                return [];
            }

            public function readJson(string $file): mixed
            {
                return null;
            }

            public function writeJson(string $file, mixed $value, int $options = JSON_PRETTY_PRINT): void
            {
            }

            public function move(string $src, string $dst, bool $overwrite = false): void
            {
            }

            public function isDir(string $dir): bool
            {
                return false;
            }

            public function isFile(string $file): bool
            {
                return false;
            }

            public function realpath(string $path): ?string
            {
                return null;
            }

            public function isReadable(string $path): bool
            {
                return false;
            }

            public function isLink(string $path): bool
            {
                return false;
            }

            public function rmdir(string $dir): void
            {
            }

            public function mkdir(string $dir, int $mode = 0755): void
            {
            }

            public function copy(string $src, string $dst, bool $overwrite = false): void
            {
            }

            public function glob(string $pattern, int $flags = 0): array
            {
                return $pattern === '@runtime/orm/Entity/User.php' ? ['@runtime/orm/Entity/User.php'] : [];
            }

            public function files(string $dir): array
            {
                return [];
            }

            public function directories(string $dir): array
            {
                return [];
            }

            public function list(string $dir, int $sorting_order = SCANDIR_SORT_ASCENDING): array
            {
                return [];
            }

            public function mtime(string $file): ?int
            {
                return null;
            }

            public function chmod(string $file, int $mode): void
            {
            }

            public function touch(string $file, ?int $time = null): void
            {
            }
        };
        $generator = $this->make(EntityGenerator::class, [
            'filesystem' => $filesystem,
            'namedLookup' => $this->mockNamedLookup,
            'compiler' => $this->mockCompiler,
        ]);

        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('users')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $this->mockFilesystem->method('exists')
            ->willReturn(false);

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $constants; ?>');

        $code = $generator->generate('default', 'users', 'App\\Entity', 'User', '');

        $this->assertStringContainsString('public const STATUS_ARCHIVED = 9;', $code);
    }

    public function testGetTableSpecMapsTypesAndCamelNames(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('user_profiles')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'id' => 'BIGINT',
                    'user_name' => 'VARCHAR(50)',
                    'created_at' => 'VARCHAR(20)',
                    'score' => 'DECIMAL(10,2)',
                    'payload' => 'JSON',
                ],
                Client::METADATA_PRIMARY_KEY => ['id', 'user_name'],
            ]);

        $spec = $this->generator->getTableSpec('default', 'user_profiles', 'camel');

        $this->assertSame('UserProfiles', $spec['className']);
        $this->assertSame(['id', 'userName'], $spec['primaryKey']);
        $this->assertSame('int', $spec['properties'][0]['phpType']);
        $this->assertSame('userName', $spec['properties'][1]['name']);
        $this->assertSame(50, $spec['properties'][1]['maxLength']);
        $this->assertNull($spec['properties'][2]['maxLength']);
        $this->assertSame('float', $spec['properties'][3]['phpType']);
        $this->assertSame('mixed', $spec['properties'][4]['phpType']);
    }

    public function testGetTableSpecClearsAuditColumnLengths(): void
    {
        $this->mockNamedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($this->mockDbClient);

        $this->mockDbClient->method('getMetadata')
            ->with('audit_log')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => [
                    'created_at' => 'VARCHAR(255)',
                    'updated_at' => 'CHAR(32)',
                    'deleted_at' => 'VARCHAR(64)',
                ],
                Client::METADATA_PRIMARY_KEY => [],
            ]);

        $spec = $this->generator->getTableSpec('default', 'audit_log');

        $this->assertSame('AuditLog', $spec['className']);
        $this->assertNull($spec['properties'][0]['maxLength']);
        $this->assertNull($spec['properties'][1]['maxLength']);
        $this->assertNull($spec['properties'][2]['maxLength']);
    }

    public function testGenerateFallsBackWhenAppConfigLookupFails(): void
    {
        $this->mockNamedLookup->method('by')->willReturn($this->mockDbClient);
        $this->mockDbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
            Client::METADATA_PRIMARY_KEY => ['id'],
        ]);
        $this->mockFilesystem->method('exists')->willReturnCallback(static function (string $path): bool {
            if ($path === '@app/config/generator.php') {
                throw new RuntimeException('alias missing');
            }

            return false;
        });
        $this->mockCompiler->method('compileString')->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $code = $this->generator->generate('default', 'test_table', 'App\Entity', 'TestEntity', '');

        $this->assertStringContainsString('namespace App\Entity;', $code);
    }
}
