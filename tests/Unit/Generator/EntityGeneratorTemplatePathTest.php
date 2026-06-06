<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Db\Client;
use Switon\Db\ClientInterface;
use Switon\Di\NamedLookupInterface;
use Switon\OrmCodegen\Generator\EntityGenerator;
use Switon\OrmCodegen\Generator\TemplateCompilerInterface;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
class EntityGeneratorTemplatePathTest extends TestCase
{
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testGeneratePrefersConfiguredNonAliasTemplatePath(): void
    {
        $generator = new EntityGenerator();
        [$filesystem, $pathAlias, $namedLookup, $compiler, $dbClient] = $this->prepareDependencies($generator);

        $configPath = sys_get_temp_dir() . '/orm-entity-generator-config-' . uniqid('', true) . '.php';
        $customTemplatePath = sys_get_temp_dir() . '/orm-entity-generator-template-' . uniqid('', true) . '.sword';
        $this->tempFiles[] = $configPath;
        $this->tempFiles[] = $customTemplatePath;

        file_put_contents(
            $configPath,
            "<?php return ['generator' => ['templatePath' => ['Entity' => '$customTemplatePath']]];"
        );
        file_put_contents($customTemplatePath, 'template-content');

        $pathAlias->method('resolve')
            ->with('@app/config/generator.php')
            ->willReturn($configPath);

        $filesystem->method('exists')
            ->willReturnCallback(static function (string $path) use ($configPath, $customTemplatePath): bool {
                return $path === $configPath || $path === $customTemplatePath;
            });
        $filesystem->method('glob')->willReturn([]);
        $filesystem->expects($this->never())->method('read');

        $compiler->expects($this->once())
            ->method('compileString')
            ->with('template-content')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $dbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
            Client::METADATA_PRIMARY_KEY => ['id'],
        ]);

        $code = $generator->generate('default', 'users', 'App\\Entity', 'User', '');
        $this->assertStringContainsString('namespace App\\Entity;', $code);
    }

    public function testGenerateUsesAppAliasTemplateWhenConfiguredTemplateMissing(): void
    {
        $generator = new EntityGenerator();
        [$filesystem, $pathAlias, $namedLookup, $compiler, $dbClient] = $this->prepareDependencies($generator);

        $pathAlias->method('resolve')
            ->with('@app/config/generator.php')
            ->willReturn('/tmp/non-existing-generator-config.php');

        $filesystem->method('exists')
            ->willReturnCallback(static function (string $path): bool {
                return $path === '@app/templates/generator/Entity.sword';
            });
        $filesystem->method('glob')->willReturn([]);
        $filesystem->expects($this->once())
            ->method('read')
            ->with('@app/templates/generator/Entity.sword')
            ->willReturn('entity-template');

        $compiler->expects($this->once())
            ->method('compileString')
            ->with('entity-template')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $dbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
            Client::METADATA_PRIMARY_KEY => ['id'],
        ]);

        $code = $generator->generate('default', 'users', 'App\\Entity', 'User', '');
        $this->assertStringContainsString('namespace App\\Entity;', $code);
    }

    public function testGenerateFallsBackToResourceAliasTemplatePath(): void
    {
        $generator = new EntityGenerator();
        [$filesystem, $pathAlias, $namedLookup, $compiler, $dbClient] = $this->prepareDependencies($generator);

        $pathAlias->method('resolve')
            ->with('@app/config/generator.php')
            ->willReturn('/tmp/non-existing-generator-config.php');

        $vendorTemplate = '@switon.orm-codegen.resources/templates/Entity.sword';
        $filesystem->method('exists')
            ->willReturnCallback(static function (string $path) use ($vendorTemplate): bool {
                return $path === $vendorTemplate;
            });
        $filesystem->method('glob')->willReturn([]);
        $filesystem->expects($this->once())
            ->method('read')
            ->with($vendorTemplate)
            ->willReturn('vendor-entity-template');

        $compiler->expects($this->once())
            ->method('compileString')
            ->with('vendor-entity-template')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $dbClient->method('getMetadata')->willReturn([
            Client::METADATA_ATTRIBUTES => ['id' => 'INT'],
            Client::METADATA_PRIMARY_KEY => ['id'],
        ]);

        $code = $generator->generate('default', 'users', 'App\\Entity', 'User', '');
        $this->assertStringContainsString('namespace App\\Entity;', $code);
    }

    /**
     * @return array{FilesystemInterface, PathAliasInterface, NamedLookupInterface, TemplateCompilerInterface, ClientInterface}
     */
    protected function prepareDependencies(EntityGenerator $generator): array
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $pathAlias = $this->createMock(PathAliasInterface::class);
        $namedLookup = $this->createMock(NamedLookupInterface::class);
        $compiler = $this->createMock(TemplateCompilerInterface::class);
        $dbClient = $this->createMock(ClientInterface::class);

        $this->inject($generator, 'filesystem', $filesystem);
        $this->inject($generator, 'pathAlias', $pathAlias);
        $this->inject($generator, 'namedLookup', $namedLookup);
        $this->inject($generator, 'compiler', $compiler);

        $namedLookup->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        return [$filesystem, $pathAlias, $namedLookup, $compiler, $dbClient];
    }

    protected function inject(object $target, string $property, mixed $value): void
    {
        $r = new ReflectionClass($target);
        $p = $r->getProperty($property);
        $p->setValue($target, $value);
    }
}
