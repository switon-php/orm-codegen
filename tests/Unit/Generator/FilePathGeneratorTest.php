<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\FilesystemInterface;
use Switon\OrmCodegen\Generator\FilePathGenerator;
use Switon\OrmCodegen\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FilePathGeneratorTest extends TestCase
{
    protected FilePathGenerator $generator;
    protected MockObject|FilesystemInterface $mockFilesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFilesystem = $this->createMock(FilesystemInterface::class);
        $this->container->set(FilesystemInterface::class, $this->mockFilesystem);

        $this->generator = new FilePathGenerator();
        $this->injector->inject($this->generator);
    }

    public function testGetEntityPathForAppNamespace(): void
    {
        $path = $this->generator->getEntityPath(
            '@app',
            'App\Entity',
            'User'
        );

        $this->assertSame('@app/Entity/User.php', $path);
    }

    public function testGetEntityPathForAreaNamespace(): void
    {
        $path = $this->generator->getEntityPath(
            '@app',
            'App\Areas\Rbac\Entity',
            'Role'
        );

        $this->assertSame('@app/Areas/Rbac/Entity/Role.php', $path);
    }

    public function testGetEntityPathForRuntimeOutput(): void
    {
        $path = $this->generator->getEntityPath(
            '@runtime/orm',
            'App\Entity',
            'User'
        );

        $this->assertSame('@runtime/orm/Entity/User.php', $path);
    }

    public function testGetEntityPathForAreaRuntimeOutput(): void
    {
        $path = $this->generator->getEntityPath(
            '@runtime/orm',
            'App\Areas\Rbac\Entity',
            'Role'
        );

        $this->assertSame('@runtime/orm/Entity/Rbac/Role.php', $path);
    }

    public function testGetRepositoryPathForAppNamespace(): void
    {
        $path = $this->generator->getRepositoryPath(
            '@app',
            'App\Entity',
            'User'
        );

        $this->assertSame('@app/Repository/UserRepository.php', $path);
    }

    public function testGetRepositoryPathForAreaNamespace(): void
    {
        $path = $this->generator->getRepositoryPath(
            '@app',
            'App\Areas\Rbac\Entity',
            'Role'
        );

        $this->assertSame('@app/Areas/Rbac/Repository/RoleRepository.php', $path);
    }

    public function testGetRepositoryPathForRuntimeOutput(): void
    {
        $path = $this->generator->getRepositoryPath(
            '@runtime/orm',
            'App\Entity',
            'User'
        );

        $this->assertSame('@runtime/orm/Repository/UserRepository.php', $path);
    }

    public function testGetRepositoryPathForAreaRuntimeOutput(): void
    {
        $path = $this->generator->getRepositoryPath(
            '@runtime/orm',
            'App\Areas\Rbac\Entity',
            'Role'
        );

        $this->assertSame('@runtime/orm/Repository/Rbac/RoleRepository.php', $path);
    }

    public function testGetRepositoryNamespaceForAppEntities(): void
    {
        $result = $this->generator->getRepositoryNamespace(
            'App\Entity',
            'User'
        );

        $this->assertSame('App\Repository', $result['namespace']);
        $this->assertSame('UserRepository', $result['className']);
    }

    public function testGetRepositoryNamespaceForAreaEntities(): void
    {
        $result = $this->generator->getRepositoryNamespace(
            'App\Areas\Rbac\Entity',
            'Role'
        );

        $this->assertSame('App\Areas\Rbac\Repository', $result['namespace']);
        $this->assertSame('RoleRepository', $result['className']);
    }

    public function testGetRepositoryNamespaceHandlesMiddleEntities(): void
    {
        $result = $this->generator->getRepositoryNamespace(
            'App\Entity\Admin',
            'User'
        );

        $this->assertSame('App\\Repository\\Admin', $result['namespace']);
        $this->assertSame('UserRepository', $result['className']);
    }

    public function testEnsureDirectoryCreatesDirectory(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('/path/to/directory')
            ->willReturn(false);

        $this->mockFilesystem->expects($this->once())
            ->method('mkdir')
            ->with('/path/to/directory', 0755);

        $this->generator->ensureDirectory('/path/to/directory/file.php');
    }

    public function testEnsureDirectorySkipsExistingDirectory(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('/path/to/directory')
            ->willReturn(true);

        $this->mockFilesystem->expects($this->never())
            ->method('mkdir');

        $this->generator->ensureDirectory('/path/to/directory/file.php');
    }
}
