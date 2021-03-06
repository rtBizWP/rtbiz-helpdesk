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
 * Description of RtHDTicketModel
 * Model for 'rt_wp_hd_ticket_index' table in DB
 * @author udit
 * @since  rt-Helpdesk 0.1
 */
if ( ! class_exists( 'Rtbiz_HD_Ticket_Model' ) ) {
	class Rtbiz_HD_Ticket_Model extends RT_DB_Model {

		public function __construct() {
			$table_name = rtbiz_hd_get_ticket_table_name();
			parent::__construct( $table_name, true );
		}

		/**
		 * check if Ticket exist in DB
		 *
		 * @param $post_id
		 *
		 * @return bool
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function is_exist( $post_id ) {
			if ( ! empty( $post_id ) ) {
				$args = array( 'post_id' => $post_id );
				$list = parent::get( $args );
				foreach ( $list as $post ) {
					if ( $post_id == $post->post_id ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * add ticket
		 *
		 * @param $data
		 *
		 * @return int
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function add_ticket( $data ) {
			$this->reset_cache();
			return parent::insert( $data );
		}
		function reset_cache(){
			wp_cache_delete( 'hd_team_load', 'hd_dashboard' );
			wp_cache_delete( 'hd_top_client', 'hd_dashboard' );
			wp_cache_delete( 'hd_ticket_by_status', 'hd_dashboard' );
		}

		/**
		 * update ticket in DB
		 *
		 * @param $data
		 * @param $where
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function update_ticket( $data, $where ) {
			$this->reset_cache();
			return parent::update( $data, $where );
		}

		/**
		 * Delete ticket in DB
		 *
		 * @param $where
		 *
		 * @return int
		 *
		 * @since rt-Helpdesk 0.1
		 */
		function delete_ticket( $where ) {
			return parent::delete( $where );
		}

		function update_ticket_status( $status, $postid ) {
			return $this->update_ticket( array( 'post_status' => $status ), array( 'post_id' => $postid ) );
		}

		function update_ticket_assignee( $assignee, $postid ) {
			return $this->update_ticket( array( 'assignee' => $assignee ), array( 'post_id' => $postid ) );
		}
	}
}
