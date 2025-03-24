<<<<<<< HEAD
define('mod_naas/test_connection', ['jquery', 'core/ajax', 'core/str'], function($, Ajax, Str) {
=======
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
 * JavaScript for testing the connection to the NAAS server.
 *
 * @module     mod_naas/test_connection
 * @copyright  2023 NAAS Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('mod_naas/test_connection', ['jquery', 'core/ajax'], function($) {
>>>>>>> f89b43c (adding copyright comments on top of the js files)
    return {
        init: function() {
            $('#testconnection').on('click', function(e) {
                e.preventDefault();
                const resultDiv = $('#connection-result');
                resultDiv.hide().removeClass();

                // Pre-fetch strings we'll need
                const successStringPromise = Str.get_string('connection_test_success', 'naas');
                const failedStringPromise = Str.get_string('connection_test_failed', 'naas');

                $.ajax({
                    url: M.cfg.wwwroot + '/mod/naas/proxy.php',
                    type: 'GET',
                    data: {
                        path: '/nuggets/search?is_default_version=true&page_size=6&fulltext='
                    },
                    success: function(response) {
                        const parsedResponse = JSON.parse(response);

                        if (parsedResponse.success) {
                            successStringPromise.done(function(successString) {
                                resultDiv.addClass("alert alert-success").text(successString);
                                resultDiv.show();
                            }).fail(function() {
                                // Fallback if string loading fails
                                resultDiv.addClass("alert alert-success").text('Success!');
                                resultDiv.show();
                            });
                        } else {
                            failedStringPromise.done(function(failedString) {
                                resultDiv.addClass("alert alert-danger").html(
                                    '<p>' + failedString + '</p><code>' + response + '</code>'
                                );
                                resultDiv.show();
                            }).fail(function() {
                                // Fallback if string loading fails
                                resultDiv.addClass("alert alert-danger").html(
                                    '<p>Failed!</p><code>' + response + '</code>'
                                );
                                resultDiv.show();
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        failedStringPromise.done(function(failedString) {
                            resultDiv.addClass("alert alert-danger").html(
                                `<p>${failedString}</p><p>${error}</p>`
                            );
                            resultDiv.show();
                        }).fail(function() {
                            // Fallback if string loading fails
                            resultDiv.addClass("alert alert-danger").html(
                                `<p>Failed!</p><p>${error}</p>`
                            );
                            resultDiv.show();
                        });
                    }
                });
            });
        }
    };
});
