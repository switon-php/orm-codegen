# ORM Codegen Package {#orm-codegen-package}

`switon/orm-codegen` generates `Entity` and `Repository` classes from database tables.

**Key features:**

- **One command writes both classes** — `entity:make` generates the `Entity` and matching `Repository`.
- **Preview first when needed** — `entity:spec` and `entity:list` are preview helpers.
- **Adjust generated names** — `--naming=camel` switches property names and adds `#[NamingStrategy(NamingStrategy::CAMEL)]`.
- **Choose the output mode** — `auto`, `app`, and `runtime` control where files are written.

---

## Installation {#installation}

**Requirements:** PHP 8.3+, `switon/orm`, `switon/db`, `switon/command`, `switon/console`, `switon/di`, `switon/core`

```bash
composer require --dev switon/orm-codegen
```

---

## Quick Start {#quick-start}

Use `entity:make` from your app root.

> **Note:** On Windows, run the equivalent command via `php switon.php ...`.

```bash
bash bin/console entity:make users --connection=default
```

Omit the table name to generate all tables in the selected connection:

```bash
bash bin/console entity:make --connection=default
```

Add `--naming=camel` when you want camel-case property names:

```bash
bash bin/console entity:make users --connection=default --naming=camel
```

That also adds `#[NamingStrategy(NamingStrategy::CAMEL)]` to the generated entity.

---

## Core Concepts {#core-concepts}

### Generation Flow {#generation-flow}

- `entity:make` writes files.
- Existing mappings are reused when they already exist.

### Preview Commands {#preview-commands}

- `entity:list` is hidden; use `bash bin/console list --all` to discover it.
- `entity:spec` shows the next class name, primary key, and properties.

### Output Mode {#output-mode}

`Switon\OrmCodegen\Generator\OutputDirectoryResolver` controls where generated files land.

- `auto` writes to `@app` when `@app/Entity` has no business entity files, otherwise to `@runtime/orm`
- `app` always writes to `@app`
- `runtime` always writes to `@runtime/orm`
- in `auto`, only the shared base `@app/Entity/Entity.php` counts as empty; subdirectories or other entity files switch output to `@runtime/orm`

---

## Configuration {#configuration}

Most projects only need scan paths.

### Entity Scan Paths {#entity-scan-paths}

If your app keeps entities outside the default locations, configure `Switon\OrmCodegen\EntityScanner.paths` in `switon.yml`:

```yaml
Switon\OrmCodegen\EntityScanner:
  paths:
    - '@app/Entity/*.php'
    - '@app/Areas/*/Entity/*.php'
    - '@app/Domain/*/Entity/*.php'
```

Only add paths that actually contain ORM entity classes.

---

## Keep in Mind {#keep-in-mind}

- `entity:make` regenerates `Entity` and `Repository` files.
- Class constants are carried forward from an existing entity file.
- Existing mappings win over new inference.
- `entity:spec` and `entity:list` are hidden; use `bash bin/console list --all` when you want discovery.

---

## Next {#next}

- **To read common questions:** [FAQ](faq.md#orm-codegen-faq)
- **To see why the package prefers inspect-first generation:** [Design Rationale](design.md#design-rationale)
- **To work with the runtime ORM contract:** [ORM Package](../../orm/docs/README.md)
