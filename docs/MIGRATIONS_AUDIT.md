# Migrations audit

The extension keeps historical migrations from `v_1_0_0` through `v_1_0_99` so existing boards can update safely from older packages.

## Consolidation policy

- Do not delete historical migration files in a published package.
- New installations may still execute the full migration chain; this is safer than losing upgrade paths.
- All version updates must use the canonical config key: `mundophpbb_downloadcenter_version`.
- New settings should be added through `config.add` in the migration that introduced them.
- Structural changes should remain idempotent where possible.

## Current final migration

- `mundophpbb\downloadcenter\migrations\v_1_0_99`

## New settings introduced in 1.0.99

- `mundophpbb_downloadcenter_show_public_stats`
- `mundophpbb_downloadcenter_feed_enabled`
- `mundophpbb_downloadcenter_rate_limit_count`
- `mundophpbb_downloadcenter_rate_limit_window`
