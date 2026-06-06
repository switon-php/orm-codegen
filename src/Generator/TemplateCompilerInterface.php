<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Compiles template source to PHP for ORM code generation.
 *
 * Guidance:
 * - the default implementation supports only the minimal subset needed by the bundled templates
 * - bind this interface to the full Sword compiler only when custom templates require richer directives
 *
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\EntityGenerator
 * @see \Switon\OrmCodegen\Generator\RepositoryGenerator
 * @see \Switon\OrmCodegen\Generator\TemplateCompiler
 */
interface TemplateCompilerInterface
{
    /**
     * Compile template string to executable PHP (eval with extract($vars)).
     */
    public function compileString(string $value): string;
}
