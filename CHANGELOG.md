# Changelog

## 1.3.4

* The permissions are now registered with the `core.permissions` event, so the ACP shows them in their own "Download Center" category with proper labels (they were created by the migration but not categorised).

## 1.3.3

* Database portability: the ACP item list no longer uses a hand-written `LIMIT` inside a sub-query (not portable across database engines). The value it selected was not used, so it was removed.

## 1.3.2

* Coding style: PHP, JavaScript, CSS and composer.json now use tabs for indentation, as required by the phpBB coding guidelines (they used spaces). Whitespace only: the code itself is unchanged.

## 1.3.1

* ACP item page: fixed data being silently dropped. The page had four separate save buttons (item, icon, version, screenshot) and each one saved only its own part, so for example choosing an icon and a download file together lost the file. There is now one **Save** button that stores everything on the page: item data, icon, a new or edited version with its file or link, the current changelog and a screenshot.
* A new item can now be created together with its first version and screenshot in a single step (previously the item had to be saved first).
* If a file is chosen but required data is missing (for example the version number), an explicit error is shown instead of ignoring the file.

## 1.3.0

* Catalog: lighter header, one compact statistics strip, search and sorting first with the extra filters (category, phpBB, PHP) in a collapsible block, category shortcuts shown only inside a category (the home page shows the category cards).
* Catalog cards: three key facts (version, downloads, updated), a quiet compatibility line and a single clear download button.
* Item page: product-style header with the facts next to the title instead of repeated three times, a preview strip with the first screenshots above the tabs.
* Template and CSS only; no database changes.

## 1.2.0

* Visual update: all colours now come from CSS design tokens, so the extension follows the board style. It detects dark styles automatically (from the real background colour of the style) and switches to a matching dark palette; light styles look the same as before.
* Accessibility: visible keyboard focus (`:focus-visible`), respects "reduce motion", 44 px touch targets on small screens, the screenshot viewer close button is now translated.
* Performance: images are lazy-loaded and decoded asynchronously, and never overflow their container.
* New language key `DOWNLOADCENTER_CLOSE` (EN and PT-BR).

## 1.1.0

* Release cleanup for the phpBB.com validation: passes the official EPV with no errors, warnings or notices.
* The 17 incremental migrations were merged into one installer (boards upgraded from 1.0.x are detected and keep their data).
* Added `license.txt`; removed internal release notes from the package; README rewritten.
* Report export in the ACP now uses a Symfony response; RSS and BBCode escaping use `utf8_htmlspecialchars`.
* SQL statements with identifiers or ids built by hand were rewritten with `sprintf` and integer casts.
* Random file name suffixes now use `random_bytes()` instead of `md5()`.
* Minimum PHP lowered to 7.4.
* Code organisation: `acp/main_module.php` (4,600 lines) and `controller/main_controller.php` (2,500 lines) were split by responsibility into small traits (`acp/section/`, `controller/section/`) and code that was duplicated between the ACP and the public controllers is now shared (`concern/`). Behaviour is unchanged: every method keeps exactly the same code.
* Security hardening:
  * The extension's allowed extensions and maximum size are now enforced before a file reaches phpBB's attachment manager (front end and ACP).
  * Removed the unreachable legacy upload code that wrote directly to `files/mundophpbb/downloadcenter/` (existing files are still served).
  * External links are decoded (`&amp;` in query strings broke redirects) and only plain http(s) URLs with a host and no credentials are followed.
  * Routes now declare their HTTP methods.
  * BBCode text is no longer escaped twice (apostrophes and ampersands were shown as entities).
  * Fixed a missing language key for invalid external URLs.

## 1.0.x

Development releases (see the project history).
