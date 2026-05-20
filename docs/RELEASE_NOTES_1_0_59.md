# Release notes - 1.0.59

## Data integrity tools

Adds a new ACP panel for data integrity diagnostics. The panel detects common inconsistencies without deleting or changing records automatically.

Checks include:

- published items without active versions;
- versions without version numbers;
- versions without file/link targets;
- local versions with missing physical files;
- items with invalid categories;
- broken support topic links;
- orphan screenshots;
- screenshots with missing files;
- orphan download records;
- physical files not linked to any version.

Use this release before wider beta testing to identify records that need manual review.
