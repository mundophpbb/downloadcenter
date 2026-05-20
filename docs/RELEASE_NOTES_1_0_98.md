# Release notes - 1.0.98

## Public listing performance

- The public catalog now loads current/fallback versions for the visible page in bulk instead of querying each item individually.
- The effective public per-page value is capped at 50 to avoid accidentally heavy catalog pages.
- The public result summary now shows the visible result range for the current page.

## Compatibility

- No database schema changes.
- Existing items, versions, permissions and files are preserved.
