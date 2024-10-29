<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );

global $wc_arcavis_shop;
$arcavis_settings = $this->settingsRepo->settings;
$settings_saved = false;
$settings_changed = false;
if(isset($_POST["save_settings"])) {
	$settings_saved = true;
	$settings_changed = (rtrim($_POST["arcavis_link"],'/') != $arcavis_settings->arcavis_link || $_POST["arcavis_username"] != $arcavis_settings->arcavis_username);
}


$initialSyncDone = true;
if('' == FmLastSyncRepository::getLastSync( 'articles' ))
  $initialSyncDone = false;
if($this->error)
  $settings_saved = false;

$progress = 1;
if($settings_saved && !$initialSyncDone)
  $progress = 2;
?>
<script type="text/javascript">
  var website_url = '<?php echo site_url(); ?>';
  var arcavis_settings_changed = <?php echo ($settings_changed) ? 'true' : 'false'; ?>;
</script>

<div id="arcavis_preloader">
  <div>
    <h3><?php _e('Data are syncing. This could take some minutes...', FM_WC_AS_TEXTDOMAIN); ?></h3>
    <h4><?php _e('Do not reload page', FM_WC_AS_TEXTDOMAIN); ?></h4>
    <div class="progress">
      <div class="indeterminate syncprogress"></div>
    </div>
    <p id="syncprogress-text"></p>
  </div>	
</div>

