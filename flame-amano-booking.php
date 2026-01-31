<?php
/*
Plugin Name: Flame Amano Booking Form
Description: Simple booking form with dropdown for 1-14 people, date, time (5pm-8:30pm in 15 min steps), name, contact, email, details, and allergy note. Responsive: two columns on desktop, one on mobile. Posts handled via admin-post.php to avoid 404. Sends email to bookings@flameamano.co.nz and a confirmation to the customer. Date in emails is dd/mm/yyyy.
Version: 1.3.3
Author: Impact Websites
*/

// Exit early if WP not loaded
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle submission via admin-post (for logged-in and non-logged-in users)
 */
add_action( 'admin_post_nopriv_flame_amano_booking_submit', 'flame_amano_handle_submission' );
add_action( 'admin_post_flame_amano_booking_submit', 'flame_amano_handle_submission' );

function flame_amano_handle_submission() {
    // ensure it is a POST
    if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        wp_safe_redirect( wp_get_referer() ?: home_url() );
        exit;
    }

    // nonce check
    if ( empty( $_POST['flame_booking_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['flame_booking_nonce'] ), 'flame_booking_submit' ) ) {
        $redirect = add_query_arg( array(
            'flame_booking'     => 'error',
            'flame_booking_msg' => rawurlencode( 'Invalid submission (security check failed).' ),
        ), wp_get_referer() ?: home_url() );
        wp_safe_redirect( esc_url_raw( $redirect ) );
        exit;
    }

    // required fields
    $required = array( 'peopleCount', 'selectedDate', 'selectedTime', 'name', 'contact', 'email' );
    foreach ( $required as $r ) {
        if ( empty( $_POST[ $r ] ) ) {
            $redirect = add_query_arg( array(
                'flame_booking'     => 'error',
                'flame_booking_msg' => rawurlencode( 'Please complete all required fields.' ),
            ), wp_get_referer() ?: home_url() );
            wp_safe_redirect( esc_url_raw( $redirect ) );
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
        ), wp_get_referer() ?: home_url() );
        wp_safe_redirect( esc_url_raw( $redirect ) );
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
        $redirect = add_query_arg( array( 'flame_booking' => 'success' ), wp_get_referer() ?: home_url() );
    } else {
        $redirect = add_query_arg( array(
            'flame_booking'     => 'error',
            'flame_booking_msg' => rawurlencode( 'There was an error sending the booking email. Please try again or contact the restaurant directly.' ),
        ), wp_get_referer() ?: home_url() );
    }

    wp_safe_redirect( esc_url_raw( $redirect ) );
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

    // Helper to determine if a date (YYYY-MM-DD) is Friday (PHP: Mon=1 .. Sun=7)
    $is_friday = false;
    if ( ! empty( $posted['selectedDate'] ) ) {
        $ts = strtotime( $posted['selectedDate'] );
        if ( $ts !== false ) {
            $dow = intval( date( 'N', $ts ) );
            if ( $dow === 5 ) {
                $is_friday = true;
            }
        }
    }

    // Ranges
    $evening_ranges = array( array( 'start' => '17:00', 'end' => '20:30' ) );
    $morning_ranges = array( array( 'start' => '11:30', 'end' => '13:30' ) );

    // Build grouped structure for initial render
    $initial_groups = array();
    if ( $is_friday ) {
        $initial_groups[] = array( 'label' => 'Lunch', 'ranges' => $morning_ranges );
        $initial_groups[] = array( 'label' => 'Dinner', 'ranges' => $evening_ranges );
    } else {
        $initial_groups[] = array( 'label' => 'Dinner', 'ranges' => $evening_ranges );
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
                for ( $t = $start_ts; $t <= $end_ts; $t += 15 * 60 ) {
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
      </div>

      <button type="submit">Submit Booking</button>
    </form>

    <script>
    (function(){
      // Minimum allowed date (same as server-side)
      var minDate = <?php echo wp_json_encode( $min_date ); ?>;
      var preservedTime = <?php echo wp_json_encode( $posted['selectedTime'] ); ?> || '';

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
            for(var t=start;t<=end;t+=15){ opts.push({value:formatValue(t),label:formatLabel(t)}); }
          });
          out.push({label:g.label,options:opts});
        });
        return out;
      }

      var morningRanges=[{start:'11:30',end:'13:30'}];
      var eveningRanges=[{start:'17:00',end:'20:30'}];

      var dateInput=document.getElementById('selectedDate');
      var timeSelect=document.getElementById('selectedTime');

      if(dateInput && minDate){ dateInput.min = minDate; }

      function rebuildTimeOptions(forDateStr){
        var groups=[{label:'Dinner',ranges:eveningRanges}];
        if(forDateStr){
          var d=new Date(forDateStr+'T00:00:00');
          if(!isNaN(d.getTime())){
            var dow=d.getDay(); // 0=Sun..6=Sat
            if(dow===5){
              groups=[{label:'Lunch',ranges:morningRanges},{label:'Dinner',ranges:eveningRanges}];
            }
          }
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
