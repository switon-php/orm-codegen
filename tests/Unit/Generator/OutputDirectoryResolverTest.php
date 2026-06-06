<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\FilesystemInterface;
use Switon\OrmCodegen\Generator\OutputDirectoryResolver;
use Switon\OrmCodegen\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OutputDirectoryResolverTest extends TestCase
{
    protected OutputDirectoryResolver $resolver;
    protected MockObject|FilesystemInterface $mockFilesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFilesystem = $this->createMock(FilesystemInterface::class);
        $this->container->set(FilesystemInterface::class, $this->mockFilesystem);
    }

    public function testResolveReturnsRuntimeWhenOutputModeIsRuntime(): void
    {
        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'runtime',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@runtime/orm', $result);
    }

    public function testResolveReturnsAppWhenOutputModeIsApp(): void
    {
        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'app',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@app', $result);
    }

    public function testResolveReturnsAppWhenEntitiesDirectoryDoesNotExist(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(false);

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'auto',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@app', $result);
    }

    public function testResolveReturnsAppWhenEntitiesDirectoryIsEmpty(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(true);

        $this->mockFilesystem->method('glob')
            ->willReturn([]);

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'auto',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@app', $result);
    }

    public function testResolveReturnsRuntimeWhenEntitiesDirectoryHasFiles(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(true);

        $this->mockFilesystem->method('glob')
            ->willReturn(['/path/to/app/Entity/User.php']);

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'auto',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@runtime/orm', $result);
    }

    public function testResolveReturnsAppWhenEntitiesDirectoryHasOnlyBaseEntityFile(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(true);

        $this->mockFilesystem->method('glob')
            ->willReturnCallback(function (string $pattern): array {
                if (str_ends_with($pattern, '/*.php')) {
                    return ['@app/Entity/Entity.php'];
                }

                return [];
            });

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'auto',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@app', $result);
    }

    public function testResolveReturnsRuntimeWhenEntitiesDirectoryHasSubdirectories(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(true);

        $this->mockFilesystem->method('glob')
            ->willReturnCallback(function ($pattern) {
                if (str_ends_with($pattern, '/*.php')) {
                    return [];
                }
                return ['/path/to/app/Entity/Subdir'];
            });

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'auto',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@runtime/orm', $result);
    }

    public function testResolveReturnsAppWhenEntitiesDirectoryHasOnlyEmptySubdirectories(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(true);

        $this->mockFilesystem->method('glob')
            ->willReturn([]);

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'auto',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@app', $result);
    }

    public function testResolveFallsBackToAutoModeWhenOutputModeIsUnknown(): void
    {
        $this->mockFilesystem->method('exists')
            ->with('@app/Entity')
            ->willReturn(true);

        $this->mockFilesystem->method('glob')
            ->willReturn(['/path/to/app/Entity/User.php']);

        $resolver = $this->make(OutputDirectoryResolver::class, [
            'mode' => 'unknown-mode',
        ]);

        $result = $resolver->resolve();

        $this->assertSame('@runtime/orm', $result);
    }
}
