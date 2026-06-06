<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\ClassScannerInterface;
use Switon\Orm\Entity;
use Switon\OrmCodegen\EntityScanner;
use Switon\OrmCodegen\Tests\TestCase;
use stdClass;

#[AllowMockObjectsWithoutExpectations]
class EntityScannerTest extends TestCase
{
    protected EntityScanner $scanner;
    protected MockObject|ClassScannerInterface $mockClassScanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClassScanner = $this->createMock(ClassScannerInterface::class);

        $this->container->replace(ClassScannerInterface::class, $this->mockClassScanner);

        $this->scanner = new EntityScanner();
        $this->injector->inject($this->scanner);
    }

    /**
     * Define a test Entity class in the App namespace so EntityScanner can reflect it.
     *
     * The scanner receives class names from ClassScanner, so unit tests can define
     * in-memory classes and return them from the class scanner mock.
     */
    protected function defineAppEntity(string $className, string $table, ?string $connection = null): void
    {
        if (class_exists($className)) {
            return;
        }

        $pos = strrpos($className, '\\');
        $namespace = substr($className, 0, $pos);
        $shortName = substr($className, $pos + 1);

        $connectionAttribute = $connection !== null
            ? "#[\\Switon\\Orm\\Attribute\\Connection('{$connection}')]\n"
            : '';

        eval(
            "namespace {$namespace};\n" .
            "{$connectionAttribute}" .
            "#[\\Switon\\Orm\\Attribute\\Table('{$table}')]\n" .
            "class {$shortName} extends \\Switon\\Orm\\Entity {}\n"
        );
    }

    public function testScanReturnsEmptyArrayWhenNoEntitiesFound(): void
    {
        $this->mockClassScanner->expects($this->once())
            ->method('scan')
            ->with([
                '@app/Entity/*.php' => 'App\\Entity\\*',
                '@app/Areas/*/Entity/*.php' => 'App\\Areas\\*\\Entity\\*',
            ], null, Entity::class)
            ->willReturn([]);

        $result = $this->scanner->scan();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testScanHandlesDirectPath(): void
    {
        $this->defineAppEntity('App\\Entity\\User', 'users');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\User']);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('default', $result);
        $this->assertSame(['users' => 'App\\Entity\\User'], $result['default']);
    }

    public function testScanHandlesGlobPattern(): void
    {
        $this->defineAppEntity('App\\Areas\\Rbac\\Entity\\Role', 'roles', 'rbac');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Areas\\Rbac\\Entity\\Role']);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('rbac', $result);
        $this->assertSame(['roles' => 'App\\Areas\\Rbac\\Entity\\Role'], $result['rbac']);
    }

    public function testScanHandlesMultiplePaths(): void
    {
        $this->defineAppEntity('App\\Entity\\User', 'users');
        $this->defineAppEntity('App\\Areas\\Rbac\\Entity\\Role', 'roles', 'rbac');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\User', 'App\\Areas\\Rbac\\Entity\\Role']);

        $result = $this->scanner->scan();

        $this->assertSame(
            [
                'default' => ['users' => 'App\\Entity\\User'],
                'rbac' => ['roles' => 'App\\Areas\\Rbac\\Entity\\Role'],
            ],
            $result
        );
    }

    public function testScanHandlesNonExistentDirectory(): void
    {
        $this->mockClassScanner->method('scan')
            ->willReturn([]);

        $result = $this->scanner->scan();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testScanReturnsEmptyArrayWhenScannerReturnsNoCandidates(): void
    {
        $this->mockClassScanner->method('scan')
            ->willReturn([]);

        $result = $this->scanner->scan();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testScanSkipsNonEntityClasses(): void
    {
        $this->mockClassScanner->method('scan')
            ->willReturn([stdClass::class]);

        $result = $this->scanner->scan();

        $this->assertSame([], $result);
    }

    public function testScanInfersTableNameWhenTableAttributeMissing(): void
    {
        $className = 'App\\Entity\\NoTableEntity';
        if (!class_exists($className)) {
            eval('namespace App\\Entity; class NoTableEntity extends \\Switon\\Orm\\Entity {}');
        }
        $this->mockClassScanner->method('scan')
            ->willReturn([$className]);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('default', $result);
        $this->assertArrayHasKey('no_table_entity', $result['default']);
        $this->assertSame($className, $result['default']['no_table_entity']);
    }

    public function testScanUsesDefaultConnectionWhenNoConnectionAttribute(): void
    {
        $this->defineAppEntity('App\\Entity\\User', 'users');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\User']);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('default', $result);
        $this->assertSame(['users' => 'App\\Entity\\User'], $result['default']);
    }

    public function testScanUsesConnectionFromAttribute(): void
    {
        $this->defineAppEntity('App\\Areas\\Rbac\\Entity\\Role', 'roles', 'rbac');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Areas\\Rbac\\Entity\\Role']);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('rbac', $result);
        $this->assertSame(['roles' => 'App\\Areas\\Rbac\\Entity\\Role'], $result['rbac']);
    }

    public function testScanBuildsCorrectMappingStructure(): void
    {
        $this->defineAppEntity('App\\Entity\\User', 'users');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\User']);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('default', $result);
        $this->assertSame(
            ['users' => 'App\\Entity\\User'],
            $result['default'],
        );
    }

    public function testScanHandlesMultipleEntitiesInSameConnection(): void
    {
        $this->defineAppEntity('App\\Entity\\User', 'users');
        $this->defineAppEntity('App\\Entity\\Role', 'roles');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\User', 'App\\Entity\\Role']);

        $result = $this->scanner->scan();

        $this->assertArrayHasKey('default', $result);
        $this->assertSame(
            [
                'users' => 'App\\Entity\\User',
                'roles' => 'App\\Entity\\Role',
            ],
            $result['default'],
        );
    }

    public function testScanHandlesMultipleConnections(): void
    {
        $this->defineAppEntity('App\\Entity\\User', 'users');
        $this->defineAppEntity('App\\Areas\\Rbac\\Entity\\Role', 'roles', 'rbac');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\User', 'App\\Areas\\Rbac\\Entity\\Role']);

        $result = $this->scanner->scan();

        $this->assertSame(
            [
                'default' => ['users' => 'App\\Entity\\User'],
                'rbac' => ['roles' => 'App\\Areas\\Rbac\\Entity\\Role'],
            ],
            $result
        );
    }

    public function testScanUsesLastEntityWhenTableNameConflictsInSameConnection(): void
    {
        $this->defineAppEntity('App\\Entity\\AuditLogA', 'audit_logs');
        $this->defineAppEntity('App\\Entity\\AuditLogB', 'audit_logs');
        $this->mockClassScanner->method('scan')
            ->willReturn(['App\\Entity\\AuditLogA', 'App\\Entity\\AuditLogB']);

        $result = $this->scanner->scan();

        $this->assertSame('App\\Entity\\AuditLogB', $result['default']['audit_logs']);
    }

}
