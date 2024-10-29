var arcavis_product_edit = (function () {

  var init = function() {
    var overlayDiv = '<div style="position:absolute;top:0;left:0;right:0;bottom:0;background-color:white;opacity:0.4;z-index:1000;"><img style="height:50px;position:absolute;bottom:0;border:none" src="'+arcavis_logo+'"></div>';
    jQuery('#title').attr('disabled', 'true');

    // categories / Tags
    if(!arcavis_doNotSyncCategories)
      jQuery('#taxonomy-product_cat').append(overlayDiv);

    jQuery('#tagsdiv-product_tag').append(overlayDiv);

    if(!arcavis_doNotSyncImages){
      jQuery('#postimagediv').append(overlayDiv);
      jQuery('#woocommerce-product-images').append(overlayDiv);
    }

    // product data
    jQuery('#product-type').attr('disabled', 'true');
    jQuery('#_virtual').attr('disabled', 'true');
    jQuery('#_downloadable').attr('disabled', 'true');
    jQuery('#general_product_data input, #general_product_data select').attr('disabled', 'true');
    jQuery('#inventory_product_data input, #inventory_product_data select').attr('disabled', 'true');

    if(arcavis_addiotionalConfig.allowSalesPriceSet) {
      jQuery("#_regular_price").removeAttr("disabled");
      jQuery("#_sale_price").removeAttr("disabled");
    }

    if(arcavis_addiotionalConfig.syncDescriptionInShortDescription)
      jQuery('#wp-excerpt-wrap').append(overlayDiv);
    else if(!arcavis_doNotSyncDescription)
      jQuery('#wp-content-wrap').append(overlayDiv);

    // arcavis hint
    jQuery('#misc-publishing-actions').append('<div class="misc-pub-section" id="arcavis-sync">Sync by: <strong>Arcavis</strong> (ArticelID: '+ arcavis_article_id +') <a href="'+arcavis_resync_link+'">Resync</a></div>');
  };

  jQuery( document ).ready(function() {
    init();
  });

  return {
    init: init
  }
})();