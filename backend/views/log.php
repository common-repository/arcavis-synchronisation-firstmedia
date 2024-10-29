<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );
global $wpdb;
?>

<div class="wrap fm_setting_page">
  <h1 class="wp-heading-inline">arcavis > <?php _e('Log', FM_WC_AS_TEXTDOMAIN); ?></h1>
  <hr class="wp-header-end">
  <br class="clear">
  <?php if (isset($logs) && count($logs) > 0) { ?>
    <table class="arcavis fm-admin-table">
      <thead>
        <th><strong><?php _e('Date', FM_WC_AS_TEXTDOMAIN); ?></strong></th>
        <th><strong><?php _e('Level', FM_WC_AS_TEXTDOMAIN); ?></strong></th>
        <th style="width:80%"><strong><?php _e('Message', FM_WC_AS_TEXTDOMAIN); ?></strong></strong></th>
      </thead>
      <tbody>
        <?php foreach ($logs as $log) { ?>
        <tr>
          <td><?php echo $log->date; ?></td>
          <td><?php echo $this->get_log_msg_level_output($log->level, true); ?></td>
          <td><?php echo $log->message; ?></td>
        </tr>
        <?php 
      } ?>
      </tbody>
    </table>
    <?php } else {
      echo '<div>'. __('No Log', FM_WC_AS_TEXTDOMAIN) .'</div>';
    }
  ?>
</div>