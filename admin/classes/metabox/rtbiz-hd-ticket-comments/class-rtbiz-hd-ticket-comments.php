<?php
/**
 * User: spock
 * Date: 19/9/14
 * Time: 4:35 PM
 */

/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
* To change this license header, choose License Headers in Project Properties.
* To change this template file, choose Tools | Templates
* and open the template in the editor.
*/

/**
 * Description of Rtbiz_HD_Ticket_Comments
 *
 * @since rt-Helpdesk 0.1
 */

if ( ! class_exists( 'Rtbiz_HD_Ticket_Comments' ) ) {
	class Rtbiz_HD_Ticket_Comments {

		public static function ui( $post ) {
			$ticket_unique_id = get_post_meta( $post->ID, '_rtbiz_hd_unique_id', true );
			rtbiz_hd_get_template( 'followup-common.php', array( 'post' => $post ) );

			// Remove this echo line if you're including add-folloup template.
			//echo '<input id="post-id" type="hidden" value="' . esc_attr( $post->ID ) .'" />';
			rtbiz_hd_get_template( 'ticket-add-followup-form.php', array( 'post' => $post, 'ticket_unique_id' => $ticket_unique_id ) );
		}

	}
}
