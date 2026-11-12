# Changelog

_All notable changes to the NaaS Moodle plugin will be documented in this file._

## Version 3.0.0 (unreleased)

### Feature
- New Nugget search widget built with Vue 3 / Vite / TypeScript, with a catalogue landing page, producer sections, filters and infinite scroll
- Catalogue cache: the search opens from a cached snapshot and revalidates it in the background; a nightly task (`refresh_catalogue`) keeps it warm
- Administration page reorganised in sections, with "Test connection" and "Refresh cache" buttons
- Completion rules on Moodle 4.x: attempts allowed, minimum number of attempts, passing grade or all attempts used
- Optional OAuth signature check on grades sent back by NaaS (setting "Require OAuth signature on grade passback")

### Fix
- An attempt only counts once NaaS has sent its result; opening a Nugget without finishing it no longer uses an attempt
- The attempts limit is also enforced when the result arrives, so launches opened in parallel cannot exceed it
- Grades outside 0–1 and grade submissions for activities without a grade item are rejected
- Backup, restore and duplication keep the attempts allowed, grading method, terms agreement and Nugget completion rules; with user data, learners' attempts are restored too
- Domain codes with dots and e-mail author keys no longer break the vocabulary cache when debugging is on
- The completion condition shows "Complete when all attempts are used" when only that option is ticked
- Saving the activity form without a Nugget now shows "Select a Nugget before saving the activity" above the search, instead of an error on a hidden field
- The nightly catalogue refresh applies the licence filters, like the live search
- Activity names are no longer escaped twice on the course index and the "next activity" button
- NaaS errors on the player page are shown as standard Moodle notifications
- An xAPI statement with an invalid JSON body, or an empty NaaS answer, is rejected with a clear error
- The widget gives up with an error after 5 s if Moodle's JavaScript loader is missing, instead of loading forever
- The activity form warns about unsaved changes after a Nugget is selected
- "Refresh cache" rebuilds the catalogue first and only then drops the cached names and Nugget documents: if NaaS does not answer, every cache stays in place
- Parallel searches no longer lose entries of the search cache index (the index is updated under a lock)
- After a change of NaaS URL, account or catalogue filter, the nightly task drops the searches cached for the old connection instead of refreshing them
- When NaaS does not answer, the catalogue landing keeps working from the cached catalogue
- Authors and producers whose name cannot be found show "Unknown author" / "Unknown producer" instead of an identifier, and are not requested again
- Structures and persons NaaS cannot name are remembered for an hour instead of being looked up on every page
- The widget sends one request for identical lookups made at the same time (for example the same author on several cards)
- The privacy export no longer prints text when there is nothing to export
- Requests to NaaS with several parameters were sent with `&amp;` between them, so NaaS ignored the page size and page of the producer and person listings
- On Moodle 4.x, the "About" entry of the activity navigation opens the Nugget's About dialog again (it did nothing)

### Accessibility
- The About, preview and rating dialogs close with Escape, take the focus when they open and give it back when they close, and are labelled by their title
- Every widget text comes from the language file (no hard-coded English left)
- The dialogs keep the keyboard focus inside them (Tab and Shift+Tab cycle through their controls)
- Search results are a list of named cards with real buttons; the selected Nugget is announced as pressed, and the arrow keys move between cards
- Text and controls meet the WCAG AA contrast ratios; rating stars are outlined when empty and filled when chosen
- The closed filter panel can no longer be reached with the keyboard, the clear-search button is 24 px wide, and the Nugget frames have a title

### Security
- Stricter validation of NaaS identifiers and xAPI statements, enrolment check on the proxy web services, external entities disabled when reading grade submissions
- The Nugget id stored in an activity is validated before it is used in a NaaS request
- HTML descriptions imported from NaaS keep only basic formatting (no scripts, event handlers or unsafe links) before they are put in the description editor
- The widget configuration is a JSON data block instead of an inline script, so pages work under a Content Security Policy without `'unsafe-inline'`
- The cached catalogue (producers, author names, e-mail addresses and biographies) is only sent to the activity form, not to the learner view; the privacy declaration now covers author profiles received from NaaS
- The browser no longer loads anything from NaaS: Nugget thumbnails, producer logos and banners are served by Moodle (`media.php`), which checks course access, only fetches images from the configured NaaS host and only returns images; the NaaS API URL is no longer sent to the browser
- The Nugget preview only opens a web page (`https`, or the NaaS host itself)

