jQuery(document).ready(function($){
    $('.wpa-email-color').wpColorPicker();
});
jQuery(document).ready(function($){
    $('.wpa-link-color').wpColorPicker();
});
jQuery(document).ready(function($){
    $('.wpa-hover-color').wpColorPicker();
});

jQuery(document).ready( function($) {

      jQuery('input#company_logo_media_manager').click(function(e) {

             e.preventDefault();
             var image_frame;
             if(image_frame){
                 image_frame.open();
             }

             image_frame = wp.media({
                           title: 'Select Media',
                           multiple : false,
                           library : {
                                type : 'image',
                            }
                       });

                       image_frame.on('close',function() {

                          var selection =  image_frame.state().get('selection');
                          var gallery_ids = new Array();
                          var my_index = 0;
                          selection.each(function(attachment) {
                             gallery_ids[my_index] = attachment['id'];
                             my_index++;
                          });
                          var ids = gallery_ids.join(",");
                          jQuery('input#company_logo').val(ids);
                          Refresh_Image(ids, 'company_logo');
                       });

                      image_frame.on('open',function() {
                        var selection =  image_frame.state().get('selection');
                        ids = jQuery('input#company_logo').val().split(',');
                        ids.forEach(function(id) {
                          attachment = wp.media.attachment(id);
                          attachment.fetch();
                          selection.add( attachment ? [ attachment ] : [] );
                        });

                      });

                    image_frame.open();
     });

});

jQuery(document).ready( function($) {

      jQuery('input#email_advert_media_manager').click(function(e) {

             e.preventDefault();
             var image_frame;
             if(image_frame){
                 image_frame.open();
             }

             image_frame = wp.media({
                           title: 'Select Media',
                           multiple : false,
                           library : {
                                type : 'image',
                            }
                       });

                       image_frame.on('close',function() {

                          var selection =  image_frame.state().get('selection');
                          var gallery_ids = new Array();
                          var my_index = 0;
                          selection.each(function(attachment) {
                             gallery_ids[my_index] = attachment['id'];
                             my_index++;
                          });
                          var ids = gallery_ids.join(",");
                          jQuery('input#email_advert').val(ids);
                          Refresh_Image(ids, 'email_advert');
                       });

                      image_frame.on('open',function() {
                        var selection =  image_frame.state().get('selection');
                        ids = jQuery('input#email_advert').val().split(',');
                        ids.forEach(function(id) {
                          attachment = wp.media.attachment(id);
                          attachment.fetch();
                          selection.add( attachment ? [ attachment ] : [] );
                        });

                      });

                    image_frame.open();
     });

});

function Refresh_Image(the_id, option_type){
        var data = {
            action: 'wpa_get_attachment_url',
            id: the_id,
            security: jQuery('input#' + option_type + '_media_manager').attr('data-button')
        };

        jQuery.get(ajaxurl, data, function(response) {

            if(response.success === true) {
                jQuery('#' + option_type + '_preview_image').attr('src', response.data.url);
            }
        });
}
