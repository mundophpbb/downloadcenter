# Release notes 1.0.64

## Migration consolidation

This beta package consolidates the migration tree to reduce maintenance noise during development.

- New clean installs use a consolidated `v_1_0_0` migration containing the current schema, configuration values, and ACP modules.
- A small `v_1_0_64` migration updates existing installations from the previous beta line to the new internal version.
- No user-facing functionality was added in this release.

For production-grade distribution, test both clean installation and update from the latest beta before release.
