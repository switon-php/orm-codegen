<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Db\Client;
use Switon\Db\ClientInterface;
use Switon\Di\NamedLookupInterface;
use Switon\OrmCodegen\Exception\TemplateCompilationException;
use Switon\OrmCodegen\Generator\EntityGenerator;
use Switon\OrmCodegen\Generator\TemplateCompilerInterface;
use ReflectionClass;

class EntityGeneratorFailureTest extends TestCase
{
    public function testGenerateRaisesTemplateCompilationExceptionOnParseError(): void
    {
        $generator = new EntityGenerator();

        $filesystem = $this->createMock(FilesystemInterface::class);
        $pathAlias = $this->createMock(PathAliasInterface::class);
        $namedLookup = $this->createMock(NamedLookupInterface::class);
        $compiler = $this->createMock(TemplateCompilerInterface::class);
        $dbClient = $this->createMock(ClientInterface::class);

        $this->inject($generator, 'filesystem', $filesystem);
        $this->inject($generator, 'pathAlias', $pathAlias);
        $this->inject($generator, 'namedLookup', $namedLookup);
        $this->inject($generator, 'compiler', $compiler);

        $namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('getMetadata')
            ->with('users')
            ->willReturn([
                Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
                Client::METADATA_PRIMARY_KEY => ['id'],
            ]);

        $pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@app/config/generator.php')
            ->willReturn('/tmp/no-generator-config.php');

        $filesystem->method('exists')
            ->willReturnCallback(static function (string $path): bool {
                return $path === '@app/templates/generator/Entity.sword';
            });

        $filesystem->method('glob')->willReturn([]);
        $filesystem->expects($this->once())
            ->method('read')
            ->with('@app/templates/generator/Entity.sword')
            ->willReturn('dummy-template');

        // Intentionally invalid compiled PHP to trigger ParseError inside generate()
        $compiler->expects($this->once())
            ->method('compileString')
            ->with('dummy-template')
            ->willReturn('<?php if (');

        $initialBufferLevel = ob_get_level();

        try {
            $generator->generate('default', 'users', 'App\\Entity', 'User', '');
            $this->fail('Expected TemplateCompilationException was not thrown.');
        } catch (TemplateCompilationException $exception) {
            $this->assertStringContainsString('Template compilation error', $exception->getMessage());
        } finally {
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_clean();
            }
        }
    }

    protected function inject(object $target, string $property, mixed $value): void
    {
        $r = new ReflectionClass($target);
        $p = $r->getProperty($property);
        $p->setValue($target, $value);
    }
}
