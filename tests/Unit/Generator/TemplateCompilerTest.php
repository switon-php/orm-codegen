<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Switon\OrmCodegen\Generator\TemplateCompiler;
use Switon\OrmCodegen\Generator\TemplateCompilerInterface;

class TemplateCompilerTest extends TestCase
{
    public function testCompileStringSupportsEchoSyntaxAndProducesExecutablePhp(): void
    {
        $compiler = new TemplateCompiler();
        $template = "Hello {{ strtoupper(\$name) }} / {!! \$raw !!}";

        $compiled = $compiler->compileString($template);

        $this->assertStringContainsString('<?= strtoupper($name) ?>', $compiled);
        $this->assertStringContainsString('<?= $raw ?>', $compiled);

        $name = 'mark';
        $raw = '<b>ok</b>';
        $output = $this->renderCompiled($compiled, ['name' => $name, 'raw' => $raw]);

        $this->assertSame('Hello MARK / <b>ok</b>', $output);
    }

    public function testCompileStringSupportsIfAndForeachWithNestedParentheses(): void
    {
        $compiler = new TemplateCompiler();
        $template = <<<'TPL'
@if (!empty($items))
@foreach (($items) as $item)
[{{ $item }}]
@endforeach
@endif
TPL;

        $compiled = $compiler->compileString($template);

        $this->assertStringContainsString('<?php if (!empty($items)): ?>', $compiled);
        $this->assertStringContainsString('<?php foreach (($items) as $item): ?>', $compiled);
        $this->assertStringContainsString('<?php endforeach; ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);

        $output = $this->renderCompiled($compiled, ['items' => ['a', 'b']]);
        $this->assertSame('[a][b]', str_replace(["\n", "\r", ' '], '', $output));

        $emptyOutput = $this->renderCompiled($compiled, ['items' => []]);
        $this->assertSame('', trim($emptyOutput));
    }

    public function testCompileStringSupportsElseIfAndElseBranches(): void
    {
        $compiler = new TemplateCompiler();
        $template = <<<'TPL'
@if (($score) >= 90)
A
@elseif (($score) >= 60)
B
@else
C
@endif
TPL;

        $compiled = $compiler->compileString($template);

        $this->assertStringContainsString('<?php if (($score) >= 90): ?>', $compiled);
        $this->assertStringContainsString('<?php elseif (($score) >= 60): ?>', $compiled);
        $this->assertStringContainsString('<?php else: ?>', $compiled);

        $this->assertSame('A', trim($this->renderCompiled($compiled, ['score' => 95])));
        $this->assertSame('B', trim($this->renderCompiled($compiled, ['score' => 80])));
        $this->assertSame('C', trim($this->renderCompiled($compiled, ['score' => 30])));
    }

    public function testTemplateCompilerImplementsContractInterface(): void
    {
        $this->assertInstanceOf(TemplateCompilerInterface::class, new TemplateCompiler());
    }

    protected function renderCompiled(string $compiled, array $vars): string
    {
        extract($vars, EXTR_SKIP);

        ob_start();
        eval('?>' . $compiled);

        return (string)ob_get_clean();
    }
}
