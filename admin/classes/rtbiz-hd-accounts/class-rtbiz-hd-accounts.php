<?php

/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Description of Rt_HD_Accounts
 * Handel backend accounts
 * @author udit
 *
 *
 */
if ( ! class_exists( 'Rtbiz_HD_Accounts' ) ) {

	/**
	 * Class Rt_HD_Accounts
	 * Manage people table column
	 * Ajax for create & autocomplete ajax & get people by key
	 *
	 * @since  0.1
	 *
	 * @author udit
	 */
	class Rtbiz_HD_Accounts {

		/**
		 * set hooks & ajax function
		 *
		 * @since 0.1
		 */
		public function __construct() {
			Rtbiz_HD::$loader->add_filter( 'rt_entity_columns', $this, 'accounts_columns', 10, 2 );
			Rtbiz_HD::$loader->add_action( 'rt_entity_manage_columns', $this, 'manage_accounts_columns', 10, 3 );

			//not use in wp-native-ui
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_add_account', $this, 'ajax_add_new_account' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_search_account', $this, 'ajax_account_autocomplete' );
			Rtbiz_HD::$loader->add_action( 'wp_ajax_rtbiz_hd_get_term_by_key', $this, 'ajax_get_term_by_key' );

		}

		/**
		 * add columns to accounts list view
		 *
		 * @since 0.1
		 *
		 * @param $columns
		 * @param $rt_entity
		 *
		 * @return mixed
		 */
		public function accounts_columns( $columns, $rt_entity ) {

			global $rt_company;
			if ( $rt_entity->post_type != $rt_company->post_type ) {
				return $columns;
			}

			global $rtbiz_hd_module;
			if ( in_array( Rtbiz_HD_Module::$post_type, array_keys( $rt_entity->enabled_post_types ) ) ) {
				$columns[ Rtbiz_HD_Module::$post_type ] = $rtbiz_hd_module->labels['name'];
			}

			return $columns;
		}

		/**
		 * manage account columns UI
		 *
		 * @since 0.1
		 *
		 * @param $column
		 * @param $post_id
		 * @param $rt_entity
		 */
		public function manage_accounts_columns( $column, $post_id, $rt_entity ) {

			global $rt_company;
			if ( $rt_entity->post_type != $rt_company->post_type ) {
				return;
			}

			switch ( $column ) {
				default:
					if ( in_array( Rtbiz_HD_Module::$post_type, array_keys( $rt_entity->enabled_post_types ) ) && Rtbiz_HD_Module::$post_type == $column ) {
						$post_details = get_post( $post_id );
						$pages = rtbiz_get_post_for_company_connection( $post_id, Rtbiz_HD_Module::$post_type );
						echo balanceTags( '<a href = edit.php?' . $post_details->post_type . '=' . $post_details->ID . '&post_type=' . Rtbiz_HD_Module::$post_type . '>' . count( $pages ) . '</a>' );
					}
					break;
			}
		}

		/**
		 * create new account by ajax call
		 *
		 * @since 0.1
		 */
		public function ajax_add_new_account() {
			$returnArray = array();
			$returnArray['status'] = false;
			$accountData = $_POST['data'];
			if ( ! isset( $accountData['new-account-name'] ) ) {
				$returnArray['status'] = false;
				$returnArray['message'] = 'Invalid Data Please Check';
			} else {
				$post_id = post_exists( $accountData['new-account-name'] );
				if ( ! empty( $post_id ) && get_post_type( $post_id ) === rtbiz_get_company_post_type() ) {
					$returnArray['status'] = false;
					$returnArray['message'] = 'Account Already Exits';
				} else {
					if ( ! isset( $accountData['new-account-note'] ) ) {
						$accountData['new-account-note'] = '';
					}
					if ( ! isset( $accountData['new-account-country'] ) ) {
						$accountData['new-account-country'] = '';
					}
					if ( ! isset( $accountData['new-account-address'] ) ) {
						$accountData['new-account-address'] = '';
					}
					if ( ! isset( $accountData['accountmeta'] ) && ! is_array( $accountData['accountmeta'] ) ) {
						$accountData['accountmeta'] = array();
					}

					$post_id = rtbiz_add_company( $accountData['new-account-name'], $accountData['new-account-note'], $accountData['new-account-address'], $accountData['new-account-country'], $accountData['accountmeta'] );

					$post = get_post( $post_id );
					$returnArray['status'] = true;
					$returnArray['data'] = array(
						'id' => $post_id,
						'label' => $accountData['new-account-name'],
						'url' => admin_url( 'edit.php?' . $post->post_type . '=' . $post->ID . '&post_type=' . $accountData['post_type'] ),
						'value' => $post->ID,
						'imghtml' => get_avatar( $accountData['new-account-name'], 24 ),
					);
				}
			}
			echo json_encode( $returnArray );
			die( 0 );
		}

		/**
		 * provides autocomplete for ajax call
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public function ajax_account_autocomplete() {
			if ( ! isset( $_POST['query'] ) ) {
				wp_die( 'Opss!! Invalid request' );
			}

			$accounts = rtbiz_search_company( $_POST['query'] );
			$result = array();
			foreach ( $accounts as $account ) {
				$result[] = array(
					'label' => $account->post_title,
					'id' => $account->ID,
					'slug' => $account->post_name,
					'imghtml' => get_avatar( '', 24 ),
					'url' => admin_url( 'edit.php?' . $account->post_type . '=' . $account->ID . '&post_type=' . $_POST['post_type'] ),
				);
			}

			echo json_encode( $result );
			die( 0 );
		}

		/**
		 * get terms by key and returns user and profile image of matching key.
		 *
		 * @since 0.1
		 */
		public function ajax_get_term_by_key() {
			if ( ! isset( $_POST['account_id'] ) ) {
				wp_die( 'Opss!! Invalid request' );
			}
			if ( ! isset( $_POST['post_type'] ) ) {
				wp_die( 'Opss!! Invalid request' );
			}

			$result = get_post( $_POST['account_id'] );
			$returnArray = array();
			if ( $result ) {
				$returnArray['url'] = admin_url( 'edit.php?' . $result->post_type . '=' . $result->ID . '&post_type=' . $_POST['post_type'] );
				$returnArray['label'] = $result->post_title;

				$returnArray['id'] = $result->ID;
				$returnArray['imghtml'] = get_avatar( $result->post_title, 24 );
			}
			echo json_encode( $returnArray );
			die( 0 );
		}

		/**
		 * get diff of account for given post
		 *
		 * @since 0.1
		 *
		 * @param $post_id
		 * @param $newTicket
		 *
		 * @return string
		 */
		public function accounts_diff_on_ticket( $post_id, $newTicket ) {

			$diffHTML = '';
			if ( ! isset( $newTicket['accounts'] ) ) {
				$newTicket['accounts'] = array();
			}
			$accounts = $newTicket['accounts'];
			$accounts = array_unique( $accounts );

			$oldAccountsString = rtbiz_company_connection_to_string( $post_id );
			$newAccountsSring = '';
			if ( ! empty( $accounts ) ) {
				$accountsArr = array();
				foreach ( $accounts as $account ) {
					$newA = get_post( $account );
					$accountsArr[] = $newA->post_title;
				}
				$newAccountsSring = implode( ',', $accountsArr );
			}
			$diff = rtbiz_hd_text_diff( $oldAccountsString, $newAccountsSring );
			if ( $diff ) {
				$diffHTML .= '<tr><th style="padding: .5em;border: 0;">Accounts</th><td>' . $diff . '</td><td></td></tr>';
			}

			return $diffHTML;
		}

		/**
		 * save account for given post
		 *
		 * @since 0.1
		 *
		 * @param $post_id
		 * @param $newTicket
		 */
		public function accounts_save_on_ticket( $post_id, $newTicket ) {
			if ( ! isset( $newTicket['accounts'] ) ) {
				$newTicket['accounts'] = array();
			}
			$accounts = array_map( 'intval', $newTicket['accounts'] );
			$accounts = array_unique( $accounts );

			$post_type = get_post_type( $post_id );

			rtbiz_clear_post_connections_to_company( $post_type, $post_id );
			foreach ( $accounts as $account ) {
				rtbiz_connect_post_to_company( $post_type, $post_id, $account );
			}
		}

	}

}
