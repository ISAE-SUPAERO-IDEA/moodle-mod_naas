define('mod_naas/test_connection', ['jquery', 'core/ajax'], function($) {
    return {
        init: function() {
            $('#testconnection').on('click', function(e) {
                e.preventDefault();
                const resultDiv = $('#connection-result');
                resultDiv.hide().removeClass();

                $.ajax({
                    url: M.cfg.wwwroot + '/mod/naas/proxy.php',
                    type: 'GET',
                    data: {
                        path: '/nuggets/search?is_default_version=true&page_size=6&fulltext='
                    },
                    success: function(response) {
                        const parsedResponse = JSON.parse(response);

                        if (parsedResponse.success) {
                            resultDiv.addClass("alert alert-success").text('Success !');
                        } else {
                            resultDiv.addClass("alert alert-danger").html(
                                '<p>Failed !</p><code>' + response + '</code>'
                            );
                        }
                        resultDiv.show();
                    },
                    error: function(xhr, status, error) {
                        resultDiv.addClass("alert alert-danger").html(
                            `<p>Failed !</p><p>${error}</p>`
                        );
                        resultDiv.show();
                    }
                });
            });
        }
    };
});
