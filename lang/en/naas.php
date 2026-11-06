<?php
// This file is part of Moodle - http://moodle.org
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English messages.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
$string['about'] = 'About';
$string['about_author'] = 'About the author';
$string['about_title'] = 'About: {$a}';
$string['active_filters'] = 'Active filters';
$string['admin_setting_help'] = 'More information';
$string['advanced'] = 'Advanced';
$string['all_nuggets'] = 'All Nuggets';
$string['attemptfirst'] = 'First attempt';
$string['attemptlast'] = 'Last attempt';
$string['attempts'] = 'Attempts allowed';
$string['attempts_help'] = 'Maximum number of attempts a learner may complete. An attempt only counts once NaaS has sent its result back to Moodle; opening the Nugget without finishing it does not use an attempt. Users who can edit the activity are not limited.';
$string['authors'] = 'Authors';
$string['back_to_catalogue'] = 'Back to catalogue';
$string['back_to_course'] = 'Back to course index';
$string['beginner'] = 'Beginner';
$string['browse_help'] = 'Choose All Nuggets or a producer to browse.';
$string['browse_no_producers'] = 'No producers in this catalogue yet.';
$string['browse_nugget_count'] = 'Nuggets';
$string['by_producers'] = 'By producers';
$string['cache_refresh'] = 'Refresh cache';
$string['cache_refresh_failed'] = 'Could not refresh the cache. Save the connection settings, then try again.';
$string['cache_refresh_information'] = 'Deletes the four caches described above and loads them again from the saved API URL, username, password, and institute. Save changes first. This can take a minute.';
$string['cache_refresh_success'] = 'The cache was cleared and loaded again from NaaS.';
$string['cache_refresh_testing'] = 'Refreshing…';
$string['cachedef_catalogue_snapshot'] = 'NaaS catalogue snapshot shown when the Nugget search opens';
$string['cachedef_nugget_documents'] = 'NaaS Nugget documents';
$string['cachedef_oauth_nonces'] = 'OAuth nonces of recent NaaS grade submissions (replay protection)';
$string['cachedef_search_results'] = 'NaaS Nugget search results';
$string['cachedef_vocabulary_entries'] = 'NaaS vocabulary labels (authors, producers, domains)';
$string['cannot_get_nugget'] = 'The Nugget could not be loaded. Please try again. If the problem persists, please contact your platform administrator.';
$string['cgu_agreement'] = 'I have read and agree to the <a target="_blank" href="https://doc.clickup.com/2594656/p/h/2f5v0-13342/fff6a689cd78033">Terms and Conditions of the NaaS platform</a>';
$string['checking_updates'] = 'Checking for updates...';
$string['clear_filters'] = 'Clear filters';
$string['clear_search'] = 'Clear search';
$string['click_to_replace'] = 'Replace the selected nugget';
$string['complete_nugget'] = 'I Finished My Learning With This Nugget';
$string['completionattemptsexhausted'] = 'Or all available attempts completed';
$string['completionattemptsexhaustederror'] = 'Completion on exhausted attempts requires a limited number of attempts allowed.';
$string['completiondetail:exhaust'] = 'Complete when all attempts are used';
$string['completiondetail:minattempts'] = 'Minimum number of attempts required: {$a}';
$string['completiondetail:passgrade'] = 'Complete on passing grade';
$string['completiondetail:passorexhaust'] = 'Complete on pass or exhaustion';
$string['completionminattempts'] = 'Minimum number of attempts: ';
$string['completionminattemptserror'] = 'Minimum number of attempts must be lower or equal to attempts allowed.';
$string['completionminattemptsgroup'] = 'Require attempts';
$string['completionminattemptsinvalid'] = 'Minimum number of attempts must be a whole number greater than zero.';
$string['completionpass'] = 'Require passing grade';
$string['completionpass_desc'] = 'Student must achieve a passing grade to complete this activity';
$string['completionpass_help'] = 'If enabled, this activity is considered complete when the student receives a pass grade (as specified in the Grade section of the nugget settings) or higher. Tick \'Or all available attempts completed\' to also mark the activity complete once the student has completed every allowed attempt (see \'Attempts allowed\'); that option can also be used on its own. An attempt only counts once NaaS has sent its result back to Moodle.';
$string['connection_test_failed'] = 'Could not connect to NaaS. Save the connection settings, then try again.';
$string['connection_test_success'] = 'Connected to NaaS. A catalogue cache was stored so the Nugget search page can open immediately.';
$string['connection_test_testing'] = 'Testing…';
$string['de'] = 'German';
$string['demo'] = 'Demo';
$string['description'] = 'Description';
$string['duration'] = 'Duration';
$string['duration_minutes'] = '{$a} minutes';
$string['en'] = 'English';
$string['error:generic_user_message'] = 'We couldn\'t process your request. Please try again or contact support if the problem persists.';
$string['error:invalid_param'] = 'Invalid value for parameter: {$a}';
$string['error:invalid_score'] = 'The grade submission from NaaS has a score outside the allowed range 0–1.';
$string['error:invalid_xapi_verb'] = 'The xAPI verb \'{$a}\' is not allowed.';
$string['error:malformed_xml'] = 'The grade submission from NaaS could not be read.';
$string['error:must_be_strictly_positive'] = 'This must be a strictly positive number.';
$string['error:naas_api:bad_request'] = 'We couldn\'t process your request. Please contact support for assistance.';
$string['error:naas_api:invalid_credentials'] = 'The credentials are incorrect.';
$string['error:naas_api:invalid_endpoint'] = 'The configured NaaS API endpoint is invalid.';
$string['error:naas_api:invalid_structure'] = 'The NaaS institute (structure) configured for this activity is invalid. Please ask your platform administrator to check the plugin configuration.';
$string['error:naas_api:not_found'] = 'The requested resource was not found on the NaaS server.';
$string['error:naas_api:unknown'] = 'The NaaS server returned an unexpected error. Please try again or contact support.';
$string['error:naas_server'] = 'An error occurred on the NaaS server';
$string['error:naas_server_unexpected'] = 'Unexpected error occurred on the NaaS server';
$string['error:not_enrolled'] = 'You are not enrolled in the course for this activity.';
$string['error:nugget_required'] = 'Select a Nugget before saving the activity.';
$string['error:oauth_invalid'] = 'The grade submission from NaaS could not be authenticated.';
$string['error:proxy_naas_api:curl'] = 'The attempt to communicate with NaaS has failed : {$a}.';
$string['error:unknown_sourced_id'] = 'The grade submission from NaaS does not match any known learner session.';
$string['error:unsupported_grade_method'] = 'The grade method "{$a}" is not supported.';
$string['error:xapi_body_invalid'] = 'The xAPI statement body is not valid JSON.';
$string['error:xapi_body_too_large'] = 'The xAPI statement body exceeds the maximum allowed size.';
$string['error_nugget_not_found'] = 'The Nugget you are trying to access does not exist or is no longer available.';
$string['es'] = 'Spanish';
$string['field_of_study'] = 'Field of study';
$string['filters'] = 'Filters';
$string['fr'] = 'French';
$string['general'] = 'General';
$string['grade_method'] = 'Grading method';
$string['grade_method_help'] = '<p>When multiple attempts are allowed, the following methods are available for calculating the final nugget grade:<ul><li>Highest grade of all attempts</li><li>First attempt (all other attempts are ignored)</li><li>Last attempt (all other attempts are ignored)</li></ul></p>';
$string['gradehighest'] = 'Highest grade';
$string['gradetopassmustbeset'] = 'Grade to pass cannot be zero as this nugget has its completion method set to require a passing grade. Please set a non-zero value.';
$string['gradetopassnotset'] = 'This nugget does not yet have a grade to pass set. It may be set in the Grade section of the nugget settings.';
$string['hide_authors'] = 'Hide';
$string['in_brief'] = 'In Brief';
$string['intermediate'] = 'Intermediate';
$string['is_public'] = 'Visibility';
$string['it'] = 'Italian';
$string['language'] = 'Language';
$string['lastmodified'] = 'Last modified';
$string['learning_outcomes'] = 'Learning outcomes';
$string['learning_outcomes_desc'] = 'You have completed this nugget. The learning objectives were:';
$string['lesson'] = 'Lesson';
$string['level'] = 'Level';
$string['license'] = 'Licence';
$string['license_1'] = 'CC BY';
$string['license_2'] = 'CC BY-SA';
$string['license_3'] = 'CC BY-NC';
$string['license_4'] = 'CC BY-NC-SA';
$string['loading'] = 'Loading...';
$string['moduleintro'] = 'Introduction to the module';
$string['modulename'] = 'Nugget';
$string['modulename_help'] = '<p>The Moodle Nugget Plugin enables a teacher to integrate a micro-content from the NaaS Server. The teacher can allow the Nugget exercice to be attempted multiple times. A time limit may be set. Each attempt is marked automatically and the grade is recorded in the gradebook.<br>Nuggets may be used:<ul><li>As course exams</li><li>As mini tests for reading assignments or at the end of a topic</li><li>As exam practice using questions from past exams</li><li>For self-assessment</li></ul></p>';
$string['modulename_link'] = 'mod/naas/view';
$string['modulenameplural'] = 'Nuggets';
$string['naas:addinstance'] = 'Add a new Nugget';
$string['naas:admin'] = 'Administrate the Nugget plugin';
$string['naas:view'] = 'Access a Nugget';
$string['naas_refresh_limit'] = 'Nightly refresh limit';
$string['naas_refresh_limit_desc'] = 'Maximum number of Nugget searches the nightly task re-fetches from NaaS. The default is the whole cache ({$a} lists). Set 0 for the same behaviour. Only lists teachers have opened (or the landing seed) are refreshed, most recently used first.';
$string['naas_server_unexpected'] = 'Unexpected error occurred on the NaaS server';
$string['naas_settings_about'] = 'About the Nugget plugin';
$string['naas_settings_about_information'] = 'This page connects Moodle to the [NaaS](https://www.naas-edu.eu/) platform so teachers can add Nugget activities.

