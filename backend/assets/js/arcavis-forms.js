jQuery(function(){
    M.AutoInit();
    jQuery('.action-showpassword').on('click', function(){
        var $target = jQuery("#" + jQuery(this).data("target"));
        if($target.attr("type") == "text")  {
            $target.attr("type", "password");
            jQuery(this).html('<span class="dashicons dashicons-visibility"></span>');
        }
        else {
            $target.attr("type", "text");
            jQuery(this).html('<span class="dashicons dashicons-hidden"></span>');
        }
    });
    jQuery('.changeProgressbar').on('click', function(){
        jQuery(this).addClass("pulse");
        jQuery(this).val("...");
        jQuery('.progressbar').removeClass("determinate").addClass("indeterminate");
        jQuery('.progressbar').parent().show();
    });
    jQuery('.waves-input-wrapper').on('click', function(){
        jQuery(this).closest('.waves-button-input').click();
    });
    jQuery('.extend').on('click', function(e){
        e.preventDefault();
        jQuery(this).find("span").toggleClass("rotate");

        jQuery('.' + jQuery(this).data('target') ).toggle();
    });
});