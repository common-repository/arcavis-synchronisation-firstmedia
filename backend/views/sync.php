
	<?php if ( $running ) { ?>
	<div id="arcavis_preloader" style="display:block">
		<div>
			<h3><?php _e( 'Data are syncing. This could take some minutes...', FM_WC_AS_TEXTDOMAIN ); ?></h3>
			<h4><?php _e( 'Do not reload page', FM_WC_AS_TEXTDOMAIN ); ?></h4>
			<div class="progress">
        		<div class="determinate progress-value" style="width: 0%"></div>
      		</div> 
			<div class="arcavis_info">
				<p id="sync-state"><?php echo 'Page ' . $running_options->page . ' of ' . $running_options->totalPages; ?></p>
				<p id="sync-memory"><?php echo SimpleStopWatch::memoryUsageStr(); ?></p>
				<form action="" method="post">
					<input class="button button-secondary" type="submit" name="sync_cancel" value="Cancel Sync" >
				</form>
			</div>
			<script>
				var website_url = '<?php echo site_url(); ?>';

				jQuery(document).ready(function(){
					jQuery('body').addClass("arcavis_sync_active");
					window.setTimeout(syncReload(), 500);
				});

				function syncReload() {
					jQuery.ajax({
						url: website_url + "/wp-admin/admin-ajax.php",
						type: 'post',
						data: {
							action: 'arcavis_sync',
							security: '<?php echo wp_create_nonce( 'arcavis-sync-security-nonce' ); ?>'
						},
						success: function (data) {
							console.log(data);
							if (data != 'done') {
								jQuery('#sync-state').html(data.info);
								jQuery('#sync-memory').html(data.meminfo);
								jQuery('.progress-value').css('width', data.percentage + '%');
								window.setTimeout(syncReload(), 250);
							} else {
								location.reload();
							}
						},
						error: function (errorThrown) {
							console.log(errorThrown);
							// window.alert("Error:" + errorThrown)
							// jQuery('#arcavis_preloader').hide();
							window.setTimeout(syncReload(), 10050);
						}
					});
				}
			</script>
		</div>
	</div>
	<?php } ?>