The username and institute ID below default to public Open Education (OER) credentials. Enter the API password (or set the NAAS_API_PASSWORD environment variable), then save and test the connection.

For your school\'s private catalogue, request API keys from [idea.lab@isae-supaero.fr](mailto:idea.lab@isae-supaero.fr). Read the [privacy notice](https://doc.clickup.com/2594656/p/h/2f5v0-8202/267a2f1cc205119).';
$string['naas_settings_access_filter'] = 'Restricted / unrestricted use';
$string['naas_settings_access_filter_all'] = 'All Nuggets';
$string['naas_settings_access_filter_help'] = 'Restricts the Nugget picker to the distribution right on the access licence granted to your institute. Already-added activities stay in courses.

All Nuggets: no extra restriction (the default).

Unrestricted use only: keep openly distributed Nuggets (uu). Hide Nuggets limited to authenticated or closed distribution (ru).

Restricted use only: keep Nuggets that require authentication and limited distribution (ru). Hide unrestricted (uu) Nuggets.';
$string['naas_settings_access_filter_restricted'] = 'Restricted use only — hide unrestricted Nuggets';
$string['naas_settings_access_filter_unrestricted'] = 'Unrestricted use only — hide restricted Nuggets';
$string['naas_settings_advanced'] = 'Advanced';
$string['naas_settings_advanced_information'] = 'Timeout and TLS options for talking to the NaaS API. Leave the defaults unless you are diagnosing a connection problem.';
$string['naas_settings_appearance'] = 'Appearance';
$string['naas_settings_appearance_information'] = 'Optional CSS sent to the Nugget player. It does not change the Moodle theme.';
$string['naas_settings_cache'] = 'Cache';
$string['naas_settings_cache_information'] = 'The plugin keeps four site-wide caches so the Nugget search can open from Moodle and then check NaaS in the background. Catalogue: producer cards (acronym, name, logo, banner, and nugget count), the first page of Nuggets, facet counts, and a stamp that records whether the catalogue changed. Searches: one entry for each list a teacher has opened (a producer, All Nuggets, or a filter), with the Nugget cards and the facet buckets. Names: the readable labels behind the hashes NaaS returns, including a producer acronym and name, an author first and last name, and domain labels. Nugget documents: the full Nugget opened in the activity form, so reopening it does not call NaaS first. Refresh cache deletes all four, then loads the catalogue, those search lists, and the author names again.';
$string['naas_settings_catalogue'] = 'Catalogue';
$string['naas_settings_catalogue_information'] = 'Optionally restrict which Nuggets teachers can find when adding an activity. Already-added activities are not affected.';
$string['naas_settings_connection'] = 'Connection';
$string['naas_settings_css'] = 'Extra CSS for Nugget player';
$string['naas_settings_css_help'] = 'CSS passed to the Nugget player (LTI css parameter), not to the whole Moodle site. Leave empty for no extra CSS. Example: body { font-family: inherit; }';
$string['naas_settings_endpoint'] = 'NaaS API URL';
$string['naas_settings_endpoint_help'] = 'Base URL of the NaaS API, including the /api path. Example: https://api.naas-edu.eu/api';
$string['naas_settings_feedback'] = 'Allow learners to rate Nuggets';
$string['naas_settings_feedback_help'] = 'When enabled, learners can rate a Nugget after they finish it.';
$string['naas_settings_filter'] = 'Catalogue search filter';
$string['naas_settings_filter_help'] = 'Optional NQL query applied to teacher search in the Nugget picker. Leave empty for no extra restriction. Example: type:video';
$string['naas_settings_information'] = 'Saved credentials are used for every Nugget search and launch. After you change these values, click Save changes, then Test connection.';
$string['naas_settings_learner'] = 'Learner experience';
$string['naas_settings_learner_information'] = 'Options that change what learners see inside a Nugget activity.';
$string['naas_settings_license_filter'] = 'Commercial use';
$string['naas_settings_license_filter_all'] = 'All Nuggets';
$string['naas_settings_license_filter_commercial'] = 'Commercial use only — hide non-commercial Nuggets';
$string['naas_settings_license_filter_help'] = 'Restricts the Nugget picker to the commercial-use right on the access licence granted to your institute. Already-added activities stay in courses.

