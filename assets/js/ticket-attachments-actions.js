jQuery(document).ready(function () {
    jQuery('.ticket_attachment_download, .ticket_attachment_delete').click(function (ev) {
        ev.preventDefault();
        if (jQuery(this).hasClass('ticket_attachment_download')) {
            var action = "orbisius_support_tickets_action_download_file";
            var nonce = OST_AA.download_nonce;
        } else {
            var action = "orbisius_support_tickets_action_delete_file";
            var nonce = OST_AA.delete_nonce;
        }
        var id = jQuery(this).attr('data-id');
        jQuery.ajax({
            method: "POST",
            url: OST_AA.ajaxurl,
            data: {
                action: action,
                _ajax_nonce: nonce,
                id: id
            },
            beforeSend: function () {

            }
        }).done(function (data) {
            if (data === "OK") {
                location.reload();
            } else {
                alert(data);
            }
        }).fail(function () {

        });
    });
});

