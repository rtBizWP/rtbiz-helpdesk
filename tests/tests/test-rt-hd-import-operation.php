<?php
/**
 * Created by PhpStorm.
 * User: sai
 * Date: 20/1/15
 * Time: 7:15 PM
 */

class test_Rt_HD_Import_Operation extends RT_WP_TestCase {
	var $rthdImportOperation;

	/**
	 * Setup Class Object and Parent Test Suite
	 *
	 */
	function setUp() {
		parent::setUp();
		$this->rthdImportOperation = new Rtbiz_HD_Import_Operation();
	}

	function  test_check_function() {
		$this->assertTrue( method_exists( $this->rthdImportOperation, 'ajax_add_new_followup_front' ), 'Class Rt_HD_Import_Operation does not have method ajax_add_new_followup_front' );
		$this->assertTrue( method_exists( $this->rthdImportOperation, 'ajax_delete_followup' ), 'Class Rt_HD_Import_Operation does not have method ajax_delete_followup' );
		$this->assertTrue( method_exists( $this->rthdImportOperation, 'ajax_load_more_followup' ), 'Class Rt_HD_Import_Operation does not have method ajax_load_more_followup' );
		$this->assertTrue( method_exists( $this->rthdImportOperation, 'ajax_add_new_ticket' ), 'Class Rt_HD_Import_Operation does not have method ajax_add_new_ticket' );
		$this->assertTrue( method_exists( $this->rthdImportOperation, 'process_email_to_ticket' ), 'Class Rt_HD_Import_Operation does not have method process_email_to_ticket' );
	}
}
