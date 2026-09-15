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

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
