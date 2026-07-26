<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\OrmCodegen\Generator\RepositoryGenerator;
use Switon\OrmCodegen\Generator\TemplateCompilerInterface;
use Switon\OrmCodegen\Tests\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class RepositoryGeneratorTest extends TestCase
{
    protected RepositoryGenerator $generator;
    protected MockObject|FilesystemInterface $mockFilesystem;
    protected MockObject|PathAliasInterface $mockPathAlias;
    protected MockObject|TemplateCompilerInterface $mockCompiler;
    protected array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFilesystem = $this->createMock(FilesystemInterface::class);
        $this->mockPathAlias = $this->createMock(PathAliasInterface::class);
        $this->mockCompiler = $this->createMock(TemplateCompilerInterface::class);

        $this->container->set(FilesystemInterface::class, $this->mockFilesystem);
        $this->container->set(PathAliasInterface::class, $this->mockPathAlias);
        $this->container->set(TemplateCompilerInterface::class, $this->mockCompiler);

        $this->generator = new RepositoryGenerator();
        $this->injector->inject($this->generator);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testGenerateCreatesRepositoryClass(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $this->mockFilesystem->method('read')
            ->willReturn('template content');

        $code = $this->generator->generate(
            'App\Repository',
            'UserRepository',
            'App\Entity',
            'User'
        );

        $this->assertIsString($code);
        $this->assertStringContainsString('<?php', $code);
        $this->assertStringContainsString('declare(strict_types=1);', $code);
        $this->assertStringContainsString('namespace App\Repository;', $code);
    }

    public function testGenerateWithAreaNamespace(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $this->mockFilesystem->method('read')
            ->willReturn('template content');

        $code = $this->generator->generate(
            'App\Areas\Rbac\Repository',
            'RoleRepository',
            'App\Areas\Rbac\Entity',
            'Role'
        );

        $this->assertStringContainsString('namespace App\Areas\Rbac\Repository;', $code);
    }

    public function testGenerateIncludesEntityClass(): void
    {
        // Use a simpler template that doesn't cause syntax errors
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $entityNamespace; echo "\\\\"; echo $entityClass; ?>');

        $this->mockFilesystem->method('read')
            ->willReturn('template content');

        $code = $this->generator->generate(
            'App\Repository',
            'UserRepository',
            'App\Entity',
            'User'
        );

        $this->assertStringContainsString('App\Entity', $code);
        $this->assertStringContainsString('User', $code);
    }

    public function testGenerateIncludesRepositoryClassName(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo $repositoryClass; ?>');

        $this->mockFilesystem->method('read')
            ->willReturn('template content');

        $code = $this->generator->generate(
            'App\Repository',
            'ProductRepository',
            'App\Entity',
            'Product'
        );

        $this->assertStringContainsString('ProductRepository', $code);
    }

    public function testGenerateFormatsCodeCorrectly(): void
    {
        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "class " . $repositoryClass . " extends AbstractRepository{"; ?>');

        $this->mockFilesystem->method('read')
            ->willReturn('template content');

        $code = $this->generator->generate(
            'App\Repository',
            'UserRepository',
            'App\Entity',
            'User'
        );

        $this->assertStringContainsString('extends AbstractRepository', $code);
        $this->assertStringContainsString('{', $code);
    }

    public function testGeneratePrefersConfiguredTemplatePathWhenConfigExists(): void
    {
        $configPath = sys_get_temp_dir() . '/orm-generator-config-' . uniqid('', true) . '.php';
        $customTemplatePath = sys_get_temp_dir() . '/orm-generator-template-' . uniqid('', true) . '.sword';
        $this->tempFiles[] = $configPath;

        file_put_contents(
            $configPath,
            "<?php return ['generator' => ['templatePath' => ['Repository' => '$customTemplatePath']]];"
        );

        $this->mockPathAlias->method('resolve')
            ->with('@app/config/generator.php')
            ->willReturn($configPath);

        $this->mockFilesystem->method('exists')
            ->willReturnCallback(static function (string $path) use ($customTemplatePath): bool {
                return $path === '@app/config/generator.php' || $path === $customTemplatePath;
            });

        $this->mockFilesystem->expects($this->once())
            ->method('read')
            ->with($customTemplatePath)
            ->willReturn('template content');

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $code = $this->generator->generate('App\\Repository', 'UserRepository', 'App\\Entity', 'User');

        $this->assertStringContainsString('namespace App\\Repository;', $code);
    }

    public function testGenerateUsesAppTemplateWhenNoConfiguredTemplate(): void
    {
        $this->mockFilesystem->method('exists')
            ->willReturnCallback(static function (string $path): bool {
                return $path === '@app/templates/generator/Repository.sword';
            });

        $this->mockFilesystem->expects($this->once())
            ->method('read')
            ->with('@app/templates/generator/Repository.sword')
            ->willReturn('template content');

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $code = $this->generator->generate('App\\Repository', 'UserRepository', 'App\\Entity', 'User');

        $this->assertStringContainsString('namespace App\\Repository;', $code);
    }

    public function testGenerateFallsBackToResourceAliasTemplatePath(): void
    {
        $vendorTemplate = '@switon.orm-codegen.resources/templates/Repository.sword';
        $this->mockFilesystem->method('exists')
            ->willReturnCallback(static function (string $path) use ($vendorTemplate): bool {
                return $path === $vendorTemplate;
            });

        $this->mockFilesystem->expects($this->once())
            ->method('read')
            ->with($vendorTemplate)
            ->willReturn('template content');

        $this->mockCompiler->method('compileString')
            ->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $code = $this->generator->generate('App\\Repository', 'UserRepository', 'App\\Entity', 'User');

        $this->assertStringContainsString('namespace App\\Repository;', $code);
    }

    public function testGenerateFallsBackWhenAppConfigLookupFails(): void
    {
        $this->mockFilesystem->method('exists')->willReturnCallback(static function (string $path): bool {
            if ($path === '@app/config/generator.php') {
                throw new RuntimeException('alias missing');
            }

            return false;
        });
        $this->mockFilesystem->method('read')->willReturn('template content');
        $this->mockCompiler->method('compileString')->willReturn('<?php echo "namespace {$namespace};"; ?>');

        $code = $this->generator->generate('App\\Repository', 'UserRepository', 'App\\Entity', 'User');

        $this->assertStringContainsString('namespace App\\Repository;', $code);
    }
}
