<?php

declare(strict_types=1);

namespace Switon\OrmCodegen;

/**
 * Scans Entity classes and returns connection-table-to-class mappings.
 *
 * Guidance: use this before generation so codegen can reuse existing entity classes instead of inferring new ones blindly.
 *
 * @see \Switon\OrmCodegen\EntityScanner
 */
interface EntityScannerInterface
{
    /**
     * @return array<string, array<string, string>> ['connection' => ['table' => 'EntityFQCN']]
     */
    public function scan(): array;
}
