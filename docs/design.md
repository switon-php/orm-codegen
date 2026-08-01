# Design Rationale {#design-rationale}

`switon/orm-codegen` keeps code generation file-based, and gives you one preview pass before you write files.

## See also {#see-also}

- [ORM Codegen Package](README.md) — commands, paths, and generation workflow
- [FAQ](faq.md#orm-codegen-faq) — short answers for common questions

---

## Inspect Before Writing {#inspect-before-writing}

The package separates current mappings from next output because generated ORM files drift easily when a project already has
its own entity-to-table mappings.

- `entity:list` shows current entity-to-table mappings
- `entity:spec` shows the next generated class name, primary key, and properties
- `entity:make` writes files only after that preview pass

That keeps the first pass easy to review and keeps scripts or direct CLI use from guessing too early.

## Reuse Mapping First {#reuse-mapping-first}

Existing entity mappings are the strongest signal.

If a table already has an entity class, codegen reuses that namespace and class name instead of inventing a new one.
That makes regeneration safer when apps already own part of the model layout.

The trade-off is that scan paths matter. If entities live outside the default locations, the package needs to see those
paths too.

## Keep Output Placement Local {#keep-output-local}

The default `auto` output mode is a compromise between first-run convenience and long-term safety.

- fresh apps can write into `@app`
- existing entity trees can move generated files into `@runtime/orm`
- explicit `app` and `runtime` modes remove the guesswork when a project wants a fixed target

The design goal is predictable placement, not a universal guess for every project.

## Keep Customization File-Based {#customization-file-based}

Template overrides stay simple on purpose.

- explicit template paths win first
- `@app/templates/generator/Entity.sword` and `@app/templates/generator/Repository.sword` are the common app-level
  override points
- built-in templates stay the fallback

That keeps long-term changes close to the app instead of turning generation into a large extension API.
