<?php

declare(strict_types=1);

namespace Switon\OrmCodegen;

use Switon\Core\Attribute\ResourceAlias;
use Switon\Core\ContainerInterface;
use Switon\Core\ServiceProviderInterface;

/**
 * Registers ORM codegen package startup wiring.
 *
 * Guidance:
 * - keep this provider as the resource-alias anchor for bundled generator templates
 * - rely on default container auto-mapping unless the package truly needs explicit bindings
 *
 * @see \Switon\Core\ServiceProviderInterface
 */
#[ResourceAlias]
class ServiceProvider implements ServiceProviderInterface
{
    /** Keeps registration empty because orm-codegen services resolve by container convention. */
    public function register(ContainerInterface $container): void
    {
    }

    /** No-op hook kept for service provider lifecycle parity. */
    public function boot(): void
    {
    }
}
