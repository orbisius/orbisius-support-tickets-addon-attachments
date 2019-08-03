jQuery(document).ready(function () {
    /*
     * Delete link
     */
    jQuery('.ticket_attachment_delete').click(function (ev) {
        ev.preventDefault();
        var link = jQuery(this);
        if (confirm('You are going to remove the attachment file: ' + link.siblings('.ticket_attachment_download').text())) {
            var action = "orbisius_support_tickets_action_delete_file";
            var nonce = OST_AA.delete_nonce;
            var id = link.attr('data-id');
            jQuery.ajax({
                method: "POST",
                url: OST_AA.ajaxurl,
                data: {
                    action: action,
                    _ajax_nonce: nonce,
                    id: id
                },
                beforeSend: function () {
                    link.attr('disabled', "disabled");
                }
            }).done(function (response) {
                alert(response.message);
                if (response.status) {
                    link.parent('li').fadeOut('fast').remove();
                } else {
                    link.removeAttr('disabled');
                }
            }).fail(function () {

            });
        }
    });
    /*
     * New attachement form
     */
    jQuery('#orbisius_support_tickets_attachments_form').submit(function (ev) {
        ev.preventDefault();
        var form = jQuery(this);
        var data = new FormData(form[0]);
        data.append("action", "orbisius_support_tickets_action_new_file");
        data.append("ticket_id", form.attr('data-id'));
        jQuery.ajax({
            method: "POST",
            url: OST_AA.ajaxurl,
            data: data,
            cache: false,
            processData: false,
            contentType: false,
            beforeSend: function () {

            }
        }).done(function (data) {
            location.reload();
        }).fail(function () {

        });
    });
});

