<?php
/*
Plugin Name: Flame Amano Booking Form
Description: Simple booking form with dropdown for 1-14 people, date, time (5pm-8:30pm in 15 min steps), name, contact, email, details, and allergy note. Responsive: two columns on desktop, one on mobile. Posts handled via admin-post.php to avoid 404. Sends email to bookings@flameamano.co.nz and a confirmation to the customer. Date in emails is dd/mm/yyyy. Includes Cloudflare Turnstile spam protection. Admin settings page for configuring per-day hours and special events.
Version: 1.5.0
Author: Impact Websites
*/

// Exit early if WP not loaded
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Configuration: Cloudflare Turnstile Keys
 * You can define these in wp-config.php (recommended) or here
 */
if ( ! defined( 'FLAME_TURNSTILE_SITE_KEY' ) ) {
    define( 'FLAME_TURNSTILE_SITE_KEY', '' ); // Add your site key here
}
if ( ! defined( 'FLAME_TURNSTILE_SECRET_KEY' ) ) {
    define( 'FLAME_TURNSTILE_SECRET_KEY', '' ); // Add your secret key here
}

/** Booking hours configuration constants */
define( 'FLAME_HOURS_MIN',  11 ); // earliest bookable hour (24-hour)
define( 'FLAME_HOURS_MAX',  21 ); // latest bookable hour (24-hour)
define( 'FLAME_SLOT_STEP',  30 ); // time-slot interval in minutes

/**
 * Handle submission via admin-post (for logged-in and non-logged-in users)
 */
add_action( 'admin_post_nopriv_flame_amano_booking_submit', 'flame_amano_handle_submission' );
add_action( 'admin_post_flame_amano_booking_submit', 'flame_amano_handle_submission' );

// ============================================================
// Hours helpers
// ============================================================

/**
 * Returns all valid half-hour time strings from 11:00 to 21:00.
 */
function flame_amano_valid_times() {
    $times = array();
    for ( $m = FLAME_HOURS_MIN * 60; $m <= FLAME_HOURS_MAX * 60; $m += FLAME_SLOT_STEP ) {
        $times[] = sprintf( '%02d:%02d', intval( $m / 60 ), $m % 60 );
    }
    return $times;
}

/**
 * Renders an HTML <select> for a time value, using the valid time list.
 *
 * @param string $name     The field name attribute.
 * @param string $selected Currently selected HH:MM value.
 * @return string HTML string.
 */
function flame_amano_time_select( $name, $selected ) {
    $out = '<select name="' . esc_attr( $name ) . '">';
    foreach ( flame_amano_valid_times() as $time ) {
        $ts    = strtotime( $time );
        $label = $ts ? date( 'g:i a', $ts ) : $time;
        $out  .= '<option value="' . esc_attr( $time ) . '"' . selected( $selected, $time, false ) . '>' . esc_html( $label ) . '</option>';
    }
    $out .= '</select>';
    return $out;
}

/**
 * Returns the default per-day hours configuration (all days, dinner only 5pm–8:30pm).
 */
function flame_amano_get_default_hours() {
    $default = array();
    for ( $i = 0; $i <= 6; $i++ ) {
        $default[ $i ] = array(
            'enabled'        => true,
            'lunch_enabled'  => false,
            'lunch_start'    => '11:30',
            'lunch_end'      => '13:30',
            'dinner_enabled' => true,
            'dinner_start'   => '17:00',
            'dinner_end'     => '20:30',
        );
    }
    return $default;
}

/**
 * Returns the saved per-day hours configuration, merged with defaults.
 */
function flame_amano_get_hours() {
    $saved    = get_option( 'flame_amano_hours', array() );
    $defaults = flame_amano_get_default_hours();
    $hours    = array();
    for ( $i = 0; $i <= 6; $i++ ) {
        $hours[ $i ] = wp_parse_args(
            isset( $saved[ $i ] ) ? (array) $saved[ $i ] : array(),
            $defaults[ $i ]
        );
    }
    return $hours;
}

/**
 * Returns the saved special events array.
 */
function flame_amano_get_special_events() {
    $events = get_option( 'flame_amano_special_events', array() );
    return is_array( $events ) ? $events : array();
}

/**
 * Converts a single day/event config array into a grouped ranges array
 * compatible with the booking form renderer.
 *
 * @param array $config Day or event config with lunch_ and dinner_ keys.
 * @return array Array of groups, each with 'label' and 'ranges'.
 */
function flame_amano_build_groups_from_config( $config ) {
    $groups = array();
    if ( ! empty( $config['lunch_enabled'] ) ) {
        $groups[] = array(
            'label'  => 'Lunch',
            'ranges' => array( array( 'start' => $config['lunch_start'], 'end' => $config['lunch_end'] ) ),
        );
    }
    if ( ! empty( $config['dinner_enabled'] ) ) {
        $groups[] = array(
            'label'  => 'Dinner',
            'ranges' => array( array( 'start' => $config['dinner_start'], 'end' => $config['dinner_end'] ) ),
        );
    }
    return $groups;
}

