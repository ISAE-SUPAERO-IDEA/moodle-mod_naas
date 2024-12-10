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

// Clés générales.
$string['modulename'] = 'Nugget';
$string['naas:addinstance'] = 'Add a new NaaS module';
$string['modulename_help'] = '<p>The Moodle Nugget Plugin enables a teacher to integrate a micro-content from the NaaS Server. The teacher can allow the Nugget exercice to be attempted multiple times. A time limit may be set. Each attempt is marked automatically and the grade is recorded in the gradebook.<br>Nuggets may be used:<ul><li>As course exams</li><li>As mini tests for reading assignments or at the end of a topic</li><li>As exam practice using questions from past exams</li><li>For self-assessment</li></ul></p>';
$string['modulename_link'] = 'mod/naas/view';
$string['modulenameplural'] = 'Nuggets';
$string['pluginadministration'] = '';
$string['pluginname'] = 'Nugget';
$string['nugget'] = 'Nugget';

// Capabilities.
$string['mod_naas:view'] = 'Can view the Nugget';
$string['mod_naas:addinstance'] = 'Can add a new Nugget to the course';

// NaaS settings.
$string['completiondetail:passorexhaust'] = 'Complete on pass or exhaustion';
$string['completiondetail:passgrade'] = 'Complete on passing grade';
$string['naas_settings'] = 'NaaS settings';
$string['naas_settings_css'] = 'NaaS CSS';
$string['naas_settings_css_help'] = 'Extra CSS to be applied to NaaS ressources)';
$string['naas_settings_endpoint'] = 'NaaS API endpoint';
$string['naas_settings_endpoint_help'] = 'Enter the NaaS API endpoint';
$string['naas_settings_feedback'] = 'NaaS feedback';
$string['naas_settings_feedback_help'] = 'Enable the option to give learners the ability to provide feedback on Nugget activities.';
$string['naas_settings_filter'] = 'NaaS search filter';
$string['naas_settings_filter_help'] = 'A query to filter search results';
$string['naas_settings_information'] = 'The NaaS API documentation may provide information on how to obtain the API endpoint and credentials. You can check their documentation for more information or contact the support team of NaaS.';
$string['naas_settings_password'] = 'NaaS API password';
$string['naas_settings_password_help'] = 'Enter the NaaS API password';
$string['naas_settings_privacy'] = 'NaaS Privacy';
$string['naas_settings_privacy_information'] = 'The Moodle Nugget Plugin requires the collection and storage of personal data such as a user\'s name and email address in order to improve the user experience by improving anonymous statistical data analysis. The collected data is stored on the NaaS server and will not be shared with any third parties.';
$string['naas_settings_privacy_learner_mail'] = 'Collect nugget learners emails';
$string['naas_settings_privacy_learner_mail_help'] = 'Send the learner email when connecting the user to the NaaS server to help improve the user experience by improving anonymous statistical data analysis';
$string['naas_settings_privacy_learner_name'] = 'Collect nugget learners names';
$string['naas_settings_privacy_learner_name_help'] = 'Send the learner name when connecting the user to the NaaS to improve their experience';
$string['naas_settings_structure_id'] = 'NaaS API structure';
$string['naas_settings_structure_id_help'] = 'Enter the NaaS API target structure';
$string['naas_settings_timeout'] = 'NaaS API timeout';
$string['naas_settings_timeout_help'] = 'Number of seconds to wait before aborting a call to the NaaS API (0 for infinite)';
$string['naas_settings_username'] = 'NaaS API user';
$string['naas_settings_username_help'] = 'Enter the NaaS API user';

// Moodle Form.
$string['cgu_agreement'] = 'I have read and agree to the <a target="_blank" href="https://doc.clickup.com/2594656/p/h/2f5v0-13342/fff6a689cd78033">Terms and Conditions of the NaaS platform</a>';
$string['general'] = 'General';
$string['lastmodified'] = 'Last modified';
$string['moduleintro'] = 'Introduction to the module';
$string['name_display'] = 'Name to display';
$string['name_help'] = 'Name of the nugget that will appear in the course section';
$string['naas_unable_connect'] = 'Unable to contact the NaaS server';
$string['unlimited'] = 'Unlimited';

