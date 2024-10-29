jQuery(document).ready(function () {

	jQuery('#start_sync').off('click').on('click', startSync);
	jQuery('#start_first_sync').off('click').on('click', startInitialSync);
	function startInitialSync() {
		var deleteExistingProducts = jQuery("#arcavis_delete_all_woocommerce_products").prop("checked") ? 'yes': 'no';
		var ignoreFirstSync = jQuery("#arcavis_ignore_first_sync").prop("checked") ? 'yes': 'no';
		var itemsPerRun = jQuery("#itemsPerRun").val();
		syncLoop(deleteExistingProducts, itemsPerRun, ignoreFirstSync);
	}

	function startSync() {
		var confirmaton = confirm('Achtung! Bestehende Produkte und Bestellungen werden gelöscht. Fortfahren?');
		if (confirmaton === true) {
			syncLoop('yes', 50, 'no');
		} else {
			return;
		}
	}

	function syncLoop(deleteExistingProducts, itemsPerRun, ignoreFirstSync) {
		jQuery('#arcavis_preloader').show();
		jQuery('body').addClass("arcavis_sync_active");
		jQuery.ajax({
			url: website_url + "/wp-admin/admin-ajax.php",
			type: 'post',
			data: {
				action: 'arcavis_start_initial_sync',
				deleteExistingProducts: deleteExistingProducts,
				itemsPerRun: itemsPerRun, 
				ignoreFirstSync: ignoreFirstSync,
			},
			success: function (data) {
				if (data.continue) {
					var progress = data.currentpage / data.maxpage * 100;
					jQuery('.syncprogress').removeClass('indeterminate').addClass('determinate').css("width", progress + "%");
					jQuery('#syncprogress-text').html(data.currentpage + ' von ' + data.maxpage + ' abgeschlossen');
					syncLoop('no', data.itemsPerRun, 'no');
				} else {
					jQuery('#arcavis_preloader').hide();
					jQuery('body').removeClass("arcavis_sync_active");
					jQuery('.form-table').hide();
					jQuery('.form-row').hide();
					jQuery('.progressbar').removeClass("indeterminate").addClass("determinate").css("width", "100%");
					jQuery('#start-first-sync-heading').html('Erstsynchronisation abgeschlossen.<br /> Letzter Schritt:<br />Cronjob einrichten.');
					jQuery('.submit').hide();
					jQuery('.first_sync_text').hide();
					jQuery('.crontext').show();
					jQuery('#step').html("3");
				}

			},
			error: function (errorThrown) {
				console.log(errorThrown);
				syncLoop('no');
			}
		});
	}

	jQuery(document).ready(function(){
		if (typeof arcavis_settings_changed !== 'undefined' && arcavis_settings_changed) {
			window.setTimeout(startSync, 100);
		}
	});
});