<div class="wrap arcavis fm_setting_page">
	<h4 class="wp-heading-inline">arcavis  > <?php _e( 'Synchronisation', FM_WC_AS_TEXTDOMAIN ); ?></h4>
	<div class="progress hidden">
		<div class="indeterminate"></div>
	</div>
	<div class="row">
		<form action="" method="post" class="col s8">
			<div class="col s12">
				<div class="col s4">
					Produkte pro Abfrage
				</div>
				<div class="col s8">
				  <input type="number" name="itemsPerRun" value="50" />
				  <br />
            		Bestimmt, wie viele Produkte bei jeder Anfrage an die API geladen werden sollen. Bei Hostings mit limitierter Leistung empfehlen wir, dies auf 10-30 zu setzen, um eine korrekte Synchronisation sicherzustellen.
				</div>
			</div>
			<div class="col s12 relative">
			<img class="title-tooltip" src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/bubble-options.png'; ?>" alt="=?" style="left: 16%; top:-16px; transform: rotate(4deg);">
				<ul class="collapsible expandable dotted noborder z-depth-4 sync-options">
						<li class="active">
							<div class="collapsible-header"><span class="dashicons dashicons-block-default"></span> Produkte</div>
							<div class="collapsible-body">
								<ul>
									<li style="padding-top: 0; padding-bottom: 10px;">Gesamtsynchronisation</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="fullSyncAllProducts" /><span class="lever"></span>
											Alle Produkte synchronisieren</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="fullSyncAllProductCategories" /><span class="lever"></span>
											Alle Produkt-Kategorien synchronisieren</label>
										</div>
									</li>
									<li style="padding-top: 32px; padding-bottom: 10px;">Teilsynchronisation</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncChangedProducts" /><span class="lever"></span>
										Alle geänderten Produkte synchronisieren (hash)</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="writeMissingProducts" /><span class="lever"></span>
											Fehlende Produkte synchronisieren</label>
										</div>
									</li>
									<li style="padding-top: 32px; padding-bottom: 10px;">Datenbereinigung</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="deleteDuplicates" /><span class="lever"></span>
											Duplikate vor Synchronisation löschen</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="deleteOldProducts" /><span class="lever"></span>
											Alte Produkte löschen (Produkte, die nicht mehr auf der Arcavis-API sind)</label>
										</div>
									</li>
									<li style="padding-top: 12px; padding-bottom: 12px;">
										<a href="#" class="extend" data-target="product-extend">Erweitern <span class="dashicons dashicons-arrow-down-alt2"></span> </a>
									</li>
									<li class="extend-content product-extend">
										<div class="switch"><label><input type="checkbox" name="deleteAllArcavisProducts" /><span class="lever"></span>
											Alle Arcavis-Produkte vor dem Import löschen</label>
										</div>
									</li>
									<li class="extend-content product-extend">
										<div class="switch"><label><input type="checkbox" name="deleteEmptyProductCategories" /><span class="lever"></span>
											Leere Produkt-Kategorien löschen</label>
										</div>
									</li>
									<li style="padding-top: 32px; padding-bottom: 10px;" class="extend-content product-extend">Erweiterte Übernahme</li>
									<li class="extend-content product-extend">
										<div class="switch"><label><input type="checkbox" name="deleteAllWooCommerceProducts" /><span class="lever"></span>
											Alle WooCommerce-Daten vor Import löschen (Achtung: Kann nicht rückgängig gemacht werden)</label>
										</div>
									</li>
									<li class="extend-content product-extend">
										<div class="switch"><label><input type="checkbox" name="mergeWooAndArcavisProductsById" /><span class="lever"></span>
											WooCommerce- &amp; Arcavis-Produkte abgleichen nach Artikel-ID (Identische Artikel-ID werden verknüpft)</label>
										</div>
									</li>
									<li class="extend-content product-extend">
										<div class="switch"><label><input type="checkbox" name="mergeWooCommerceAndArcavisProducts" /><span class="lever"></span>
											WooCommerce- &amp; Arcavis-Produkte verknüpfen nach Titel (Artikel-ID sollte bevorzugt werden)</label>
										</div>
									</li>
								</ul>
							</div>
						</li>
						<li>
							<div class="collapsible-header"><span class="dashicons dashicons-format-image"></span> Bilder</div>
							<div class="collapsible-body">
								<ul>
									<li style="padding-top: 0; padding-bottom: 10px;">Bildsynchronisation</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncCheckImages" /><span class="lever"></span>
											Bilder prüfen</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncImages" /><span class="lever"></span>
											Bilder synchronisieren</label>
										</div>
									</li>
									<li style="padding-top: 32px; padding-bottom: 10px;">Produkte ohne Bilder</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="countProductsWithoutThumbnail" /><span class="lever"></span>
											Produkte ohne Bild zählen</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncSingleProductsWithoutThumbnail" /><span class="lever"></span>
											Einzelne Produkte ohne Bild synchronisieren</label>
										</div>
									</li>
								</ul>
							</div>
						</li>
						<li>
							<div class="collapsible-header"><span class="dashicons dashicons-chart-bar"></span> Zahlungen</div>
							<div class="collapsible-body">
								<ul>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncPaymentsSinceLastSync" /><span class="lever"></span>
											Zahlungen seit letzter Synchronisation prüfen und übermitteln</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncPayments" /><span class="lever"></span>
											Zahlungen der letzten zwei Wochen übermitteln</label>
										</div>
									</li>
									<li>
										<div class="switch"><label><input type="checkbox" name="syncAllPayments" /><span class="lever"></span>
											Alle Zahlungen übermitteln (Nicht empfohlen, kann zu Fehlern, Komplikationen und Unstimmigkeiten führen)</label>
										</div>
									</li>
								</ul>
							</div>
						</li>
					</ul>
				</div>
				<div class="col s12">
					<input class="waves-effect waves-light btn" type="submit" name="sync_check" value="Synchronisation starten" >
				</div>
		</form>
		<div class="col s4 relative">
			<h5>Letzte Synchronisation</h5>
			<div>
				<?php
					$log = $wc_arcavis_shop->logger->get_last_log( '**arcavis_sync_check_done' );
					if ( ! empty( $log ) ) {
						echo $this->get_log_msg_output( $log );
					}
				?>
			</div>
		</div>
	</div>
</div>