/**
 * Returns the time groups for a given date string (YYYY-MM-DD).
 * Special events take priority over regular per-day hours.
 *
 * @param string $date_str YYYY-MM-DD, or empty for today.
 * @return array Groups array suitable for the booking form.
 */
function flame_amano_get_groups_for_date( $date_str ) {
    $ts = ! empty( $date_str ) ? strtotime( $date_str ) : false;

    // Check special events first.
    if ( $ts !== false ) {
        foreach ( flame_amano_get_special_events() as $event ) {
            if ( ! empty( $event['enabled'] ) && ! empty( $event['date'] ) && $event['date'] === $date_str ) {
                return flame_amano_build_groups_from_config( $event );
            }
        }
    }

    // Fall back to regular weekly hours.
    $hours = flame_amano_get_hours();
    $dow   = $ts !== false ? intval( date( 'w', $ts ) ) : intval( date( 'w' ) ); // 0=Sun..6=Sat
    $day   = isset( $hours[ $dow ] ) ? $hours[ $dow ] : array();

    if ( empty( $day['enabled'] ) ) {
        return array();
    }

    return flame_amano_build_groups_from_config( $day );
}

// ============================================================
// Admin: menu registration
// ============================================================

add_action( 'admin_menu', 'flame_amano_admin_menu' );

function flame_amano_admin_menu() {
    add_menu_page(
        'Flame Amano Booking Hours',
        'Flame Booking',
        'manage_options',
        'flame-amano-booking',
        'flame_amano_settings_page',
        'dashicons-calendar-alt',
        30
    );
}

// ============================================================
// Admin: save settings handler
// ============================================================

add_action( 'admin_post_flame_amano_save_settings', 'flame_amano_save_settings' );

