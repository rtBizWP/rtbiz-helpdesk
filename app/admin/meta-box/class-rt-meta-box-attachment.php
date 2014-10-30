<?php
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
 * Description of RT_HD_Admin_Meta_Boxes
 *
 * @since rt-Helpdesk 0.1
 */

if ( ! class_exists( 'RT_Meta_Box_Attachment' ) ) {
	class RT_Meta_Box_Attachment {

		/**
		 * Output the metabox
		 *
		 * @since rt-Helpdesk 0.1
		 *
		 * @param $post
		 */
		public static function ui( $post ) {

			$post_type = $post->post_type;

			$attachments = array();
			if ( isset( $post->ID ) ) {
				$attachments = get_posts( array(
											'posts_per_page' => - 1,
											'post_parent'    => $post->ID,
											'post_type'      => 'attachment',
											) );
			}?>

			<div id="attachment-container" class="row_group">
			<a href="#" class="button" id="add_ticket_attachment"><?php _e( 'Add', RT_HD_TEXT_DOMAIN ); ?></a>
			<ul id="divAttachmentList" class="scroll-height">
				<?php
			foreach ( $attachments as $attachment ) {
				$extn_array = explode( '.', $attachment->guid );
				$extn       = $extn_array[ count( $extn_array ) - 1 ]; ?>
				<li data-attachment-id="<?php echo esc_attr( $attachment->ID ); ?>" class="attachment-item row_group">
					<a href="#" class="delete_row rthd_delete_attachment">x</a> <a target="_blank"
					                                                               href="<?php echo esc_url( wp_get_attachment_url( $attachment->ID ) ); ?>">
						<img height="20px" width="20px"
						     src="<?php echo esc_url( RT_HD_URL . 'app/assets/file-type/' . $extn . '.png' ); ?>"/><?php
						echo esc_attr( $attachment->post_title ); ?>
					</a> <input type="hidden" name="attachment[]" value="<?php echo esc_attr( $attachment->ID ); ?>"/>
					</li><?php
			} ?>
			</ul>
			</div><?php
		}

		/**
		 * Save meta box data
		 *
		 * @since rt-Helpdesk 0.1
		 *
		 * @param $post_id
		 * @param $post
		 */
		public static function save( $post_id, $post ) {

			global $rt_hd_tickets_operation;
			if ( isset( $_POST['attachment'] ) && ! empty( $_POST['attachment'] ) ){
				$rt_hd_tickets_operation->ticket_attachment_update( $_POST['attachment'], $post_id );
			}

		}
	}
}