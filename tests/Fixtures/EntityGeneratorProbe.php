<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Fixtures;

use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Di\NamedLookupInterface;
use Switon\OrmCodegen\Generator\EntityGenerator;
use Switon\OrmCodegen\Generator\TemplateCompilerInterface;

class EntityGeneratorProbe extends EntityGenerator
{
    public function assignDependenciesForTest(
        FilesystemInterface $filesystem,
        PathAliasInterface $pathAlias,
        NamedLookupInterface $namedLookup,
        TemplateCompilerInterface $compiler,
    ): void {
        $this->filesystem = $filesystem;
        $this->pathAlias = $pathAlias;
        $this->namedLookup = $namedLookup;
        $this->compiler = $compiler;
    }
}