function flame_amano_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    check_admin_referer( 'flame_amano_save_settings', 'flame_amano_settings_nonce' );

    $valid_times  = flame_amano_valid_times();
    $default_day  = flame_amano_get_default_hours()[0];

    // --- Per-day hours ---
    $hours_input = isset( $_POST['flame_hours'] ) ? (array) wp_unslash( $_POST['flame_hours'] ) : array();
    $hours       = array();
    for ( $i = 0; $i <= 6; $i++ ) {
        $d = isset( $hours_input[ $i ] ) ? (array) $hours_input[ $i ] : array();

        $lunch_start  = isset( $d['lunch_start'] )  && in_array( $d['lunch_start'],  $valid_times, true ) ? $d['lunch_start']  : $default_day['lunch_start'];
        $lunch_end    = isset( $d['lunch_end'] )    && in_array( $d['lunch_end'],    $valid_times, true ) ? $d['lunch_end']    : $default_day['lunch_end'];
        $dinner_start = isset( $d['dinner_start'] ) && in_array( $d['dinner_start'], $valid_times, true ) ? $d['dinner_start'] : $default_day['dinner_start'];
        $dinner_end   = isset( $d['dinner_end'] )   && in_array( $d['dinner_end'],   $valid_times, true ) ? $d['dinner_end']   : $default_day['dinner_end'];

        $hours[ $i ] = array(
            'enabled'        => ! empty( $d['enabled'] ),
            'lunch_enabled'  => ! empty( $d['lunch_enabled'] ),
            'lunch_start'    => $lunch_start,
            'lunch_end'      => $lunch_end,
            'dinner_enabled' => ! empty( $d['dinner_enabled'] ),
            'dinner_start'   => $dinner_start,
            'dinner_end'     => $dinner_end,
        );
    }
    update_option( 'flame_amano_hours', $hours );

    // --- Special events ---
    $events_input = isset( $_POST['flame_events'] ) ? (array) wp_unslash( $_POST['flame_events'] ) : array();
    $events       = array();
    foreach ( $events_input as $ev ) {
        $ev   = (array) $ev;
        $date = isset( $ev['date'] ) ? sanitize_text_field( $ev['date'] ) : '';
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            continue;
        }

        $lunch_start  = isset( $ev['lunch_start'] )  && in_array( $ev['lunch_start'],  $valid_times, true ) ? $ev['lunch_start']  : $default_day['lunch_start'];
        $lunch_end    = isset( $ev['lunch_end'] )    && in_array( $ev['lunch_end'],    $valid_times, true ) ? $ev['lunch_end']    : $default_day['lunch_end'];
        $dinner_start = isset( $ev['dinner_start'] ) && in_array( $ev['dinner_start'], $valid_times, true ) ? $ev['dinner_start'] : $default_day['dinner_start'];
        $dinner_end   = isset( $ev['dinner_end'] )   && in_array( $ev['dinner_end'],   $valid_times, true ) ? $ev['dinner_end']   : $default_day['dinner_end'];

        $events[] = array(
            'date'           => $date,
            'name'           => sanitize_text_field( isset( $ev['name'] ) ? $ev['name'] : '' ),
            'enabled'        => ! empty( $ev['enabled'] ),
            'lunch_enabled'  => ! empty( $ev['lunch_enabled'] ),
            'lunch_start'    => $lunch_start,
            'lunch_end'      => $lunch_end,
            'dinner_enabled' => ! empty( $ev['dinner_enabled'] ),
            'dinner_start'   => $dinner_start,
            'dinner_end'     => $dinner_end,
        );
    }
    update_option( 'flame_amano_special_events', $events );

    wp_safe_redirect( add_query_arg( array( 'page' => 'flame-amano-booking', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
    exit;
}

// ============================================================
// Admin: settings page render
// ============================================================

function flame_amano_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $saved       = ! empty( $_GET['saved'] );
    $hours       = flame_amano_get_hours();
    $events      = flame_amano_get_special_events();
    $valid_times = flame_amano_valid_times();
    $day_names   = array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' );
    $day_order   = array( 1, 2, 3, 4, 5, 6, 0 ); // Mon first, Sun last
    ?>
    <div class="wrap">
      <h1>Flame Amano Booking Hours</h1>

      <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'flame_amano_save_settings', 'flame_amano_settings_nonce' ); ?>
        <input type="hidden" name="action" value="flame_amano_save_settings" />

        <h2>Regular Hours</h2>
        <p>Configure which service periods are available on each day of the week. Time slots shown to customers will be generated in 30-minute steps within each enabled range.</p>

        <table class="wp-list-table widefat fixed striped" style="max-width:980px;border-collapse:collapse;">
          <thead>
            <tr>
              <th style="width:100px;">Day</th>
              <th style="width:55px;text-align:center;">Open</th>
              <th style="width:55px;text-align:center;">Lunch</th>
              <th>Lunch Start</th>
              <th>Lunch End</th>
              <th style="width:55px;text-align:center;">Dinner</th>
              <th>Dinner Start</th>
              <th>Dinner End</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $day_order as $dow ) :
                $d = $hours[ $dow ];
            ?>
            <tr>
              <td><strong><?php echo esc_html( $day_names[ $dow ] ); ?></strong></td>
              <td style="text-align:center;"><input type="checkbox" name="flame_hours[<?php echo esc_attr( $dow ); ?>][enabled]" value="1" <?php checked( ! empty( $d['enabled'] ) ); ?>></td>
              <td style="text-align:center;"><input type="checkbox" name="flame_hours[<?php echo esc_attr( $dow ); ?>][lunch_enabled]" value="1" <?php checked( ! empty( $d['lunch_enabled'] ) ); ?>></td>
              <td><?php echo flame_amano_time_select( "flame_hours[{$dow}][lunch_start]", $d['lunch_start'] ); ?></td>
              <td><?php echo flame_amano_time_select( "flame_hours[{$dow}][lunch_end]",   $d['lunch_end']   ); ?></td>
              <td style="text-align:center;"><input type="checkbox" name="flame_hours[<?php echo esc_attr( $dow ); ?>][dinner_enabled]" value="1" <?php checked( ! empty( $d['dinner_enabled'] ) ); ?>></td>
              <td><?php echo flame_amano_time_select( "flame_hours[{$dow}][dinner_start]", $d['dinner_start'] ); ?></td>
              <td><?php echo flame_amano_time_select( "flame_hours[{$dow}][dinner_end]",   $d['dinner_end']   ); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <h2 style="margin-top:32px;">Special Events</h2>
        <p>Override hours for specific dates (e.g. public holidays, private events). Special events take priority over regular weekly hours on the matching date.</p>

        <table class="wp-list-table widefat fixed striped" id="flame-events-table" style="max-width:1100px;border-collapse:collapse;">
          <thead>
            <tr>
              <th style="width:130px;">Date</th>
              <th style="width:150px;">Event Name</th>
              <th style="width:55px;text-align:center;">Open</th>
              <th style="width:55px;text-align:center;">Lunch</th>
              <th>Lunch Start</th>
              <th>Lunch End</th>
              <th style="width:55px;text-align:center;">Dinner</th>
              <th>Dinner Start</th>
              <th>Dinner End</th>
              <th style="width:70px;"></th>
            </tr>
          </thead>
          <tbody id="flame-events-body">
            <?php
            $event_index = 0;
            foreach ( $events as $event ) :
                $ev_lunch_start  = isset( $event['lunch_start'] )  ? $event['lunch_start']  : '11:30';
                $ev_lunch_end    = isset( $event['lunch_end'] )    ? $event['lunch_end']    : '13:30';
                $ev_dinner_start = isset( $event['dinner_start'] ) ? $event['dinner_start'] : '17:00';
                $ev_dinner_end   = isset( $event['dinner_end'] )   ? $event['dinner_end']   : '20:30';
            ?>
            <tr>
              <td><input type="date" name="flame_events[<?php echo esc_attr( $event_index ); ?>][date]" value="<?php echo esc_attr( $event['date'] ?? '' ); ?>" style="width:120px;"></td>
              <td><input type="text" name="flame_events[<?php echo esc_attr( $event_index ); ?>][name]" value="<?php echo esc_attr( $event['name'] ?? '' ); ?>" placeholder="Event name" style="width:140px;"></td>
              <td style="text-align:center;"><input type="checkbox" name="flame_events[<?php echo esc_attr( $event_index ); ?>][enabled]" value="1" <?php checked( ! empty( $event['enabled'] ) ); ?>></td>
              <td style="text-align:center;"><input type="checkbox" name="flame_events[<?php echo esc_attr( $event_index ); ?>][lunch_enabled]" value="1" <?php checked( ! empty( $event['lunch_enabled'] ) ); ?>></td>
              <td><?php echo flame_amano_time_select( "flame_events[{$event_index}][lunch_start]",  $ev_lunch_start  ); ?></td>
              <td><?php echo flame_amano_time_select( "flame_events[{$event_index}][lunch_end]",    $ev_lunch_end    ); ?></td>
              <td style="text-align:center;"><input type="checkbox" name="flame_events[<?php echo esc_attr( $event_index ); ?>][dinner_enabled]" value="1" <?php checked( ! empty( $event['dinner_enabled'] ) ); ?>></td>
              <td><?php echo flame_amano_time_select( "flame_events[{$event_index}][dinner_start]", $ev_dinner_start ); ?></td>
              <td><?php echo flame_amano_time_select( "flame_events[{$event_index}][dinner_end]",   $ev_dinner_end   ); ?></td>
              <td><button type="button" class="button flame-remove-event">Remove</button></td>
            </tr>
            <?php
                $event_index++;
            endforeach;
            ?>
          </tbody>
        </table>

        <p><button type="button" class="button" id="flame-add-event">+ Add Special Event</button></p>

        <p class="submit"><input type="submit" class="button-primary" value="Save Settings"></p>
      </form>
    </div>

    <script>
    (function(){
      var tbody  = document.getElementById('flame-events-body');
      var addBtn = document.getElementById('flame-add-event');
      var rowIdx = <?php echo (int) $event_index; ?>;
      var timeOptions = <?php echo wp_json_encode( array_map( function( $t ) {
          $ts = strtotime( $t );
          return array( 'value' => $t, 'label' => $ts ? date( 'g:i a', $ts ) : $t );
      }, $valid_times ) ); ?>;
      var defaultTimes = <?php
          $def = flame_amano_get_default_hours()[0];
          echo wp_json_encode( array(
              'lunch_start'  => $def['lunch_start'],
              'lunch_end'    => $def['lunch_end'],
              'dinner_start' => $def['dinner_start'],
              'dinner_end'   => $def['dinner_end'],
          ) );
      ?>;

      function makeSelect(name, selected) {
        var html = '<select name="' + name + '">';
        timeOptions.forEach(function(o) {
          html += '<option value="' + o.value + '"' + (o.value === selected ? ' selected' : '') + '>' + o.label + '</option>';
        });
        html += '</select>';
        return html;
      }

      function addRow() {
        var i  = rowIdx++;
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td><input type="date" name="flame_events[' + i + '][date]" style="width:120px;"></td>' +
          '<td><input type="text" name="flame_events[' + i + '][name]" placeholder="Event name" style="width:140px;"></td>' +
          '<td style="text-align:center;"><input type="checkbox" name="flame_events[' + i + '][enabled]" value="1" checked></td>' +
          '<td style="text-align:center;"><input type="checkbox" name="flame_events[' + i + '][lunch_enabled]" value="1"></td>' +
          '<td>' + makeSelect('flame_events[' + i + '][lunch_start]',  defaultTimes.lunch_start)  + '</td>' +
          '<td>' + makeSelect('flame_events[' + i + '][lunch_end]',    defaultTimes.lunch_end)    + '</td>' +
          '<td style="text-align:center;"><input type="checkbox" name="flame_events[' + i + '][dinner_enabled]" value="1" checked></td>' +
          '<td>' + makeSelect('flame_events[' + i + '][dinner_start]', defaultTimes.dinner_start) + '</td>' +
          '<td>' + makeSelect('flame_events[' + i + '][dinner_end]',   defaultTimes.dinner_end)   + '</td>' +
          '<td><button type="button" class="button flame-remove-event">Remove</button></td>';
        tbody.appendChild(tr);
      }

      addBtn.addEventListener('click', addRow);
      tbody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('flame-remove-event')) {
          e.target.closest('tr').remove();
        }
      });
    })();
    </script>
    <?php
}

