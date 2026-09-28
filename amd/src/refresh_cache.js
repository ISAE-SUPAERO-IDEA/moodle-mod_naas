// This file is part of Moodle - http://moodle.org/
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
 * Rebuild the NaaS caches from the plugin settings page.
 *
 * @module     mod_naas/refresh_cache
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_naas/refresh_cache', ['core/ajax', 'core/str'], function(Ajax, Str) {

    /**
     * User-facing text from a Moodle ajax exception. Never returns debuginfo.
     *
     * @param {*} error
     * @return {string}
     */
    function getUserMessage(error) {
        if (!error) {
            return '';
        }
        if (typeof error === 'string') {
            return error;
        }
        if (typeof error.error === 'string' && error.error !== '') {
            return error.error;
        }
        if (typeof error.message === 'string' && error.message !== '') {
            return error.message;
        }
        return '';
    }

    /**
     * Show a status banner in the result region using textContent (no HTML).
     *
     * @param {HTMLElement} resultDiv
     * @param {string} cssClass
     * @param {string} message
     */
    function showResult(resultDiv, cssClass, message) {
        resultDiv.className = 'connection-result mt-2 ' + cssClass;
        resultDiv.textContent = message;
        resultDiv.hidden = false;
    }

    return {
        /**
         * Bind the Refresh cache button.
         */
        init: function() {
            const button = document.getElementById('refreshcache');
            const resultDiv = document.getElementById('cache-refresh-result');
            if (!button || !resultDiv || button.dataset.naasBound) {
                return;
            }
            button.dataset.naasBound = '1';

            let inFlight = false;

            button.addEventListener('click', function(e) {
                e.preventDefault();
                if (inFlight) {
                    return;
                }

                inFlight = true;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');

                const originalLabel = button.textContent;
                const testingLabel = button.getAttribute('data-testing-label');
                if (testingLabel) {
                    button.textContent = testingLabel;
                }

                resultDiv.className = 'connection-result mt-2';
                resultDiv.textContent = '';
                resultDiv.hidden = true;

                const reset = function() {
                    inFlight = false;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    button.textContent = originalLabel;
                };

                Ajax.call([{
                    methodname: 'mod_naas_refresh_cache',
                    args: {},
                }])[0]
                    .then(function() {
                        return Str.get_string('cache_refresh_success', 'naas');
                    })
                    .then(function(successString) {
                        showResult(resultDiv, 'alert alert-success', successString);
                    })
                    .catch(function(error) {
                        const detail = getUserMessage(error);
                        return Str.get_string('cache_refresh_failed', 'naas')
                            .then(function(failedMessage) {
                                showResult(resultDiv, 'alert alert-danger', detail || failedMessage);
                            })
                            .catch(function() {
                                showResult(resultDiv, 'alert alert-danger', detail || 'Failed!');
                            });
                    })
                    .then(reset);
            });
        }
    };
});
