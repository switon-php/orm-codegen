<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Generator;

use ParseError;
use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\Naming;
use Switon\Core\PathAliasInterface;
use Switon\Db\Client;
use Switon\Db\ClientInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Orm\Attribute\Connection;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\NamingStrategy;
use Switon\Orm\Attribute\Table;
use Switon\OrmCodegen\Exception\TemplateCompilationException;
use Throwable;

use function in_array;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function substr;

/**
 * Entity generator service for generating Entity classes from database tables.
 *
 * Guidance: keep entity generation metadata-driven so table structure, naming mode, and template choice stay centralized here.
 *
 * @see \Switon\OrmCodegen\Command\EntityCommand Typical consumer
 * @see \Switon\OrmCodegen\Generator\TemplateCompilerInterface
 * @see \Switon\OrmCodegen\Exception\TemplateCompilationException
 * @see \Switon\Orm\Attribute\Table Generated code marker
 */
class EntityGenerator implements EntityGeneratorInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;
    /** @var NamedLookupInterface<ClientInterface> */
    #[Autowired] protected NamedLookupInterface $namedLookup;
    #[Autowired] protected TemplateCompilerInterface $compiler;

    /**
     * Returns the machine-readable entity spec for one table without generating PHP source.
     * For entity:spec output; no code generation.
     *
     * @param string $connection Database connection name
     * @param string $table Table name
     * @param string $naming Naming strategy: 'camel' or '' for original
     *
     * @return array{className: string, primaryKey: array<string>, properties: list<array{name: string, phpType: string, maxLength: int|null}>}
     */
    public function getTableSpec(string $connection, string $table, string $naming = ''): array
    {
        $db = $this->namedLookup->by(ClientInterface::class, $connection);
        $metadata = $db->getMetadata($table);
        $tableFields = (array)$metadata[Client::METADATA_ATTRIBUTES];
        $primaryKeys = (array)$metadata[Client::METADATA_PRIMARY_KEY];
        $camelized = ($naming === 'camel');
        $auditColumns = ['created_at', 'updated_at', 'deleted_at'];

        $properties = [];
        foreach ($tableFields as $fieldName => $dbType) {
            $name = $camelized ? Naming::camel($fieldName) : $fieldName;
            $maxLength = $this->extractStringLength($dbType);
            $isAudit = in_array($fieldName, $auditColumns, true);
            $properties[] = [
                'name' => $name,
                'phpType' => $this->dbTypeToPhpType($dbType),
                'maxLength' => ($maxLength !== null && !$isAudit) ? $maxLength : null,
            ];
        }

        $pkNames = array_map(
            static fn (string $col) => $camelized ? Naming::camel($col) : $col,
            $primaryKeys
        );

        return [
            'className' => Naming::pascal($table),
            'primaryKey' => $pkNames,
            'properties' => $properties,
        ];
    }

    /**
     * Generates entity class code for one database table.
     *
     * @param string $connection Database connection name
     * @param string $table Table name
     * @param string $namespace Entity namespace
     * @param string $className Entity class name
     * @param string $naming Naming strategy: 'camel' or '' for original
     *
     * @return string Generated Entity class code
     */
    public function generate(
        string $connection,
        string $table,
        string $namespace,
        string $className,
        string $naming = ''
    ): string {
        $db = $this->namedLookup->by(ClientInterface::class, $connection);
        $metadata = $db->getMetadata($table);

        $tableFields = (array)$metadata[Client::METADATA_ATTRIBUTES];
        $primaryKeys = (array)$metadata[Client::METADATA_PRIMARY_KEY];

        // Determine base class: check if App\Entity\Entity exists
        $appEntityPath = '@app/Entity/Entity.php';
        $hasAppEntity = $this->filesystem->exists($appEntityPath);

        // Determine base class full name and short name for extends
        if ($hasAppEntity) {
            $baseClassFull = 'App\Entity\Entity';
            // If in same namespace, use short name; otherwise use full name (will be imported)
            $baseClassShort = ($namespace === 'App\Entity') ? 'Entity' : 'App\Entity\Entity';
        } else {
            $baseClassFull = 'Switon\Orm\Entity';
            $baseClassShort = 'Switon\Orm\Entity';
        }

        // Build uses array
        $uses = [];
        $attributes = [];

        // Add base class to uses if needed (only if not in same namespace)
        if ($hasAppEntity) {
            if ($namespace !== 'App\Entity') {
                // If using App\Entity\Entity but not in App\Entity namespace, import it
                $uses[] = 'App\Entity\Entity';
                // After import, use short name in extends
                $baseClassShort = 'Entity';
            }
        } else {
            $uses[] = 'Switon\Orm\Entity';
        }

        if ($connection !== 'default') {
            $uses[] = Connection::class;
            $attributes[] = "#[Connection('$connection')]";
        }

        if (!empty($primaryKeys)) {
            $uses[] = Id::class;
        }

        $uses[] = Table::class;

        // Add NamingStrategy attribute based on naming strategy
        // 'camel': Field names are camelCase (e.g., $userId), DB columns are also camelCase (e.g., userId)
        //          Need CamelNamingStrategy (no conversion, keep as-is)
        // '': Field names are snake_case (e.g., $user_id), DB columns are also snake_case (e.g., user_id)
        //     No annotation needed (no conversion, uses default behavior)
        $camelized = ($naming === 'camel');
        if ($naming === 'camel') {
            $uses[] = NamingStrategy::class;
            $attributes[] = '#[NamingStrategy(NamingStrategy::CAMEL)]';
        }

        // Get constants from existing file if exists
        $constants = $this->getConstantsFromFile($className);

        $auditColumns = ['created_at', 'updated_at', 'deleted_at'];

        // Prepare field data for template
        $fieldData = [];
        foreach ($tableFields as $fieldName => $dbType) {
            $fieldNameFinal = $camelized ? Naming::camel($fieldName) : $fieldName;
            $isPrimaryKey = in_array($fieldName, $primaryKeys, true);
            $maxLength = $this->extractStringLength($dbType);
            $isAudit = in_array($fieldName, $auditColumns, true);

            $fieldData[] = [
                'name' => $fieldNameFinal,
                'phpType' => $this->dbTypeToPhpType($dbType),
                'isPrimaryKey' => $isPrimaryKey,
                'maxLength' => ($maxLength !== null && !$isAudit) ? $maxLength : null,
            ];
        }

        $hasMaxLength = false;
        foreach ($fieldData as $f) {
            if ($f['maxLength'] !== null) {
                $hasMaxLength = true;
                break;
            }
        }
        if ($hasMaxLength) {
            $uses[] = 'Switon\Validating\Attribute\MaxLength';
        }

        // Prepare template variables
        $vars = [
            'namespace' => $namespace,
            'uses' => $uses,
            'attributes' => $attributes,
            'tableAttribute' => "#[Table('$table')]" . PHP_EOL,
            'className' => $className,
            'baseClass' => $baseClassShort, // Use short name for extends clause
            'constants' => $constants,
            'fields' => $fieldData,
        ];

        // Get template path
        $templatePath = $this->getTemplatePath('Entity');

        // Read template: alias path via filesystem abstraction, absolute path via native read.
        $template = str_starts_with($templatePath, '@')
            ? $this->filesystem->read($templatePath)
            : file_get_contents($templatePath);
        if (!is_string($template) || $template === '') {
            TemplateCompilationException::raise('Template file is empty: {templatePath}', [
                'templatePath' => $templatePath,
            ]);
        }

        // Compile template
        $compiled = $this->compiler->compileString($template);

        if ($fieldData === []) {
            TemplateCompilationException::raise('Invalid fields data for table: {table}', ['table' => $table]);
        }

        // Execute compiled template
        ob_start();
        extract($vars, EXTR_SKIP);
        try {
            eval('?>' . $compiled);
        } catch (ParseError $e) {
            // Core Exception::raise() accepts only ?Exception as previous; ParseError is Error, so do not pass it
            TemplateCompilationException::raise(
                'Template compilation error: {error}. Preview: {compiledPreview}',
                [
                    'error' => $e->getMessage(),
                    'templatePath' => $templatePath,
                    'compiledPreview' => substr($compiled, 0, 800),
                ]
            );
        }

        // Get generated code and prepend PHP opening tag
        $generated = ob_get_clean();
        $code = "<?php\n\ndeclare(strict_types=1);\n\n" . $generated;

        // Format code: ensure { is on new line after extends
        return preg_replace('/\bextends\s+(\S+)\s*\{/', "extends $1\n{", $code);
    }

    /**
     * Resolves the entity template path using config, app override, then package fallback priority.
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

    /**
     * Get constants from existing Entity file.
     *
     * @param string $entityName Entity class name
     *
     * @return string Constants code (indented)
     */
    protected function getConstantsFromFile(string $entityName): string
    {
        $possiblePaths = [
            "@app/Entity/$entityName.php",
            "@app/Areas/*/Entity/$entityName.php",
            "@runtime/orm/Entity/$entityName.php",
            "@runtime/orm/Entity/*/$entityName.php",
            "@runtime/orm/Entity/*/*/$entityName.php",
        ];

        foreach ($possiblePaths as $pattern) {
            $files = $this->filesystem->glob($pattern);
            if (empty($files)) {
                continue;
            }

            $file = $files[0];
            if (!$this->filesystem->exists($file)) {
                continue;
            }

            $content = $this->filesystem->read($file);
            $constants = '';
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                if (preg_match('#^\s*(?:public|protected|private)?\s*const(?:\s+[\w\\\\|&?]+)?\s+[A-Z\d_]+\s*=#', $line) === 1) {
                    $constants .= $line . "\n";
                } elseif (trim($line) === '' && $constants !== '') {
                    $constants .= "\n";
                }
            }

            if ($constants) {
                // Ensure proper indentation (4 spaces)
                $indented = '';
                foreach (explode("\n", trim($constants)) as $line) {
                    if (trim($line) === '') {
                        $indented .= "\n";
                    } else {
                        // Re-indent to 4 spaces
                        $indented .= '    ' . trim($line) . "\n";
                    }
                }
                return trim($indented);
            }
        }

        return '';
    }

    /**
     * Extract string length from DB type (e.g. varchar(255) -> 255).
     *
     * @param string $type Database column type
     *
     * @return int|null Length or null if not applicable
     */
    protected function extractStringLength(string $type): ?int
    {
        if (preg_match('#\b(?:var)?char\s*\(\s*(\d+)\s*\)#i', $type, $m) === 1) {
            $n = (int)$m[1];
            return $n > 0 ? $n : null;
        }
        return null;
    }

    /**
     * Convert database type to PHP type.
     *
     * @param string $type Database column type
     *
     * @return string PHP type (int, float, string, mixed)
     */
    protected function dbTypeToPhpType(string $type): string
    {
        if (preg_match('#^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)#i', $type)) {
            return 'int';
        } elseif (preg_match('#^(CHAR|VARCHAR|BINARY|VARBINARY|BLOB|TEXT|ENUM|SET)#i', $type)) {
            return 'string';
        } elseif (preg_match('#^(TINYBLOB|BLOB|MEDIUMBLOB|LONGBLOB)#i', $type)) {
            return 'string';
        } elseif (preg_match('#^(TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT)#i', $type)) {
            return 'string';
        } elseif (preg_match('#^(DECIMAL|NUMERIC|DOUBLE|FLOAT)#i', $type)) {
            return 'float';
        } elseif (preg_match('#^(DATE|TIME|DATETIME|TIMESTAMP)#i', $type)) {
            return 'string';
        } else {
            return 'mixed';
        }
    }
}