All Nuggets: no extra restriction (the default).

Commercial use only: keep Nuggets that allow commercial reuse (co) and hide non-commercial (nc) ones.

Non-commercial only: keep Nuggets that forbid commercial reuse (nc) and hide commercial (co) ones.';
$string['naas_settings_license_filter_noncommercial'] = 'Non-commercial only — hide commercial-use Nuggets';
$string['naas_settings_nugbot'] = 'Show Nugbot in Nuggets';
$string['naas_settings_nugbot_help'] = 'When enabled, learners see the Nugbot assistant inside launched Nuggets. The setting is sent to NaaS as the LTI option feature.nugbot. Leave unchecked to hide Nugbot (the default).';
$string['naas_settings_password'] = 'API password';
$string['naas_settings_password_help'] = 'Password for the NaaS API. There is no default in the plugin: enter it here or set the NAAS_API_PASSWORD environment variable. Save the form before using Test connection.';
$string['naas_settings_privacy'] = 'Privacy';
$string['naas_settings_privacy_information'] = 'When both options below are enabled, the learner\'s name and email are sent to NaaS so sessions can be personalised. If either option is off, learning traces stay anonymous on NaaS (anonymous mode). The data is stored on the NaaS server and is not shared with third parties.';
$string['naas_settings_privacy_learner_mail'] = 'Send learner email to NaaS';
$string['naas_settings_privacy_learner_mail_help'] = 'Send the learner\'s Moodle email when they open a Nugget. NaaS can use it to link the same person across Nuggets.';
$string['naas_settings_privacy_learner_name'] = 'Send learner name to NaaS';
$string['naas_settings_privacy_learner_name_help'] = 'Send the learner\'s Moodle full name when they open a Nugget, so NaaS can personalise the experience.';
$string['naas_settings_require_outcome_oauth'] = 'Require OAuth signature on grade passback';
$string['naas_settings_require_outcome_oauth_help'] = 'When enabled, Moodle rejects LTI Basic Outcomes (replaceResult) that are not signed with the nugget\'s LTI key/secret (OAuth body-hash).

