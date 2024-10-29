(function ($) {
    $("#adv-importer-form").on("submit", function (e) {
        e.preventDefault();
        var t = $(this);
        // console.log("Submit trigger");

        // gether information
        var user_input = $("#adv-importer-meta-string").val();
        var nonce = $("#adv-importer-nonce").val();

        var ajax_url = $(this).attr("action");
        var func_name = "adv_importer_update_settings";
        var submit_btn = t.find("[type='submit']");
        var alert_div = t.find(".alert");
        submit_btn.attr("disabled", true);
        var old_btn_text = submit_btn.text();

        $.ajax({
            url: ajax_url,
            method: "POST",
            beforeSend: function () {
                submit_btn.text("Please wait");
            },

            data: {
                action: func_name,
                nonce: nonce,
                "adv-importer-meta-string": user_input,
            },

            success: function (res) {
                res = JSON.parse(res);

                if (res.status == true) {
                    alert_div.attr("class", "alert success").text(res.message);
                } else {
                    alert_div.attr("class", "alert error").text(res.message);
                }

                submit_btn.attr("disabled", false).text(old_btn_text);
            },
            complete: function (res) {
                submit_btn.attr("disabled", false).text(old_btn_text);
                // console.log(res);
            },
        });
    });
})(jQuery);
