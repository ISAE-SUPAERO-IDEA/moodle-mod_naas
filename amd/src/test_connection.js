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
 * JavaScript module to test connection to NAAS API.
 *
 * @copyright  2023 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('mod_naas/test_connection', ['jquery', 'core/ajax', 'core/str'], function($, ajax, Str) {
    return {
        init: function() {
            $('#testconnection').on('click', function(e) {
                e.preventDefault();
                const resultDiv = $('#connection-result');
                resultDiv.hide().removeClass();

                // Pre-fetch strings we'll need
                const successStringPromise = Str.get_string('connection_test_success', 'naas');
                const failedStringPromise = Str.get_string('connection_test_failed', 'naas');

                ajax.call([{
                    methodname: 'mod_naas_test_config',
                    args: {},
                }])[0].done(function(response) {
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
                            const errorContainer = $('<div class="alert alert-danger"></div>');
                            const paragraph = $('<p></p>').text(failedString);
                            const codeBlock = $(
                                '<code></code>').text(`${parsedResponse.error.code} - ${parsedResponse.error.message}`
                            );

                            errorContainer.append(paragraph).append(codeBlock);
                            resultDiv.empty().append(errorContainer);


                            resultDiv.show();
                        }).fail(function() {
                            // Fallback if string loading fails
                            resultDiv.addClass("alert alert-danger").html(
                                '<p>Failed!</p><code>' + parsedResponse + '</code>'
                            );
                            resultDiv.show();
                        });
                    }
                }).fail(function(error) {
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
                });
             });
        }
    };
});
