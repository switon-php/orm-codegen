<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\OrmCodegen\Exception\TemplateCompilationException;
use Throwable;

use function preg_replace;

/**
 * Repository generator service for generating Repository classes.
 *
 * Guidance: keep repository generation template-driven so app overrides can replace the emitted shape without changing the CLI flow.
 *
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\OutputDirectoryResolver
 * @see \Switon\OrmCodegen\Generator\FilePathGenerator
 * @see \Switon\OrmCodegen\Generator\TemplateCompilerInterface
 */
class RepositoryGenerator implements RepositoryGeneratorInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected TemplateCompilerInterface $compiler;

    /**
     * Generates repository class code from the resolved entity and repository names.
     *
     * @param string $namespace Repository namespace
     * @param string $repositoryClass Repository class name
     * @param string $entityNamespace Entity namespace
     * @param string $entityClass Entity class name
     *
     * @return string Generated Repository class code
     */
    public function generate(
        string $namespace,
        string $repositoryClass,
        string $entityNamespace,
        string $entityClass
    ): string {
        // Prepare template variables
        $vars = [
            'namespace' => $namespace,
            'repositoryClass' => $repositoryClass,
            'entityNamespace' => $entityNamespace,
            'entityClass' => $entityClass,
        ];

        // Get template path
        $templatePath = $this->getTemplatePath('Repository');

        // Read template
        $template = $this->filesystem->read($templatePath);
        if ($template === '') {
            TemplateCompilationException::raise(
                'Template file is empty: {templatePath}',
                ['templatePath' => $templatePath]
            );
        }

        // Compile template
        $compiled = $this->compiler->compileString($template);

        // Execute compiled template
        ob_start();
        extract($vars, EXTR_SKIP);
        eval('?>' . $compiled);

        // Get generated code and prepend PHP opening tag
        $generated = ob_get_clean();
        $code = "<?php\n\ndeclare(strict_types=1);\n\n" . $generated;

        // Format code: ensure { is on new line after extends
        return preg_replace('/\bextends\s+(\S+)\s*\{/', "extends $1\n{", $code);
    }

    /**
     * Resolves the repository template path using config, app override, then package fallback priority.
     *
     * Lookup priority:
     * 1. Config specified path (config/generator.php)
     * 2. @app/templates/generator/{templateName}.sword
     * 3. @switon.orm-codegen.resources/templates/{templateName}.sword
     *
     * @param string $templateName Template name (Entity or Repository)
     *
     * @return string Template file path
     */
    protected function getTemplatePath(string $templateName): string
    {
        // Load config if exists
        try {
            $configPath = $this->pathAlias->resolve('@app/config/generator.php');
        } catch (Throwable $e) {
            $configPath = $this->pathAlias->resolve('@root') . '/config/generator.php';
        }

        if ($this->filesystem->exists($configPath)) {
            $config = require $configPath;
            if (isset($config['generator']['templatePath'][$templateName])) {
                $configuredPath = $config['generator']['templatePath'][$templateName];
                if ($configuredPath && $this->filesystem->exists($configuredPath)) {
                    return $configuredPath;
                }
            }
        }

        // Check app template directory
        $appTemplatePath = "@app/templates/generator/$templateName.sword";
        if ($this->filesystem->exists($appTemplatePath)) {
            return $appTemplatePath;
        }

        return '@switon.orm-codegen.resources/templates/' . $templateName . '.sword';
    }
}
