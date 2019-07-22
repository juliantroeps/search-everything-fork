# Search Everything Fork

An updated fork of the [Search Everything. WordPress Plugin](https://wordpress.org/plugins/search-everything).

Originaly developed by @dancameron and @sproutventure, and later maintained and further developed by Zemanta.

## Description

Search Everything improves WordPress default search functionality without modifying any of the template pages. You can configure it to search pages, excerpts, attachments, drafts, comments, tags and custom fields (metadata) and you can specify your own search highlight style. It also offers the ability to exclude specific pages and posts. It does not search password-protected content. Simply install, configure... and search.

Search Everything plugin now includes a writing helper called Research Everything that lets you search for your posts and link to them while writing. You can also enable Power Search to research posts from the wider web (for WP3.7 and above).

## Changelog

** 8.1.10 **
- Fixed create_function deprecation error

### Previous Changelog

** 8.1.9 **
- Fixed a search issue that caused all results to be returned regardless of the options.

** 8.1.8 **
- Fixed a migration/update issue

** 8.1.7 **
- Compatibility with WordPress 4.7
- Security update: resolve SQL injection vunerability related to WP 4.7

** 8.1.6 **
- Security update: filtering out empty search strings that could enable sql injections

** 8.1.5 **
- Compatibility with PHP 7
- Bypassing highlighting in dashboard searches

** 8.1.4 **
- Removed unnecessary styles on frontend
- Fixed php notice showing up sometimes
- Czech language added

** 8.1.3 **
- Support for multitag search

** 8.1.2 **
- CSS bugfix

** 8.1.1 **
- Security update (CSRF vunerability fix)
- Added form validation to Options page

** 8.1 **
- Fixed link search bug
- Fixed bug of limiting number of results in Research Everything
- Improved code robustness
- Fixed translation system
- Fixed upgrade bug
- Renamed methods with too generic names
- Fixed admin notices - they're only visible to admins now

** 8.0 **
- Added research widget on compose screen
- Reorganized settings
- Security updates

** 7.0.4 **
- Urgent bugfix - changed migration script

** 7.0.3 **
- Fixed vulnerability issue in se_search_default and started escaping terms
- Refactored code, extracted html from PHP code
- Added support for ajax call


** 7.0.2 **
- Added config file with installation and migration functions
- Refactored code, removed Yes options
- Replaced deprecated functions
