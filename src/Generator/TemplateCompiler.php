<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

/**
 * Minimal Sword-style compiler for ORM codegen templates only.
 *
 * Supports {{ }}, {!! !!}, @if/@elseif/@else/@endif, @foreach/@endforeach. Uses the same
 * balanced-parens directive pattern as {@see \Switon\Rendering\Engine\Sword\Compiler}
 * so expressions like @if (!empty($x)) compile correctly.
 *
 * @see \Switon\OrmCodegen\Generator\TemplateCompilerInterface
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\EntityGenerator
 * @see \Switon\OrmCodegen\Generator\RepositoryGenerator
 */
class TemplateCompiler implements TemplateCompilerInterface
{
    /** One level of nested parens for @directive(expr), e.g. @if (!empty($x)). Same idea as Sword. */
    protected const string DIR_EXPR = '\( ([^()]*(?:\([^()]*\)[^()]*)*) \)';

    /**
     * Compiles the minimal supported template syntax into executable PHP for eval-based rendering.
     */
    public function compileString(string $value): string
    {
        // Raw echo {!! ... !!}
        $value = preg_replace_callback(
            '/\{!!\s*(.+?)\s*!!\}/s',
            static fn ($m) => '<?= ' . trim($m[1]) . ' ?>',
            $value
        );

        // Escaped echo {{ ... }} (for codegen no need for e())
        $value = preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            static fn ($m) => '<?= ' . trim($m[1]) . ' ?>',
            $value
        );

        // @endif
        $value = preg_replace('/\s*@endif\s*/', ' <?php endif; ?> ', $value);

        // @endforeach
        $value = preg_replace('/\s*@endforeach\s*/', ' <?php endforeach; ?> ', $value);

        // @elseif (expr)
        $value = preg_replace_callback(
            '/\s*@elseif\s*' . self::DIR_EXPR . '\s*/x',
            static fn ($m) => ' <?php elseif (' . trim($m[1]) . '): ?> ',
            $value
        );

        // @else
        $value = preg_replace('/\s*@else\b\s*/', ' <?php else: ?> ', $value);

        // @if (expr) — nested parens so @if (!empty($uses)) works
        $value = preg_replace_callback(
            '/\s*@if\s*' . self::DIR_EXPR . '\s*/x',
            static fn ($m) => ' <?php if (' . trim($m[1]) . '): ?> ',
            $value
        );

        // @foreach ($x as $y)
        return preg_replace_callback(
            '/\s*@foreach\s*' . self::DIR_EXPR . '\s*/x',
            static fn ($m) => ' <?php foreach (' . trim($m[1]) . '): ?> ',
            $value
        );
    }
}
