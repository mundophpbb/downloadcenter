# Release Notes - 1.0.52

## Support topic synchronization

- The support topic is now synchronized when an item is saved in the ACP.
- If the item has no linked topic and a support forum is configured, the extension creates one automatically.
- If the item already has a linked topic, the first post is updated with the current item description and latest version information.
- If the linked topic no longer exists, the extension clears the stale link and creates a new topic when possible.

## Version history cleanup

- Version history is kept focused on technical release data: version number, compatibility, download target, size, downloads, date, and changelog.
- The complete description remains attached to the main item, not to individual versions.
