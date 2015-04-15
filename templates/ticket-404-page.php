<?php
/**
 * Created by PhpStorm.
 * User: udit
 * Date: 11/7/14
 * Time: 9:07 PM
 */

get_header();

do_action( 'rthd_ticket_front_page_after_header' );

global $rthd_messages;

?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">

		<section class="error-404 not-found entry-content">
			<div class="page-content">
				<div class="rthd-container">
					<?php
						foreach ( $rthd_messages as $key => $message ) {
							if ( $message['displayed'] == 'no' ) {
								echo '<div class="' . $message['type'] . '">' . $message['message'] . '</div>';
								$rthd_messages[$key]['displayed'] = 'yes';
							}
						}
					?>
				</div>
				<div class="rthd-my-ticket">
					<?php
					global $current_user;
					echo balanceTags( do_shortcode( '[rt_hd_tickets userid = ' . $current_user->ID . ']' ) ); ?>
				</div>
				<div class="rthd-fav-ticket">
					<?php
					global $current_user;
					echo balanceTags( do_shortcode( '[rt_hd_tickets userid = ' . $current_user->ID . ' fav= true]' ) ); ?>
				</div>
			</div>
		</section>

	</main>
</div>

<?php

do_action( 'rthd_ticket_front_page_before_footer' );

get_footer();