Leave this **off** until the NaaS API signs grade posts (LisOutcome). Turning it on too early blocks all grades.

After NaaS is updated and verified in staging, enable this setting. It is temporary: once every deployment requires signed outcomes, the option can be removed and verification made permanent.';
$string['naas_settings_require_outcome_oauth_off'] = '**Warning:** grades sent back by NaaS are not authenticated, so a learner could submit their own grade. Enable "Require OAuth signature on grade passback" as soon as NaaS signs grade submissions. This page will say when a signed submission has been received.';
$string['naas_settings_require_outcome_oauth_ready'] = '**Action needed:** NaaS signed a grade submission on {$a}, and the signature was valid. You can now enable "Require OAuth signature on grade passback" so that learners cannot submit their own grades.';
$string['naas_settings_ssl_noverify'] = 'Disable SSL certificate verification (development only)';
$string['naas_settings_ssl_noverify_active'] = '**Warning:** SSL certificate verification is currently disabled. Turn this off before using the site in production.';
$string['naas_settings_ssl_noverify_help'] = 'Only enable this in development environments with self-signed certificates. Must stay disabled in production.';
$string['naas_settings_structure_id'] = 'Institute ID';
$string['naas_settings_structure_id_help'] = 'NaaS institute identifier (sometimes called structure ID). The default is the public Open Education institute.';
$string['naas_settings_timeout'] = 'Request timeout (seconds)';
$string['naas_settings_timeout_help'] = 'Seconds to wait before aborting a NaaS API call. Use 0 to wait indefinitely (not recommended in production).';
$string['naas_settings_username'] = 'API username';
$string['naas_settings_username_help'] = 'API username. The default is the public Open Education (OER) account, which can search openly licensed Nuggets. Replace it with your institute username for a private catalogue.';
$string['name_display'] = 'Nugget display title';
$string['name_help'] = 'Name of the nugget that will appear in the course section';
$string['next_unit'] = 'Next Resource';
$string['no_nugget'] = 'No nugget found';
$string['nomoreattempts'] = 'No more attempts are allowed';
$string['nonewmodules'] = 'This course has no NaaS module included.';
$string['nugget'] = 'Nugget';
$string['nugget_search'] = 'Search Nuggets';
$string['nugget_search_collecting'] = 'We are collecting nugget data, please wait.';
$string['nugget_search_here'] = 'To get started, enter a keyword';
$string['nugget_search_no_result'] = 'The search returned no results, please use another keyword.';
$string['open_access'] = 'Open Access';
$string['partner_with'] = 'With';
$string['pl'] = 'Polish';
$string['pluginadministration'] = 'Nugget administration';
$string['pluginname'] = 'Nugget';
$string['prerequisites'] = 'Prerequisites';
$string['preview_button'] = 'Preview';
$string['preview_title'] = 'Preview: {$a}';
$string['privacy:metadata:core_completion'] = 'The Nugget plugin stores when a Nugget activity as been completed.';
$string['privacy:metadata:core_grades'] = 'Once completed, the NaaS will send back to plugin a grade for the user session on the Nugget activity.';
$string['privacy:metadata:naas'] = 'The Nugget activity communicates with the NaaS platform to retrieve the Nugget, track when the Nugget is completed and send back a grade. Learning traces linked to the execution of Nuggets are collected anonymously on the NaaS platform. When the collect of user email is activated for the plugin, those traces will be associated to the email, allowing to personalize the user experience. The collected data is stored on the NaaS server and will not be shared with any third parties. In return, NaaS sends the public profile of each Nugget author (name, e-mail address, biography). The plugin keeps these profiles in the Moodle cache and shows them to course users in the Nugget details. Authors are not Moodle users, so these profiles are not part of any Moodle user\'s data.';
$string['privacy:metadata:naas:context_id'] = 'The ID of the Nugget.';
$string['privacy:metadata:naas:lis_outcome_service_url'] = 'The URL that the NaaS will use to communicate the Nugget completion and the obtained grade.';
$string['privacy:metadata:naas:lis_person_contact_email_primary'] = 'The email of the user. This information can be used to personalize the user experience by associating its different sessions on different Nuggets. The administrator can disable transmission of this information.';
$string['privacy:metadata:naas:lis_person_name_full'] = 'The firstname and lastname of the user. This information is used to improve the user experience. The administrator can disable transmission of this information.';
$string['privacy:metadata:naas:lis_result_sourcedid'] = 'The ID of the Nugget activity session.';
$string['privacy:metadata:naas:oauth_consumer_key'] = 'A NaaS partner identifier, enabling  access rights to be applied to the various Nuggets available. Nuggets published as Open Educational Resources are automatically available for any partner.';
$string['privacy:metadata:naas:oauth_timestamp'] = 'Time stamp used to establish the OAuth authentication.';
$string['privacy:metadata:naas_activity_outcome'] = 'The Nugget plugin create a session of each time a user engages in a Nugget activity. This session will allow the Nugget plugin to associate the grade provided by the Nugget without sending any personal data.';
$string['privacy:metadata:naas_activity_outcome:activity_id'] = 'The ID of the Nugget activity.';
$string['privacy:metadata:naas_activity_outcome:date_added'] = 'Creation date of the session.';
$string['privacy:metadata:naas_activity_outcome:id'] = 'The internal ID of the user session on a particular instance of a Nugget activity.';
$string['privacy:metadata:naas_activity_outcome:score'] = 'The normalised score (between 0 and 1) sent back by the NaaS for this session, if any.';
$string['privacy:metadata:naas_activity_outcome:sourced_id'] = 'The session id. This is created by the Nugget plugin. Each session has a unique ID. It will be sent to the NaaS to associate the Nugget outcomes with this session.';
$string['privacy:metadata:naas_activity_outcome:timecompleted'] = 'When the NaaS sent back the result of this session, which closes the attempt. Empty while the session has no result.';
$string['privacy:metadata:naas_activity_outcome:user_id'] = 'The ID of the user accessing the Nugget activity.';
$string['privacy:metadata:naas_xapi'] = 'The Nugget activity transmits xAPI learning traces to the NaaS platform. The data collected is stored on the NaaS server and is not shared with third parties. The administrator can allow the collection of names and e-mail addresses to personalize the user experience, or make these traces anonymous.';
$string['privacy:metadata:naas_xapi:body'] = 'Additional data related to the interaction, such as rating values or completion status.';
$string['privacy:metadata:naas_xapi:email'] = 'The user\'s email is sent with xAPI statements to identify the user across different sessions.';
$string['privacy:metadata:naas_xapi:name'] = 'The user\'s full name is sent with xAPI statements to identify the user in learning records.';
$string['privacy:metadata:naas_xapi:resource_link_id'] = 'The resource link ID that identifies the specific session.';
$string['privacy:metadata:naas_xapi:verb'] = 'The action type being recorded (e.g., \'experienced\', \'completed\', \'rated\').';
$string['privacy:metadata:naas_xapi:version_id'] = 'The version ID of the nugget content being interacted with.';
$string['privacy:metadata:naastable'] = 'The Nugget plugin creates an entry for each Nugget activity to store its configuration.';
$string['privacy:metadata:naastable:allowofflineattempts'] = 'Whether to allow the NaaS to be attempted offline in the mobile app.';
$string['privacy:metadata:naastable:attempts'] = 'The maximum number of attempts a student is allowed.';
$string['privacy:metadata:naastable:cgu_agreement'] = 'The CGU of the NaaS platform must be accepted by the Nugget activity creator.';
$string['privacy:metadata:naastable:completionattemptsexhausted'] = 'Marks the activity as complete when all available attempts have been exhausted.';
$string['privacy:metadata:naastable:completionminattempts'] = 'Requires a minimum number of attempts before the activity can be marked as complete.';
$string['privacy:metadata:naastable:completionpass'] = 'Sets the activity to complete only if the user successfully passes it.';
$string['privacy:metadata:naastable:course'] = 'The ID of the course containing the Nugget activity.';
$string['privacy:metadata:naastable:grade_method'] = 'The grading method (one of the values NAAS_GRADEHIGHEST, NAAS_ATTEMPTFIRST or NAAS_ATTEMPTLAST).';
$string['privacy:metadata:naastable:id'] = 'The ID of this Nugget configuration.';
$string['privacy:metadata:naastable:intro'] = 'The general introduction of the Nugget activity.';
$string['privacy:metadata:naastable:introformat'] = 'The format of the intro (MOODLE, HTML, MARKDOWN...).';
$string['privacy:metadata:naastable:name'] = 'The name of the activity.';
$string['privacy:metadata:naastable:nugget_id'] = 'The ID of the Nugget of this activity.';
$string['privacy:metadata:naastable:timecreated'] = 'The created date of this Nugget activity.';
$string['privacy:metadata:naastable:timemodified'] = 'The last modified date of this Nugget activity.';
$string['private'] = 'Private';
$string['producers'] = 'Producers';
$string['public'] = 'Public';
$string['publication_date'] = 'Publication date';
$string['rating_description'] = 'Your rating will be used to improve the quality of our content';
$string['rating_send'] = 'Send';
$string['rating_sent'] = 'Sent';
$string['rating_title'] = 'Rate this nugget';
$string['rating_value'] = '{$a} out of 5';
$string['references'] = 'References';
$string['remove_filter'] = 'Remove filter {$a}';
$string['retry'] = 'Retry';
$string['select_button'] = 'Select';
$string['show_more_authors'] = 'Show more';
$string['show_more_nugget_button'] = 'Show more ...';
$string['structure_id'] = 'Structure';
$string['sv'] = 'Swedish';
$string['tags'] = 'Tags';
$string['task_refresh_catalogue'] = 'Refresh the Nugget catalogue cache';
$string['test_connection'] = 'Test connection';
$string['test_connection_information'] = 'Tests the saved API URL, username, password, and institute ID — not values you have typed but not saved. Click Save changes first, then test. A successful test also stores a catalogue cache (Nuggets, producers, aggregations) used to paint the search page instantly.';
$string['tutorial'] = 'Tutorial';
$string['type'] = 'Type';
$string['unlimited'] = 'Unlimited';
$string['view_as_cards'] = 'Cards';
$string['view_as_list'] = 'List';
