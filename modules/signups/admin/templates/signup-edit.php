<?php
/**
 * Signup Edit/Create Template
 *
 * Form for creating or editing event signups.
 *
 * Available variables:
 * - $signup (object|null) - Signup object if editing
 * - $is_new (bool) - Whether creating new signup
 * - $pco_events (array) - PCO calendar events
 * - $updated (bool) - Whether form was just saved
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php echo $is_new ? __('Add New Signup', 'mypco-online') : __('Edit Signup', 'mypco-online'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=mypco-signups')); ?>" class="page-title-action">
        ← <?php _e('Back to List', 'mypco-online'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ($updated): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Signup saved successfully!', 'mypco-online'); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" style="max-width: 800px;">
        <?php wp_nonce_field('save_signup'); ?>
        <?php if (!$is_new): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($signup->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="pco_event"><?php _e('Select PCO Event', 'mypco-online'); ?> *</label></th>
                <td>
                    <select name="pco_event" id="pco_event" style="width: 400px;" onchange="populateEventData(this)">
                        <option value="">— <?php _e('Select Event', 'mypco-online'); ?> —</option>
                        <?php if (!isset($pco_events['error'])): ?>
                            <?php foreach ($pco_events as $event):
                                $selected = ($signup && $signup->event_id === $event['instance_id']) ? 'selected' : '';
                                $event_date = date('M j, Y g:i A', strtotime($event['starts_at']));
                                ?>
                                <option value="<?php echo esc_attr(json_encode($event)); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($event['name'] . ' - ' . $event_date); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="description"><?php _e('Select an event from Planning Center Calendar', 'mypco-online'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="event_name"><?php _e('Event Name', 'mypco-online'); ?> *</label></th>
                <td>
                    <input type="text" name="event_name" id="event_name" style="width: 400px;" 
                           value="<?php echo esc_attr($signup->event_name ?? ''); ?>" required />
                    <input type="hidden" name="event_id" id="event_id" 
                           value="<?php echo esc_attr($signup->event_id ?? ''); ?>" />
                </td>
            </tr>

            <tr>
                <th><label for="event_date"><?php _e('Event Date', 'mypco-online'); ?> *</label></th>
                <td>
                    <input type="datetime-local" name="event_date" id="event_date" style="width: 300px;" 
                           value="<?php echo esc_attr($signup && $signup->event_date ? date('Y-m-d\TH:i', strtotime($signup->event_date)) : ''); ?>" 
                           required />
                </td>
            </tr>

            <tr>
                <th><label for="google_form_url"><?php _e('Google Form URL', 'mypco-online'); ?></label></th>
                <td>
                    <input type="url" name="google_form_url" id="google_form_url" style="width: 500px;" 
                           value="<?php echo esc_attr($signup->google_form_url ?? ''); ?>" 
                           placeholder="https://docs.google.com/forms/..." />
                    <input type="hidden" name="google_form_id" id="google_form_id" 
                           value="<?php echo esc_attr($signup->google_form_id ?? ''); ?>" />
                    <p class="description"><?php _e('Paste the full URL of your Google Form', 'mypco-online'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="max_attendees"><?php _e('Maximum Attendees', 'mypco-online'); ?></label></th>
                <td>
                    <input type="number" name="max_attendees" id="max_attendees" class="regular-text" 
                           value="<?php echo esc_attr($signup->max_attendees ?? 0); ?>" min="0" />
                    <p class="description"><?php _e('Leave 0 for unlimited capacity', 'mypco-online'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="payment_required"><?php _e('Payment', 'mypco-online'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="payment_required" id="payment_required" value="1" 
                               <?php checked($signup->payment_required ?? 0, 1); ?> 
                               onchange="togglePaymentFields(this)" />
                        <?php _e('Require payment', 'mypco-online'); ?>
                    </label>

                    <div id="payment_fields" style="margin-top: 15px; <?php echo ($signup->payment_required ?? 0) ? '' : 'display: none;'; ?>">
                        <div style="margin-bottom: 15px;">
                            <label for="payment_amount" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php _e('Payment Amount', 'mypco-online'); ?>
                            </label>
                            <input type="number" name="payment_amount" id="payment_amount" class="regular-text" 
                                   value="<?php echo esc_attr($signup->payment_amount ?? 0); ?>" 
                                   min="0" step="0.01" />
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label>
                                <input type="checkbox" name="allow_partial_payment" id="allow_partial_payment" value="1" 
                                       <?php checked($signup->allow_partial_payment ?? 0, 1); ?> 
                                       onchange="togglePartialPayment(this)" />
                                <?php _e('Allow partial payment (deposit)', 'mypco-online'); ?>
                            </label>

                            <div id="partial_payment_fields" style="margin-top: 10px; margin-left: 25px; <?php echo ($signup->allow_partial_payment ?? 0) ? '' : 'display: none;'; ?>">
                                <label for="minimum_payment" style="display: block; margin-bottom: 5px;">
                                    <?php _e('Minimum Payment Required', 'mypco-online'); ?>
                                </label>
                                <input type="number" name="minimum_payment" id="minimum_payment" class="regular-text" 
                                       value="<?php echo esc_attr($signup->minimum_payment ?? 0); ?>" 
                                       min="0" step="0.01" />
                                <p class="description"><?php _e('The minimum amount that must be paid now (e.g., deposit)', 'mypco-online'); ?></p>
                            </div>
                        </div>

                        <div>
                            <label for="payment_description" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <?php _e('Payment Description', 'mypco-online'); ?>
                            </label>
                            <textarea name="payment_description" id="payment_description" rows="3" class="large-text" style="width: 500px;"><?php echo esc_textarea($signup->payment_description ?? ''); ?></textarea>
                            <p class="description"><?php _e('This will appear on the payment form and email', 'mypco-online'); ?></p>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th><label for="is_active"><?php _e('Status', 'mypco-online'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               <?php checked($signup->is_active ?? 1, 1); ?> />
                        <?php _e('Active (accepting registrations)', 'mypco-online'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="save_signup" class="button button-primary button-large" 
                   value="<?php echo $is_new ? __('Create Signup', 'mypco-online') : __('Update Signup', 'mypco-online'); ?>" />
        </p>
    </form>

    <script>
        function populateEventData(select) {
            if (!select.value) return;

            const event = JSON.parse(select.value);
            document.getElementById('event_id').value = event.instance_id;
            document.getElementById('event_name').value = event.name;

            // Convert ISO date to datetime-local format
            const date = new Date(event.starts_at);
            const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
            document.getElementById('event_date').value = localDate.toISOString().slice(0, 16);
        }

        function togglePaymentFields(checkbox) {
            document.getElementById('payment_fields').style.display = checkbox.checked ? '' : 'none';
        }

        function togglePartialPayment(checkbox) {
            document.getElementById('partial_payment_fields').style.display = checkbox.checked ? '' : 'none';
        }

        // Auto-extract form ID from URL
        document.getElementById('google_form_url').addEventListener('blur', function() {
            const url = this.value;
            const match = url.match(/\/d\/(?:e\/)?([a-zA-Z0-9_-]+)/);
            
            if (match && match[1]) {
                document.getElementById('google_form_id').value = match[1];
            }
        });
    </script>
</div>
