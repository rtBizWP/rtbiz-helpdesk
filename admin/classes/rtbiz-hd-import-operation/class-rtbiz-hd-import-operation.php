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
 * Description of Rt_HD_Tickets
 *
 * @author udit
 *
 * @since  rt-Helpdesk 0.1
 */
if ( ! class_exists( 'Rtbiz_HD_Import_Operation' ) ) {

	/**
	 * Class Rt_HD_Tickets
	 * HD : Help Desk
	 * This class is for tickets related functions
	 *
	 * @since rt-Helpdesk 0.1
	 */
	class Rtbiz_HD_Import_Operation {

		/**
		 * @var int
		 * Helpdesk comment types
		 */
		public static $FOLLOWUP_BOT = 5;
		public static $FOLLOWUP_PUBLIC = 10;
		public static $FOLLOWUP_STAFF = 30;

		/**
		 * set hooks
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function __construct() {
			Rtbiz_HD::$loader->add_filter( 'comments_clauses', $this, 'filter_comment_from_admin', 100, 1 );

			Rtbiz_HD::$loader->add_action( 'comment_post', $this, 'mail_new_comment_data', 10, 2 );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_update_followup', $this, 'ajax_update_followup' );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_add_new_followup_front', $this, 'ajax_add_new_followup_front' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_nopriv_rtbiz_hd_add_new_followup_front', $this, 'ajax_add_new_followup_front' );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_delete_followup', $this, 'ajax_delete_followup' );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_load_more_followup', $this, 'ajax_load_more_followup' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_nopriv_rtbiz_hd_load_more_followup', $this, 'ajax_load_more_followup' );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_add_new_ticket_ajax', $this, 'ajax_add_new_ticket' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_nopriv_rtbiz_hd_add_new_ticket_ajax', $this, 'ajax_add_new_ticket' );

			Rtbiz_HD::$loader->add_action( 'read_rt_mailbox_email_'. RTBIZ_HD_TEXT_DOMAIN, $this, 'process_email_to_ticket', 10, 16 );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_ticket_bulk_edit', $this, 'ajax_ticket_bulk_edit' ); //no use

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_front_end_status_change', $this, 'ajax_front_end_status_change' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_front_end_assignee_change', $this, 'ajax_front_end_assignee_change' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_front_end_watch_unwatch', $this, 'ajax_front_end_watch_unwatch' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_add_subscriber_email', $this, 'ajax_add_subscriber_email' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_remove_subscriber_email', $this, 'ajax_remove_subscriber_email' );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_quick_download', $this, 'ajax_quick_download' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_nopriv_rtbiz_hd_quick_download', $this, 'ajax_quick_download' );

			Rtbiz_HD::$loader->add_action( 'wp_ajax_nopriv_rtbiz_hd_upload_attachment', $this, 'ajax_upload_attachment' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_upload_attachment', $this, 'ajax_upload_attachment' );

			// commented ajax call for checking followup duplicate and all other code
			//			Rtbiz_HD::$loader->add_action( 'wp_ajax_rthd_check_duplicate_followup', $this, 'rthd_check_duplicate_followup' );
			//			Rtbiz_HD::$loader->add_action( 'wp_ajax_nopriv_rthd_check_duplicate_followup', $this, 'rthd_check_duplicate_followup' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_front_end_product_change', $this, 'ajax_front_end_product_change' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_fav_ticket', $this, 'ajax_favourite_tickets' );
		}

		/**
		 * Ajax call back of favorite ticket
		 */
		public function ajax_favourite_tickets() {
			$status = false;
			if ( ! empty( $_POST['nonce'] )
			     && ! empty( $_POST['post_id'] )
			     && get_post_type( $_POST['post_id'] ) == Rtbiz_HD_Module::$post_type
				 && wp_verify_nonce( $_POST['nonce'], 'heythisisrthd_ticket_fav_'.$_POST['post_id'] )
			) {
				$label = '';
				$favs = rtbiz_hd_get_user_fav_ticket( get_current_user_id() );
				if ( in_array( $_POST['post_id'], $favs ) ) {
					rtbiz_hd_delete_user_fav_ticket( get_current_user_id(), $_POST['post_id'] );
					$label = 'Favorite this ticket';
				} else {
					rtbiz_hd_add_user_fav_ticket( get_current_user_id(), $_POST['post_id'] );
					$label = 'Remove this ticket from favorites';
				}
				$status = true;
			}
			echo json_encode( array( 'status' => $status, 'label' => $label ) );
			die();
		}

		/**
		 * Ajax call for changing product of the post.
		 */
		public function ajax_front_end_product_change() {
			$flag = false;
			if ( ! empty( $_POST['product_id'] ) && ! empty( $_POST['post_id'] ) ) {
				$return = wp_set_object_terms( $_POST['post_id'], array( intval( $_POST['product_id'] ) ), Rt_Products::$product_slug, false );
				if ( ! $return instanceof WP_Error && ! empty( $return ) ) {
					$flag = true;
				}
			}
			echo json_encode( array( 'status' => $flag ) );
			die();
		}

		/**
		 *  Check followup duplicate
		 */
		public function rthd_check_duplicate_followup() {
			$return_data = array( 'status' => false );
			if ( ! empty( $_POST['followup_content'] ) && ! empty( $_POST['followup_ticket_unique_id'] ) ) {
				global $signature;
				$rthd_ticket = $this->get_ticket_from_ticket_unique_id( $_POST['followup_ticket_unique_id'] );
				$comment_post_ID = $rthd_ticket->ID;
				$commenttime     = current_time( 'mysql', 1 );
				$d               = new DateTime( $commenttime );
				$UTC             = new DateTimeZone( 'UTC' );
				$d->setTimezone( $UTC );
				$timeStamp      = $d->getTimestamp();
				$commentDate    = gmdate( 'Y-m-d H:i:s', ( intval( $timeStamp ) + ( get_option( 'gmt_offset' ) * 3600 ) ) );
				$comment_content_old = $_POST['followup_content'];
				$comment_content     = str_replace( $signature, '', $_POST['followup_content'] );
				$checkDupli = $this->check_duplicate_comment( $comment_post_ID, $commentDate, $comment_content, $comment_content_old );
				if ( false !== $checkDupli ) {
					$return_data['isDuplicate'] = true;
				} else {
					$return_data['isDuplicate'] = false;
				}
				$return_data['status'] = true;
			}
			echo json_encode( $return_data );
			die();
		}

		/**
		 * For create attachment
		 */
		public function ajax_upload_attachment() {
			$response = array();
			if ( $_FILES ) {
				$comment_post_ID = '';
				if ( ! empty( $_POST['followup_ticket_unique_id'] ) ) {
					$rthd_ticket = $this->get_ticket_from_ticket_unique_id( $_POST['followup_ticket_unique_id'] );
					$comment_post_ID = $rthd_ticket->ID;
				}
				$attachment = $_FILES['file'];
				$uploaded[] = Rtbiz_HD_Product_Support::insert_attachment( $attachment );

				$response['status'] = false;

				if ( isset( $uploaded[0] ) && !empty( $uploaded[0] ) ) {
					$attachments = $this->add_attachment_to_post( $uploaded, $comment_post_ID );
					if ( !empty( $attachments['ids'] ) ) {
						$response['attach_ids'] = $attachments['ids'];
					} else {
						$response['attach_ids'] = array();
					}
					$response['status'] = true;
				}

				echo json_encode( $response );
				die();
			}
		}

		/**
		 * For quick download link
		 */
		public function ajax_quick_download() {
			if ( ! empty( $_POST['url'] ) ) {
				$file = $_POST['url'] ;
				header( 'Content-Type: octet-stream' );
				header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
				header( 'Expires: 0' );
				header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
				header( 'Pragma: public' );
				header( 'Content-Length: ' . filesize( $file ) );
				readfile( $file );
				die();
			}
		}

		/**
		 * ajax call for add subscriber or contact
		 */
		public function ajax_add_subscriber_email() {
			global $rtbiz_hd_email_notification;
			$response = array();
			$response['status'] = false;
			$response['is_contact'] = false;
			if ( ! empty( $_POST['post_id'] ) && ! empty( $_POST['email'] ) ) {
				$user = get_user_by( 'email',$_POST['email'] );
				if ( $user ) {
					if ( user_can( $user, rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' ) ) ) { // add user to subscriber
						$ticket_subscribers = get_post_meta( $_POST['post_id'], '_rtbiz_hd_subscribe_to', true );
						if ( empty( $ticket_subscribers ) ) {
							$ticket_subscribers = array();
						}
						if ( ! in_array( $user->ID, $ticket_subscribers ) ) {
							$ticket_subscribers[] = $user->ID;
							update_post_meta( $_POST['post_id'], '_rtbiz_hd_subscribe_to', $ticket_subscribers );
							$rtbiz_hd_email_notification->notification_ticket_subscribed( $_POST['post_id'], Rtbiz_HD_Module::$post_type, array( array( 'email' => $user->user_email, 'name' => $user->display_name ) ) );
							$response['status'] = true;
						} else {
							$response['msg'] = 'Already subscribed.';
						}
					} else { // add user to p2p connection
						$user_contact_info = rtbiz_get_contact_by_email( $_POST['email'] );
						$user_contact_info = $user_contact_info[0];
						if ( ! p2p_connection_exists( Rtbiz_HD_Module::$post_type . '_to_' . rtbiz_get_contact_post_type(), array( 'from' => $_POST['post_id'], 'to' => $user_contact_info->ID ) ) ) {
							rtbiz_connect_post_to_contact( Rtbiz_HD_Module::$post_type, $_POST['post_id'], $user_contact_info->ID );
							$response['status'] = true;
							$response['is_contact'] = true;
						} else {
							$response['status'] = false;
							$response['msg'] = 'Already subscribed.';
						}
					}
				} else { // create user and then add to p2p
					$create_wp_user = false;
					$user_contact_info = rtbiz_get_contact_by_email( $_POST['email'] );
					if ( ! empty( $user_contact_info[0]->ID ) ){
						$user_contact_info = $user_contact_info[0];
						if ( ! p2p_connection_exists( Rtbiz_HD_Module::$post_type . '_to_' . rtbiz_get_contact_post_type(), array( 'from' => $_POST['post_id'], 'to' => $user_contact_info->ID ) ) ) {
							rtbiz_connect_post_to_contact( Rtbiz_HD_Module::$post_type, $_POST['post_id'], $user_contact_info->ID );
							$response['status'] = true;
							$response['is_contact'] = true;
						} else {
							$response['status'] = false;
							$response['msg'] = 'Already subscribed.';
						}
					} else {
						$this->add_contacts_to_post( array( array( 'address' => $_POST['email'] ) ), $_POST['post_id'], $create_wp_user );
						$response['status'] = true;
						$response['is_contact'] = true;
					}
				}
			} else {
				$response['msg'] = 'Something went wrong.';
			}
			$can = current_user_can( rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' ) );
			if ( $response['is_contact'] || ( $can && true == $response['status'] ) ) {
				$user = get_user_by( 'email', $_POST['email'] );
				$response['avatar'] = get_avatar( $_POST['email'], 48 );
				if ( ! empty( $user ) ) {
					$response['display_name'] = $user->display_name;
				} else {
					$response['display_name'] = $_POST['email'];
				}
				global $wpdb;
				$count_names = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(comment_author_email) from '.$wpdb->comments.' where comment_post_ID= %d AND comment_author_email = %s AND comment_type= %d', $_POST['post_id'], $_POST['email'], self::$FOLLOWUP_PUBLIC ) );
				if ( empty( $count_names ) ) {
					$response['has_replied'] = false;
				} else {
					$response['has_replied'] = true;
				}
				if ( $can ) {
					$response['edit_link'] = rtbiz_hd_biz_user_profile_link( $_POST['email'] );
				} else {
					$response['edit_link'] = '#';
				}
			}
			echo json_encode( $response );
			die();
		}


		public function ajax_remove_subscriber_email(){
			global $rtbiz_hd_email_notification;
			$response = array();
			$response['status'] = false;
			if ( ! empty( $_POST['post_id'] ) && ! empty( $_POST['email'] ) ) {
				$user = get_user_by( 'email',$_POST['email'] );
				if ( $user ) {
					// remove user to subscriber
					$ticket_subscribers = get_post_meta( $_POST['post_id'], '_rtbiz_hd_subscribe_to', true );
					if ( in_array( $user->ID, $ticket_subscribers ) ) {
						unset( $ticket_subscribers[ array_search( $user->ID, $ticket_subscribers ) ] );
						update_post_meta( $_POST['post_id'], '_rtbiz_hd_subscribe_to', $ticket_subscribers );
						$rtbiz_hd_email_notification->notification_ticket_unsubscribed( $_POST['post_id'], Rtbiz_HD_Module::$post_type, array(
							array(
								'email' => $user->user_email,
								'name'  => $user->display_name
							)
						) );
						$response['status'] = true;
					}
				}

				// remove user to p2p connection
				$user_contact_info = rtbiz_get_contact_by_email( $_POST['email'] );
				$user_contact_info = $user_contact_info[0];
				if ( p2p_connection_exists( Rtbiz_HD_Module::$post_type . '_to_' . rtbiz_get_contact_post_type(), array( 'from' => $_POST['post_id'], 'to' => $user_contact_info->ID ) ) ) {
					rtbiz_clear_post_connection_to_contact( Rtbiz_HD_Module::$post_type, $_POST['post_id'], $user_contact_info );
					$response['status'] = true;
				}
			}
			echo json_encode( $response );
			die();
		}


		public function ajax_ticket_bulk_edit() {

			$post_ids = ( isset( $_POST['post_ids'] ) && ! empty( $_POST['post_ids'] ) ) ? $_POST['post_ids'] : array();
			$status = ( isset( $_POST['ticket_status'] ) && ! empty( $_POST['ticket_status'] ) ) ? $_POST['ticket_status'] : null;

			if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
				global $rtbiz_hd_module, $rtbiz_hd_email_notification, $rtbiz_hd_ticket_history_model;

				$labels = $rtbiz_hd_module->labels;
				$flag = false;

				foreach ( $post_ids as $post_id ) {

					$diff_body = '<table style="width:100%;border-collapse:collapse;border:none">';

					if ( ! empty( $status ) ) {
						// Status Diff
						$post_statuses = $rtbiz_hd_module->get_custom_statuses();
						$old_status = ucfirst( get_post_status( $post_id ) );
						$new_status = ucfirst( $_POST['ticket_status'] );
						foreach ( $post_statuses as $status ) {
							if ( ucfirst( $status['slug'] ) == $old_status ) {
								$old_status = $status['name'];
							}
							if ( ucfirst( $status['slug'] ) == $new_status ) {
								$new_status = $status['name'];
							}
						}
						$diff = rtbiz_hd_text_diff( $old_status, $new_status );
						if ( $diff ) {
							$diff_body .= '<tr><th style="padding: .5em;border: 0;"> Status </th><td>' . $diff . '</td><td></td></tr>';
							/* Insert History for status */
							$id = $rtbiz_hd_ticket_history_model->insert(
								array(
									'ticket_id'   => $post_id,
									'type'        => 'post_status',
									'old_value'   => $old_status,
									'new_value'   => $new_status,
									'update_time' => current_time( 'mysql' ),
									'updated_by'  => get_current_user_id(),
								) );
						}
						$flag = true;
					}

					if ( isset( $_POST['tax_input'] ) && ! empty( $_POST['tax_input'] ) ) {

						foreach ( $_POST['tax_input'] as $tax_slug => $tax_ids ) {
							$diff = rtbiz_hd_get_taxonomy_diff( $post_id, $tax_slug );

							$tax_info = get_taxonomy( $tax_slug );

							if ( '' != $diff ) {
								$diff_body .= '<tr><th style="padding: .5em;border: 0;">'.$tax_info->labels->name.'</th><td>' . $diff . '</td><td></td></tr>';
								$flag = true;
							}
						}
					}

					$diff_body .= '</table>';

					if ( $flag ) {
						$rtbiz_hd_email_notification->notification_ticket_updated( $post_id, $labels['name'], $diff_body, array() );
					}
				}
			}
		}

		public function ajax_add_new_ticket() {
			$result = array();
			global $rtbiz_hd_ticket_index_model;

			if ( ! isset( $_POST['nonce'] ) && ! wp_verify_nonce( $_POST['nonce'], 'rt_hd_ticket_edit' ) ) {
				$result['status'] = false;
				$result['msg'] = 'Incorrect nonce';
				echo json_encode( $result );
				die();
			}
			if ( ! isset( $_POST['post_id'] ) && ! isset( $_POST['body'] ) ) {
				$result['status'] = false;
				$result['msg'] = 'Incorrect Param';
				echo json_encode( $result );
				die();
			}
			wp_update_post( array(
				                'ID'           => $_POST['post_id'],
				                'post_content' => rtbiz_hd_content_filter( $_POST['body'] ),
			                ) );

			$dataArray = array(
				'post_id' => $_POST['post_id'],
				'post_content' => rtbiz_hd_content_filter( $_POST['body'] ),
				'date_update'     => current_time( 'mysql' ),
				'date_update_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'user_updated_by' => get_current_user_id(),
			);
			$rtbiz_hd_ticket_index_model->add_ticket( $dataArray );

			update_post_meta( $_POST['post_id'], '_rtbiz_hd_markdown_data', $_POST['body_markdown'] );
			update_post_meta( $_POST['post_id'], '_rtbiz_hd_updated_by', get_current_user_id() );

			$body = 'Ticket content updated : '. rtbiz_hd_content_filter( $_POST['body'] );
			global $rtbiz_hd_module, $rtbiz_hd_email_notification;
			$labels = $rtbiz_hd_module->labels;
			$rtbiz_hd_email_notification->notification_ticket_updated( $_POST['post_id'], $labels['name'], $body, array() );
			$result['status'] = true;
			echo json_encode( $result );
			die();
		}

		/**
		 * filer comment from admin
		 *
		 * @param $_comment1
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function filter_comment_from_admin( $_comment1 ) {
			global $hook_suffix;
			if ( in_array( $hook_suffix, array( 'edit-comments.php', 'index.php' ) ) ) {
				global $wpdb;
				if ( false === strpos( $_comment1['join'], $wpdb->posts ) ) {
					$_comment1['join'] .= " left join $wpdb->posts on {$wpdb->comments}.comment_post_id = {$wpdb->posts}.id ";
				}
				$_comment1['where'] .= " and $wpdb->posts.post_type NOT IN ( '" . Rtbiz_HD_Module::$post_type . "' ) ";
			}

			return $_comment1;
		}

		/**
		 * add new ticket
		 *
		 * @param        $title
		 * @param        $body
		 * @param        $mailtime
		 * @param        $allemail
		 * @param        $uploaded
		 * @param        $senderEmail
		 * @param string $messageid
		 * @param string $inreplyto
		 * @param string $references
		 * @param array  $subscriber
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function insert_new_ticket( $title, $body, $mailtime, $allemail, $uploaded, $senderEmail, $messageid = '', $inreplyto = '', $references = '', $subscriber = array(), $originalBody = '', $mailbox_email_address = '' ) {
			global $rtbiz_hd_module, $rtbiz_hd_tickets_operation, $rtbiz_hd_ticket_history_model, $rtbiz_hd_contacts;
			$d             = new DateTime( $mailtime );
			$timeStamp     = $d->getTimestamp();
			$post_date     = gmdate( 'Y-m-d H:i:s', ( intval( $timeStamp ) + ( get_option( 'gmt_offset' ) * 3600 ) ) );
			$post_date_gmt = gmdate( 'Y-m-d H:i:s', ( intval( $timeStamp ) ) );
			$post_type     = Rtbiz_HD_Module::$post_type;
			$labels        = $rtbiz_hd_module->labels;
			$settings      = rtbiz_hd_get_redux_settings();

			$new_all_emails = array();
			$senderName = '';
			// remove creator from all emails
			foreach ( $allemail as $email ) {
				if ( ! empty( $email['name'] ) ) {
                    $email['name'] = preg_replace( '/\s+/', ' ', $email['name'] );
					$email['name'] = preg_replace( '/\"/', '', $email['name'] );
                }
				if ( $senderEmail != $email['address'] ) {
					$new_all_emails[] = $email;
				} elseif ( ! empty( $email['name'] ) ) {
					$senderName = $email['name'];
				}
			}
			$allemail = $new_all_emails;

			$userid = $rtbiz_hd_contacts->get_user_from_email( $senderEmail, $senderName );

			if ( is_array( $body ) ){
				$markdown_body = $body['markdown'];
				$body = $body['html'];
			}

			$postArray = array(
				'post_author'   => $settings['rthd_default_user'],
				'post_content'  => rtbiz_hd_content_filter( $body ),
				'post_date'     => $post_date,
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_type'     => $post_type,
				'post_date_gmt' => $post_date_gmt,
			);

			$dataArray = array(
				'assignee'     => $postArray['post_author'],
				'post_content' => rtbiz_hd_content_filter( $postArray['post_content'] ),
				'post_status'  => $postArray['post_status'],
				'post_title'   => $postArray['post_title'],
			);

			$post_id = $rtbiz_hd_tickets_operation->ticket_default_field_update( $postArray, $dataArray, $post_type, '', $userid, $userid );
			if ( '' != $originalBody ) {
				add_post_meta( $post_id, '_rtbiz_hd_original_email_body', $originalBody );
			}
			// Updating Post Status from publish to unanswered
			$rtbiz_hd_tickets_operation->ticket_default_field_update( array( 'post_status' => 'hd-unanswered', 'post_name' => $post_id ), array( 'post_status' => 'hd-unanswered' ), $post_type, $post_id, $userid, $userid );

			$rtbiz_hd_ticket_history_model->insert( array(
				'ticket_id'   => $post_id,
				'type'        => 'post_status',
				'old_value'   => 'auto-draft',
				'new_value'   => 'hd-unanswered',
				'update_time' => current_time( 'mysql' ),
				'updated_by'  => get_current_user_id(),
			) );

			$rtbiz_hd_tickets_operation->ticket_subscribe_update( $subscriber, $postArray['post_author'], $post_id );

			$this->add_contacts_to_post( $allemail, $post_id );

			$uploaded = $this->add_attachment_to_post( $uploaded, $post_id );
			if ( ! empty( $uploaded['files'] ) ) {
				$uploaded = $uploaded['files'];
			} else {
				$uploaded = array();
			}

			update_post_meta( $post_id, '_rtbiz_hd_email', $senderEmail );

			//add markdown code
			if ( isset( $markdown_body ) && ! empty( $markdown_body ) ){
				update_post_meta( $post_id, '_rtbiz_hd_markdown_data', $markdown_body );
			}

			global $transaction_id;
			if ( isset( $transaction_id ) && $transaction_id > 0 ) {
				update_post_meta( $post_id, '_rtbiz_hd_transaction_id', $transaction_id );
			}
			if ( '' != $messageid ) {
				update_post_meta( $post_id, '_rtlib_messageid', $messageid );
			}
			if ( '' != $inreplyto ) {
				update_post_meta( $post_id, '_rtlib_inreplyto', $inreplyto );
			}
			if ( function_exists('rtmb_add_message_id_in_ref_id') ) {
				$references = rtmb_add_message_id_in_ref_id( $messageid, $references, $post_id );
			}
			if ( '' != $references ) {
				update_post_meta( $post_id, '_rtlib_references', $references );
			}
			if ( '' != $mailbox_email_address ) {
				update_post_meta( $post_id, '_rtbiz_hd_ticket_with_mailbox', $mailbox_email_address );
			}

			// Call action to add product info into ticket meta data.
			do_action( 'rtbiz_hd_add_ticket_product_info', $post_id );

			// Call action to change default assignee accoding to products
			do_action( 'rtbiz_hd_before_send_notification', $post_id, get_post( $post_id ) );

			//send Notification
			global $bulkimport, $gravity_auto_import, $rtbiz_hd_email_notification, $helpdesk_import_ticket_id;
			if ( isset( $gravity_auto_import ) && $gravity_auto_import ) {
				$helpdesk_import_ticket_id = $post_id;
				add_filter( 'gform_pre_send_email', array( &$this, 'hijack_mail_subject' ), 999, 2 );
			} else {
				/**
				 * $orignalBody will be empty in case of gravity form imports
				 * But it is taken care of by above if condition.
				 * $originalBody will also be empty if a ticket is created from default support form
				 * Hence pass $email_parse flag based on that.
				 * $email_parse = ! empty( $originalBody )
				 */
				//$rtbiz_hd_email_notification->notification_new_ticket_assigned( $post_id, $settings['rthd_default_user'], $labels['name'], $allemail, $uploaded, $email_parse = ! empty( $originalBody ) );
			}

			$rtbiz_hd_email_notification->notification_new_ticket_created( $post_id,$labels['name'], $body, $uploaded );

			do_action( 'rtbiz_hd_auto_response', $post_id, $mailtime );

			return $post_id;
		}

		/**
		 * check email subject for existence of ticket related conversation
		 *
		 * @param $data
		 * @param $message_format
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function hijack_mail_subject( $data, $message_format ) {
			global $helpdesk_import_ticket_id;
			$data['subject'] = '[' . ucfirst( Rtbiz_HD_Module::$name ) . ' #' . $helpdesk_import_ticket_id . ']' . $data['subject'];
			$hd_url          = admin_url( "post.php?post={$helpdesk_import_ticket_id}action=edit" );
			$data['message'] = str_replace( '--rtcamp_hd_link--', $hd_url, $data['message'] );

			return $data;
		}

		/**
		 * add attachment to post from mail
		 *
		 * @since 0.1
		 *
		 * @param      $uploaded
		 * @param      $post_id
		 * @param int  $comment_id
		 *
		 * @return array
		 */
		public function add_attachment_to_post( $uploaded, $post_id = '', $comment_id = 0 ) {
			global $rtbiz_hd_admin;
			$return = array();
			if ( isset( $uploaded ) ) {

				foreach ( $uploaded as $upload ) {

					//Postid is null when support page requested
					$post_attachment_hashes = '';
					if ( ! empty( $post_id ) ) {
						$post_attachment_hashes = get_post_meta( $post_id, '_rtbiz_hd_attachment_hash' );
					}

					$file_location = null;
					if ( ! is_array( $upload ) ) {
						$file_location = get_attached_file( $upload );
					} else {
						$file_location = $upload['file'];
					}

					if ( empty( $post_attachment_hashes ) || ! in_array( md5_file( $file_location ), $post_attachment_hashes ) ) {
						if ( ! is_array( $upload ) ) {
							$attachment = get_post( $upload );
							if ( ! empty( $post_id ) && $attachment->post_parent != $post_id ) {
								$attachment->post_parent = $post_id;
								wp_update_post( $attachment );
								add_post_meta( $post_id, '_rtbiz_hd_attachment_hash', md5_file( $file_location ) );
							}
							$attach_id = $upload;
						} else {

							$attachment = array(
								'post_title'     => $upload['filename'],
								'image_alt'      => $upload['filename'],
								'post_content'   => '',
								'post_excerpt'   => '',
								'post_parent'    => ! empty( $post_id ) ? $post_id : '',
								'post_mime_type' => $this->get_mime_type_from_extn( $upload['extn'] ),
								'guid'           => $upload['url'],
							);
							add_filter( 'upload_dir', array(
								$rtbiz_hd_admin,
								'custom_upload_dir',
							) );//added hook for add addon specific folder for attachment
							$attach_id = wp_insert_attachment( $attachment );
							remove_filter( 'upload_dir', array(
								$rtbiz_hd_admin,
								'custom_upload_dir',
							) );//remove hook for add addon specific folder for attachment
							add_post_meta( $attach_id, '_wp_attached_file', $file_location );
							add_post_meta( $attach_id, '_rthd_file_hash', md5_file( $upload['file'] ) );
							if ( ! empty( $post_id ) ) {
								add_post_meta( $post_id, '_rtbiz_hd_attachment_hash', md5_file( $file_location ) );
							}
						}
					} else {
						if ( ! is_array( $upload ) ) {
							$attach_id = $upload;
						} else {
							$attach = get_posts( array(
								                     'post_type' => 'attachment',
								                     'post_status' => 'any',
								                     'posts_per_page' => -1,
								                     'post_parent' => $post_id,
								                     'meta_key'     => '_rthd_file_hash',
								                     'meta_value'   => md5_file( $file_location ),
							                     ));
							if ( ! empty( $attach ) && ! empty( $attach[0] ) ) {
								$attach = $attach[0];
								$attach_id = $attach->ID;
							}
						}
					}

					if ( ! empty( $attach_id ) ) {
						$return['ids'][]   = $attach_id;
						$return['files'][] = array( 'file' => $file_location );
						if ( $comment_id > 0 ) {
							add_comment_meta( $comment_id, '_rtbiz_hd_attachment', $attach_id );
						}
					}
				}
			}
			return $return;
		}

		/**
		 * attach_contacts with post
		 *
		 * @since 0.1
		 *
		 * @param      $allemail
		 * @param      $post_id
		 * @param bool $create_wp_user
		 */
		public function add_contacts_to_post( $allemail, $post_id, $create_wp_user = false ) {
			/* @var $rtbiz_hd_contacts Rtbiz_HD_Contacts */
			global $rtbiz_hd_contacts,$rtbiz_hd_ticket_index_model;
			$ticket_creator = rtbiz_hd_get_ticket_creator( $post_id );
			$postterms = array();

			foreach ( $allemail as $email ) {
				// skip ticket creator getting added in contact list of ticket.
				if ( ! empty( $ticket_creator ) && $email['address'] == $ticket_creator->primary_email ) {
					continue;
				}
				$contacts = rtbiz_get_contact_by_email( $email );
				if ( ! empty( $contacts ) ) {
					foreach ( $contacts as $contact ) {
						$postterms[] = $contact->ID;
					}
				} else {
					$contact        = $rtbiz_hd_contacts->insert_new_contact( $email['address'], ( isset( $email['name'] ) ) ? $email['name'] : $email['address'], $create_wp_user );
					$postterms[]    = $contact->ID;
				}
			}
			$postterms = array_unique( $postterms );
			if ( ! empty( $postterms ) ) {
				$post_type = get_post_type( $post_id );
				foreach ( $postterms as $term ) {
					rtbiz_connect_post_to_contact( $post_type, $post_id, $term );
				}
				// Update Index
				$where       = array( 'post_id' => $post_id );
				$attr_name   = rtbiz_get_contact_post_type();
				$data        = array(
					$attr_name        => implode( ',', $postterms ),
					'date_update'     => current_time( 'mysql' ),
					'date_update_gmt' => gmdate( 'Y-m-d H:i:s' ),
					'user_updated_by' => get_current_user_id(),
				);
				$rtbiz_hd_ticket_index_model->update_ticket( $data, $where );
				// System Notification -- Contact added
			}
		}

		/**
		 * get post id from subject
		 *
		 * @param $subject
		 *
		 * @return int
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function get_post_id_from_subject( $subject ) {
			//\[([A-Za-z]+)\ \#([1-9]+)\]
			$pattern     = '/\[([A-Za-z]+)\ \#([0-9]+)\]/im';
			$intMatch    = preg_match_all( $pattern, $subject, $matches );
			$module_name = strtolower( Rtbiz_HD_Module::$name );
			$post_type   = Rtbiz_HD_Module::$post_type;
			if ( count( $matches ) > 0 ) {
				if ( isset( $matches[2][0] ) && isset( $matches[1][0] ) ) {
					if ( strtolower( $matches[1][0] ) == $module_name && get_post_type( intval( $matches[2][0] ) ) == $post_type ) {
						return intval( $matches[2][0] );
					}
				}
			}

			return 0;
		}

		/**
		 * Process email as ticket if it is related to ticket conversation and add ticket for that email.
		 *
		 * @param        $title
		 * @param        $body
		 * @param        $fromemail
		 * @param        $mailtime
		 * @param        $allemails
		 * @param        $uploaded
		 * @param        $mailBodyText
		 * @param bool   $check_duplicate
		 * @param bool   $userid
		 * @param string $messageid
		 * @param string $inreplyto
		 * @param string $references
		 *
		 * @param string $mailbox_email
		 * @param string $originalBody
		 *
		 * @return bool
		 * @internal param string $from_email
		 *
		 * @internal param $allemail
		 * @internal param array $subscriber
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function process_email_to_ticket(
			$title,
			$body,
			$fromemail,
			$mailtime,
			$allemails,
			$uploaded,
			$mailBodyText,
			$check_duplicate = true,
			$userid = false,
			$messageid = '',
			$inreplyto = '',
			$references = '',
			$mailbox_email = '',
			$originalBody = ''
		) {

			// check mailbox reading enable or not
			if ( empty( $mailbox_email ) || ! rtbiz_hd_is_enable_mailbox_reading() || rtbiz_hd_get_web_only_support() ) {
				if ( 'rthd_gf_import' !== $messageid ){
					return false;
				} else {
					$messageid = '';
				}
			}

			//Exclude mailbox email form all emails
			$contactEmail = array();
			if ( ! empty( $allemails ) && is_array( $allemails ) ) {
				foreach ( $allemails as $email ) {
					if ( ! rtmb_get_module_mailbox_email( $email['address'], RTBIZ_HD_TEXT_DOMAIN ) ) { //check mail is exist in mailbox or not
						$contactEmail[] = $email;
					}
				}
			}

			$allemails = $contactEmail;
			if ( rtbiz_hd_check_email_blacklisted( $fromemail['address'] ) ) {
				return false;
			}
			$emails_array = rtbiz_hd_filter_emails( $allemails );
			$subscriber = $emails_array['subscriber'];
			$allemail = $emails_array['allemail'];

			global $rtbiz_hd_contacts;

			if ( empty( $userid ) ) {
				$userid = $rtbiz_hd_contacts->get_user_from_email( $fromemail['address'] );
			} else {
				$userid = rtbiz_hd_get_contact_id_by_user_id( $userid, true );
			}
			//always true in mail cron  is use for importer
			if ( ! $check_duplicate ) {
				$success_flag = $this->insert_new_ticket( $title, $body, $mailtime, $allemail, $uploaded, $fromemail['address'], '', '', '', $subscriber, '', $mailbox_email );

				rtbiz_hd_log( 'Mail Parse Status : ' . var_export( $success_flag, true ) . "\n\r" );

				if ( ! $success_flag ) {
					foreach ( $uploaded as $u ) {
						unlink( $u['file'] );
					}
				}

				return $success_flag;
			}
			//-----------------------------------------------------------------------------//
			global $threadPostId;
			$forwardFlag = false;
			if ( ! isset( $threadPostId ) ) {
				$forwardFlag = strpos( strtolower( $title ), 'fwd:' );
				if ( false === $forwardFlag ) {
					$forwardFlag = false;
				} else {
					$forwardFlag = true;
				}

				if ( false === strpos( strtolower( $title ), 're:' ) ) {
					$replyFlag = false;
				} else {
					$replyFlag = true;
				}

				$postid = $this->get_post_id_from_subject( $title );

				rtbiz_hd_log( "POST ID FOUND FROM MAIL SUBJECT\n\r" );

				if ( ! $postid ) {
					//get postID from inreply to and refrence meta
					$postid = $this->get_post_id_from_mail_meta( $inreplyto, $references );
					rtbiz_hd_log( "POST ID FOUND FROM MAIL META\n\r" );
				}

				//if we got post id from subject
			} else {
				$postid = $threadPostId;
			}

			$dndEmails = array();

			if ( $postid && get_post( $postid ) != null ) { // if post id found from title or mail meta & mail is Re: or Fwd:
				if ( ! rtbiz_hd_get_reply_via_email() ) {
					rtbiz_hd_log( 'Mail Parse Status : ' . var_export( false, true ) . " Reply via email | false \n\r" );
					return false;
				}

				if ( $forwardFlag ) {
					$this->process_forward_email_data( $title, $body, $mailtime, $allemail, $mailBodyText, $dndEmails );
				}

				$success_flag = $this->insert_post_comment( $postid, $userid, $body, $fromemail['name'], $fromemail['address'], $mailtime, $uploaded, $allemail, $dndEmails, $messageid, $inreplyto, $references, $subscriber, $originalBody );

				rtbiz_hd_log( 'Mail Parse Status : ' . var_export( $success_flag, true ) . "\n\r" );

				if ( ! $success_flag ) {
					foreach ( $uploaded as $u ) {
						unlink( $u['file'] );
					}
				} else if ( is_numeric( $success_flag ) ) {
					$success_flag = true;
				}

				return $success_flag;
			}
			//if subject is re to post title

			if ( $replyFlag ) { // if post id not found from title or mail meta & mail is Re: or Fwd:
				rtbiz_hd_log( "MAIL IS A REPLY / FORWARD OF PREVIOUS TICKET\n\r" );
				$title       = str_replace( 'Re:', '', $title );
				$title       = str_replace( 're:', '', $title );
				$title       = trim( $title );
				$existPostId = $this->post_exists( $title, '', $fromemail['address'] ); //found title in post
				if ( ! isset( $fromemail['name'] ) ) {
					$fromemail['name'] = $fromemail['address'];
				}
				//if given post title exits then it will be add as comment other wise as post
				if ( $existPostId ) {
					if ( ! rtbiz_hd_get_reply_via_email() ) {
						rtbiz_hd_log( 'Mail Parse Status : ' . var_export( false, true ) . " Reply via email | false \n\r" );
						return false;
					}

					$success_flag = $this->insert_post_comment( $existPostId, $userid, $body, $fromemail['name'], $fromemail['address'], $mailtime, $uploaded, $allemail, $dndEmails, $messageid, $inreplyto, $references, $subscriber, $originalBody );
					rtbiz_hd_log( 'Mail Parse Status : ' . var_export( $success_flag, true ) . "\n\r" );

					if ( ! $success_flag ) {
						foreach ( $uploaded as $u ) {
							unlink( $u['file'] );
						}
					} else if ( is_numeric( $success_flag ) ) {
						$success_flag = true;
					}
					return $success_flag;
				} else {
					$success_flag = $this->insert_new_ticket( $title, $body, $mailtime, $allemail, $uploaded, $fromemail['address'], $messageid, $inreplyto, $references, $subscriber, $originalBody, $mailbox_email );
					rtbiz_hd_log( 'Mail Parse Status : ' . var_export( $success_flag, true ) . "\n\r" );

					if ( ! $success_flag ) {
						foreach ( $uploaded as $u ) {
							unlink( $u['file'] );
						}
					}
					return $success_flag;
				}
			} else { // if post id not found from title or mail meta & mail is not Re: or Fwd:
				$existPostId = $this->post_exists( $title, $mailtime );
				//if given post title exits then it will be add as comment other wise as post
				rtbiz_hd_log( 'Post Exists : '. var_export( $existPostId, true ) . "\r\n" );
				if ( ! $existPostId ) {
					$success_flag = $this->insert_new_ticket( $title, $body, $mailtime, $allemail, $uploaded, $fromemail['address'], $messageid, $inreplyto, $references, $subscriber, $originalBody, $mailbox_email );
					rtbiz_hd_log( 'Mail Parse Status : ' . var_export( $success_flag, true ) . "\n\r" );

					if ( ! $success_flag ) {
						foreach ( $uploaded as $u ) {
							unlink( $u['file'] );
						}
					}
					return $success_flag;
				} else {
					if ( ! rtbiz_hd_get_reply_via_email() ) {
						rtbiz_hd_log( 'Mail Parse Status : ' . var_export( false, true ) . " Reply via email | false \n\r" );
						return false;
					}
					$success_flag = $this->insert_post_comment( $existPostId, $userid, $body, $fromemail['name'], $fromemail['address'], $mailtime, $uploaded, $allemail, $dndEmails, $messageid, $inreplyto, $references, $subscriber, $originalBody );
					rtbiz_hd_log( 'Mail Parse Status : ' . var_export( $success_flag, true ) . "\n\r" );

					if ( ! $success_flag ) {
						foreach ( $uploaded as $u ) {
							unlink( $u['file'] );
						}
					} else if ( is_numeric( $success_flag ) ) {
						$success_flag = true;
					}
					return $success_flag;
				}
			}

			return false;
		}

		/**
		 * Get post id from email meta
		 *
		 * @param $inreplyto
		 * @param $refrences
		 *
		 * @return bool / id
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function get_post_id_from_mail_meta( $inreplyto, $refrences ) {
			if ( '' == $inreplyto && '' == $refrences ) {
				return false;
			}
			global $wpdb;
			$sql1       = "select post_id as id ,'post' as type from {$wpdb->postmeta} where ";
			$sql2       = "select comment_id as id, 'comment' as type from {$wpdb->commentmeta} where ";
			$operatorOr = '';
			if ( '' != $inreplyto ) {
				$sql1 .= " ({$wpdb->postmeta}.meta_key in('_rtlib_messageid','_rtlib_references','_rtlib_inreplyto') and {$wpdb->postmeta}.meta_value like '%{$inreplyto}%' ) ";
				$sql2 .= " ({$wpdb->commentmeta}.meta_key in('_rtlib_messageid','_rtlib_references','_rtlib_inreplyto') and {$wpdb->commentmeta}.meta_value like '%{$inreplyto}%' ) ";
			}

			$sql = $sql1 . ' union ' . $sql2;
			$result = $wpdb->get_results( $sql );
			if ( ! empty( $result ) ) {
				$row = $result[0];
				if ( 'post' == $row->type ) {
					return $row->id;
				} else {
					$comment = get_comment( $row->id );
					return $comment->comment_post_ID;
				}
			} else {
				return false;
			}
		}

		/**
		 * process forward email data
		 *
		 * @param $title
		 * @param $body
		 * @param $mailtime
		 * @param $allemail
		 * @param $mailBodyText
		 * @param $dndEmails
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function process_forward_email_data( &$title, &$body, &$mailtime, &$allemail, &$mailBodyText, &$dndEmails ) {
			$forwardArray = explode( "\r", $mailBodyText );
			$dndEmails    = array();
			foreach ( $forwardArray as $forwardline ) {
				$forwardline = strtolower( $forwardline );
				if ( ! ( false === strpos( $forwardline, 'to:' ) ) ) {
					$dndEmails = array_merge( $dndEmails, $this->extract_all_email_from_string( $forwardline ) );
				}
				if ( ! ( false === strpos( $forwardline, 'cc:' ) ) ) {
					$dndEmails = array_merge( $dndEmails, $this->extract_all_email_from_string( $forwardline ) );
				}
				if ( ! ( false === strpos( $forwardline, 'data:' ) ) ) {
					$mailtime = trim( str_replace( 'date:', '', $forwardline ) );
				}
				if ( ! ( false === strpos( $forwardline, 'title:' ) ) ) {
					$title = trim( str_replace( 'title:', '', $forwardline ) );
				}
			}

			foreach ( $dndEmails as $dndEmail ) {
				$allemail[] = array( 'address' => $dndEmail, 'name' => $dndEmail );
			}
		}

		/**
		 * Get all email from string
		 *
		 * @param $string
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function extract_all_email_from_string( $string ) {
			$pattern  = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,3})(?:\.[a-z]{2})?/i';
			$intmatch = preg_match_all( $pattern, $string, $matches );
			if ( ! empty( $matches[0] ) ) {
				return $matches[0];
			}
		}

		/**
		 * check for post existence
		 *
		 * @param        $title
		 * @param string $date
		 *
		 * @return int
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function post_exists( $title, $date = '', $sender = '' ) {
			global $wpdb, $rtbiz_hd_email_notification;
			if ( trim( $title ) == '' ) {
				return 0;
			}
			$post_title = stripslashes( sanitize_post_field( 'post_title', $title, 0, 'db' ) );
			//$post_date  = stripslashes( sanitize_post_field( 'post_date', $date, 0, 'db' ) );

			$query = "SELECT ID FROM $wpdb->posts WHERE 1=1 and post_type IN ('" . Rtbiz_HD_Module::$post_type . "') ";
			$args  = array();

			if ( ! empty( $date ) && '' != $date ) {
				$d   = new DateTime( $date );
				$UTC = new DateTimeZone( 'UTC' );
				$d->setTimezone( $UTC );
				$timeStamp = $d->getTimestamp();
				$postDate  = gmdate( 'Y-m-d H:i:s', ( intval( $timeStamp ) + ( get_option( 'gmt_offset' ) * 3600 ) ) );
				//              $sql       = "select * from $wpdb->comments where  comment_post_id=%d  and comment_date between subtime(%s,'00:10:00') and addtime(%s,'00:10:00')";
				//              $query .= " AND post_date between subtime(%s,'00:10:00') and addtime(%s,'00:10:00')";
				$args[] = $postDate;
				$args[] = $postDate;
			}

			if ( ! empty( $title ) && trim( $title ) != '' ) {
				$query .= ' AND post_title = %s';
				$args[] = $post_title;
			}

			if ( ! empty( $args ) ) {
				//return $wpdb->get_var( $wpdb->prepare( $query, $args ) );
				$postids = $wpdb->get_col( $wpdb->prepare( $query, $args ) );
				if ( ! empty( $postids ) ) {
					if ( ! empty( $sender ) ) {
						// get staff emails
						$staff_emails = array();
						$staffs = Rtbiz_HD_Utils::get_hd_rtcamp_user();
						foreach ( $staffs as $staff ) {
							$staff_emails[] = $staff->user_email;
						}
						if ( ! in_array( $sender, $staff_emails ) ){
							foreach ( $postids as $post_id ){
								// get ticket creator email for post
								$ContactEmail  = $rtbiz_hd_email_notification->get_contacts( $post_id );
								$ContactEmail  = wp_list_pluck( $ContactEmail, 'email' );
								$ticket_created_by  = rtbiz_hd_get_ticket_creator( $post_id );
								$ContactEmail[] = $ticket_created_by->primary_email;
								if ( in_array( $sender, $ContactEmail ) ){
									return $post_id;
									break;
								}
							}
						} else {
							return $postids[0];
						}
					} else {
						return $postids[0];
					}
				}
			}

			return 0;
		}

		/**
		 * Add comment to post
		 *
		 * @param            $comment_post_ID
		 * @param            $contact_id
		 * @param            $comment_content
		 * @param            $comment_author
		 * @param            $comment_author_email
		 * @param            $commenttime
		 * @param            $uploaded
		 * @param array      $allemails : All contact
		 * @param            $dndEmails
		 * @param string     $messageid
		 * @param string     $inreplyto
		 * @param string     $references
		 * @param array      $subscriber : All staff
		 * @param string     $originalBody
		 * @param string     $comment_type
		 * @param int        $comment_parent
		 *
		 * @param bool       $keep_status
		 * @param bool       $force_skip_duplicate_check
		 *
		 * @return bool
		 * @since rt-Helpdesk 0.1
		 */
		public function insert_post_comment( $comment_post_ID, $contact_id, $comment_content, $comment_author, $comment_author_email, $commenttime, $uploaded, $allemails = array(), $dndEmails, $messageid = '', $inreplyto = '', $references = '', $subscriber = array(), $originalBody = '', $comment_type = '10', $comment_parent = 0, $keep_status = false, $force_skip_duplicate_check = true, $sensitive = false ) {

			if ( ! rtbiz_hd_can_user_access( $contact_id,$comment_post_ID ) ) {
				return false;
			}
			global $rtbiz_hd_ticket_index_model;
			$post_type       = get_post_type( $comment_post_ID );
			$d               = new DateTime( $commenttime );
			$UTC             = new DateTimeZone( 'UTC' );
			$d->setTimezone( $UTC );
			$timeStamp      = $d->getTimestamp();
			$commentDate    = gmdate( 'Y-m-d H:i:s', ( intval( $timeStamp ) + ( get_option( 'gmt_offset' ) * 3600 ) ) );
			$commentDateGmt = gmdate( 'Y-m-d H:i:s', ( intval( $timeStamp ) ) );
			global $signature;
			$this->add_contacts_to_post( $allemails, $comment_post_ID );

			// markdown data
			if ( is_array( $comment_content ) ){
				$markdown_content = $comment_content['markdown'];
				$comment_content = $comment_content['html'];
			}

			$comment_content_old = $comment_content;
			$comment_content     = wp_kses_post( stripslashes( str_replace( $signature, '', $comment_content ) ) );
			$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
			$comment_author_ip = empty( $comment_author_ip ) ? ' ' : $comment_author_ip;
			$comment_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : '';
			$comment_agent = empty( $comment_agent ) ? ' ' : $comment_author;
			$user = '';
			if ( ! empty( $comment_author_email ) ) {
				$user = get_user_by( 'email', $comment_author_email );
			}

			/* auto assign flag set */
			global $rtbiz_hd_email_notification;
			$redux = rtbiz_hd_get_redux_settings();
			$autoAssingeFlag = ( isset( $redux['rthd_enable_auto_assign'] ) && 1 == $redux['rthd_enable_auto_assign'] ) ;
			$autoAssignEvent = ( isset( $redux['rthd_auto_assign_events'] ) ) ? $redux['rthd_auto_assign_events'] : '' ;
			$isFirstStaffComment = false;
			//check auto assign feature enable and followup created by staff
			if ( self::$FOLLOWUP_STAFF != $comment_type && $autoAssingeFlag && $rtbiz_hd_email_notification->is_internal_user( $comment_author_email ) ) {
				if ( 'on_first_followup' == $autoAssignEvent ) {
					$Comment = $this->get_first_staff_followup( $comment_post_ID );
					$Comment = array_filter( $Comment );
					if ( empty( $Comment ) ) {
						$isFirstStaffComment = true;
					}
				}
			}
			$user_id = rtbiz_hd_get_user_id_by_contact_id( $contact_id );
			$data                = array(
				'comment_post_ID'      => $comment_post_ID,
				'comment_author'       => is_object( $user ) ? $user->display_name : $comment_author,
				'comment_author_email' => $comment_author_email,
				'comment_author_url'   => 'http://',
				'comment_content'      => $comment_content,
				'comment_type'         => $comment_type,
				'comment_parent'       => $comment_parent,
				'user_id'              => empty( $user_id ) ? 0 : $user_id,
				'comment_author_IP'    => $comment_author_ip,
				'comment_agent'        => $comment_agent,
				'comment_date'         => $commentDate,
				'comment_date_gmt'     => $commentDateGmt,
				'comment_approved'     => 1,
			);
			if ( $this->check_duplicate_from_message_id( $messageid ) ) {
				return false;
			}
			if ( ! $force_skip_duplicate_check ) {
				$checkDupli = $this->check_duplicate_comment( $comment_post_ID, $commentDate, $comment_content, $comment_content_old );
				if ( false !== $checkDupli ) {
					if ( '' != $messageid ) {
						add_comment_meta( $checkDupli, '_rtlib_messageid', $messageid );
					}
					if ( '' != $inreplyto ) {
						add_comment_meta( $checkDupli, '_rtlib_inreplyto', $inreplyto );
					}
					if ( '' != $references ) {
						add_comment_meta( $checkDupli, '_rtlib_references', $references );
					}
					return false;
				}
			}

			$comment_id = wp_insert_comment( $data );
			update_comment_meta( $comment_id, '_rtbiz_hd_followup_author', $contact_id );

			if ( '' != $originalBody ) {
				add_comment_meta( $comment_id, '_rtbiz_hd_original_email', $originalBody );
			}
			if ( '' != $messageid ) {
				add_comment_meta( $comment_id, '_rtlib_messageid', $messageid );
			}
			if ( '' != $inreplyto ) {
				add_comment_meta( $comment_id, '_rtlib_inreplyto', $inreplyto );
			}
			if ( function_exists( 'rtmb_add_message_id_in_ref_id' ) ) {
				$references = rtmb_add_message_id_in_ref_id( $messageid, $references, $comment_post_ID );
			}
			if ( '' != $references ) {
				add_comment_meta( $comment_id, '_rtlib_references', $references );
			}

			if ( $sensitive ){
				update_comment_meta( $comment_id, '_rtbiz_hd_sensitive', true );
			}

			//add markdown code
			if ( isset( $markdown_content ) && ! empty( $markdown_content ) ){
				update_comment_meta( $comment_id, '_rtbiz_hd_markdown_data', $markdown_content );
			}

			$data  = array(
				'date_update'     => current_time( 'mysql' ),
				'date_update_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'user_updated_by' => empty( $user_id )? 0 : $user_id,
				'last_comment_id' => $comment_id,
			);
			$where = array(
				'post_id' => $comment_post_ID,
			);
			$rtbiz_hd_ticket_index_model->update_ticket( $data, $where );
			/* System Notification -- Followup Added to the ticket */

			/* Toggle Ticket Status */
			$post = get_post( $comment_post_ID );

			if ( $rtbiz_hd_email_notification->is_internal_user( $comment_author_email ) ) {
				if ( $keep_status ) {
					if ( $post->post_status != 'hd-unanswered' ) {
						wp_update_post( array( 'ID' => $comment_post_ID, 'post_status' => 'hd-unanswered' ) );
					}
				} else {
					if ( $post->post_status != 'hd-answered' ) {
						wp_update_post( array( 'ID' => $comment_post_ID, 'post_status' => 'hd-answered' ) );
					}
				}
			} else {
				if ( $post->post_status != 'hd-unanswered' ) {
					wp_update_post( array( 'ID' => $comment_post_ID, 'post_status' => 'hd-unanswered' ) );
				}
			}
			/* end of status toogle code */

			if ( isset( $allemails ) ) {
				foreach ( $allemails as $email ) {
					if ( isset( $email['key'] ) ) {
						$meta = get_comment_meta( $comment_id, '_email_' . $email['key'], true );
						if ( empty( $meta ) ) {
							update_comment_meta( $comment_id, '_email_' . $email['key'], $email['address'] );
						} else {
							update_comment_meta( $comment_id, '_email_' . $email['key'], $meta . ',' . $email['address'] );
						}
					}
				}
			}

			$subscribe_to = get_post_meta( $comment_post_ID, '_rtbiz_hd_subscribe_to', true );
			if ( ! empty( $subscriber )  ) {
				if ( empty( $subscribe_to ) ) {
					$subscribe_to = array();
				}
				foreach ( $subscriber as $sub ) {
					if ( is_email( $sub ) ) {
						$sub_userid = get_user_by( 'email', $sub );
						if ( ! empty( $sub_userid ) ) {
							$subscribe_to[] = $sub_userid->ID;
						}
					}
					// in email we are given userid of subscriber
					if ( (string)(int)$sub == $sub ){
						$sub_user = get_userdata( $sub );
						if ( ! empty( $sub_user ) ) {
							$subscribe_to[] = $sub;
						}
					}
				}
				$subscribe_to = array_unique( $subscribe_to );
			}
			update_post_meta( $comment_post_ID, '_rtbiz_hd_subscribe_to', $subscribe_to );

			/* assignee toogle code */
			//check auto assign feature enable and followup created by staff
			if ( $autoAssingeFlag && $rtbiz_hd_email_notification->is_internal_user( $comment_author_email ) ) {
				//check on 'on_first_followup' selected and its first staff followup || select 'on_any_followup'
				if ( ( 'on_first_followup' == $autoAssignEvent && $isFirstStaffComment ) || ( self::$FOLLOWUP_STAFF != $comment_type && 'on_any_followup' == $autoAssignEvent ) ) {
					wp_update_post( array( 'ID' => $comment_post_ID, 'post_author' => $user_id ) );
				}
			}
			/* end assignee toogle code */

			$uploaded = $this->add_attachment_to_post( $uploaded, $comment_post_ID, $comment_id );

			if ( ! empty( $uploaded['files'] ) ) {
				$uploaded = $uploaded['files'];
			} else {
				$uploaded = array();
			}

			global $threadPostId;
			if ( ! isset( $threadPostId ) ) {
				global $rtbiz_hd_email_notification;
				$rtbiz_hd_email_notification->notification_new_followup_added( get_comment( $comment_id ), $comment_type, $uploaded, $sensitive );
			}

			// fololowup crated by client then hook will called
			if ( ! empty( $comment_author_email )  && ! $rtbiz_hd_email_notification->is_internal_user( $comment_author_email ) ) {
				do_action( 'rtbiz_hd_auto_response', $comment_post_ID, $commenttime );
			}
			return $comment_id;
		}

		/**
		 * get first staff comment if not exist return false
		 *
		 * @param $comment_post_ID	:
		 *
		 * @return array
		 */
		public function get_first_staff_followup( $comment_post_ID ) {

			$staff = get_post_meta( $comment_post_ID, '_rtbiz_hd_subscribe_to', true );;
			$post_author_id = get_post_field( 'post_author', $comment_post_ID );
			if ( is_numeric( $post_author_id ) ) {
				$staff = array_merge( $staff, array( $post_author_id ) );
			}
			$args = array(
				'author__in' => $staff,
				'post_id' => $comment_post_ID,
				'orderby' => 'comment_date_gmt',
				'order' => 'ASC',
				'number' => '1',
			);
			$comments = get_comments( $args );
			return $comments;
		}

		/**
		 * Check for duplicate comment
		 *
		 * @param $comment_post_ID
		 * @param $comment_date
		 * @param $comment_content
		 * @param $comment_content_old
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function check_duplicate_comment( $comment_post_ID, $comment_date, $comment_content, $comment_content_old ) {
			global $wpdb;
			$sql    = "select * from $wpdb->comments where  comment_post_id=%d  and comment_date between subtime(%s,'00:10:00') and addtime(%s,'00:10:00')";
			$sql    = $wpdb->prepare( $sql, $comment_post_ID, $comment_date, $comment_date );
			$result = $wpdb->get_results( $sql );
			global $signature;
			foreach ( $result as $row ) {
				if ( trim( strip_tags( $comment_content ) ) == trim( strip_tags( $row->comment_content ) ) ) {
					return $row->comment_ID;
				} else {
					$tempStr = str_replace( trim( strip_tags( $signature ) ), '', trim( strip_tags( $comment_content_old ) ) );
					if ( trim( strip_tags( $tempStr ) ) == trim( strip_tags( $row->comment_content ) ) ) {
						return $row->comment_ID;
					}
				}
			}

			return false;
		}

		/**
		 * Check duplicate message from message ID
		 *
		 * @param $messageid
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function check_duplicate_from_message_id( $messageid ) {
			global $wpdb;
			if ( $messageid && trim( $messageid ) == '' ) {
				return false;
			}

			$sql    = $wpdb->prepare( "select meta_value from $wpdb->commentmeta where $wpdb->commentmeta.meta_key = '_rtlib_messageid' and $wpdb->commentmeta.meta_value = %s", $messageid );
			$result = $wpdb->get_results( $sql );
			if ( empty( $result ) ) {

				$sql    = $wpdb->prepare( "select meta_value from $wpdb->postmeta where $wpdb->postmeta.meta_key = '_rtlib_messageid' and $wpdb->postmeta.meta_value = %s", $messageid );
				$result = $wpdb->get_results( $sql );

				return ! empty( $result );
			} else {
				return ! empty( $result );
			}
		}

		/**
		 * Send new mail as someone post new comment
		 *
		 * @param $comment_id
		 *
		 * @since rt-Helpdesk 0.1
		 * @return bool
		 */
		public function mail_new_comment_data( $comment_id ) {
			if ( ! $this->check_setting_for_new_followup_email( ) ) {
				return false;
			}
			if ( isset( $_REQUEST['commentSendAttachment'] ) && '' != $_REQUEST['commentSendAttachment'] ) {
				$arrAttache = explode( ',', $_REQUEST['commentSendAttachment'] );
				foreach ( $arrAttache as $strAttach ) {
					add_comment_meta( $comment_id, '_rtbiz_hd_attachment', intval( $strAttach ) );
				}
			}
			$this->mail_comment_data( $comment_id, array(), '', array() );
		}

		/**
		 *  mail comment data
		 *
		 * @param       $comment_id
		 * @param       $uploaded
		 * @param       $senderemail
		 * @param       $allemails
		 * @param array $dndEmails
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function mail_comment_data( $comment_id, $uploaded, $senderemail, $allemails, $dndEmails = array() ) {
			if ( ! is_object( $comment_id ) ) {
				return;
			}
			$comment         = get_comment( $comment_id );
			$comment_post_ID = $comment->comment_post_ID;

			if ( get_post_type( $comment_post_ID ) != Rtbiz_HD_Module::$post_type ) {
				return true;
			}

			$subject = rtbiz_hd_create_new_ticket_title( 'rthd_new_followup_email_title', $comment_post_ID );

			$mailbody = rtbiz_hd_content_filter( $comment->comment_content );

			//commentSendAttachment
			$attachment = array();
			if ( isset( $_REQUEST['commentSendAttachment'] ) && '' != $_REQUEST['commentSendAttachment'] ) {
				$arrAttache = explode( ',', $_REQUEST['commentSendAttachment'] );
				foreach ( $arrAttache as $strAttach ) {
					$attachfile = get_attached_file( intval( $strAttach ) );
					if ( $attachfile ) {
						$attachment[] = $attachfile;
					}
				}
			} else {
				$_REQUEST['commentSendAttachment'] = '';
			}

			if ( isset( $uploaded ) && is_array( $uploaded ) && ! empty( $uploaded ) ) {
				foreach ( $uploaded as $upload ) {
					$attachment[] = $upload['file'];
				}
			}

			$toEmail = array();
			if ( isset( $_POST['helpdesk-email-to'] ) ) {
				foreach ( $_POST['helpdesk-email-to'] as $key => $email ) {
					$toEmail[] = array( 'email' => $email, 'name' => '' );
				}
			}
			$ccEmail = array();
			if ( isset( $_POST['helpdesk-email-cc'] ) ) {
				foreach ( $_POST['helpdesk-email-cc'] as $key => $email ) {
					$ccEmail[] = array( 'email' => $email, 'name' => '' );
				}
			}
			$bccEmail = array();
			if ( isset( $_POST['helpdesk-email-bcc'] ) ) {
				foreach ( $_POST['helpdesk-email-bcc'] as $key => $email ) {
					$bccEmail[] = array( 'email' => $email, 'name' => '' );
				}
			}

			if ( isset( $_POST['comment-reply-from'] ) ) {
				$fromemail = $_POST['comment-reply-from'];
				//to set default email
			}
			if ( empty( $toEmail ) && empty( $ccEmail ) && empty( $bccEmail ) ) {
				return false;
			}

			global $rtbiz_hd_email_notification;
			$title = $rtbiz_hd_email_notification->get_email_title( $comment_post_ID );
			return $rtbiz_hd_email_notification->insert_new_send_email( $subject, rtbiz_hd_get_general_body_template( $mailbody, $title , $comment_post_ID, true ), $toEmail, $ccEmail, $bccEmail, $attachment, $comment_id, 'comment' );
		}

		/**
		 * Get mine type from extension of file
		 *
		 * @param $strFileType
		 *
		 * @return string
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function get_mime_type_from_extn( $strFileType ) {
			$ContentType = 'application/octet-stream';
			$strFileType = strtolower( '.' . $strFileType );
			if ( '.asf' == $strFileType ) {
				$ContentType = 'video/x-ms-asf';
			}
			if ( '.avi' == $strFileType ) {
				$ContentType = 'video/avi';
			}
			if ( '.doc' == $strFileType ) {
				$ContentType = 'application/msword';
			}
			if ( '.zip' == $strFileType ) {
				$ContentType = 'application/zip';
			}
			if ( '.xls' == $strFileType ) {
				$ContentType = 'application/vnd.ms-excel';
			}
			if ( '.gif' == $strFileType ) {
				$ContentType = 'image/gif';
			}
			if ( '.jpg' == $strFileType || 'jpeg' == $strFileType ) {
				$ContentType = 'image/jpeg';
			}
			if ( '.wav' == $strFileType ) {
				$ContentType = 'audio/wav';
			}
			if ( '.mp3' == $strFileType ) {
				$ContentType = 'audio/mpeg3';
			}
			if ( '.mpg' == $strFileType || 'mpeg' == $strFileType ) {
				$ContentType = 'video/mpeg';
			}
			if ( '.rtf' == $strFileType ) {
				$ContentType = 'application/rtf';
			}
			if ( '.htm' == $strFileType || 'html' == $strFileType ) {
				$ContentType = 'text/html';
			}
			if ( '.xml' == $strFileType ) {
				$ContentType = 'text/xml';
			}
			if ( '.xsl' == $strFileType ) {
				$ContentType = 'text/xsl';
			}
			if ( '.css' == $strFileType ) {
				$ContentType = 'text/css';
			}
			if ( '.php' == $strFileType ) {
				$ContentType = 'text/php';
			}
			if ( '.asp' == $strFileType ) {
				$ContentType = 'text/asp';
			}
			if ( '.pdf' == $strFileType ) {
				$ContentType = 'application/pdf';
			}

			return $ContentType;
		}


		public function process_file_attachment($file) {
			if ( UPLOAD_ERR_OK !== $_FILES[ $file ]['error'] ) { __return_false(); }
			require_once( ABSPATH . 'wp-admin' . '/includes/image.php' );
			require_once( ABSPATH . 'wp-admin' . '/includes/file.php' );
			require_once( ABSPATH . 'wp-admin' . '/includes/media.php' );
			$attachment_id = media_handle_upload( $file, '' );
			return $attachment_id;
		}

		/**
		 * Add follow up of tickets on front end
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function ajax_add_new_followup_front() {
			$returnArray     = array();
			$returnArray['status']  = false;

			if ( ! is_user_logged_in() ) {
				$returnArray['message'] = 'ERROR: please login to continue.';
				echo json_encode( $returnArray );
				die( 0 );
			}

			if ( ! isset( $_POST['followup_content'] ) || empty( $_POST['followup_content'] ) ) {
				$returnArray['status']  = false;
				$returnArray['message'] = 'ERROR: please type a comment.';
				echo json_encode( $returnArray );
				die( 0 );
			}

			if ( ! isset( $_POST['followuptype'] ) ) {
				$returnArray['message'] = 'ERROR: Invalid Request';
				echo json_encode( $returnArray );
				die( 0 );
			}
			if ( ! isset( $_POST['followup_ticket_unique_id'] ) ) {
				$returnArray['message'] = 'ERROR: Invalid Ticket.';
				echo json_encode( $returnArray );
				die( 0 );
			}

			$rthd_ticket = $this->get_ticket_from_ticket_unique_id( $_POST['followup_ticket_unique_id'] );
			$comment_post_ID = $rthd_ticket->ID;
			$comment_content['markdown'] = $_POST['followup_markdown'];
			$comment_content['html'] = rtbiz_hd_content_filter( $_POST['followup_content'] );

			$user = wp_get_current_user();
			$userid = $comment_author = $comment_author_email = '';

			if ( $user->exists() ) {
				$userid              = $user->ID;
				$comment_author       = esc_sql( $user->display_name );
				$comment_author_email = esc_sql( $user->user_email );
			}

			$comment_type                   = $_POST['followuptype'];
			$sensitive                      = ( "true" === $_POST['private_comment'] ) ? true : false;
			$comment_parent                 = 0;

			if ( isset( $_REQUEST['follwoup-time'] ) && '' != $_REQUEST['follwoup-time'] ) {
				$d                           = new DateTime( $_REQUEST['follwoup-time'] );
				$commenttime = $d->format( 'Y-m-d H:i:s' );
				//$commentdata['comment_date_gmt'] = $d->format( 'Y-m-d H:i:s' );
			} else {
				$commenttime     = current_time( 'mysql', 1 );
				//$commentdata['comment_date_gmt'] = current_time( 'mysql', 1 );
			}
			$allemail = array();
			$dndEmails = array();
			$subscriber = array();

			$rtCampUser = Rtbiz_HD_Utils::get_hd_rtcamp_user();
			$hdUser     = array();
			foreach ( $rtCampUser as $rUser ) {
				$hdUser[ $rUser->user_email ] = $rUser->ID;
			}

			// add followup creator into contact or subscribers

			if ( ! array_key_exists( $comment_author_email, $hdUser ) ) {
				if ( ! empty( $black_list_emails ) ) {
					foreach ( $black_list_emails as $email ) {
						if ( ! preg_match( '/'.str_replace( '*','\/*',$email ).'/',  $comment_author_email ) ) {
							$allemail[] = array( 'address' => $comment_author_email, 'name' => $comment_author, 'key' => 'to' );
						} else {
							$returnArray['message'] = 'ERROR: You are blacklisted for this system';
							echo json_encode( $returnArray );
							die( 0 );
						}
					}
				} else {
					$allemail[] = array( 'address' => $comment_author_email, 'name' => $comment_author, 'key' => 'to' );
				}
			} else {
				$subscriber[] = $comment_author_email;
			}

			$keep_status = false;
			if ( isset( $_POST['rthd_keep_status'] ) && ! empty( $_POST['rthd_keep_status'] ) && 'true' == $_POST['rthd_keep_status'] ) {
				$keep_status = true;
			}

			if ( self::$FOLLOWUP_STAFF == $comment_type ){
				$keep_status = true;
			}

			$uploaded = explode( ',', $_POST['followup_attachments'] );
			//          $force_duplicate = false;
			//          if ( ! empty( $_POST['followup_duplicate_force'] ) ) {
				$force_duplicate = true;
			//          }
			$contact_id = rtbiz_hd_get_contact_id_by_user_id( $userid, true );
			$comment_ID = $this->insert_post_comment( $comment_post_ID, $contact_id , $comment_content, $comment_author, $comment_author_email, $commenttime, array_filter( $uploaded ), $allemail, $dndEmails, '', '', '', $subscriber, '', $comment_type, $comment_parent, $keep_status, $force_duplicate, $sensitive );

			if ( empty( $comment_ID ) ) {
				$returnArray['status'] = false;
				$returnArray['message'] = 'Something went wrong please contact admin.';
			} else {
				$returnArray['status'] = true;
				$returnArray['comment_count'] = get_comments(
					array(
						'order'     => 'DESC',
						'post_id'   => $comment_post_ID,
						'post_type' => $rthd_ticket->post_type,
						'count'     => true,
					) );

					$returnArray['comment_id'] = $comment_ID;
					$returnArray['comment_type']       = $comment_type;
					$current_user_contact_id = rtbiz_hd_get_contact_id_by_user_id( get_current_user_id() );
					$comment_render_type = 'left';
					$cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' );
					$user_edit = current_user_can( $cap ) || ( $current_user_contact_id == $contact_id );
					$comment = get_comment( $comment_ID );
					$returnArray['comment_content'] = rtbiz_hd_render_comment( $comment, $user_edit, $comment_render_type, false );
				    // do some logic
					$returnArray['assign_value'] = get_post_field( 'post_author', $comment_post_ID, 'raw' );
					if ( current_user_can( $cap ) ) {
						$commentlink = '<a href="'.rtbiz_hd_biz_user_profile_link( $comment->comment_author_email ).'" >'.$comment->comment_author.'</a>';
						$returnArray['post_status'] = get_post_field( 'post_status', $comment_post_ID, 'raw' );
					} else {
						$commentlink = $comment->comment_author;
						$returnArray['post_status'] = rtbiz_hd_status_markup( get_post_field( 'post_status', $comment_post_ID, 'raw' ) );
					}
					$returnArray['last_reply'] = esc_attr( human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) ) ) . ' ago by ' . $commentlink;

					do_action( 'rt_hd_ajax_after_new_ticket_followup', $returnArray, $comment_post_ID );
			}
			echo json_encode( $returnArray );
			ob_end_flush();
			die( 0 );
		}

		public function get_ticket_from_ticket_unique_id( $ticket_unique_id ) {
			$args             = array(
				'meta_key'    => '_rtbiz_hd_unique_id',
				'meta_value'  => $ticket_unique_id,
				'post_status' => 'any',
				'post_type'   => Rtbiz_HD_Module::$post_type,
			);
			$ticketpost       = get_posts( $args );
			if ( empty( $ticketpost ) ) {
				return false;
			}
			if ( is_array( $ticketpost ) ) {
				$ticketpost = $ticketpost[0];
			}
			return $ticketpost;
		}

		/**
		 * add new followup on AJAX
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function ajax_update_followup() {
			global $rtbiz_hd_email_notification;

			$returnArray     = array();
			$returnArray['status']  = false;
			if ( ! is_user_logged_in() ) {
				$returnArray['message'] = 'ERROR: please login to continue.';
				echo json_encode( $returnArray );
				die( 0 );
			}
			if ( ! isset( $_POST['comment_id'] ) || intval( $_POST['comment_id'] ) <= 0 ) {
				$returnArray['message'] = 'ERROR: Invalid comment ID.';
				echo json_encode( $returnArray );
				die( 0 );
			}
			if ( ! ( isset( $_POST['followup_post_id'] ) && get_post_type( intval( $_POST['followup_post_id'] ) ) == $_POST['post_type'] ) ) {
				$returnArray['message'] = 'ERROR: Invalid Post ID.';
				echo json_encode( $returnArray );
				die( 0 );
			}
			if ( '' == $_POST['followup_content'] ) {
				$returnArray['message'] = 'ERROR: please type a comment.';
				echo json_encode( $returnArray );
				die( 0 );
			}

			$comment_post_ID = $_POST['followup_post_id'];
			$post_type       = get_post_type( $comment_post_ID );
			$comment_content = rtbiz_hd_content_filter( $_POST['followup_content'] );
			$comment_type = $_POST['followuptype'];
			$sensitive = "true" === $_POST['private_comment'] ? true : false;

			$user = wp_get_current_user();
			if ( $user->exists() ) {
				$user_id              = $user->ID;
				$comment_author       = esc_sql( $user->display_name );
				$comment_author_email = esc_sql( $user->user_email );
			}

			$commentdata = get_comment( $_POST['comment_id'], ARRAY_A );
//			$contact_id              = get_comment_meta( $_POST['comment_id'], '_rtbiz_hd_followup_author', true );
			$cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' );
			$current_user_contact_id = rtbiz_hd_get_contact_id_by_user_id( get_current_user_id() );
			$contact_id              = get_comment_meta( $_POST['comment_id'], '_rtbiz_hd_followup_author', true );
			$user_edit = current_user_can( $cap ) || ( ! empty( $contact_id ) && $current_user_contact_id == $contact_id );
			if ( ! $user_edit ) {
				$returnArray['message'] = 'ERROR: Unauthorized Access';
				echo json_encode( $returnArray );
				die( 0 );
			}
			$oldCommentBody = $commentdata['comment_content'];
			$commentdata['comment_content'] = wp_kses_post( stripslashes( $comment_content ) );

			$oldDate                        = $commentdata['comment_date'];
			$newDate                        = '';
			if ( isset( $_REQUEST['follwoup-time'] ) && '' != $_REQUEST['follwoup-time'] ) {
				$d                           = new DateTime( $_REQUEST['follwoup-time'] );
				$UTC                         = new DateTimeZone( 'UTC' );
				$commentdata['comment_date'] = $d->format( 'Y-m-d H:i:s' );
				$newDate                     = $commentdata['comment_date'];
				$d->setTimezone( $UTC );
				$commentdata['comment_date_gmt'] = $d->format( 'Y-m-d H:i:s' );
			} else {
				$newDate = current_time( 'mysql', 1 );
			}

			$oldtype = $commentdata['comment_type'];
			$commentdata['comment_type'] = $comment_type;

			wp_update_comment( $commentdata ); //update comment
			//todo: remove below line when comment wordpress start supporting comment_type edit
			rtbiz_hd_edit_comment_type( $commentdata['comment_ID'], $comment_type );

			if ( $sensitive ){
				update_comment_meta( $commentdata['comment_ID'], '_rtbiz_hd_sensitive', true );
			} else {
				delete_comment_meta( $commentdata['comment_ID'], '_rtbiz_hd_sensitive' );
			}

			if ( $user_id ){
				update_comment_meta( $commentdata['comment_ID'], '_rtbiz_hd_comment_update_by', $user_id );
			}

			//update markdown content
			$markdown_content = isset( $_POST['followup_markdown'] ) && ! empty( $_POST['followup_markdown'] ) ? $_POST['followup_markdown'] : '';
			update_comment_meta( $commentdata['comment_ID'], '_rtbiz_hd_markdown_data', $markdown_content );

			$uploaded = array();
			$attachment = array();
			$comment = get_comment( $_POST['comment_id'] );
			if ( isset( $_REQUEST['attachemntlist'] ) && ! empty( $_REQUEST['attachemntlist'] ) ) {
				delete_comment_meta( $_POST['comment_id'], '_rtbiz_hd_attachment' );
				foreach ( $_REQUEST['attachemntlist'] as $strAttach ) {
					$attachfile = get_attached_file( intval( $strAttach ) );
					if ( $attachfile ) {
						$attachment[] = $attachfile;
						$extn_array   = explode( '.', $attachfile );
						$extn         = $extn_array[ count( $extn_array ) - 1 ];
						$file_array   = explode( '/', $attachfile );
						$fileName     = $file_array[ count( $file_array ) - 1 ];
						$uploaded[]   = array(
							'filename'      => $fileName,
							'extn'          => $extn,
							'url'           => wp_get_attachment_url( intval( $strAttach ) ),
							'file'          => $attachfile,
						);
					}
				}
				$uploaded = $this->add_attachment_to_post( $uploaded, $comment_post_ID, $_POST['comment_id'] );
				if ( ! empty( $uploaded['files'] ) ) {
					$uploaded = $uploaded['files'];
				} else {
					$uploaded = array();
				}
			}

			$rtbiz_hd_email_notification->notification_followup_updated( $comment, get_current_user_id(), $oldtype, $comment_type, $oldCommentBody, $commentdata['comment_content'], $sensitive );

			$returnArray['status']        = true;
			$returnArray['comment_type']       = $comment->comment_type;
			$comment_render_type = 'left';
			$cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' );
			if ( ! empty( $comment_user ) ) {
				if ( $comment_user->has_cap( $cap ) ) {
					$comment_render_type = 'right';
				}
			}
			clean_comment_cache( $comment->comment_ID );
			$current_user_contact_id = rtbiz_hd_get_contact_id_by_user_id( get_current_user_id() );
			$contact_id              = get_comment_meta( $_POST['comment_id'], '_rtbiz_hd_followup_author', true );
			$user_edit = current_user_can( $cap ) || ( ! empty( $contact_id ) && $current_user_contact_id == $contact_id );
			$returnArray['comment_content'] = rtbiz_hd_render_comment( get_comment( $comment->comment_ID ), $user_edit, $comment_render_type, false );
			echo json_encode( $returnArray );
			die( 0 );
		}

		/**
		 * check for if sending of email is Allowed from sendmail
		 *
		 * @param $email
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function is_allow_to_sendemail_fromemail( $email ) {
			$user_id = get_current_user_id();
			global $wpdb, $rt_mail_accounts_model, $rtbiz_hd_mail_acl_model;
			$sql    = $wpdb->prepare( '(select * from {$rt_mail_accounts_model->table_name} where user_id=%d and email = %s)
								union (select a.* from {$rtbiz_hd_mail_acl_model->table_name} b inner join
								{$rt_mail_accounts_model->table_name} a on a.email=b.email where b.allow_user=%d and a.email=%s)', $user_id, $email, $user_id, $email );
			$result = $wpdb->get_results( $sql );
			if ( $result && ! empty( $result ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * remove follow up AJAX
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function ajax_delete_followup() {
			global $rtbiz_hd_email_notification;
			$response = array();
			if ( ! isset( $_POST['comment_id'] ) ) {
				die( 0 );
			}
			$comment          = get_comment( $_POST['comment_id'] );
			$attachments_urls = get_comment_meta( $_POST['comment_id'], '_rtbiz_hd_attachment' );
			$sensitive = get_comment_meta( $comment->comment_ID, '_rtbiz_hd_sensitive', true ) == 1 ? true : false;
			$attachments = get_children( array( 'post_parent' => $comment->comment_post_ID, 'post_type' => 'attachment' ) );
			if ( ! empty( $attachments ) && ! empty( $attachments_urls ) ) {
				foreach ( $attachments as $att ) {
					if ( in_array( wp_get_attachment_url( $att->ID ),$attachments_urls ) ) {
						wp_delete_attachment( $att->ID, true );
					}
				}
			}
			$response['status'] = wp_delete_comment( $_POST['comment_id'], true );
			$rtbiz_hd_email_notification->notification_followup_deleted( $comment, get_current_user_id(), $sensitive );
			echo json_encode( $response );
			die( 0 );
		}

		/**
		 * Request import for gmail thread
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function gmail_thread_import_request() {
			$response = array();
			if ( ! isset( $_POST['post_id'] ) ) {
				$response['false']   = true;
				$response['message'] = 'Invalid Post ID';
				echo json_encode( $response );
				die( 0 );
			}

			if ( get_post_type( $_POST['post_id'] ) != Rtbiz_HD_Module::$post_type ) {
				$response['false']   = true;
				$response['message'] = 'Invalid Post Type';
				echo json_encode( $response );
				die( 0 );
			}

			global $rt_mail_settings;
			$signature    = '';
			$email_type   = '';
			$imap_server  = '';
			$access_token = $rt_mail_settings->get_accesstoken_from_email( $_POST['email'], $signature, $email_type, $imap_server );

			if ( 'goauth' != $email_type ) {
				$response['false']   = true;
				$response['message'] = 'Email is registered with invalid type. Thread Importing is only allowed via Google Oauth Emails.';
				echo json_encode( $response );
				die( 0 );
			}

			$result = $this->insert_import_mail_thread_request( $_POST['email'], $_POST['thread_id'], $_POST['post_id'] );
			if ( ! empty( $result ) ) {
				$response['status'] = true;
			} else {
				$response['false']   = true;
				$response['message'] = 'Error in inserting request, Please try again';
			}
			echo json_encode( $response );
			die( 0 );
		}

		/**
		 * insert import mail thread request
		 *
		 * @param     $email
		 * @param     $threaid
		 * @param     $post_id
		 * @param int $userid
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function insert_import_mail_thread_request( $email, $threaid, $post_id, $userid = 0 ) {
			if ( 0 == $userid ) {
				$userid = get_current_user_id();
			}
			$args = array(
				'email'    => $email,
				'threadid' => $threaid,
				'post_id'  => $post_id,
				'user_id'  => $userid,
				'status'   => 'r',
			);
			global $rt_mail_thread_importer_model;
			$rows_affected = $rt_mail_thread_importer_model->add_thread( $args );

			return $rows_affected;
		}
		public function check_setting_for_new_followup_email() {
			$redux = rtbiz_hd_get_redux_settings();
			if ( isset( $redux['rthd_notification_events']['new_comment_added'] ) && 1 != $redux['rthd_notification_events']['new_comment_added'] ) {
				return false;
			}
			return true;
		}

		public function ajax_load_more_followup() {
			$response = array();
			$response['status'] = false;
			if ( isset( $_REQUEST['post_id'] ) && isset( $_REQUEST['offset'] ) && isset( $_REQUEST['limit'] ) ) {
				$postid = $_REQUEST['post_id'];
				$offset = $_REQUEST['offset'];
				$Limit = $_REQUEST['limit'];
				//				if ( ! isset($_POST['getall']) && $_POST['all']!='true') {
				//					$offset = $offset + $Limit;
				//				}
			} else {
				echo json_encode( $response );
				die();
			}

			$comments = get_comments( array(
	              'post_id' => $postid,
	              'status'  => 'approve',
	              'order'   => 'ASC',
	              'number' => $Limit,
	              'offset' => $offset,
	          ) );
			//          $user_edit = current_user_can( rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'editor' ) );
			$commenthtml = '';
			$count = 0;
			$cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' );
			$current_user = wp_get_current_user();
			$current_user_contact_id = rtbiz_hd_get_contact_id_by_user_id( $current_user );
			foreach ( $comments as $comment ) {
				$comment_render_type = 'left';
				$contact_id              = get_comment_meta( $comment->comment_ID, '_rtbiz_hd_followup_author', true );
				$user_edit = current_user_can( $cap ) || ( ! empty( $contact_id ) && $current_user_contact_id == $contact_id );
				$commenthtml .= rtbiz_hd_render_comment( $comment, $user_edit, $comment_render_type, false );
				$count++;
			}

			$placeholder = '';
			if ( ! ( $count < $Limit ) ) {
				$placeholder = '<div class="content-stream stream-loading js-loading-placeholder"><img id="load-more-hdspinner" class="js-loading-placeholder" src="' . admin_url() . 'images/spinner.gif' . '" /></div>';
			}

			$response['offset'] = $offset;
			$response['comments'] = $commenthtml;
			$response['placeholder'] = $placeholder;
			$response['status'] = true;
			echo json_encode( $response );
			die();
		}

		public function ajax_front_end_status_change() {
			$response = array();
			$response['status'] = false;
			$post_id = $_POST['post_id'];
			$old = get_post_status( $post_id );
			$post_status = $_POST['post_status'];
			if ( $post_id ) {
				$ticket = array(
					'ID'          => $post_id,
					'post_status' => $post_status,
					);
				wp_update_post( $ticket );
			}
			$response['stauts_markup'] = rtbiz_hd_status_markup( $post_status );
			$response['status'] = true;
			global $rtbiz_hd_module;
			$labels = $rtbiz_hd_module->labels;
			rtbiz_hd_update_ticket_updated_by_user( $post_id, get_current_user_id() );

			do_action( 'rt_hd_ajax_front_end_after_ticket_status_changed', $post_id, $old, $post_status );

			global $rtbiz_hd_email_notification;
			$body = $labels['name'].' Status changed from <strong>'.rtbiz_hd_status_markup( $old ).'</strong> to <strong>'.rtbiz_hd_status_markup( $post_status ).'</strong>.';
			$rtbiz_hd_email_notification->notification_ticket_updated( $post_id, $labels['name'], $body, array() );
			echo json_encode( $response );
			die();
		}

		/**
		 * Change ticket assignee. Request come from front end.
		 */
		public function ajax_front_end_assignee_change() {
			$response = array();
			$response['status'] = false;
			$post_id = $_POST['post_id'];
			$old_post = get_post( $post_id );
			$new_assignee = $_POST['post_author'];
			global $rtbiz_hd_ticket_index_model;
			if ( $old_post->post_author != $new_assignee ) {
				if ( $post_id ) {
					$ticket = array(
						'ID'          => $post_id,
						'post_author' => $new_assignee,
						);
					wp_update_post( $ticket );
					$rtbiz_hd_ticket_index_model->update_ticket_assignee( $new_assignee, $post_id );
				}

				$response['status'] = true;
				global $rtbiz_hd_module;

				$labels = $rtbiz_hd_module->labels;
				rtbiz_hd_update_ticket_updated_by_user( $post_id, get_current_user_id() );

				do_action( 'rt_hd_ajax_front_end_after_ticket_assignee_changed', $post_id, $old_post->post_author, $new_assignee );

				global $rtbiz_hd_email_notification;

				$rtbiz_hd_email_notification->notification_new_ticket_reassigned( $post_id, $old_post->post_author, $new_assignee, $labels['name'] );
			}
			echo json_encode( $response );
			die();
		}

		/**
		 * User can watch/unwatch ticket.
		 */
		public function ajax_front_end_watch_unwatch() {
			global $rtbiz_hd_email_notification;

			$cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' );
			$current_user_id = get_current_user_id();
			$current_user = get_user_by( 'id', $current_user_id );

			$response = $subscriber_info = array();
			$response['status'] = false;

			$post_id = $_POST['post_id'];
			$post_type = get_post_type( $post_id );
			$watch_unwatch = $_POST['watch_unwatch'];

			$subscriber_info[] = array( 'email' => $current_user->user_email, 'name' => $current_user->display_name );

			if ( 'watch' == $watch_unwatch ) {
				if ( current_user_can( $cap ) ) {
					// Add user to ticket subcriber list.
					$ticket_subscribers = get_post_meta( $post_id, '_rtbiz_hd_subscribe_to', true );

					$ticket_subscribers[] = $current_user_id;

					update_post_meta( $post_id, '_rtbiz_hd_subscribe_to', $ticket_subscribers );

					$rtbiz_hd_email_notification->notification_ticket_subscribed( $post_id, $post_type, $subscriber_info );
				} else {
					// Add user to ticket contact list.
					$user_contact_info = rtbiz_get_contact_by_email( $current_user->user_email );

					rtbiz_connect_post_to_contact( $post_type, $post_id, $user_contact_info );
				}

				$response['status'] = true;
				$response['label'] = 'Unsubscribe notifications from this ticket';
				$response['value'] = 'unwatch';
			} else if ( 'unwatch' == $watch_unwatch ) {
				if ( current_user_can( $cap ) ) {
					// Remove user from ticket subcriber list.
					$ticket_subscribers = get_post_meta( $post_id, '_rtbiz_hd_subscribe_to', true );
					$new_ticket_subscribers = array();

					foreach ( $ticket_subscribers as $ticket_subscriber ) {
						if ( $ticket_subscriber != $current_user_id ) {
							$new_ticket_subscribers[] = $ticket_subscriber;
						}
					}
					update_post_meta( $post_id, '_rtbiz_hd_subscribe_to', $new_ticket_subscribers );

					$rtbiz_hd_email_notification->notification_ticket_unsubscribed( $post_id, $post_type, $subscriber_info );
				} else {
					// Remove user from ticket contact list.
					$user_contact_info = rtbiz_get_contact_by_email( $current_user->user_email );

					rtbiz_clear_post_connection_to_contact( $post_type, $post_id, $user_contact_info );
				}

				$response['status'] = true;
				$response['label'] = 'Subscribe for notifications from this ticket';
				$response['value'] = 'watch';
			}

			echo json_encode( $response );
			die();
		}
	}

}
