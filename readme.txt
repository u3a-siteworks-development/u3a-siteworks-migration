=== u3a-siteworks-migration ===
Requires at least: 5.9
Tested up to: 6.5
Stable tag: 5.9
Requires PHP: 7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site Builder import

== Description ==

Imports Site Builder XML files into a WordPress site which has the SiteWorks core plugin installed

== Changelog ==
= 1.2.10 =
* Scans zip file on upload to check <fname> content for misplaced tags (Site Builder export bug)
= 1.2.9 =
* Fix issue reporting missing group files in the "Missing files" document.
= 1.2.8 =
* Fix error scanning for files in migration folder if filename starts with '-'
= 1.2.7 =
* Clarify display of admin form
= 1.2.6 =
* Fix "Headers already sent" message at end of migration
= 1.2.5 =
* Bug 948 - Remove regex that tried to handle { ... } conversion within <p> tags.
* Add JavaScript to disable the Migrate button once form has been submitted
= 1.2.4 =
* Bug 807 - Removed extra spaces that were being added around page and document links.
= 1.2.3 =
* Adds facility to upload a Site Builder zip file instead of having to manually extract to correct folder
