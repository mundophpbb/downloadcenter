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
* Access control either by the extension's global modes or by phpBB permissions (ACL).
* English and Brazilian Portuguese.

## Requirements

phpBB 3.3.0 or later, PHP 7.4 or later. The folder `files/mundophpbb/downloadcenter/` must be writable.

## Installation

1. Copy the extension to `ext/mundophpbb/downloadcenter/`.
2. ACP > Customise > Manage extensions > enable **Download Center**.
3. ACP > Extensions > Download Center > Settings: review allowed extensions, maximum upload size, public access and submissions.
4. Open *Diagnostics* to check the files folder, `.htaccess` and the PHP upload limits.

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
