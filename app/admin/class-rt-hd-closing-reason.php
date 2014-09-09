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
 * Description of Rt_HD_Closing_Reason
 *
 * @author udit
 *
 * @since rt-Helpdesk 0.1
 */
if ( ! class_exists( 'Rt_HD_Closing_Reason' ) ) {
	class Rt_HD_Closing_Reason {
		public function __construct() {

		}

		/**
		 * Create taxonomy for accounts
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function closing_reason( $post_type ) {
			$labels     = array(
				'name'                  => __( 'Closing Reason', RT_HD_TEXT_DOMAIN ),
				'search_items'          => __( 'Search Closing Reason', RT_HD_TEXT_DOMAIN ),
				'all_items'             => __( 'All Closing Reasons', RT_HD_TEXT_DOMAIN ),
				'edit_item'             => __( 'Edit Closing Reason', RT_HD_TEXT_DOMAIN ),
				'update_item'           => __( 'Update Closing Reason', RT_HD_TEXT_DOMAIN ),
				'add_new_item'          => __( 'Add New Closing Reason', RT_HD_TEXT_DOMAIN ),
				'new_item_name'         => __( 'New Closing Reason', RT_HD_TEXT_DOMAIN ),
				'menu_name'             => __( 'Closing Reasons', RT_HD_TEXT_DOMAIN ),
				'choose_from_most_used' => __( 'Choose from the most used Closing Reasons', RT_HD_TEXT_DOMAIN ),
			);
			$editor_cap = rt_biz_get_access_role_cap( RT_HD_TEXT_DOMAIN, 'editor' );
			register_taxonomy( rthd_attribute_taxonomy_name( 'closing-reason' ), array( $post_type ), array(
				'hierarchical'          => false,
				'labels'                => $labels,
				'show_ui'               => true,
				'query_var'             => true,
				'update_count_callback' => 'rthd_update_post_term_count',
				'rewrite'               => array( 'slug' => rthd_attribute_taxonomy_name( 'closing-reason' ) ),
				'capabilities'          => array(
					'manage_terms' => $editor_cap,
					'edit_terms'   => $editor_cap,
					'delete_terms' => $editor_cap,
					'assign_terms' => $editor_cap,
				),
			) );
		}

		/**
		 * Save closing reason
		 *
		 * @param $post_id
		 * @param $newTicket
		 */
		function save_closing_reason( $post_id, $newTicket ) {
			if ( ! isset( $newTicket[ 'closing_reason' ] ) ) {
				$newTicket[ 'closing_reason' ] = array();
			}
			$contacts = array_map( 'intval', $newTicket[ 'closing_reason' ] );
			$contacts = array_unique( $contacts );
			wp_set_post_terms( $post_id, $contacts, rthd_attribute_taxonomy_name( 'closing-reason' ) );
		}

		/**
		 * Closing Reason Email Diff
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function closing_reason_diff( $post_id, $newTicket ) {

			$diffHTML = '';
			if ( ! isset( $newTicket[ 'closing_reason' ] ) ) {
				$newTicket[ 'closing_reason' ] = array();
			}
			$contacts = $newTicket[ 'closing_reason' ];
			$contacts = array_unique( $contacts );

			$oldContactsString = rthd_post_term_to_string( $post_id, rthd_attribute_taxonomy_name( 'closing-reason' ) );
			$newContactsSring  = '';
			if ( ! empty( $contacts ) ) {
				$contactsArr = array();
				foreach ( $contacts as $contact ) {
					$newC = get_term_by( 'id', $contact, rthd_attribute_taxonomy_name( 'closing-reason' ) );
					if ( isset( $newC->name ) && ! empty( $newC->name ) ) {
						$contactsArr[ ] = $newC->name;
					}
				}
				$newContactsSring = implode( ',', $contactsArr );
			}
			$diff = rthd_text_diff( $oldContactsString, $newContactsSring );
			if ( $diff ) {
				$diffHTML .= '<tr><th style="padding: .5em;border: 0;">Closing Reason</th><td>' . $diff . '</td><td></td></tr>';
			}

			return $diffHTML;
		}

		/**
		 * Render Closing Reasons - DOM Element
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function get_closing_reasons( $post_id, $user_edit = true ) {
			global $rthd_form;
			$options   = array();
			$terms     = get_terms( rthd_attribute_taxonomy_name( 'closing-reason' ), array( 'hide_empty' => false ) );
			$post_term = wp_get_post_terms( $post_id, rthd_attribute_taxonomy_name( 'closing-reason' ), array( 'fields' => 'ids' ) );
			// Default Selected Term for the attribute. can beset via settings -- later on
			$selected_term = '-11111';
			if ( ! empty( $post_term ) ) {
				$selected_term = $post_term[ 0 ];
				$options[ ]    = array( __( 'Select a Reason', RT_HD_TEXT_DOMAIN ) => '', 'selected' => false, );
			} else {
				$options[ ] = array( __( 'Select a Reason', RT_HD_TEXT_DOMAIN ) => '', 'selected' => true, );
			}
			foreach ( $terms as $term ) {
				$options[ ] = array( $term->name => $term->term_id, 'selected' => ( $term->term_id == $selected_term ) ? true : false, );
			}
			$args = array( 'id' => 'rthd_closing_reason', 'name' => 'post[closing_reason][]', 'rtForm_options' => $options, );

			if ( $user_edit ) {
				echo $rthd_form->get_select( $args );
			} else {
				$term = get_term( $selected_term, rthd_attribute_taxonomy_name( $attr->attribute_name ) );
				echo '<span class="rthd_view_mode">' . esc_html( $term->name ). '</span>';
			}
		}
	}
}
