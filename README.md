# Switon ORM Codegen Package

[![Codegen CI](https://img.shields.io/github/actions/workflow/status/switon-php/orm-codegen/ci.yml?branch=main&label=Codegen%20CI)](https://github.com/switon-php/orm-codegen/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's ORM scaffolding tool for database scans, entity and repository generation, and template overrides.

## Highlights

- **Entity scanning:** existing entities can be grouped by connection and table.
- **Code generation:** entity and repository pairs can be generated from live database metadata.
- **Template control:** apps can replace the default templates.
- **Output routing:** generated files can be placed in the right app directories.
- **Class resolution:** generated class names are resolved through the package helpers.

## Installation

```bash
composer require --dev switon/orm-codegen
```

## Quick Start

```bash
bash bin/console entity:make --connection=default
```

Docs: https://docs.switon.dev/latest/orm-codegen

## License

MIT.
