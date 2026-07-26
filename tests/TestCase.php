<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Switon\Core\App;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\InjectorInterface;
use Switon\Core\PathAlias;
use Switon\Core\PathAliasInterface;
use Switon\Core\ResourceAliasRegistrar;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Eventing\EventDispatcher as RealEventDispatcher;
use Switon\Eventing\EventDispatcherInterface as SwitonEventDispatcherInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\OrmCodegen\ServiceProvider as OrmCodegenServiceProvider;
use Switon\Testing\EventDispatcher as TestEventDispatcher;
use Switon\Testing\InMemoryContextManager;
use Switon\Testing\Mock\MockConsole;
use Switon\Testing\MockLogger;

use function sys_get_temp_dir;

/**
 * Base test case for orm-codegen tests.
 */
abstract class TestCase extends BaseTestCase
{
    protected DiContainer $container;
    protected InjectorInterface $injector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new DiContainer();
        $diProvider = new DiServiceProvider();
        $diProvider->register($this->container);
        $diProvider->boot();

        App::setContainer($this->container);

        $pathAlias = new PathAlias();
        $pathAlias->set('@runtime', sys_get_temp_dir());
        $this->container->set(PathAliasInterface::class, $pathAlias);
        $this->container->set(PathAlias::class, $pathAlias);
        $this->container->make(ResourceAliasRegistrar::class)
            ->register($pathAlias, OrmCodegenServiceProvider::class);
        $this->container->set(ContextManagerInterface::class, InMemoryContextManager::class);
        $this->container->set(LoggerInterface::class, new MockLogger());

        $mockConsole = new MockConsole();
        $this->container->set(ConsoleInterface::class, $mockConsole);
        $this->container->set(MockConsole::class, $mockConsole);

        $this->container->set(RealEventDispatcher::class, RealEventDispatcher::class);
        $this->container->set(SwitonEventDispatcherInterface::class, function (): TestEventDispatcher {
            $dispatcher = new TestEventDispatcher($this->container->get(RealEventDispatcher::class));
            $dispatcher->setDispatchEvents(false);

            return $dispatcher;
        });
        $this->container->set(PsrEventDispatcherInterface::class, SwitonEventDispatcherInterface::class);
        $this->container->set(PsrListenerProviderInterface::class, ListenerProviderInterface::class);

        $this->injector = $this->container->get(InjectorInterface::class);
        $this->setUpContainer();
        $this->injector->inject($this);
    }

    protected function setUpContainer(): void
    {
        $stubIdentity = $this->createStub(\Switon\Principal\IdentityInterface::class);
        $stubIdentity->method('isGuest')->willReturn(true);
        $stubIdentity->method('getId')->willReturn(0);
        $stubIdentity->method('getName')->willReturn('');
        $stubIdentity->method('getRoles')->willReturn([]);
        $this->container->set(\Switon\Principal\IdentityInterface::class, $stubIdentity);
    }

    protected function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $relationClass
     *
     * @return T
     */
    protected function createRelation(string $relationClass, array $parameters = []): object
    {
        return $this->make($relationClass, $parameters);
    }
}