<div class="wrap arcavis">
  <?php if($initialSyncDone){ ?>
  <h4>arcavis > <?php _e('Settings', FM_WC_AS_TEXTDOMAIN); ?></h4>
  <div class="progress hidden">
    <div class="indeterminate progressbar"></div>
  </div> 
  <?php } ?>
  <?php 
      if($settings_saved) {
  ?>
    <div class="notice notice-success is-dismissible">
      <p><?php _e('Settings saved!', FM_WC_AS_TEXTDOMAIN); ?></p>
    </div>
  <?php
  }
  ?>
  <?php 
      if($this->error) {
  ?>
    <div class="notice notice-error">
      <p><?php echo $this->message; ?></p>
    </div>
  <?php
  }
  ?>
  <?php if(!$initialSyncDone){ ?>
    <div class="row">
      <h4>Einrichtung der Arcavis-Synchronisation: Schritt <span id="step"><?php echo $progress; ?></span> von 3</h4>
      <div class="progress">
        <div class="determinate progressbar" style="width: <?php echo $progress == 1 ? '33': '66'; ?>%"></div>
      </div>  
    </div>
  <?php } ?>
  <?php if($progress == 1){ ?>
  <form method="post" action="" class="container">
    <div class="row">
      <ul class="tabs swipeable">
        <li class="tab"><a class="active" href="#baseOptions">Grundeinstellungen</a></li>
        <li class="tab"><a href="#extendedOptions">Erweitert</a></li>
      </ul>
    </div>
    <div id="baseOptions" class="row">
      <div class="col s12 col s12 dotted noborder z-depth-4 relative" style="padding-top: 2.2em !important; margin-top: 4em; margin-bottom: 1.4em;">
        <img class="title-tooltip" src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/bubble-api.png'; ?>" alt="Arcavis API">
        <div class="row">
          <div class="col s3">
            <label for="arcavis_link"><?php _e('Arcavis Installations-URL', FM_WC_AS_TEXTDOMAIN); ?></label>
          </div>
          <div class="col s9">
            <input type="url" id="arcavis_link" name="arcavis_link" required="required" placeholder='https://test.arcavis.ch' value="<?php echo isset($arcavis_settings->arcavis_link) ? $arcavis_settings->arcavis_link : ''; ?>" class="regular-text" />
          </div>
        </div>
        <div class="row">
          <div class="col s3">
            <label for="arcavis_username"><?php _e('Username', FM_WC_AS_TEXTDOMAIN); ?></label>
          </div>
          <div class="col s9">
            <input type="text" id="arcavis_username" name="arcavis_username" required="required" value="<?php echo isset($arcavis_settings->arcavis_username) ? $arcavis_settings->arcavis_username : ''; ?>" class="regular-text" />
          </div>
        </div>
        <div class="row">
          <div class="col s3">
            <label for="arcavis_password"><?php _e('Password', FM_WC_AS_TEXTDOMAIN); ?></label>
          </div>
          <div class="col s9">  
            <input type="password" id="arcavis_password" name="arcavis_password" required="required" value="<?php echo isset($arcavis_settings->arcavis_password) ? $arcavis_settings->arcavis_password : ''; ?>" class="regular-text w-95" />
            <a class="waves-effect waves-light action-showpassword w-5 tooltipped" data-position="bottom" data-tooltip="Passwort anzeigen/verstecken" data-target="arcavis_password"><span class="dashicons dashicons-visibility"></span></a>
          </div>
        </div>
      </div>
      <div class="col s12">
        <div class="row">
          <div class="col s3">
            <label for="arcavis_sync_interval"><?php _e('Synchronisation intervall', FM_WC_AS_TEXTDOMAIN); ?></label>
          </div>
          <div class="col s9">  
            <input type="number" id="arcavis_sync_interval" name="arcavis_sync_interval" min="1" max="360" required="required" value="<?php echo ($arcavis_settings->arcavis_sync_interval == '' && isset($arcavis_settings->arcavis_sync_interval)) ? 60 : $arcavis_settings->arcavis_sync_interval; ?>" class="regular-text w-95" />
            <div class="w-5">min</div>
          </div>
        </div>
        <div class="row">
          <div class="col s3">
            <label for="arcavis_filter_webshop"><?php _e('Filter Active (Tag: WebShop)', FM_WC_AS_TEXTDOMAIN); ?></label>
          </div>
          <div class="col s9">  
          <div class="switch"><label>
            <input type="checkbox" id="arcavis_filter_webshop" name="arcavis_filter_webshop" <?php echo ($arcavis_settings->arcavis_filter_by_tag == 'WebShop') ? 'checked="checked"' : ''; ?>" />
            <span class="lever"></span>
              </label>
            </div>
            <br />
            Ist diese Konfiguration aktiv, werden von der Synchronisation ausschliesslich Produkte übernommen, die den Tag "WebShop" zugewiesen haben.
          </div>
        </div>
      </div>
    </div>
    <div id="extendedOptions" class="row">
        <div class="row">
          <div class="col s3">
            <label for="arcavis_doNotSyncDescription">Produkt-Beschreibungen nicht synchronisieren</label>
          </div>
          <div class="col s9">  
          <div class="switch"><label>
            <input type="checkbox" id="arcavis_doNotSyncDescription" name="arcavis_doNotSyncDescription" <?php echo ($arcavis_settings->arcavis_doNotSyncDescription) ? 'checked="checked"' : ''; ?>" />
            <span class="lever"></span>
              </label>
            </div>
            <br />
            Ist diese Konfiguration aktiv, kann die Produktbeschreibung/Content manuell im WordPress bearbeitet werden. Eine erstmalige Synchronisation wird trotzdem durchgeführt.
          </div>
        </div>
        <div class="row">
          <div class="col s3">
            <label for="arcavis_doNotSyncImages">Bilder nicht synchronisieren</label>
          </div>
          <div class="col s9">  
          <div class="switch"><label>
            <input type="checkbox" id="arcavis_doNotSyncImages" name="arcavis_doNotSyncImages" <?php echo ($arcavis_settings->arcavis_doNotSyncImages) ? 'checked="checked"' : ''; ?>" />
            <span class="lever"></span>
              </label>
            </div>
            <br />
            Ist diese Konfiguration aktiv, können Bilder manuell im WordPress bearbeitet werden. Eine erstmalige Synchronisation wird trotzdem durchgeführt.
          </div>
        </div>
        <div class="row">
          <div class="col s3">
            <label for="arcavis_addtionalConfigJson">Zusätzliche Anweisungen (JSON)</label>
          </div>
          <div class="col s9">  
            <textarea id="arcavis_addtionalConfigJson" name="arcavis_addtionalConfigJson"><?php echo esc_js( $arcavis_settings->arcavis_addtionalConfigJson ); ?></textarea>
            <a href="#" class="extend" data-target="json-extend">Standard <span class="dashicons dashicons-arrow-down-alt2"></span> </a>
            <div class="extend-content json-extend">
            <?php echo $this->settingsRepo->getAdditionalSettingsDefaultJson(); ?>
            </div>
          </div>
        </div>
    </div>
    <div class="row">
      <div class="submit" style="margin-top:20px">
        <input name="save_settings" id="submit" class="waves-effect waves-light btn changeProgressbar" value="<?php echo $initialSyncDone ? __('Speichern', FM_WC_AS_TEXTDOMAIN): __('Weiter', FM_WC_AS_TEXTDOMAIN); ?>" type="submit">
      </div>
      <div style="float:right; display: none;">	
       <input type="button" name="" value="<?php _e('Clear all and resync', FM_WC_AS_TEXTDOMAIN); ?>" class="button arcavis_danger" id="start_sync" >		  
      </div>
    </div>
  </form>
  <?php }
       elseif($progress == 2){ ?>
  <form method="post" action="" class="container">
    <div class="row">
      <div class="col s12">
        <h4 id="start-first-sync-heading">Erstsynchronisation starten</h4>
        <p class="first_sync_text">
          Dieser Vorgang wird sämtliche Produkte inkl. Bilder, Kategorien & Tags aus Arcavis ins WooCommerce importieren. <br />
        </p>
        <p class="crontext" style="display:none;">
            Die Erstsynchronisation konnte erfolgreich durchgeführt werden. Bitte stellen Sie sicher, dass der WordPress-Cronjob korrekt konfiguriert ist. Dieser ist zur weiteren erfolgreichen Synchronisation notwendig und sollte alle 5-10 Minuten aufgerufen werden.<br />
            <br />
            Cronjob-URL:
            <span style="display: block; padding: 15px;font-size: 1.3em;background: #f7f7f7;border-radius: 3px;box-shadow: 1px 1px 2px #dbd9d961;"><?php echo site_url(); ?>/wp-cron.php</span>
            <br />
            Der Cronjob sollte manuell vom Server aufgerufen werden und in WordPress deaktiviert werden. Fügen Sie dazu folgende Zeile in die wp-config.php ein:<br />
          <span style="display: block; padding: 15px;font-size: 1.3em;background: #f7f7f7;border-radius: 3px;box-shadow: 1px 1px 2px #dbd9d961;">define('DISABLE_WP_CRON', true);</span>
        </p>
      </div>
      <div class="form-row col s12 dotted noborder z-depth-4 relative" style="padding-top: 2.2em !important; margin-top: 4em; margin-bottom: 1.4em;">
        <img class="title-tooltip" src="<?php echo plugin_dir_url( dirname( __FILE__, 1 ) ) . 'assets/images/bubble-exclamation.png'; ?>" alt="!" style="left: 7%;">
        <div class="row">
          <div class="col s3">
            <label for="arcavis_delete_all_woocommerce_products">Bisherige WooCommerce-Produkte löschen</label>
          </div>
          <div class="col s9">
            <div class="switch"><label>
            <input type="checkbox" id="arcavis_delete_all_woocommerce_products" name="arcavis_delete_all_woocommerce_products" />
            <span class="lever"></span>
              </label>
            </div>
            <br />
            Um die WooCommerce-Produkte ausschliesslich von Arcavis zu verwalten, müssen sämtliche bisherige Produkte gelöscht werden.<br />
            Sie können die bisherigen Produkte auch weiterhin im WooComemrce lassen, wir empfehlen dies jedoch nicht.<br />
            Achtung: Dies kann nicht rückgängig gemacht werden.
          </div>
        </div>
      </div>
      <div class="form-row col s12">
        <div class="row">
          <div class="col s3">
            <label for="itemsPerRun">Produkte pro Abfrage</label>
          </div>
          <div class="col s9">
            <input type="number" id="itemsPerRun" name="itemsPerRun" value="50" />
            <br />
            Bestimmt, wie viele Produkte bei jeder Anfrage an die API geladen werden sollen. Bei Hostings mit limitierter Leistung empfehlen wir, dies auf 10-30 zu setzen, um eine korrekte Synchronisation sicherzustellen.
          </div>
        </div>
        <div class="row">
          <div class="col s3">
            <label for="arcavis_ignore_first_sync">Erstsynchronisierung manuell durchführen</label>
          </div>
          <div class="col s9">
            <div class="switch"><label>
            <input type="checkbox" id="arcavis_ignore_first_sync" name="arcavis_ignore_first_sync" />
            <span class="lever"></span>
              </label>
            </div>
            <br />
            Sie können die Erstsynchronisierung auch überspringen und manuell durchführen. Nicht empfohlen, kann jedoch bei Leistungslimitieren Hostingsystemen abhilfe schaffen.
          </div>
        </div>
      </div>
      <div class="submit" style="margin-top:20px">
        <input name="start_first_sync" id="start_first_sync" class="waves-effect waves-light btn changeProgressbar" value="<?php _e('Erstsynchronisation starten', FM_WC_AS_TEXTDOMAIN); ?>" type="button">
      </div>
    </div>
  </form>
  <?php } ?>
</div>