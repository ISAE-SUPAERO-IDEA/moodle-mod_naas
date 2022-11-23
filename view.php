<?php

/**
 * NaaS module nugget view
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once('classes/NaasClient.php');

$id        = optional_param('id', 0, PARAM_INT);        // Course module ID
$u         = optional_param('u', 0, PARAM_INT);         // Naas instance id
$redirect  = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($u) {  // Two ways to specify the module
    $naas_instance = $DB->get_record('naas', array('id'=>$u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('naas', $naas->id, $naas->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
    $naas_instance = $DB->get_record('naas', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new NaasClient($config);
$nugget_detail = $naas->request('GET', "/nuggets/".$naas_instance->nugget_id);

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Check credentials
require_course_login($course, true, $cm);
require_capability('mod/naas:view', $context);

// Completion and trigger events.
naas_view($course, $cm, $context);

// Set page stuff
$PAGE->set_cm($cm, $course); // Set's up global $COURSE.
$PAGE->set_context($context);
$url = new moodle_url('/mod/naas/view.php', array('id' => $cm->id));
$PAGE->set_url($url);
$pagetitle = strip_tags($course->shortname.': '.format_string($naas_instance->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

// Print the page header.
echo $OUTPUT->header();
echo $OUTPUT->heading($naas_instance->name);


// Get authors full name
$nugget_authors = [];
foreach ($nugget_detail->payload->authors as $author) {
    $request = $naas->request('GET', "/persons/".$author);
    array_push($nugget_authors, strtoupper($request->payload->firstname." ".$request->payload->lastname));
}

// Create Detail Modal
function is_shown($val) {
    if (empty($val)) return false;
    if ($val == "") return false;
    return true;
}

echo '<a href="javascript:;" class="btn btn-primary" style="margin-bottom: 7px;" onclick=showModal()>See nugget details</a>';
echo '
    <script>
        function showModal() { document.getElementById("DetailModal").style.display = "block"; }
        function hideModal() { document.getElementById("DetailModal").style.display = "none"; }
    </script>
    <div id="DetailModal" style="display:none;">
        <transition name="modal-fade">
            <div class="nugget-modal-backdrop">
                <div class="nugget-modal">
                    <header class="nugget-modal-header">
                        <h3>Details : '.$nugget_detail->payload->name.'</h3>
                        <button
                            type="button"
                            class="btn-close"
                            onclick="hideModal()"
                        >
                            x
                        </button>
                    </header>
                    <section class="nugget-modal-body">
                        <div class="row" style="margin: 0 15px;">
                            <div style="float: left; width: 69%;">
                                ';
                                if (is_shown($nugget_detail->payload->resume)) {    // Resume
                                    echo '
                                    <div>
                                        <h3>'.get_string('resume','naas').'</h3>
                                        <p class="p-position">'.$nugget_detail->payload->resume.'</p>
                                    </div>';
                                }
                                if (is_shown($nugget_authors)) {    // About author
                                    echo '
                                    <div>
                                        <h3>'.get_string('about_author','naas').'</h3>
                                        <h5>'.join( ", ", $nugget_authors).'</h5>
                                    </div>
                                    ';
                                }
                                echo '
                            </div>
                            <div style="width: 2%;"></div>
                            ';
                            if (is_shown($nugget_detail->payload->duration) || is_shown($nugget_detail->payload->language) || is_shown($nugget_detail->payload->level) || is_shown($nugget_detail->payload->domains) || is_shown($nugget_detail->payload->tags)) {   // In brief
                                echo '
                                <div style="float: right; width: 29%;">
                                    <h3>'.get_string('in_brief','naas').'</h3>
                                    <div>
                                        <ul style="list-style: none">
                                            ';
                                            if (is_shown($nugget_detail->payload->duration)) {      // Duration
                                                echo '
                                                <li>
                                                    <i class="icon fa fa-clock-o"></i>
                                                    '.get_string('duration','naas').':
                                                    <strong id="formatage-duration">'.$nugget_detail->payload->duration.' minutes</strong>
                                                </li>
                                                ';
                                            }
                                            if (is_shown($nugget_detail->payload->language)) {      // Language
                                                echo '
                                                <li>
                                                    <i class="icon fa fa-globe"></i>
                                                    '.get_string('language','naas').':
                                                    <strong>'.get_string($nugget_detail->payload->language,'naas').'</strong>
                                                </li>
                                                ';
                                            }
                                            if (is_shown($nugget_detail->payload->level)) {         // Level
                                                echo '
                                                <li>
                                                    <i class="icon fa fa-arrow-up"></i>
                                                    '.get_string('level','naas').':
                                                    <strong>'.get_string($nugget_detail->payload->level,'naas').'</strong>
                                                </li>
                                                ';
                                            }
                                            if (is_shown($nugget_detail->payload->domains)) {       // Field of study
                                                echo '
                                                <li>
                                                    <i class="icon fa fa-home"></i>
                                                    '.get_string('field_of_study','naas').':<br />
                                                    ';
                                                    foreach ($nugget_detail->payload->domains as $domain) {
                                                        echo '
                                                        <span style="margin-left: 30px;">
                                                            <span class="badge badge-pill badge-primary">'.$domain.'</span>
                                                            <br/>
                                                        </span>
                                                        ';
                                                    }
                                                    echo '
                                                </li>
                                                ';
                                            }
                                            if (is_shown($nugget_detail->payload->tags)) {          // Tags
                                                echo '
                                                <li>
                                                    <i class="icon fa fa-tag"></i>
                                                    '.get_string('tags','naas').':<br />
                                                    ';
                                                    foreach ($nugget_detail->payload->tags as $tag) {
                                                        echo '
                                                        <span style="margin-left: 30px;">
                                                            <span class="badge badge-pill badge-primary">'.$tag.'</span>
                                                            <br/>
                                                        </span>
                                                        ';
                                                    }
                                                    echo '
                                                </li>
                                                ';
                                            }
                                            echo '
                                        </ul>
                                    </div>
                                </div>
                                ';
                            }
                        echo '
                        </div>
                        ';
                        if (is_shown($nugget_detail->payload->prerequisites)) {     // Prerequisites
                            echo '
                            <div class="row" style="margin: 0 15px;">
                                <div class="w-100">
                                  <h3>'.get_string('prerequisites','naas').'</h3>
                                  <ul class="about-list ul-position">
                                    ';
                                    foreach ($nugget_detail->payload->prerequisites as $prerequisite) {
                                        echo '
                                        <li>
                                          <p>'.$prerequisite.'</p>
                                        </li>
                                        ';
                                    }
                                    echo '
                                  </ul>
                                </div>
                            </div>
                            ';
                        }
                        if (is_shown($nugget_detail->payload->learning_outcomes)) {     // Learning outcomes
                            echo '
                            <div class="row" style="margin: 0 15px;">
                                <div class="w-100">
                                    <h3>'.get_string('learning_outcomes','naas').'</h3>
                                    <ul class="about-list ul-position">
                                    ';
                                    foreach ($nugget_detail->payload->learning_outcomes as $learning_outcome) {
                                        echo '
                                        <li>
                                            <p>'.$learning_outcome.'</p>
                                        </li>
                                        ';
                                    }
                                    echo '
                                  </ul>
                                </div>
                            </div>
                            ';
                        }
                        if (is_shown($nugget_detail->payload->references)) {      // References
                            echo '
                            <div class="row" style="margin: 0 15px;">
                                <div class="w-100">
                                    <h3>'.get_string('references','naas').'</h3>
                                    <ul class="about-list ul-position">
                                    ';
                                    foreach ($nugget_detail->payload->references as $reference) {
                                        echo '
                                        <li>
                                            <p>'.$reference.'</p>
                                        </li>
                                        ';
                                    }
                                    echo '
                                  </ul>
                                </div>
                            </div>
                            ';
                        }
                        echo '
                    </section>
                </div>
            </div>
        </transition>
    </div>
';


// Request the launch content with an iframe tag.
$iframeresizer_url = new moodle_url('/mod/naas/assets/iframeResizer.min.js');
echo "<script src='$iframeresizer_url' ></script>";
echo "<script>window.setTimeout(() => { iFrameResize({ log: true, checkOrigin:false }, '#naascontentframe') }, 100);</script>";
echo "<iframe id='naascontentframe' height='600px' width='100%' src='launch.php?id=" . $cm->id .
     "&triggerview=0\' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";

// Back to course button
echo "<a class='back-to-course btn btn-primary' href=".$CFG->wwwroot."/course/view.php?id=".$COURSE->id.">".get_string('back_to_course', 'naas')."</a>";

// Finish the page.
echo $OUTPUT->footer();
