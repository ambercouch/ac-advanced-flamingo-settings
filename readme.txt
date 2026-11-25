=== AC Advanced Flamingo Settings ===

Contributors: amberCouch
Tags: contact form 7, flamingo, contact form database, import, export
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.4

License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Flamingo import/export, message management, upload retention and optional form integrations.

== Description ==

Enhance Flamingo with additional customization options!

This plugin extends Flamingo, the Contact Form 7 database storage plugin, by adding useful tools for better form submission management:

* **Import/Export Feature**

  * Export Flamingo messages to a JSON file for backup or migration.
  * Import messages back into Flamingo while preserving all metadata, including the "Channel" column.
  * Imports are processed in optimized batches for improved reliability on large datasets.
  * Duplicate messages are automatically detected and skipped using content hashing.
  * Clear and reliable admin notices summarise import results.

* **Persistent File Uploads**

  * Optionally persist Contact Form 7 uploads to permanent URLs.
  * Stored upload URLs can be viewed and accessed from Flamingo messages.
  * Persistent uploads are **disabled by default** to avoid unexpected storage growth.

* **Uploaded Files Management**

  * Dedicated admin screen to view all persisted uploaded files.
  * Image uploads display thumbnail previews.
  * Non-image uploads display file-type icons.
  * Download files directly using a dedicated Download button.
  * Delete individual files or bulk delete all stored uploads.

* **Integrations Management**

  * Dedicated Integrations screen for managing optional add-ons.
  * View activated, installed, and available integrations from one place.
  * See available integration support for Divi, WPBakery, Enfold, and Beaver Builder.
  * Activate installed add-ons where available.
  * Access integration-specific settings when supported by an active add-on.

* **Enhanced Inbound Messages**

  * Display selected submission fields directly in the Flamingo Inbound Messages list.
  * Choose which form fields appear in the table.

* **Better Contact Log Management**

  * Add a "Latest Message" link to each contact in the address book.
  * Reorder the Flamingo menu, setting Inbound Messages as the default view.
  * Rename the Flamingo menu for better organisation.
  * Optionally disable the Address Book entirely.

* **Improved Usability**

  * A **Settings** link is added to the WordPress **Plugins** page for quick access.

This plugin is ideal for sites that rely heavily on Flamingo and need better organisation, safer backups, optional long-term file retention, and easier management of compatible add-ons.

== Requirements ==

