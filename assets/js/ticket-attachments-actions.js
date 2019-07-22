jQuery(document).ready(function () {
    jQuery('.ticket_attachment_delete').click(function (ev) {
        ev.preventDefault();
        var link = jQuery(this);
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
                link.attr('disabled', true);
            }
        }).done(function (data) {
            if (data === "OK") {
                link.parent('li').fadeOut('fast').remove();
            } else {
                alert(data);                
                link.attr('disabled', false);
            }
        }).fail(function () {

        });
    });
});

