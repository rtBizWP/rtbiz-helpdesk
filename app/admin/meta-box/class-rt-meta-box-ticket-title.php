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

if ( ! class_exists( 'RT_Meta_Box_Ticket_Title' ) ) {
	class RT_Meta_Box_Ticket_Title {

		/**
		 * Metabox Ui for ticket info
		 *
		 * @since 0.1
		 */
		public static function ui( $post ) {
			?>
			<style type="text/css">
				#post-body-content, #titlediv, #minor-publishing-actions, #misc-publishing-actions, #visibility, #delete-action, #titlediv {
					display: none
				}
				#rt-hd-ticket-title .handlediv, #rt-hd-ticket-title h3.hndle,
				#commentsdiv .handlediv, #commentsdiv h3.hndle{
					display: none;
				}
				#title {
					width: 85%;
				}

				#content {
					color: black;
				}
			</style>
			<script>
				jQuery(window).ready(function($) {
					$('#post-body-content #wp-content-editor-tools' ).remove();
					$('#post-body-content #wp-content-editor-container' ).remove();
				});
			</script>
			<h2><?php printf( 'Ticket #%s : ' , $post->ID ); ?><input name="post_title" size="30" value="<?php echo esc_html( $post->post_title ); ?>" id="title" autocomplete="off" type="text"></h2>
			<?php
				wp_editor( $post->post_content, 'content', array(
					'textarea_name' => 'content',
					'editor_class' => 'wp-editor-area',
					'editor_height' => 360,
					'textarea_rows' => 15,
					'media_buttons' => false,
					'teeny' => true,
					'tinymce' => array(
						'resize' => false,
						'add_unload_trigger' => false,
					),
				) );
			?>
		<?php
		}
	}
}