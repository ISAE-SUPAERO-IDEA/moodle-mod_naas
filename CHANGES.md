# Changelog

_All notable changes to the NaaS Moodle plugin will be documented in this file._

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

