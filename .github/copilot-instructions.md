# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`apcupsd`, version 1.3.1) targeting Cacti 1.2.24+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions
- **Platform**: Cacti Plugin Architecture (UPS monitoring via `apcupsd` daemon and/or SNMP)
- **Database**: MySQL/MariaDB

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `cacti_snmp_*`)
- `apcaccess` CLI tool (via `apcupsd` daemon) for non-SNMP UPS polling
- `templates/` graph/device template XML

## Project Structure

```
apcupsd/                # Repository root (install to plugins/apcupsd/ in Cacti)
├── locales/               # Internationalization files
├── templates/               # Graph/device template XML
├── database.php                # DB helper routines
├── poller_apcupsd.php            # Background poller entry point (CLI)
├── upses.php                       # UPS device administration UI
├── INFO                              # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                          # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Plugin lifecycle/hook-registration functions** MUST be prefixed `plugin_apcupsd_`: `plugin_apcupsd_install()`, `plugin_apcupsd_upgrade()`, `plugin_apcupsd_version()`.
- **All other functions** MUST be prefixed `apcupsd_`: `apcupsd_check_upgrade()`, `apcupsd_setup_table()`, `apcupsd_poller_bottom()`.
- Match the existing prefix used by the function you are editing; do not introduce a third naming scheme.

### Database Tables
Tables use **unprefixed, descriptive names** (not `plugin_apcupsd_`): `apcupsd_ups`, `apcupsd_ups_stats`. Preserve this naming.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Use prepared statements for anything involving variable input:

```php
// CORRECT
db_execute_prepared("UPDATE plugin_config SET version = ?, name = ?, author = ?, webpage = ? WHERE directory = ?",
	array($info['version'], $info['longname'], $info['author'], $info['homepage'], $info['name']));

// WRONG - never do this with request-derived values
db_fetch_row("SELECT * FROM apcupsd_ups WHERE id = $id");
```

### Shell/Collector Security
Anything shelling out to `apcaccess` must pass hostnames/ports through `cacti_escapeshellarg()`; never build the command line via raw concatenation of device-supplied values.

### SNMP Data Handling
Suppress and defensively check SNMP calls for SNMP-based UPS collection, since not all UPS models expose the same MIB branches.

### Input Validation
Use `get_filter_request_var()` / `get_nfilter_request_var()` for request input; never read `$_GET`/`$_POST` directly.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

## Database Operations

### Upgrade Handling
Version-gate schema changes in `apcupsd_check_upgrade()` (`setup.php`), guarded with `db_column_exists()`:

```php
function apcupsd_check_upgrade() {
	$info    = plugin_apcupsd_version();
	$current = $info['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='apcupsd'");

	if ($current != $old) {
		if (api_plugin_is_enabled('apcupsd')) {
			api_plugin_enable_hooks('apcupsd');
		}

		if (!db_column_exists('apcupsd_ups_stats', 'ups_master')) {
			db_execute('ALTER TABLE apcupsd_ups_stats ADD COLUMN ups_master varchar(128) NOT NULL default "" AFTER ups_name');
		}
	}
}
```

## Internationalization

ALL user-facing strings MUST use `__()` with the `'apcupsd'` text domain, e.g. `__('Manage UPS\'s', 'apcupsd')`.

## Plugin Architecture

### Plugin Hooks
Register all plugin hooks in `plugin_apcupsd_install()` (`setup.php`):

```php
api_plugin_register_hook('apcupsd', 'config_arrays',        'apcupsd_config_arrays',        'setup.php');
api_plugin_register_hook('apcupsd', 'config_settings',      'apcupsd_config_settings',      'setup.php');
api_plugin_register_hook('apcupsd', 'poller_bottom',        'apcupsd_poller_bottom',        'setup.php');
api_plugin_register_hook('apcupsd', 'draw_navigation_text', 'apcupsd_draw_navigation_text', 'setup.php');
api_plugin_register_hook('apcupsd', 'replicate_out',        'apcupsd_replicate_out',        'setup.php');

api_plugin_register_realm('apcupsd', 'upses.php', __('Manage UPS\'s', 'apcupsd'), 1);
```

### Remote Poller Replication
`apcupsd_replicate_out()` supports syncing UPS definitions to remote pollers; keep new replicated fields consistent with this hook rather than introducing a separate sync mechanism.

## Best Practices

1. Guard all schema changes with `db_column_exists()`/`db_table_exists()`.
2. Escape all shell arguments passed to `apcaccess`.
3. Treat SNMP-based collection defensively (suppress + `is_array()` checks).
4. Wrap all user-facing strings with `__('text', 'apcupsd')`.

## Common Pitfalls to Avoid

```php
// WRONG - unconditional ALTER TABLE on every load
db_execute('ALTER TABLE apcupsd_ups_stats ADD COLUMN ups_master varchar(128)...');

// CORRECT - guarded
if (!db_column_exists('apcupsd_ups_stats', 'ups_master')) {
	db_execute('ALTER TABLE apcupsd_ups_stats ADD COLUMN ups_master varchar(128) NOT NULL default "" AFTER ups_name');
}
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allow_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **Plugin table-creation API.** Use `api_plugin_db_table_create()`/`api_plugin_db_add_column()`
  (from Cacti core's `lib/plugins.php`) instead of raw `CREATE TABLE`/`ALTER TABLE ... ADD COLUMN`.
  Both are idempotent (safe no-ops when already applied), so the same call can run unconditionally
  from both the install AND upgrade paths.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.