### Chore
- `moment` replaced by the browser `Intl` API (−58 KB of JavaScript); the bundle no longer ships an unused `style.css`
- Release archive: development files are excluded with `export-ignore`; translations are managed on AMOS
- Unused language strings removed; English strings sorted and written for AMOS (no sentence built from pieces)
- The search widget is split into smaller components and composables; the structure and person lookups have their own classes (`structure_lookup`, `person_lookup`)
- The "Test connection" and "Refresh cache" buttons share one admin setting and one AMD module
- Moodle 4.4 and 4.5: the web services no longer load `lib/externallib.php` (they use the `core_external` classes, with a compatibility layer on Moodle 4.1)
- CI: Moodle 4.4 and 4.5 added to the test matrix, PHP 8.0 dropped, and the build fails if fewer PHPUnit tests than expected run
- Supported Moodle versions declared: 4.1 to 5.0; the plugin now requires Moodle 4.1
- The plugin no longer depends on `mod_url` (leftover page-type, drag-and-drop and content-export callbacks removed)
- CI: PHPStan at level 5 and blocking, PHP and widget coverage measured with minimums, Behat scenarios for the search widget against a fake NaaS
- Widget build tools updated (Vite 6, Vitest 4, vue-tsc 3): no known vulnerability left in the JavaScript dependencies
- CI: Moodle 5.0 added to the test matrix (PostgreSQL 14)
- Opening an activity form no longer calls NaaS for the selected Nugget when its stored copy is less than a day old: the nightly task revalidates the Nuggets of every activity, in batches when NaaS supports `nugget_ids` and one by one otherwise
- `NuggetSearchWidget.vue` is split into results, grid and selected-Nugget components and query and selection composables
- The web services follow Moodle's layout: one class per function (`mod_naas\external\get_nugget`, …) with shared helpers in `naas_external_base`, instead of one 770-line `proxy_naas_api` class; their names and behaviour are unchanged
- Development notes (`docs/` and planning files) removed from the repository
- Tests: PHPUnit metadata also written as attributes (no deprecation on Moodle 5.0 / PHPUnit 11), widget coverage at 100 %, PHP coverage raised, Behat checks the widget, the preview and the About dialog with axe (WCAG 2.2 AA)
- The catalogue cache is split into `catalogue_cache`, `catalogue_producers`, `catalogue_export` and `catalogue_warmer`; the widget filter panel into a facet column, a label composable and selection helpers
- Tests: component tests for the whole widget (coverage minimum raised to 90 % of lines), Behat checks the signed LTI 1.1 launch form and the unknown-Nugget error

### Upgrade notes
- Requires Node 22 to rebuild the widget; the bundle is now `assets/vue/naas_widget-<version>.js`
- NaaS images are now requested by the Moodle server, which must be able to reach the NaaS host. An image hosted elsewhere than the NaaS API host is not shown
- Existing launches are not counted as completed attempts after the upgrade. No existing activity is affected: the attempts and minimum-attempts settings could not be set before 3.0.0, so they are 0 (unlimited / not required) everywhere
- Grades sent back by NaaS are not authenticated until the "Require OAuth signature on grade passback" setting is enabled. Leave it off until the NaaS platform signs grade submissions; the plugin settings page shows when a correctly signed submission has been received, after which the setting can be enabled

## Version 2.5.2 (2026-09-18)

### Feature
- Show producer and partner information in the Nugget About view
- Send the Moodle UI language to NaaS so the player can match it

### Fix
- Clearer error when the configured NaaS institute is invalid
- Handle nugget loading errors and distinguish a missing nugget from a bad API endpoint
- Remove inoperative navigation buttons from the nugget rating popup

### Chore
- Render the activity view and widget with Mustache / Output API
- Add CodeQL, PHPStan, and npm audit GitHub Actions workflows


## Version 2.5.1 (2026-03-09)

### Fix
- Fix the language change of a Nugget


