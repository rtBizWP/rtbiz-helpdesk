<?php
/**
 * Don't load this file directly!
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Rt_HD_Contacts
 *
 * @author udit
 *
 * @since rt-Helpdesk 0.1
 */
if ( !class_exists( 'Rt_HD_Contacts' ) ) {

	/**
	 * Class Rt_HD_Contacts
	 */
	class Rt_HD_Contacts {

		/**
		 * @var string
		 */
		public $email_key = 'contact_email';
		/**
		 * @var string
		 */
		public $user_id = 'contact_user_id';
		/**
		 * @var string
		 */
		public $user_role = 'contacts';

		public function __construct() {
			$this->hooks();
		}

		/**
		 * Hooks
		 * @since rt-Helpdesk 0.1
		 */
		function hooks() {

			add_filter( 'rt_entity_columns', array( $this, 'contacts_columns' ), 10, 2 );
			add_action( 'rt_entity_manage_columns', array( $this, 'manage_contacts_columns' ), 10, 3 );

			add_action( 'wp_ajax_rthd_search_contact', array( $this, 'contact_autocomplete_ajax' ) );
			add_action( 'wp_ajax_rthd_get_term_meta', array( $this, 'get_taxonomy_meta_ajax' ) );

			add_action( 'wp_ajax_rthd_get_account_contacts', array( $this, 'get_account_contacts_ajax' ) );
			add_action( 'wp_ajax_rthd_add_contact', array( $this, 'add_new_contact_ajax' ) );
		}

		/**
		 * Create custom column 'Tickets' for Contacts taxonomy
		 *
		 * @param $columns
		 * @param $rt_entity
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function contacts_columns( $columns, $rt_entity ) {

			global $rt_person;
			if ( $rt_entity->post_type != $rt_person->post_type ) {
				return $columns;
			}

			global $rt_hd_module;
			if ( in_array( Rt_HD_Module::$post_type, array_keys( $rt_entity->enabled_post_types ) ) ) {
				$columns[Rt_HD_Module::$post_type] = $rt_hd_module->labels['name'];
			}

			return $columns;
		}

		/**
		 * Get count of contact terms used in individual ticket. This function returns the exact count
		 *
		 * @param $column
		 * @param $post_id
		 * @param $rt_entity
		 *
		 * @internal param string $out
		 * @internal param string $column_name
		 * @internal param int $term_id
		 * @return string $out
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function manage_contacts_columns( $column, $post_id, $rt_entity ) {

			global $rt_person;
			if ( $rt_entity->post_type != $rt_person->post_type ) {
				return;
			}

			switch ( $column ) {
				default:
					if ( in_array( Rt_HD_Module::$post_type, array_keys( $rt_entity->enabled_post_types ) ) && $column == Rt_HD_Module::$post_type ) {
						$post_details = get_post( $post_id );
						$pages        = rt_biz_get_post_for_person_connection( $post_id, Rt_HD_Module::$post_type );
						echo '<a href = edit.php?' . $post_details->post_type . '=' . $post_details->ID . '&post_type=' . Rt_HD_Module::$post_type . '>' . count( $pages ) . '</a>';
					}
					break;
			}
		}

		/**
		 * add new contact
		 *
		 * @param $email
		 * @param $title
		 *
		 * @return mixed|null|WP_Post
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function insert_new_contact( $email, $title ) {

			$contact = rt_biz_get_person_by_email( $email );
			if ( !$contact ) {
				if ( trim( $title ) == "" ) {
					$title = $email;
				}

				$contact_id = rt_biz_add_person( $title );
				$contact    = get_post( $contact_id );
			}
			$contactmeta = rt_biz_get_entity_meta( $contact->ID, $this->email_key, true );
			if ( !$contactmeta ) {
				rt_biz_add_entity_meta( $contact->ID, $this->email_key, $email, true );
				global $transaction_id;
				if ( isset( $transaction_id ) && $transaction_id > 0 ) {
					add_post_meta( $contact->ID, '_transaction_id', $transaction_id, true );
				}
				$userid = $this->get_user_from_email( $email );
				if ( $userid != 1 ) {
					rt_biz_add_entity_meta( $contact->ID, $this->user_id, $userid );
				}
			}

			return $contact;
		}

		/**
		 * get user from email id
		 *
		 * @param $email
		 *
		 * @return bool|int|null
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function get_user_from_email( $email ) {
			$userid = username_exists( $email );
			//
			if ( !$userid ) {
				$userid = 1;
			}

			return $userid;
			//** followin code if to create use if not exits
			if ( !$userid ) {
				$userid = @email_exists( $email );
				if ( !$userid ) {
					add_filter( 'wpmu_welcome_user_notification', '__return_false' );
					$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
					$userid          = wp_create_user( $email, $random_password, $email );

					$role = get_role( $this->user_role );
					if ( $role == null ) {
						add_role( $this->user_role, 'Contacts' );
					}
					$user = new WP_User( $userid );
					$user->set_role( $this->user_role );
				}
			}

			return $userid;
		}

		/**
		 * contacts different on ticket
		 *
		 * @param $post_id
		 * @param $newTicket
		 *
		 * @return string
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function contacts_diff_on_ticket( $post_id, $newTicket ) {

			$diffHTML = '';
			if ( !isset( $newTicket['contacts'] ) ) {
				$newTicket['contacts'] = array();
			}
			$contacts = $newTicket['contacts'];
			$contacts = array_unique( $contacts );

			$oldContactsString = rt_biz_person_connection_to_string( $post_id );
			$newContactsSring  = '';
			if ( !empty( $contacts ) ) {
				$contactsArr = array();
				foreach ( $contacts as $contact ) {
					$newC          = get_post( $contact );
					$contactsArr[] = $newC->post_title;
				}
				$newContactsSring = implode( ',', $contactsArr );
			}
			$diff = rthd_text_diff( $oldContactsString, $newContactsSring );
			if ( $diff ) {
				$diffHTML .= '<tr><th style="padding: .5em;border: 0;">Contacts</th><td>' . $diff . '</td><td></td></tr>';
			}

			return $diffHTML;
		}

		/**
		 * contact save on tickets
		 *
		 * @param $post_id
		 * @param $newTicket
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function contacts_save_on_ticket( $post_id, $newTicket ) {
			if ( !isset( $newTicket['contacts'] ) ) {
				$newTicket['contacts'] = array();
			}
			$contacts = array_map( 'intval', $newTicket['contacts'] );
			$contacts = array_unique( $contacts );

			$post_type = get_post_type( $post_id );

			rt_biz_clear_post_connections_to_person( $post_type, $post_id );
			foreach ( $contacts as $contact ) {
				rt_biz_connect_post_to_person( $post_type, $post_id, $contact );
			}
		}

		/**
		 * AJAX call to get accounts
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function get_account_contacts_ajax() {

			$contacts = rt_biz_get_organization_to_person_connection( $_POST['query'] );
			$result   = array();
			foreach ( $contacts as $contact ) {
				$email    = rt_biz_get_entity_meta( $contact->ID, $this->email_key, true );
				$result[] = array(
					'label'   => $contact->post_title,
					'id'      => $contact->ID,
					'slug'    => $contact->post_name,
					'email'   => $email,
					'imghtml' => get_avatar( $email, 24 ),
					'url'     => admin_url( "edit.php?" . $contact->post_type . "=" . $contact->ID . "&post_type=" . $_POST['post_type'] ),
				);
			}

			echo json_encode( $result );
			die( 0 );
		}

		/**
		 * AJAX call to get taxonomy meta
		 * @since rt-Helpdesk 0.1
		 */
		public function get_taxonomy_meta_ajax() {
			if ( !isset( $_POST['query'] ) ) {
				wp_die( 'Opss!! Invalid request' );
			}

			$post_id   = $_POST['query'];
			$post_type = get_post_type( $post_id );
			$result    = get_post_meta( $post_id );

			$accounts = rt_biz_get_post_for_organization_connection( $post_id, $post_type, $fetch_account = true );
			foreach ( $accounts as $account ) {
				$result['account_id'] = $account->ID;
			}
			echo json_encode( $result );
			die( 0 );
		}

		/**
		 * AJAX call for autocomplete contact
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function contact_autocomplete_ajax() {
			if ( !isset( $_POST["query"] ) ) {
				wp_die( 'Opss!! Invalid request' );
			}

			$contacts = rt_biz_search_person( $_POST['query'] );
			$result   = array();
			foreach ( $contacts as $contact ) {
				$result[] = array(
					'label'   => $contact->post_title,
					'id'      => $contact->ID,
					'slug'    => $contact->post_name,
					'imghtml' => get_avatar( '', 24 ),
					'url'     => admin_url( "edit.php?" . $contact->post_type . "=" . $contact->ID . "&post_type=" . $_POST['post_type'] ),
				);
			}

			echo json_encode( $result );
			die( 0 );
		}

		/**
		 * add new contact AJAX call
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function add_new_contact_ajax() {
			$returnArray           = array();
			$returnArray['status'] = false;
			$accountData           = $_POST['data'];
			if ( !isset( $accountData['new-contact-name'] ) ) {
				$returnArray['status']  = false;
				$returnArray['message'] = 'Invalid Data Please Check';
			} else {
				$post_id = post_exists( $accountData['new-contact-name'] );
				if ( !empty( $post_id ) && get_post_type( $post_id ) === rt_biz_get_person_post_type() ) {
					$returnArray['status']  = false;
					$returnArray['message'] = 'Term Already Exits';
				} else {
					if ( isset( $accountData['contactmeta'] ) && !empty( $accountData['contactmeta'] ) ) {
						foreach ( $accountData['contactmeta'] as $cmeta => $metavalue ) {
							foreach ( $metavalue as $metadata ) {
								if ( strstr( $cmeta, 'email' ) != false ) {
									$result = rt_biz_get_person_by_email( $metadata );
									if ( $result && !empty( $result ) ) {
										$returnArray['status']  = false;
										$returnArray['message'] = $metadata . ' is already exits';
										echo json_encode( $returnArray );
										die( 0 );
									}
								}
							}
						}
					}

					$post_id = rt_biz_add_person( $accountData['new-contact-name'], $accountData['new-contact-description'] );

					if ( isset( $accountData['new-contact-account'] )
					     && trim( $accountData['new-contact-account'] ) != ''
					) {

						rt_biz_connect_organization_to_person( $accountData['new-contact-account'], $post_id );
					}

					$email = $accountData['new-contact-name'];

					if ( isset( $accountData['contactmeta'] ) && !empty( $accountData['contactmeta'] ) ) {

						foreach ( $accountData['contactmeta'] as $cmeta => $metavalue ) {
							foreach ( $metavalue as $metadata ) {
								if ( strstr( $cmeta, 'email' ) != false ) {
									$email = $metadata;
								}

								rt_biz_add_entity_meta( $post_id, $cmeta, $metadata );
							}
						}
					}
					$returnArray['status'] = true;

					$post                = get_post( $post_id );
					$returnArray['data'] = array(
						'id'      => $post_id,
						'value'   => $post->ID,
						'label'   => $accountData['new-contact-name'],
						'url'     => admin_url( 'edit.php?' . $post->post_type . '=' . $post->ID . '&post_type=' . $accountData['post_type'] ),
						"imghtml" => get_avatar( $email, 50 )
					);
				}
			}
			echo json_encode( $returnArray );
			die( 0 );
		}
	}
}