* WordPress 6.7 or later and PHP 7.4 or later.
* Flamingo 2.6.3 or later, installed and active.
* Divi capture requires the separate Divi integration; it is never installed or activated automatically.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ac-advanced-flamingo-settings/` directory, or install the plugin via the WordPress plugin repository.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → AC Flamingo** to configure your preferences.
4. For message import/export, go to **Flamingo → Message Sync**.
5. To manage stored uploads, go to **Flamingo → Uploaded Files**.
6. To view optional add-ons, go to the **Integrations** screen in the Flamingo/ACAFS admin area.

== Frequently Asked Questions ==

= Does this plugin work without Flamingo? =
No. AC Advanced Flamingo Settings is an extension for Flamingo and requires it to function.

= Is persistent file upload enabled by default? =
No. Persistent uploads are **disabled by default** and must be explicitly enabled in the settings.

= Can I preview uploaded files? =
Yes. Image uploads show thumbnail previews on the Uploaded Files page. Non-image files show a file-type icon.

= Can I download uploaded files directly? =
Yes. Each uploaded file has a dedicated Download button. Existing file name links also continue to work.

= Can I delete uploaded files after import? =
Yes. You can delete individual files or remove all stored uploads from the Uploaded Files admin page.

= What does the Integrations screen do? =
The Integrations screen helps you view and manage optional add-ons that extend Flamingo support to other form builders and themes.

= Which integrations are shown? =
The Integrations screen can show available support for Divi, WPBakery, Enfold, and Beaver Builder.

= Can I choose which form fields appear in the Inbound Messages table? =
Yes. You can select exactly which fields are displayed via the settings panel.

= Can I disable the Address Book? =
Yes. The Address Book can be disabled entirely from the settings.

= How does the import/export feature work? =

* **Export**: Click "Export Messages" in **Flamingo → Message Sync** to download a JSON backup.
* **Import**: Upload a previously exported JSON file to restore messages.
* Existing messages are automatically skipped, and a summary is shown on completion.

= Is there a reusable import/export API for add-ons? =

Yes. After ACAFS has loaded, integrations can retrieve the transport-neutral services with `acafs_plugin()->export_service()` and `acafs_plugin()->import_service()`. The export service provides `count()` and `export_to_file()`, while the import service provides `read_file()` and `import_batch()`. These APIs return structured results or `WP_Error` values and do not depend on an admin HTTP request.

== Screenshots ==

1. **Settings Page** – Configure Flamingo behaviour and plugin options.
2. **Enhanced Inbound Messages** – Display submission details directly in the message list.
3. **Import/Export Page** – Backup and restore Flamingo messages.
4. **Uploaded Files Page** – View, preview, download, and manage persisted file uploads.
5. **Integrations Page** – View and manage optional Flamingo integrations.

== Divi capture migration ==

If you enabled Divi Contact Form capture in Settings 1.4.3, that saved preference
is retained. Capture now requires the separate AC Divi Contact Form for Flamingo
plugin. Settings does not install or activate integrations automatically.

When capture is enabled but the integration is missing, inactive, lacks its
capture handler or dependencies, administrators see a persistent recovery notice.
Follow its instructions to restore the standalone integration, Flamingo and Divi,
then submit a test form and verify exactly one complete Flamingo Inbound Message.
Sites that never enabled capture, or explicitly disabled it, see no warning.

The notice clears when the integration is ready. Reported message-storage or
channel-assignment failures remain visible until a subsequent successful capture
is observed. For partial saves, repair the existing record rather than creating a
duplicate. The notice does not guarantee email delivery or diagnose every possible
third-party failure. It does not modify existing messages or channel hierarchies.

Saving another Settings form does not clear the Divi preference when its field is
absent. Use the explicit Divi capture checkbox to disable capture intentionally.

Upgrade from bundled capture during a controlled pause of public form submissions,
including direct POST/AJAX requests. Back up first, preserve the saved preference,
then install/activate Divi integration 1.0.2 and upgrade Settings to 1.6.4 while
submissions remain paused. Older bundled builds can fail because of plugin load
order or Divi field-array handling; overlap is not an uninterrupted-capture guarantee.
Through controlled access, reload each relevant form with a fresh nonce and verify
exactly one complete record per successful submission, no records for validation
failures, correct fields/channels and unchanged existing records. Resolve notices
and PHP errors before continuing. Clear cached forms and repeat the final check
before restoring public submissions. Keep any rollback under maintenance; obsolete
bundled capture is not a reliable fallback. The integration readme contains the
full controlled upgrade procedure.


== Channel hierarchy export/import ==

New JSON exports retain the existing top-level message list and legacy channel
fields, and add channel_paths. Each path is an ordered list of name/slug pairs
from its root to an explicitly assigned channel. Every assigned channel includes
its full ancestry, even when no message is assigned directly to its parents.
Multiple assigned channels and deeper hierarchies are preserved.

Both the admin background importer and CLI callers of the shared import service
resolve these paths parent-first. Destination term IDs are discovered locally;
exported numeric IDs are never used for mapping. Names are matched within the
resolved parent, with slugs distinguishing otherwise ambiguous siblings. This
also permits reuse when WordPress generated a different destination slug suffix.
Existing channels are never moved to another parent to satisfy an import.

Legacy exports without paths remain supported. A unique flat slug can reuse an
existing channel, or create a flat root when absent. Numeric-ID-only exports stay
unassigned because source IDs and missing ancestry cannot be reconstructed safely.
Old files that never contained parents cannot recover that lost information.
Malformed new path data fails validation rather than silently importing flat.

Channel creation and persisted assignment failures count as failed messages, not
successful imports. Incomplete messages are removed where possible so retries
remain safe; any cleanup failure is reported with the incomplete post ID through
the service result. Parents created before a later failure can remain and will be
reused on retry. The admin completion notice reports imported, skipped and failed
counts across batches. Existing title/content duplicate detection is unchanged:
reimporting a duplicate does not rewrite its prior channel assignment.

== Changelog ==

= 1.6.4 =

* Add contextual AC Advanced Flamingo CLI information below Import/Export and in the Integrations add-ons screen.
* Permanently dismiss the Import/Export promotion per administrator; hide it automatically when CLI is active.
* Show installed/active CLI status and clarify that AI workflows require authorised WP-CLI access.
* Existing free import/export tools remain unchanged; no premium code, tracking or remote assets are loaded.

= 1.6.3 =
* Preserve repeated metadata rows, serialized values, zero values and literal backslashes during imports.
* Report metadata and channel failures accurately and remove incomplete messages where possible for safe retries.
* Preserve complete parent-child channel paths across sites without relying on source term IDs.
* Reuse channels within their correct parent, including slug collisions and concurrent creation.
* Preserve enabled Divi capture preferences and show persistent, actionable migration or capture-failure warnings.
* Report accurate imported, skipped and failed totals in admin imports.
* Harden admin import nonce and upload-origin validation and use safe redirects.
* Report file-deletion failures accurately and avoid following external directory symlinks.
* Use site-local export date defaults without applying the timezone offset twice.
* Align supported requirements with WordPress 6.7+ and PHP 7.4+.


= 1.6.2 =
* Corrected duplicate detection during message imports when WordPress sanitises message titles.

= 1.6.1 =
* Corrected the internal ACAFS version constant.
* Added missing release notes for version 1.6.0.

= 1.6.0 =
* Improved Flamingo message import/export portability between different WordPress sites.
* Channels are now preserved correctly when importing messages into a site where taxonomy term IDs differ.
* Added reusable import/export services for integrations and add-ons.
* Improved validation and reliability of message import/export processing.

= 1.5.0 =

* Improve the Uploaded Files admin page with image thumbnails for image uploads.
* Add file-type icons for non-image uploads on the Uploaded Files admin page.
* Add a dedicated Download button for each stored uploaded file.
* Add a dedicated Integrations screen for managing optional add-ons.
* Add integration status sections for activated, installed, and available integrations.
* Add integration cards for Divi, WPBakery, Enfold, and Beaver Builder.
* Add prompts for installed integrations that have not yet been activated.
* Add integration-specific settings when supported by an active add-on.
* Confirm compatibility with WordPress 6.9.4.

= 1.4.3 =

* Add Uploaded Files admin page with secure file deletion controls.
* Add option to disable persistent uploads by default.
* Improve safety and management of stored CF7 uploads.

= 1.4.2 =

* Fix missing vendor folder in version 1.4.0.

= 1.4.0 =

* Persist CF7 uploads to permanent URLs and store them in Flamingo.
* Make stored upload URLs clickable on the Flamingo single message screen.
* Performance and background import improvements.
* Internal code quality and autoloading improvements.

= 1.3.0 =

* Import now processes messages in batches for significant speed gains.
* Improved duplicate detection using content hashes.
* More reliable admin notices for long-running imports.

= 1.2.0 =

* Skip existing messages during import with clear user feedback.
* Add a **Settings** link to the Plugins page.
* Improve JSON export reliability.

= 1.1.0 =

* Introduce Import/Export feature for Flamingo messages.
* Preserve the "Channel" column during import.

= 1.0.0 =

* Initial release with enhanced Flamingo settings and message management.

== Upgrade Notice ==

= 1.6.4 =
Adds optional CLI add-on information to Import/Export and Integrations. The Import/Export panel can be permanently dismissed per administrator and is hidden when CLI is active. Existing free functionality is unchanged.

= 1.6.3 =
Requires WordPress 6.7+ and PHP 7.4+. Pause public Divi submissions and back up. Install/activate Divi 1.0.2 and upgrade Settings while paused. Preserve the preference and verify complete capture before restoring traffic. Legacy files cannot reconstruct missing ancestors.


= 1.6.2 =
Prevents duplicate Flamingo messages when importing exports whose message titles are normalised by WordPress.


= 1.6.1 =
Maintenance release correcting the internal plugin version value and completing the 1.6.0 release documentation.

= 1.6.0 =
Improves message migration between WordPress sites by preserving Flamingo channels reliably during export and import.

= 1.5.0 =
Improves the Uploaded Files admin page with thumbnails, file-type icons, and direct download buttons. Also introduces a new Integrations screen for managing optional add-ons.

== Support ==
For support or feature requests, visit https://ambercouch.co.uk/
