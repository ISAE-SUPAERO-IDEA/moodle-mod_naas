define('mod_naas/test_connection', ['jquery', 'core/ajax', 'core/str'], function($, Ajax, Str) {
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
                        action: 'test-config'
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