function flame_amano_handle_submission() {
    // ensure it is a POST
    if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        wp_safe_redirect( wp_get_referer() ?: home_url() );
        exit;
    }

    // Build a clean base URL for redirects. wp_get_referer() may return an HTML-entity-encoded
    // URL (e.g. & as &amp;) because wp_nonce_field() runs esc_url() on the current page URL
    // when outputting the _wp_http_referer hidden field. Decoding entities and stripping any
    // previous flame_booking params ensures redirect URLs are always valid and parseable.
    $redirect_base = html_entity_decode( wp_get_referer() ?: home_url(), ENT_QUOTES, 'UTF-8' );
    $redirect_base = remove_query_arg( array( 'flame_booking', 'flame_booking_msg' ), $redirect_base );

    // nonce check
    if ( empty( $_POST['flame_booking_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['flame_booking_nonce'] ), 'flame_booking_submit' ) ) {
        $redirect = add_query_arg( array(
            'flame_booking'     => 'error',
            'flame_booking_msg' => rawurlencode( 'Invalid submission (security check failed).' ),
        ), $redirect_base );
        wp_safe_redirect( $redirect );
        exit;
    }

    // Cloudflare Turnstile verification
    if ( defined( 'FLAME_TURNSTILE_SECRET_KEY' ) && ! empty( FLAME_TURNSTILE_SECRET_KEY ) ) {
        $turnstile_response = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
        
        if ( empty( $turnstile_response ) ) {
            $redirect = add_query_arg( array(
                'flame_booking'     => 'error',
                'flame_booking_msg' => rawurlencode( 'Please complete the security verification.' ),
            ), $redirect_base );
            wp_safe_redirect( $redirect );
            exit;
        }

        // Verify the Turnstile response with Cloudflare
        $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        
        // Get client IP address (handle proxies)
        $client_ip = '';
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_list = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $client_ip = trim( $ip_list[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        }
        
        $verify_data = array(
            'secret'   => FLAME_TURNSTILE_SECRET_KEY,
            'response' => $turnstile_response,
            'remoteip' => $client_ip,
        );

        $verify_response = wp_remote_post( $verify_url, array(
            'body' => $verify_data,
        ) );

        if ( is_wp_error( $verify_response ) ) {
            $redirect = add_query_arg( array(
                'flame_booking'     => 'error',
                'flame_booking_msg' => rawurlencode( 'Security verification failed. Please try again.' ),
            ), $redirect_base );
            wp_safe_redirect( $redirect );
            exit;
        }

        $verify_body = wp_remote_retrieve_body( $verify_response );
        $verify_result = json_decode( $verify_body, true );

        if ( ! is_array( $verify_result ) || empty( $verify_result['success'] ) ) {
            $redirect = add_query_arg( array(
                'flame_booking'     => 'error',
                'flame_booking_msg' => rawurlencode( 'Security verification failed. Please try again.' ),
            ), $redirect_base );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    // required fields
    $required = array( 'peopleCount', 'selectedDate', 'selectedTime', 'name', 'contact', 'email' );
    foreach ( $required as $r ) {
        if ( empty( $_POST[ $r ] ) ) {
            $redirect = add_query_arg( array(
                'flame_booking'     => 'error',
                'flame_booking_msg' => rawurlencode( 'Please complete all required fields.' ),
            ), $redirect_base );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    // Minimum allowed date: allow bookings from today
    $min_date = date( 'Y-m-d' );

    // sanitize inputs
    $people = intval( wp_unslash( $_POST['peopleCount'] ) );
    $selected_date_raw = sanitize_text_field( wp_unslash( $_POST['selectedDate'] ) );
    $selected_date_ts  = strtotime( $selected_date_raw );
    $min_date_ts       = strtotime( $min_date );

    if ( $selected_date_ts === false || $selected_date_ts < $min_date_ts ) {
        $redirect = add_query_arg( array(
            'flame_booking'     => 'error',
            'flame_booking_msg' => rawurlencode( 'Booking date not available. Please choose a date on or after the opening date.' ),
        ), $redirect_base );
        wp_safe_redirect( $redirect );
        exit;
    }

    $time_raw = sanitize_text_field( wp_unslash( $_POST['selectedTime'] ) );
    $time_display = ( strtotime( $time_raw ) !== false ) ? date( 'g:i a', strtotime( $time_raw ) ) : $time_raw;
    $name    = sanitize_text_field( wp_unslash( $_POST['name'] ) );
    $contact = sanitize_text_field( wp_unslash( $_POST['contact'] ) );
    $email   = sanitize_email( wp_unslash( $_POST['email'] ) );
    $details = sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) );

    // Format date as dd/mm/yyyy for emails
    $date_for_email = ( $selected_date_ts !== false ) ? date( 'd/m/Y', $selected_date_ts ) : $selected_date_raw;

    // prepare email to restaurant
    $recipient = 'bookings@flameamano.co.nz'; // requested recipient
    $site_name = get_bloginfo( 'name' );
    $subject   = "New booking request - {$site_name} ({$date_for_email} {$time_display})";
    $message   = "<h3>New booking request</h3>";
    $message  .= "<p><strong>Name:</strong> " . esc_html( $name ) . "</p>";
    $message  .= "<p><strong>Contact:</strong> " . esc_html( $contact ) . "</p>";
    $message  .= "<p><strong>Email:</strong> " . esc_html( $email ) . "</p>";
    $message  .= "<p><strong>People:</strong> " . esc_html( $people ) . "</p>";
    $message  .= "<p><strong>Date:</strong> " . esc_html( $date_for_email ) . "</p>";
    $message  .= "<p><strong>Time:</strong> " . esc_html( $time_display ) . "</p>";
    if ( strlen( $details ) ) {
        $message .= "<p><strong>Details / Allergies:</strong><br>" . nl2br( esc_html( $details ) ) . "</p>";
    }

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    if ( is_email( $email ) ) {
        $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
    }
    // From header: use site name and admin email if available
    // <<< ONLY CHANGE: force admin email to bookings address so emails come from bookings@flameamano.co.nz
    $admin_email = 'bookings@flameamano.co.nz';
    // >>> end change
    if ( $admin_email ) {
        $headers[] = 'From: ' . wp_specialchars_decode( $site_name ) . ' <' . $admin_email . '>';
    }

    // Send email to restaurant
    $sent_to_restaurant = wp_mail( $recipient, $subject, $message, $headers );

    // Prepare and send confirmation email to the customer (if customer's email valid)
    $sent_to_customer = true; // default true when no customer email available (shouldn't happen because email is required)
    if ( is_email( $email ) ) {
        $customer_subject = "Your booking request - {$site_name} ({$date_for_email} {$time_display})";
        // Message body for customer (plain HTML)
        $customer_message  = "<p>Hi " . esc_html( $name ) . ",</p>";
        $customer_message .= "<p>Thanks for your booking request.</p>";
        $customer_message .= "<p><strong>Please note this is not yet confirmed - we'll email you once your reservation is secured.</strong></p>";
        $customer_message .= "<p><strong>Booking details</strong><br>";
        $customer_message .= "People: " . esc_html( $people ) . "<br>";
        $customer_message .= "Date: " . esc_html( $date_for_email ) . "<br>";
        $customer_message .= "Time: " . esc_html( $time_display ) . "<br>";
        if ( strlen( $details ) ) {
            $customer_message .= "Details / Allergies: " . nl2br( esc_html( $details ) ) . "<br>";
        }
        $customer_message .= "</p>";
        $customer_message .= "<p>For certainty, you're welcome to call us at <strong>06 355 0450</strong> or <strong>021 835 431</strong>.</p>";
        $customer_message .= "<p>Thanks,<br>" . esc_html( $site_name ) . "</p>";

        $customer_headers = array( 'Content-Type: text/html; charset=UTF-8' );
        // Set From as bookings address if possible
        $bookings_from = 'bookings@flameamano.co.nz';
        $customer_headers[] = 'From: ' . wp_specialchars_decode( $site_name ) . ' <' . $bookings_from . '>';
        if ( $admin_email ) {
            $customer_headers[] = 'Reply-To: ' . wp_specialchars_decode( $site_name ) . ' <' . $admin_email . '>';
        }

        $sent_to_customer = wp_mail( $email, $customer_subject, $customer_message, $customer_headers );
    }

    // Decide redirect: consider restaurant email as primary success requirement
    if ( $sent_to_restaurant ) {
        // If customer email failed, still treat as success but include note (we can append a query param if desired)
        $redirect = add_query_arg( array( 'flame_booking' => 'success' ), $redirect_base );
    } else {
        $redirect = add_query_arg( array(
            'flame_booking'     => 'error',
            'flame_booking_msg' => rawurlencode( 'There was an error sending the booking email. Please try again or contact the restaurant directly.' ),
        ), $redirect_base );
    }

    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Shortcode: renders the form (no POST handling inline anymore)
 */
add_shortcode( 'flame_amano_booking_form', function() {
    ob_start();

    // show messages from redirect
    $msg_html = '';
    $status = '';
    if ( ! empty( $_GET['flame_booking'] ) ) {
        $status = sanitize_text_field( wp_unslash( $_GET['flame_booking'] ) );
        if ( 'success' === $status ) {
            // Confirmation with two side-by-side phone buttons (responsive)
            $phone1_display = '06 355 0450';
            $phone1_tel = '+6463550450';
            $phone2_display = '021 835 431';
            $phone2_tel = '+6421835431';
            $confirmation = '
<div style="margin-top:15px;background:#e2ffe2;padding:18px;border-radius:6px;">
  <strong>Thanks for your booking request</strong>
  <p style="margin:.5em 0 0;">
    Please note this is not yet confirmed - we\'ll email you once your reservation is secured.
  </p>
  <div style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap;">
    <a href="tel:' . esc_attr( $phone1_tel ) . '" style="flex:1;min-width:160px;text-align:center;padding:12px 14px;background:#bb9739;color:#111;border-radius:6px;font-weight:700;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,0.08);">Call ' . esc_html( $phone1_display ) . '</a>
    <a href="tel:' . esc_attr( $phone2_tel ) . '" style="flex:1;min-width:160px;text-align:center;padding:12px 14px;background:#bb9739;color:#111;border-radius:6px;font-weight:700;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,0.08);">Call ' . esc_html( $phone2_display ) . '</a>
  </div>
</div>';
            echo $confirmation;
            // Do not render the form when successful - only show confirmation
            return ob_get_clean();
        } else {
            $err = ! empty( $_GET['flame_booking_msg'] ) ? rawurldecode( wp_unslash( $_GET['flame_booking_msg'] ) ) : 'Submission failed.';
            $msg_html = "<div style='margin-top:15px;background:#ffe2e2;padding:10px;border-radius:4px;color:#900;'><strong>Error:</strong> " . esc_html( $err ) . "</div>";
        }
    }

    // preserve posted values for re-rendering the form after submit (if user returned)
    $posted = array();
    foreach ( array( 'peopleCount', 'selectedDate', 'selectedTime', 'name', 'contact', 'email', 'details' ) as $k ) {
        $posted[ $k ] = isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : '';
    }

    // Minimum allowed date: allow bookings from today
    $min_date = date( 'Y-m-d' );

    // Build grouped time structure for the initial render based on the preserved/posted date.
    $initial_date   = ! empty( $posted['selectedDate'] ) ? $posted['selectedDate'] : $min_date;
    $initial_groups = flame_amano_get_groups_for_date( $initial_date );

    // Build per-day and special-event configs to pass to the JS layer so the
    // client can rebuild time options on date change without a server round-trip.
    $hours_raw = flame_amano_get_hours();
    $js_hours  = array(); // indexed 0–6 (Sun–Sat), value = groups array
    for ( $i = 0; $i <= 6; $i++ ) {
        $js_hours[ $i ] = flame_amano_build_groups_from_config( $hours_raw[ $i ] );
    }
    $js_events = array(); // keyed by 'YYYY-MM-DD', value = groups array
    foreach ( flame_amano_get_special_events() as $ev ) {
        if ( ! empty( $ev['enabled'] ) && ! empty( $ev['date'] ) ) {
            $js_events[ $ev['date'] ] = flame_amano_build_groups_from_config( $ev );
        }
    }

    // Function to generate grouped time options server-side for initial render
    $generate_grouped_time_options = function( $groups, $selected ) {
        $out = '';
        foreach ( $groups as $g ) {
            $label  = isset( $g['label'] ) ? $g['label'] : '';
            $ranges = isset( $g['ranges'] ) ? $g['ranges'] : array();
            $out   .= '<optgroup label="' . esc_attr( $label ) . '">';
            foreach ( $ranges as $r ) {
                $start_ts = strtotime( $r['start'] );
                $end_ts   = strtotime( $r['end'] );
                if ( $start_ts === false || $end_ts === false || $end_ts < $start_ts ) {
                    continue;
                }
                for ( $t = $start_ts; $t <= $end_ts; $t += FLAME_SLOT_STEP * 60 ) {
                    $value      = date( 'H:i', $t );
                    $label_time = date( 'g:i a', $t );
                    $out       .= '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label_time ) . '</option>';
                }
            }
            $out .= '</optgroup>';
        }
        return $out;
    };
    ?>

    <?php echo $msg_html; ?>

    <form id="flame-amano-booking-form"
          class="sbp-stage"
          method="post"
          action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
          autocomplete="off">
      <?php wp_nonce_field( 'flame_booking_submit', 'flame_booking_nonce' ); ?>
      <input type="hidden" name="action" value="flame_amano_booking_submit" />
      <div class="sbp-form-grid">
        <div class="sbp-form-group">
          <label for="peopleCount">How many people?</label>
          <select id="peopleCount" name="peopleCount" required>
            <option value="">Select number of people</option>
            <?php for ( $i = 1; $i <= 14; $i++ ): ?>
              <option value="<?php echo esc_attr( $i ); ?>" <?php echo selected( $posted['peopleCount'], (string) $i, false ); ?>><?php echo esc_html( $i ); ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="sbp-form-group">
          <label for="selectedDate">Choose a date</label>
          <input type="date" id="selectedDate" name="selectedDate" value="<?php echo esc_attr( $posted['selectedDate'] ); ?>" min="<?php echo esc_attr( $min_date ); ?>" required>
        </div>

        <div class="sbp-form-group">
          <label for="selectedTime">Choose a time</label>
          <select id="selectedTime" name="selectedTime" required>
            <option value="">Select a time</option>
            <?php echo $generate_grouped_time_options( $initial_groups, $posted['selectedTime'] ); ?>
          </select>
        </div>

        <div class="sbp-form-group">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" placeholder="Your name" value="<?php echo esc_attr( $posted['name'] ); ?>" required>
        </div>

        <div class="sbp-form-group">
          <label for="contact">Contact Number</label>
          <input type="text" id="contact" name="contact" placeholder="Phone number" value="<?php echo esc_attr( $posted['contact'] ); ?>" required>
        </div>

        <div class="sbp-form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="Email address" value="<?php echo esc_attr( $posted['email'] ); ?>" required>
        </div>

        <div class="sbp-form-group sbp-form-group-full">
          <label for="details">Additional details</label>
          <textarea id="details" name="details" placeholder="Enter any details..."><?php echo esc_textarea( $posted['details'] ); ?></textarea>
          <div style="font-size:12px;color:#555;margin-top:3px;margin-bottom:10px;">Let us know about any allergies etc.</div>
        </div>

        <?php if ( defined( 'FLAME_TURNSTILE_SITE_KEY' ) && ! empty( FLAME_TURNSTILE_SITE_KEY ) ): ?>
        <div class="sbp-form-group sbp-form-group-full">
          <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( FLAME_TURNSTILE_SITE_KEY ); ?>"></div>
        </div>
        <?php endif; ?>
      </div>

      <button type="submit">Submit Booking</button>
    </form>

    <?php if ( defined( 'FLAME_TURNSTILE_SITE_KEY' ) && ! empty( FLAME_TURNSTILE_SITE_KEY ) ): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>

    <script>
    (function(){
      var minDate = <?php echo wp_json_encode( $min_date ); ?>;
      var preservedTime = <?php echo wp_json_encode( $posted['selectedTime'] ); ?> || '';
      var slotStep = <?php echo (int) FLAME_SLOT_STEP; ?>;

      // Per-day groups config: key = day-of-week integer (0=Sun..6=Sat)
      var hoursConfig = <?php echo wp_json_encode( $js_hours ); ?>;
      // Special-event groups config: key = 'YYYY-MM-DD'
      var eventsConfig = <?php echo wp_json_encode( $js_events ); ?>;

      function parseHM(hm){ var p=hm.split(':'); return parseInt(p[0],10)*60 + parseInt(p[1],10); }
      function formatValue(mins){ var h=Math.floor(mins/60), m=mins%60; return (h<10?'0'+h:h)+':'+(m<10?'0'+m:m); }
      function formatLabel(mins){ var h=Math.floor(mins/60), m=mins%60, ampm=h>=12?'pm':'am', hr=h%12; if(hr===0)hr=12; return hr+':'+(m<10?'0'+m:m)+' '+ampm; }

      function buildGroupedOptions(groups){
        var out=[];
        groups.forEach(function(g){
          var opts=[];
          g.ranges.forEach(function(r){
            var start=parseHM(r.start), end=parseHM(r.end);
            if(isNaN(start)||isNaN(end)||end<start) return;
            for(var t=start;t<=end;t+=slotStep){ opts.push({value:formatValue(t),label:formatLabel(t)}); }
          });
          if(opts.length) out.push({label:g.label,options:opts});
        });
        return out;
      }

      var dateInput=document.getElementById('selectedDate');
      var timeSelect=document.getElementById('selectedTime');

      if(dateInput && minDate){ dateInput.min = minDate; }

      function rebuildTimeOptions(forDateStr){
        var groups = [];
        if(forDateStr && eventsConfig[forDateStr] !== undefined){
          groups = eventsConfig[forDateStr];
        } else if(forDateStr){
          var d = new Date(forDateStr+'T00:00:00');
          if(!isNaN(d.getTime())){
            var dow = d.getDay(); // 0=Sun..6=Sat
            groups = hoursConfig[dow] || [];
          }
        } else {
          var today = new Date();
          groups = hoursConfig[today.getDay()] || [];
        }
        var prevValue = preservedTime || timeSelect.value || '';
        timeSelect.innerHTML = '<option value="">Select a time</option>';
        var grouped = buildGroupedOptions(groups);
        grouped.forEach(function(g){
          var og = document.createElement('optgroup');
          og.label = g.label;
          g.options.forEach(function(o){
            var op = document.createElement('option');
            op.value = o.value;
            op.textContent = o.label;
            if(o.value === prevValue) op.selected = true;
            og.appendChild(op);
          });
          timeSelect.appendChild(og);
        });
      }

      document.addEventListener('DOMContentLoaded', function(){ rebuildTimeOptions(dateInput.value); });
      dateInput.addEventListener('change', function(){ preservedTime = document.getElementById('selectedTime').value || preservedTime; rebuildTimeOptions(this.value); });
    })();
    </script>

    <style>
    #flame-amano-booking-form { max-width:600px; margin:20px auto; padding:28px 22px; border:1px solid #ddd; border-radius:6px; background:#fafafa; }
    .sbp-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px 24px; }
    .sbp-form-group { display:flex; flex-direction:column; }
    .sbp-form-group-full { grid-column:1 / -1; }
    #flame-amano-booking-form label { margin-bottom:5px; font-weight:bold; }
    #flame-amano-booking-form input, #flame-amano-booking-form select, #flame-amano-booking-form textarea { width:100%; padding:5px; border:1px solid #ccc; border-radius:4px; font-size:15px; box-sizing:border-box; }
    #flame-amano-booking-form textarea { margin-top:10px !important; min-height:70px; resize:vertical; }
    #flame-amano-booking-form input::placeholder, #flame-amano-booking-form textarea::placeholder { font-size:15px; color:#aaa; opacity:1; }
    #flame-amano-booking-form button { margin-top:18px; padding:14px 12px; font-size:1em; border-radius:4px; border:none; background:#bb9739; color:#222; font-weight:bold; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,0.09); transition:background 0.2s, color 0.2s; width:100%; display:block; }
    #flame-amano-booking-form button:hover { background:#a67f2f; color:#fff; }
    @media (max-width:600px){ .sbp-form-grid { grid-template-columns:1fr; gap:14px 0; } #flame-amano-booking-form { padding:18px 8px; } }
    </style>
    <?php
    return ob_get_clean();
} );
