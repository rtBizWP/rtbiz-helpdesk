<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RtHDTicketHistoryModel
 * Model for 'wp_hd_ticket_history' table in DB
 * @author udit
 * @since  rt-Helpdesk 0.1
 */
if ( ! class_exists( 'Rtbiz_HD_Ticket_History_Model' ) ) {
	class Rtbiz_HD_Ticket_History_Model extends RT_DB_Model {
		public function __construct() {
			parent::__construct( 'wp_hd_ticket_history' );
		}

		function insert($data, $format = null){
			return parent::insert($data, $format);
		}

		function update($data, $where, $format = null, $where_format = null){
			return parent::update($data, $where, $format, $where_format);
		}

		function get($columns, $offset = false, $per_page = false, $order_by = 'id desc'){
			return parent::get($columns, $offset, $per_page, $order_by);
		}

		function delete($where, $where_format = null){
			return parent::delete($where, $where_format);
		}
	}
}
