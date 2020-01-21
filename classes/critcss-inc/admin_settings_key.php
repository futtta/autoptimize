<?php

// NOTE: implements section 4, id 4.1 of the specs

// Render key panel
function ao_ccss_render_key($key, $status, $status_msg, $message, $color) { ?>
  <ul id="key-panel">
    <li class="itemDetail">
      <h2 class="itemTitle fleft"><?php _e('API Key', 'autoptimize'); ?>: <span style="color:<?php echo $color; ?>;"><?php echo $status_msg; ?></span></h2>
      <button type="button" class="toggle-btn">
        <?php if ($status != 'valid') { ?>
        <span class="toggle-indicator dashicons dashicons-arrow-up"></span>
        <?php } else { ?>
        <span class="toggle-indicator dashicons dashicons-arrow-up dashicons-arrow-down"></span>
        <?php } ?>
      </button>
      <?php if ($status != 'valid') { ?>
      <div class="collapsible">
      <?php } else { ?>
      <div class="collapsible hidden">
      <?php } ?>
        <?php if ($status != 'valid') { ?>
        <div style="clear:both;padding:2px 10px;border-left:solid;border-left-width:5px;border-left-color:<?php echo $color; ?>;background-color:white;">
          <p><?php echo $message; ?></p>
        </div>
        <?php } ?>
        <table id="key" class="form-table">
          <tr>
            <th scope="row">
              <?php _e('Your API Key', 'autoptimize'); ?>
            </th>
            <td>
              <textarea id="autoptimize_ccss_key" name="autoptimize_ccss_key" rows='3' style="width:100%;" placeholder="<?php _e('Please enter your criticalcss.com API key here...', 'autoptimize'); ?>"><?php echo trim($key); ?></textarea>
              <p class="notes">
                <?php _e('Enter your <a href="https://criticalcss.com/account/api-keys?aff=1" target="_blank">criticalcss.com</a> API key above. The key is revalidated every time a new job is sent to it.<br />To obtain your API key, go to <a href="https://criticalcss.com/account/api-keys?aff=1" target="_blank">criticalcss.com</a> > Account > API Keys.<br />Requests to generate a critical CSS via the API are priced at £5 per domain per month.<br /><strong>Not sure yet? With the <a href="https://criticalcss.com/faq/?aff=1#trial" target="_blank">30 day free trial</a>, you have nothing to lose!</strong>', 'autoptimize'); ?>
              </p>
            </td>
          </tr>
        </table>
      </div>
    </li>
  </ul>
  <?php
}
?>