// Vue Form.
$string['about'] = 'About';
$string['back_to_course'] = 'Back to Course Index';
$string['click_to_replace'] = 'Replace the selected nugget';
$string['clear_filters'] = 'Clear filters';
$string['hide_authors'] = 'Hide';
$string['loading'] = 'Loading...';
$string['next_unit'] = 'Next Unit';
$string['no_nugget'] = 'No nugget found';
$string['nugget_search'] = 'Search Nuggets';
$string['nugget_search_here'] = 'To get started, enter a keyword';
$string['nugget_search_no_result'] = 'The search returned no results, please use another keyword.';
$string['preview_button'] = 'Preview';
$string['select_button'] = 'Select';
$string['show_more_authors'] = 'Show more';
$string['show_more_nugget_button'] = 'Show more ...';

// Metadata.
$string['about_author'] = 'About the author';
$string['advanced'] = 'Advanced';
$string['authors'] = 'Authors';
$string['description'] = 'Description';
$string['en'] = 'English';
$string['field_of_study'] = 'Field of study';
$string['fr'] = 'French';
$string['de'] = 'German';
$string['it'] = 'Italian';
$string['es'] = 'Spanish';
$string['intermediate'] = 'Intermediate';
$string['in_brief'] = 'In Brief';
$string['language'] = 'Language';
$string['level'] = 'Level';
$string['lesson'] = 'Lesson';
$string['pl'] = 'Polish';
$string['preview'] = 'Preview: ';
$string['producers'] = 'Producers';
$string['publication_date'] = 'Publication date';
$string['references'] = 'References';
$string['tags'] = 'Tags';
$string['tutorial'] = 'Tutorial';
$string['structure_id'] = 'Structure';
$string['type'] = 'Type';
$string['demo'] = 'Demo';
$string['sv'] = 'Swedish';
$string['beginner'] = 'Beginner';
$string['gradehighest'] = 'Highest grade';
$string['gradeaverage'] = 'Average grade';
$string['grade_method'] = 'Grading method';
$string['grade_method_help'] = '<p>When multiple attempts are allowed, the following methods are available for calculating the final nugget grade:<ul><li>Highest grade of all attempts</li><li>Average (mean) grade of all attempts</li><li>First attempt (all other attempts are ignored)</li><li>Last attempt (all other attempts are ignored)</li></ul></p>';
$string['grade_type'] = 'Type of grading';
$string["learning_outcomes"] = "Learning outcomes";
$string["prerequisites"] = "Prerequisites";





// Rating.
$string['complete_nugget'] = 'I Finished My Learning With This Nugget';
$string['learning_outcomes_desc'] = 'You have completed this nugget. The learning objectives were: ';
$string['rating_description'] = 'Your rating will be used to improve the quality of our content';
$string['rating_send'] = 'Send';
$string['rating_sent'] = 'Sent';
$string['rating_title'] = 'Rate this nugget';
$string["duration"] = "Duration";
$string['attemptfirst'] = 'First attempt';
$string['attemptlast'] = 'Last attempt';

// Activity Completion section.
$string['completionpass'] = 'Require passing grade';
$string['completionpass_desc'] = 'Student must achieve a passing grade to complete this activity';
$string['completionpass_help'] = 'If enabled, this activity is considered complete when the student receives a pass grade (as specified in the Grade section of the nugget settings) or higher.';
$string['completionminattempts'] = 'Minimum number of attempts: ';
$string['completionminattemptsdesc'] = 'Minimum number of attempts required: {$a}';
$string['completionminattemptsgroup'] = 'Require attempts';
$string['completionminattemptserror'] = 'Minimum number of attempts must be lower or equal to attempts allowed.';

// Activity Completion section error messages.
$string['gradecategoryonmodform'] = 'Grade category on mod form';
$string['gradetopass'] = 'Grade to pass';
$string['gradetopassnotset'] = 'This nugget does not yet have a grade to pass set. It may be set in the Grade section of the nugget settings.';
$string['gradetopassmustbeset'] = 'Grade to pass cannot be zero as this nugget has its completion method set to require passing grade. Please set a non-zero value.';
$string['gradingmethod'] = 'Grading method';
$string['gradingmethods'] = 'Grading methods';
