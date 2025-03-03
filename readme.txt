=== AC Advanced Flamingo Settings ===

Contributors: amberCouch
Tags: contact form 7, flamingo, contact form database, import, export
Requires at least: 5.4
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.1.0

License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AC Advanced Flamingo Settings enhances Flamingo for Contact Form 7, adding advanced options for managing form submissions efficiently.

== Description ==

Enhance Flamingo with additional customization options!

This plugin extends Flamingo, the Contact Form 7 database storage plugin, by adding powerful features for better form submission management:

- **New: Import/Export Feature**
  - Export Flamingo messages to a JSON file for backup or migration.
  - Import messages back into Flamingo while preserving all metadata, including the "Channel" column.

- **Enhanced Inbound Messages**
  - Display submission details directly in the Flamingo Inbound Messages list.
  - Choose which form fields to show in the Inbound Messages table.

- **Better Contact Log Management**
  - Add a "Latest Message" link to each contact in your address book.
  - Reorder the Flamingo menu, setting Inbound Messages as the default view.
  - Rename the Flamingo menu for better organization.
  - Optionally disable the Address Book if not needed.

This plugin is perfect for users who rely on Flamingo and need a better-organized contact log with backup and migration capabilities.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ac-advanced-flamingo-settings/` directory, or install the plugin via the WordPress plugin repository.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings → AC Flamingo** to configure your preferences.
4. For import/export, go to **Flamingo → Message Sync** and follow the instructions.

== Frequently Asked Questions ==

= Does this plugin work without Flamingo? =
No. AC Advanced Flamingo Settings is designed as an extension for Flamingo and requires it to function.

= Can I choose which form fields appear in the Inbound Messages table? =
Yes! You can select the specific fields you want to display from the settings panel.

= Can I disable the Address Book? =
Yes! If you do not use the Address Book, you can disable it entirely via the settings.

= How do I rename the Flamingo menu? =
Go to **Settings → AC Flamingo** and enter your preferred name for the menu.

= How does the import/export feature work? =
- **Export**: Click "Export Messages" in **Flamingo → Message Sync** to download all Flamingo messages in a JSON file.
- **Import**: Upload a previously exported JSON file to restore messages, including the "Channel" column.

== Screenshots ==

1. **Settings Page** - Customize Flamingo behavior and menu options.
2. **Enhanced Inbound Messages** - Display form submission details directly in the table.
3. **Import/Export Page** - Export and import Flamingo messages easily.

== Changelog ==

= 1.1.0 =
- **New Import/Export feature**: Backup and restore Flamingo messages.
- **Preserves the "Channel" column** during import.
- **UI improvements** to the settings page.

= 1.0.1 =
- Added required header.

= 1.0.0 =
Initial release with the following features:
- Customizable submission details in Inbound Messages.
- Option to disable the Address Book.
- Menu reordering and renaming for Flamingo.
- Enhanced settings panel.

== Upgrade Notice ==

= 1.1.0 =
This update introduces an **import/export feature** for backing up and migrating Flamingo messages. If upgrading, visit **Flamingo → Message Sync** to start using it.

== Support ==
For support or feature requests, visit [https://ambercouch.co.uk/](https://ambercouch.co.uk/)
