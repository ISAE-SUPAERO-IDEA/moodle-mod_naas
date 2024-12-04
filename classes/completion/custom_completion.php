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

namespace mod_naas\completion;

use context_module;
use core_completion\activity_custom_completion;
use grade_grade;
use grade_item;
use naas;

/**
 * Activity custom completion subclass for the naas activity.
 *
 * Class for defining mod_naas's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given naas instance and a user.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class custom_completion extends activity_custom_completion {

    /**
     * Check passing grade (or no attempts left) requirement for completion.
     *
     * @return bool True if the passing grade (or no attempts left) requirement is disabled or met.
     */
    protected function check_passing_grade_or_all_attempts(): bool {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $completionpassorattempts = $this->cm->customdata['customcompletionrules']['completionpassorattemptsexhausted'];

        if (empty($completionpassorattempts['completionpass'])) {
            return true;
        }

        // Check for passing grade.
        $item = grade_item::fetch([
            'courseid' => $this->cm->get_course()->id,
            'itemtype' => 'mod',
            'itemmodule' => 'naas',
            'iteminstance' => $this->cm->instance,
            'outcomeid' => null,
        ]);
        if ($item) {
            $grades = grade_grade::fetch_users_grades($item, [$this->userid], false);
            if (!empty($grades[$this->userid]) && $grades[$this->userid]->is_passed($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);

        switch ($rule) {
            case 'completionpassorattemptsexhausted':
                $status = static::check_passing_grade_or_all_attempts();
                break;
        }

        return empty($status) ? COMPLETION_INCOMPLETE : COMPLETION_COMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionpassorattemptsexhausted',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $completionpassorattempts = $this->cm->customdata['customcompletionrules']['completionpassorattemptsexhausted'] ?? [];
        if (!empty($completionpassorattempts['completionattemptsexhausted'])) {
            $passorallattemptslabel = get_string('completiondetail:passorexhaust', 'naas');
        } else {
            $passorallattemptslabel = get_string('completiondetail:passgrade', 'naas');
        }

        return [
            'completionpassorattemptsexhausted' => $passorallattemptslabel,
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionusegrade',
            'completionpassorattemptsexhausted',
        ];
    }
}