## Version 2.5.0 (2025-09-30)

### Chore
- This version brings Moodle 5.0 compatibility


## Version 2.4.6 (2025-09-26)

### Improvements
- Change the plugin type from "resource" to "activity"
- Improve the responsivity of the "About" pop-up
- Improve the responsivity of the layout of Nuggets search results

## Version 2.4.5 (2025-07-16)

### Fix
- Implement naas_update_grades so that a course containing Nuggets can be reset


## Version 2.4.4 (2025-06-17)

### Chore
- Use AMOS for French translations

### Fix
- Submitting a new grade when retrying an activity
- Nugget preview was incorrect after changing the search criteria

## Version 2.4.3 (2025-06-04)

### Fix
- Fix hide / show section containing Nuggets
- Fix xAPI response handling

## Version 2.4.2 (2025-06-02)

### Fix
- Fix incorrect types on get_nugget which break edit Nugget activity
- Add missing translation


## Version 2.4.1 (2025-05-19)

### Fix
- Fix next activity button
- Fix index.php to list Nugget activities
- Fix Incorrect URL in xapi.php call

### Chore
- Describe the xAPI traces in the Privacy API
- Reimplement NaaS API and xAPI usages with External Services
- Don't call curl_init directly
- Use Access API and enforce context checking
- Remove unused install.php
- Add Missing language string for capabilities definitions
- Add Missing Header and Copyright Information in JS File
- Replace Hard-coded language string

## Version 2.4.0 (2025-03-24)

### Feature
- Add support for the Nugget feedback feature

### Fix
- Fix language loading when starting a Nugget
- Remove stored Nugget sessions when removing a Nugget activity

### Chore
- Fix messages for capabilities
- Provide i18n message for hard-coded content


## Version 2.3.1 (2025-03-04)

### Fix
- Restore the "fr" lang file


## Version 2.3.0 (2025-03-03)

### Feature
- Simplify grading config: only set "Grading strategy", "Maximum grade", and "Grade to pass"
- Store "Maximum grade" when creating a Nugget activity

### Chore
- Rework grading implementation

### Fix
- "FIRST GRADE" grading strategy


## Version 2.2.3 (2025-02-24)

### Fix
- Grade "0" wasn't recorded


## Version 2.2.2 (2025-02-10)

### Fix
- Fix the grading feedback from the NaaS platform

## Version 2.2.1 (2025-02-04)

### Fix
- Fix the proxy URL (previous fix was not correct)

## Version 2.2.0 (2025-02-03)

### Features
- Add the "naas" table description to the Privacy API
- Add a "Test connection" button to check communication with the NaaS platform on the plugin settings page
- Improve http errors handling and display error messages 

### Fix
- Fix the proxy URL

## Version 2.1.0 (2025-01-23)

### Features
- Implement the Privacy API
- Add an error message when a Nugget cannot be retrieved

### Chore
- Fix CSS classes without proper namespace
- Fix Config.php included incorrectly
- Add missing info in thirdpartylibs.xml
- Improve terminology
- Remove direct access to $_GET/$_POST


## Version 2.0.0 (2024-12-09)

### Chore
- Improve code source compliance with Moodle standards
- Enumerate 3rd party libraries
- Set up Moodle Plugin CI
- Improve inline documentation
- Add ISAE-SUPAERO copyright on source files


## 2024-07-29

### Features
- Add LTI return from NaaS
- Set "Grade" to Nugget activity
- Configure Nugget activity completion with view, grade or passing grade
- Proxy bypass configuration

### Fixes
- Various minor bug fixes


## 2022-12-13

### Features
- Detail & Preview on nugget for Author before adding in course
- Detail of nugget for learners
- Better English/French translation
- Remove result limitation in nugget search and adding "Show more result" button


## 2022-08-31

### Features
- Change search widget
- Change Admin rôle

### Fix
- Fix "Add new nugget" icon


## 2022-03-01

### Feature
- Search filters


## 2022-01-19

### Feature
- Various ergonomic improvements

### Fixes
- Fixed the bug preventing the completion of the nugget activity.
- Fixed the bug preventing the logs of nugget activity usage from being retrieved.

