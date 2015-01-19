<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of Rt_HD_Admin
 * Rt_HD_Admin is main class for admin backend and UI.
 * @author udit
 * @since  rt-Helpdesk 0.1
 */
if ( ! class_exists( 'Rt_HD_Admin' ) ) {
	class Rt_HD_Admin {
		private $admin_cap, $editor_cap, $author_cap;

		public function __construct() {

			$this->admin_cap  = rt_biz_get_access_role_cap( RT_HD_TEXT_DOMAIN, 'admin' );
			$this->editor_cap = rt_biz_get_access_role_cap( RT_HD_TEXT_DOMAIN, 'editor' );
			$this->author_cap = rt_biz_get_access_role_cap( RT_HD_TEXT_DOMAIN, 'author' );

			if ( is_admin() ) {
				$this->hooks();
			}
		}

		/**
		 * Hooks
		 * @since rt-Helpdesk 0.1
		 */
		function hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'load_styles_scripts' ) );
			add_filter( 'pre_insert_term', array( $this, 'remove_wocommerce_actions' ), 10, 2 );

			//upload folder change
			//			add_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_upload_prefilter' ) );
			//			add_filter( 'wp_handle_upload', array( $this, 'handle_upload' ) );
		}

		/**
		 * Register CSS and JS
		 * @since rt-Helpdesk 0.1
		 */
		function load_styles_scripts() {
			global $post, $pagenow, $wp_scripts;
			$rthd_post_type = isset( $_GET['post'] ) ? get_post_type( $_GET['post'] )  :'';
			if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ) ) && $rthd_post_type == Rt_HD_Module::$post_type ) {
				if ( isset( $post->post_type ) && $post->post_type == Rt_HD_Module::$post_type ) {

					wp_enqueue_script( 'jquery-ui-timepicker-addon', RT_HD_URL . 'app/assets/javascripts/jquery-ui-timepicker-addon.js', array(
							'jquery-ui-datepicker',
							'jquery-ui-slider',
						), RT_HD_VERSION, true );

					if ( ! wp_script_is( 'jquery-ui-datepicker' ) ) {
						wp_enqueue_script( 'jquery-ui-datepicker' );
					}
					if ( ! wp_script_is( 'jquery-ui-autocomplete' ) ) {
						wp_enqueue_script( 'jquery-ui-autocomplete', '', array(
								'jquery-ui-widget',
								'jquery-ui-position',
							), '1.9.2' );
					}

					wp_enqueue_script( 'moment-js', RT_HD_URL . 'app/assets/javascripts/moment.js', array( 'jquery' ), RT_HD_VERSION, true );
				}
				wp_enqueue_style( 'rthd_date_styles', '//code.jquery.com/ui/1.11.1/themes/smoothness/jquery-ui.css', array(), RT_HD_VERSION );
				wp_enqueue_style( 'rthd_admin_styles', RT_HD_URL . 'app/assets/css/admin_new.css', array(), RT_HD_VERSION );
				wp_enqueue_style( 'rthd_css', RT_HD_URL . 'app/assets/css/rt-hd-css.css', array(), RT_HD_VERSION );
				wp_enqueue_script( 'jquery-tiptip', RT_HD_URL . 'app/assets/javascripts/jquery-tiptip/jquery.tipTip.js', array( 'jquery' ), RT_HD_VERSION, true );
				wp_enqueue_script( 'rthd-admin-js', RT_HD_URL . 'app/assets/javascripts/admin_new.js', array( 'jquery-tiptip' ), RT_HD_VERSION, true );
				wp_enqueue_script( 'rthd-app-loadmore', RT_HD_URL . 'app/assets/javascripts/jquery.ba-throttle-debounce.js', array( 'jquery' ), RT_HD_VERSION, true );
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-form', array( 'jquery' ), false, true );
				wp_enqueue_script( 'jquery-ui-dialog' );

				wp_enqueue_style( 'rthd-followup-css', RT_HD_URL . 'app/assets/css/follow-up.css', array(), RT_HD_VERSION, 'all' );
				global $wp_scripts;
				$ui = $wp_scripts->query( 'jquery-ui-core' );
				// tell WordPress to load the Smoothness theme from Google CDN
				$protocol = is_ssl() ? 'https' : 'http';
				$url      = "$protocol://ajax.googleapis.com/ajax/libs/jqueryui/" . $ui->ver . '/themes/smoothness/jquery-ui.css';
				if ( ! wp_style_is( 'jquery-ui-smoothness' ) ) {
					wp_enqueue_style( 'jquery-ui-smoothness', $url, array(), RT_HD_VERSION, 'all' );
				}
			}

			if ( ! wp_script_is( 'jquery-ui-progressbar' ) ) {
				wp_enqueue_script( 'jquery-ui-progressbar', '', array(
					'jquery-ui-widget',
					'jquery-ui-position',
				), '1.9.2' );
			}

			wp_enqueue_script( 'rthd_setting_js', RT_HD_URL . 'app/assets/javascripts/rt-custom-status.js', RT_HD_VERSION, true );
			$this->localize_scripts();
		}

		/**
		 * Passes data to JS
		 * @since rt-Helpdesk 0.1
		 */
		function localize_scripts() {
			global $post, $pagenow, $wp_scripts;
			$rthd_post_type = isset( $_GET['post'] ) ? get_post_type( $_GET['post'] )  :'';
			if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ) ) && $rthd_post_type == Rt_HD_Module::$post_type ) {
				$user_edit = false;
				if ( current_user_can( 'edit_' . Rt_HD_Module::$post_type ) ) {
					$user_edit = true;
				}
				wp_localize_script( 'rthd-admin-js', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
				wp_localize_script( 'rthd-admin-js', 'rthd_post_type', $rthd_post_type );
				wp_localize_script( 'rthd-admin-js', 'rthd_user_edit', array( $user_edit ) );
			} else {
				wp_localize_script( 'rthd-admin-js', 'rthd_user_edit', array( '' ) );
			}
		}

		/**
		 * @param $term
		 * @param $taxonomy
		 *
		 * @return mixed
		 * @since rt-Helpdesk 0.1
		 */
		function remove_wocommerce_actions( $term, $taxonomy ) {
			$attrs     = rthd_get_all_attributes();
			$attr_list = array( 'contacts', 'accounts' );
			foreach ( $attrs as $attr ) {
				if ( 'taxonomy' == $attr->attribute_store_as ) {
					$attr_list[] = $attr->attribute_name;
				}
			}
			if ( in_array( $taxonomy, $attr_list ) ) {
				remove_action( 'create_term', 'woocommerce_create_term', 5, 3 );
				remove_action( 'delete_term', 'woocommerce_delete_term', 5, 3 );
			}

			return $term;
		}

		/**
		 * add filter for Update path of helpdesk upload
		 *
		 * @param $file
		 *
		 * @return mixed
		 */
		function handle_upload_prefilter( $file ) {
			$postype = $_REQUEST['post_type'];
			if ( empty( $postype ) && !empty( $_REQUEST['post_id'] ) ){
				$postype = get_post_type( $_REQUEST['post_id'] );
			}
			if( $postype == Rt_HD_Module::$post_type ){
				add_filter( 'upload_dir', array( $this, 'custom_upload_dir' ) );
			}
			return $file;
		}

		/**
		 * remode filter for Update path of helpdesk upload
		 *
		 * @param $fileinfo
		 *
		 * @return mixed
		 */
		function handle_upload( $fileinfo )   {
			$postype = $_REQUEST['post_type'];
			if ( empty( $postype ) && !empty( $_REQUEST['post_id'] ) ) {
				$postype = get_post_type( $_REQUEST['post_id'] );
			}
			if ( $postype == Rt_HD_Module::$post_type ) {
				remove_filter( 'upload_dir', array( $this, 'custom_upload_dir' ) );
			}
			return $fileinfo;
		}

		/**
		 * Update path for helpdesk upload
		 *
		 * @param $args
		 *
		 * @return mixed
		 */
		function custom_upload_dir($args) {
			$args['path'] = $args['basedir'] . '/' . RT_HD_TEXT_DOMAIN . $args['subdir'];
			$args['url'] = $args['baseurl'] . '/' . RT_HD_TEXT_DOMAIN . $args['subdir'];
			return $args;
		}



	}
}