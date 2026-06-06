<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Exception;

use Switon\OrmCodegen\Exception as BaseException;

/**
 * Exception for entity template compilation failures.
 *
 * Thrown when template rendering fails during entity or repository code generation.
 *
 * @see \Switon\Orm\Exception
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\EntityGenerator
 * @see \Switon\OrmCodegen\Generator\RepositoryGenerator
 * @see \Switon\OrmCodegen\Generator\TemplateCompilerInterface
 */
class TemplateCompilationException extends BaseException
{
}
