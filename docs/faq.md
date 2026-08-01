# ORM Codegen FAQ {#orm-codegen-faq}

Short answers for the most common generation questions.

> **Prerequisites:** [ORM Codegen Package](README.md)

## Getting Started {#getting-started}

### Which command should I start with? {#which-command}

Use `entity:make` to write files. Use `entity:list` or `entity:spec` only when you want a preview first.

### How do I generate all tables from one connection? {#all-tables}

Run `bash bin/console entity:make --connection=default`.

### How do I generate one table only? {#single-table}

Pass the table name as the first argument, for example `bash bin/console entity:make users --connection=default`.

### What does `--naming=camel` change? {#naming-camel}

It changes generated property naming and adds `#[NamingStrategy(NamingStrategy::CAMEL)]` to the generated entity. It does not rename the database table.

### Why can I not see `entity:spec` in the normal CLI list? {#hidden-inspection-actions}

`entity:spec` and `entity:list` are hidden. Use `bash bin/console list --all` when you want CLI discovery.

## Generation Behavior {#generation-behavior}

### How are class names chosen? {#class-name-resolution}

If a table is already linked, codegen reuses that namespace and class name. If not, it infers a name from the table.

### Why did files generate under `@runtime/orm` instead of `@app`? {#runtime-app}

`auto` switches to `@runtime/orm` once `@app/Entity` has any entity file other than the shared base `Entity.php`, or any subdirectory. That keeps fresh output out of an existing app tree.

### What does one successful generation run write? {#generation-output}

Usually one `Entity` file and one `Repository` file. If `@root/docs/entities.md` is available, codegen also keeps that table list in sync.

### Does `entity:make` overwrite target files? {#overwrite-target-files}

Yes. Repeated manual edits in generated files are not the stable workflow. Keep long-term customization in templates.

## Configuration {#configuration}

### How do I make codegen scan entities outside `@app/Entity`? {#scan-paths}

Configure `Switon\OrmCodegen\EntityScanner.paths` in `switon.yml`.

### How do I override generation templates? {#override-templates}

Add `@app/templates/generator/Entity.sword` and `@app/templates/generator/Repository.sword`, or set explicit template paths in `config/generator.php`. The built-in template files use the `.sword` extension.

### How do I inspect the current codegen option surface? {#inspect-options}

Use `bash bin/console config:options 'Switon\OrmCodegen\EntityScanner'` for scan paths and `bash bin/console config:options 'Switon\OrmCodegen\Generator\OutputDirectoryResolver'` for output mode.

## Troubleshooting {#troubleshooting}

### Why is codegen using the wrong database? {#wrong-connection}

Most often the command ran without the intended `--connection`, so it fell back to `default`.

### Why are generated classes not using my preferred namespace or class name? {#unexpected-name}

Most often an existing entity-to-table mapping already won. Check mappings first, then adjust scan paths if you
expect new inference.

### Why are generated classes not using my preferred custom form? {#custom-shape}

If the form needs to stay different, move it into templates instead of editing generated files after each run.
