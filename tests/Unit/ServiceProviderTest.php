<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\ContainerInterface;
use Switon\Core\PathAliasInterface;
use Switon\OrmCodegen\ServiceProvider;
use Switon\Testing\Container;
use Switon\Testing\PackagePathAssert;

final class ServiceProviderTest extends TestCase
{
    public function testRegisterIsNoop(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('set');

        $provider->register($container);

        $this->addToAssertionCount(1);
    }

    public function testContainerRegistersOrmCodegenResourceAliasFromProviderAttribute(): void
    {
        $container = new Container();
        $pathAlias = $container->get(PathAliasInterface::class);
        $resourceRoot = $pathAlias->get('@switon.orm-codegen.resources');
        $this->assertIsString($resourceRoot);
        PackagePathAssert::assertSameAsPackagePath(ServiceProvider::class, $resourceRoot, 'resources');
    }
}
