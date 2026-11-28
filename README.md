# Download Center (mundophpbb/downloadcenter)

A download center for phpBB 3.3: categories, items with several versions and changelog, local uploads or external links, user submissions with moderation, notifications, support topics, screenshots, statistics and secure delivery.

## Features

* Categories, items, versions (phpBB / PHP compatibility, changelog, size).
* Local upload, external link or an existing phpBB attachment.
* Secure download through a controller route, with duplicate-click protection and an optional rate limit.
* Public submissions by members, approval or rejection in the ACP, "My submissions" area, limited author editing.
* phpBB notifications for pending, approved and rejected items.
* Automatic support topic for each item (optional).
* Screenshots and item images, BBCode descriptions and changelogs.
* Search and filters, responsive cards, optional public statistics and RSS feed.
* ACP (Extensions tab): dashboard, settings, categories, items, pending, files library, data integrity, diagnostics and logs.
* Downloads by people and by bots shown separately in the ACP, while the totals keep counting everything.
* Access control either by the extension's global modes or by phpBB permissions (ACL).
* English and Brazilian Portuguese.

## Style colours

The public pages follow the colours of the active board style. They read the colours the style gives to the category bar, forum rows, panels, text, headings and links, and use them for the Download Center icons, buttons, pills, cards and tables. Child styles and appearance extensions such as prosilver SE Appearance are followed automatically. Colours that would not be readable keep the Download Center defaults. To keep the built-in palette in a style, set `data-dc-palette="off"` on its `<html>` element.

## Requirements

phpBB 3.3.0 or later, PHP 7.4 or later. The folder `files/mundophpbb/downloadcenter/` must be writable.

## Installation

1. Copy the extension to `ext/mundophpbb/downloadcenter/`.
2. ACP > Customise > Manage extensions > enable **Download Center**.
3. ACP > Extensions > Download Center > Settings: review allowed extensions, maximum upload size, public access and submissions.
4. Open *Diagnostics* to check the files folder, `.htaccess` and the PHP upload limits.

## Reviewing activity logs

Open **Extensions > Download Center > Logs** to filter by exact IPv4 or IPv6 address, user ID, action, item, version, message or date. Selecting an IP address or user ID in the list applies the corresponding filter. The address comes from the phpBB session.

New download entries name the item and version and identify catalog files, phpBB attachments and external redirects. A redirect entry records the handoff to the external site. The existing duplicate window applies to registered downloads.

## People and bots

Every download counts in the totals, as before. Since 1.3.13 each download is also marked as made by a **person** or by a **bot**, and the browser name (user agent) is stored with it. The dashboard shows both figures, the most downloaded items and versions show the split, and the logs can be filtered by *Downloaded by*.

A guest download is marked as bot when phpBB recognises a bot (ACP > General > Spiders/Robots), when the browser name contains a known search engine, AI crawler, link preview, monitor or script (curl, wget, Python and others), or when it is not a browser at all. Registered members always count as people. More identifiers can be added in *Settings > Extra bot identifiers*.

When upgrading, existing downloads are sorted as far as the stored data allows: downloads by phpBB bot accounts become bots and downloads by registered members become people. Older guest downloads stay *Not classified*, because their browser was never recorded.

## Changelog from the ZIP file

Leave the changelog field empty when adding a version with a ZIP file, and the notes of that version are read from the CHANGELOG.md (or CHANGELOG, CHANGES.md, HISTORY.md) inside the ZIP and converted to BBCode. Typical headings such as `## 1.3.14`, `## [1.3.14] - 2026-11-26` and `Version 1.3.14` are recognised. When the file has no section for that version, the changelog stays empty. A changelog typed in the form is always kept.

## Reusing uploaded files in the ACP

In the item version form, open **Previously uploaded file** and select a file by its original name. The list includes attachments already linked to Download Center versions and files from the legacy local folder. Use the search field to filter by name. Selecting a file automatically chooses the local download type. A new upload takes priority over the selected existing file.

Original names are preserved in phpBB attachment metadata and used for downloads. Physical storage names remain managed by phpBB. Reusing a file keeps the same attachment reference; removing one version file preserves other Download Center versions that use it.

## Duplicate upload prevention

When a file is uploaded again for an existing item, the extension checks that item’s attachments for the same original name, size and SHA-256 content. After phpBB validates the upload, an identical file reuses the existing attachment and the fresh temporary copy is removed. Applies to the ACP and authorised author version updates. Different names or contents remain separate. Other items, legacy files and existing duplicate copies are not merged. Simultaneous uploads can still produce duplicates; this check does not lock concurrent requests.

## Upgrading to 1.3.7

Disable the extension, replace its files, enable it again and purge the phpBB cache. Do not delete extension data. No existing uploads are renamed or moved.

## Upgrading from 1.0.x

Replace the files and purge the cache. The old migrations were merged; your data and settings are kept.

## Security note

Uploaded files are stored under `files/`. The `.htaccess` blocks direct access on Apache; on Nginx add a rule that denies `files/mundophpbb/downloadcenter/`, otherwise files can be fetched without the permission checks.

## Code layout

* `acp/main_module.php` - ACP entry point and dispatcher; each screen lives in `acp/section/` (dashboard, items, pending, files, logs, settings, diagnostics, integrity...).
* `controller/main_controller.php` - public pages; the code is in `controller/section/` (browse, author, uploads, screenshots, access, paging).
* `controller/download_controller.php` - download, screenshot and image delivery.
* `concern/` - helpers shared by the ACP and the controllers (access modes, upload rules, text tools).
* `service/notification_helper.php` and `notification/` - phpBB notifications.

## License

GPL-2.0-only. See `license.txt`.
