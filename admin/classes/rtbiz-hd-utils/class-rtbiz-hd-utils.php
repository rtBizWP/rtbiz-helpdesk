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
 * Description of Rt_HD_Utils
 * Help desk utility functions
 * @author udit
 */
if ( ! class_exists( 'Rtbiz_HD_Utils' ) ) {

	/**
	 * Class Rt_HD_Utils
	 */
	class Rtbiz_HD_Utils {

		/**
		 * @param $string
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		static public function force_utf_8( $string ) {
			//          return preg_replace('/[^(\x20-\x7F)]*/','', $string);
			//          $string = preg_replace( '/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
			//                                  '|(?<=^|[\x00-\x7F])[\x80-\xBF]+' .
			//                                  '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
			//                                  '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
			//                                  '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/' ,
			//                                '?', $string );

			//          $string = preg_replace( '/\xE0[\x80-\x9F][\x80-\xBF]' . '|\xED[\xA0-\xBF][\x80-\xBF]/S','?', $string );

			//          http://grokbase.com/t/php/php-notes/03bhzv260m/note-37492-added-to-function-quoted-printable-decode
			//          http://www.cnblogs.com/wangjiangze/archive/2013/04/16/3024446.html
			//          http://www.bestwebframeworks.com/tutorials/php/140/decode-and-solve-in-php-quoted-printable-characters-from-plain-emails/
			//          $string = quoted_printable_decode( $string );
			//          $string = imap_qprint( $string );

			// Old CRM Code
			//          $string = preg_replace('/[^(\x20-\x7F)]*/','', $string);

			// UTF-8

			//reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ?
			$string = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
				'|[\x00-\x7F][\x80-\xBF]+'.
				'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
				'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
				'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
			'?', $string );

			//reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
			$string = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
			'|\xED[\xA0-\xBF][\x80-\xBF]/S','?', $string );

			return $string;
		}

		/**
		 * mime type key being extension
		 *
		 * @var array
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public static $mime_types = array(
			'pdf'  => 'application/pdf',
			'exe'  => 'application/octet-stream',
			'zip'  => 'application/zip',
			'docx' => 'application/msword',
			'doc'  => 'application/msword',
			'xls'  => 'application/vnd.ms-excel',
			'ppt'  => 'application/vnd.ms-powerpoint',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'jpeg' => 'image/jpg',
			'jpg'  => 'image/jpg',
			'mp3'  => 'audio/mpeg',
			'wav'  => 'audio/x-wav',
			'mpeg' => 'video/mpeg',
			'mpg'  => 'video/mpeg',
			'mpe'  => 'video/mpeg',
			'mov'  => 'video/quicktime',
			'avi'  => 'video/x-msvideo',
			'3gp'  => 'video/3gpp',
			'css'  => 'text/css',
			'jsc'  => 'application/javascript',
			'js'   => 'application/javascript',
			'php'  => 'text/html',
			'htm'  => 'text/html',
			'html' => 'text/html',
		);

		/**
		 * Logging errors
		 *
		 * @param        $msg
		 * @param string $filename
		 *
		 * @since rt-Helpdesk 0.1
		 */
		static public function log( $msg, $filename = 'error_log.txt' ) {
			$log_file = '/tmp/rtbiz-helpdesk' . $filename;
			if ( $fp = fopen( $log_file, 'a+' ) ) {
				fwrite( $fp, "\n" . '[' . date( DATE_RSS ) . '] ' . $msg . "\n" );
				fclose( $fp );
			}
		}

		/**
		 * Set accounts
		 *
		 * @param $rCount
		 *
		 * @since rt-Helpdesk 0.1
		 */
		static public function set_accounts( $rCount ) {
			$log_file = RTBIZ_HD_PATH . 'mailaccount.txt';
			if ( $fp = fopen( $log_file, 'w+' ) ) {
				fwrite( $fp, $rCount );
				fclose( $fp );
			}
		}

		/**
		 * Determine if a post exists based on title, content, and date
		 *
		 * @param string $title   Post title
		 * @param string $content Optional post content
		 * @param string $date    Optional post date
		 *
		 * @return int Post ID if post exists, 0 otherwise.
		 *
		 * @since rt-Helpdesk 0.1
		 */
		static public function post_exists( $title, $content = '', $date = '' ) {
			global $wpdb;

			$post_title   = stripslashes( sanitize_post_field( 'post_title', $title, 0, 'db' ) );
			$post_content = stripslashes( sanitize_post_field( 'post_content', $content, 0, 'db' ) );
			$post_date    = stripslashes( sanitize_post_field( 'post_date', $date, 0, 'db' ) );

			$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
			$args  = array();

			if ( ! empty( $date ) ) {
				$query .= ' AND post_date = %s';
				$args[] = $post_date;
			}

			if ( ! empty( $title ) ) {
				$query .= ' AND post_title = %s';
				$args[] = $post_title;
			}

			if ( ! empty( $content ) ) {
				$query .= 'AND post_content = %s';
				$args[] = $post_content;
			}

			if ( ! empty( $args ) ) {
				return $wpdb->get_var( $wpdb->prepare( $query, $args ) );
			}

			return 0;
		}

		/**
		 * Get user
		 *
		 * @return mixed
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public static function get_hd_rtcamp_user() {
			$users = rtbiz_get_module_employee( RTBIZ_HD_TEXT_DOMAIN );

			return $users;
		}

		/**
		 * Get mime type of file
		 *
		 * @param $file
		 *
		 * @return string
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public static function get_mime_type( $file ) {

			// our list of mime types

			$extension = strtolower( end( explode( '.', $file ) ) );
			if ( isset( self::$mime_types[ $extension ] ) ) {
				return self::$mime_types[ $extension ];
			} else {
				return 'application/octet-stream';
			}
		}

		/**
		 * Get extension of file
		 *
		 * @param $file
		 *
		 * @return int|string
		 *
		 * @since rt-Helpdesk 0.1
		 */
		public static function get_extention( $file ) {

			foreach ( self::$mime_types as $key => $mime ) {
				if ( $mime == $file ) {
					return $key;
				}
			}

			return 'tmp';
		}

	}

}
