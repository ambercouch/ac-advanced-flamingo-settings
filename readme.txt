=== AC Advanced Flamingo Settings ===

Contributors: amberCouch
Tags: contact form 7, flamingo, contact form database, import, export
Requires at least: 5.4
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.4.3

License: GPLv2 or later
License URI: [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

AC Advanced Flamingo Settings enhances and extends the functionality of the CF7 Flamingo plugin by adding customization options, import/export tools, and improved handling of uploaded files.

== Description ==

Enhance Flamingo with additional customization options!

This plugin extends Flamingo, the Contact Form 7 database storage plugin, by adding powerful features for better form submission management:

* **Import/Export Feature (Enhanced)**

  * Export Flamingo messages to a JSON file for backup or migration.
  * Import messages back into Flamingo while preserving all metadata, including the "Channel" column.
  * Imports are processed in optimized batches for improved reliability on large datasets.
  * Duplicate messages are automatically detected and skipped using content hashing.
  * Clear and reliable admin notices summarise import results.

* **Persistent File Uploads (Optional)**

  * Optionally persist Contact Form 7 uploads to permanent URLs.
  * Stored upload URLs can be viewed and accessed from Flamingo messages.
  * **Disabled by default** to avoid unexpected storage growth.

* **Uploaded Files Management (New)**

  * Dedicated admin screen to view all persisted uploaded files.
  * Download files directly via secure links.
  * Delete individual files or bulk delete all stored uploads.

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

This plugin is ideal for sites that rely heavily on Flamingo and need better organisation, safer backups, and optional long-term file retention.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ac-advanced-flamingo-settings/` directory, or install the plugin via the WordPress plugin repository.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → AC Flamingo** to configure your preferences.
4. For message import/export, go to **Flamingo → Message Sync**.
5. To manage stored uploads, go to **Flamingo → Uploaded Files**.

== Frequently Asked Questions ==

= Does this plugin work without Flamingo? =
No. AC Advanced Flamingo Settings is an extension for Flamingo and requires it to function.

= Is persistent file upload enabled by default? =
No. Persistent uploads are **disabled by default** and must be explicitly enabled in the settings.

= Can I delete uploaded files after import? =
Yes. You can delete individual files or remove all stored uploads from the Uploaded Files admin page.

= Can I choose which form fields appear in the Inbound Messages table? =
Yes. You can select exactly which fields are displayed via the settings panel.

= Can I disable the Address Book? =
Yes. The Address Book can be disabled entirely from the settings.

= How does the import/export feature work? =

* **Export**: Click "Export Messages" in **Flamingo → Message Sync** to download a JSON backup.
* **Import**: Upload a previously exported JSON file to restore messages.
* Existing messages are automatically skipped, and a summary is shown on completion.

== Screenshots ==

1. **Settings Page** – Configure Flamingo behaviour and plugin options.
2. **Enhanced Inbound Messages** – Display submission details directly in the message list.
3. **Import/Export Page** – Backup and restore Flamingo messages.
4. **Uploaded Files Page** – View and manage persisted file uploads.

== Changelog ==

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

= 1.4.3 =
Introduces an Uploaded Files management screen and disables persistent uploads by default. Review your settings after upgrading.

== Support ==
For support or feature requests, visit [https://ambercouch.co.uk/](https://ambercouch.co.uk/)
