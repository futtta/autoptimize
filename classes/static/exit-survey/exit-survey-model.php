<?php

$data = [
    "home" => home_url(),
    "post_url" => 'https://misc.optimizingmatters.com/ao_exit_survey/index.php'
]

?>

<div class="ao-plugin-uninstall-feedback-popup ao-feedback" id="ao_uninstall_feedback_popup" data-modal="<?php echo base64_encode(json_encode($data))?>">
    <div class="popup--header">
        <h5> Sorry to see you go, we would appreciate if you let us know why you're deactivating Autoptimize </h5>
    </div><!--/.popup--header-->
    <div class="popup--body">
        <ul class="popup--form">
            <li ao-option-id="5">
                <input type="radio" name="ao-deactivate-option" id="ao_feedback5">
                <label for="ao_feedback5">
                    I don't see a performance improvement
                </label>
            </li>
            <li ao-option-id="6">
                <input type="radio" name="ao-deactivate-option" id="ao_feedback6">
                <label for="ao_feedback6">
                    It broke my site
                </label>
            <li ao-option-id="4">
                <input type="radio" name="ao-deactivate-option" id="ao_feedback4">
                <label for="ao_feedback4">
                    I found a better solution
                </label>
            <li ao-option-id="999">
                <input type="radio" name="ao-deactivate-option" id="ao_feedback999">
                <label for="ao_feedback999">
                    Other (specify below) </label>
                <textarea width="100%" rows="2" name="comments" placeholder="What can we do better?"></textarea></li>
        </ul>
    </div><!--/.popup--body-->
    <div class="popup--footer">
        <div class="actions">
            <a href="#" class="info-disclosure-link">What info do we collect?</a>
            <div class="info-disclosure-content"><p>Below is a detailed view of all data that RapidLoad will receive if
                    you fill in this survey. Email address or IP addresses will not be sent.</p>
                <ul>
                    <li><strong>Plugin version </strong> <code
                                id="ao_plugin_version"> <?php echo AUTOPTIMIZE_PLUGIN_VERSION ?> </code></li>
                    <li><strong>Current website:</strong> <code> <?php echo trailingslashit(get_site_url()) ?> </code></li>
                    <li><strong>Uninstall reason </strong> <i> Selected reason from the above survey </i></li>
                </ul>
            </div>
            <div class="buttons">
                <input type="submit"
                       name="ao-deactivate-no"
                       id="ao-deactivate-no"
                       class="button"
                       value="Skip &amp; Deactivate">
                <input type="submit"
                       name="ao-deactivate-yes"
                       id="ao-deactivate-yes"
                       class="button button-primary"
                       value="Submit &amp; Deactivate"
                       data-after-text="Submit &amp; Deactivate"
                       disabled="1"></div>

        </div><!--/.actions-->
    </div><!--/.popup--footer-->
</div>
