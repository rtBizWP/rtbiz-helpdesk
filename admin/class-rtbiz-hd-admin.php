<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin
 */
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin
 * @author     Your Name <email@example.com>
 */
if ( !class_exists( 'Rtbiz_HD_Admin' ) ) {

	class Rtbiz_HD_Admin {

		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    1.0.0
		 */
		public function __construct() {

		}

		public function init_admin() {

			global $rtbiz_hd_mail_acl_model, $rtbiz_hd_ticket_history_model, $rtbiz_hd_ticket_index_model,
			$rtbiz_hd_attributes, $rtbiz_hd_module, $rtbiz_hd_cpt_tickets, $rtbiz_hd_reports, $rtbiz_hd_dashboard,
			$rtbiz_hd_accounts, $rtbiz_hd_contacts, $rtbiz_hd_tickets_operation, $rtbiz_hd_email_notification, $rtbiz_hd_auto_response, $rtbiz_hd_migration, $rtbiz_hd_mailbox ;

			$rtbiz_hd_mail_acl_model = new Rtbiz_HD_Mail_ACL_Model();
			$rtbiz_hd_ticket_history_model = new Rtbiz_HD_Ticket_History_Model();
			$rtbiz_hd_ticket_index_model = new Rtbiz_HD_Ticket_Model();

			$rtbiz_hd_attributes = new Rtbiz_HD_Attributes();
			$rtbiz_hd_module = new Rtbiz_HD_Module();
			$rtbiz_hd_cpt_tickets = new Rtbiz_HD_CPT_Tickets();

			$rtbiz_hd_dashboard = new Rtbiz_HD_Dashboard();
			$page_slugs = array( Rtbiz_HD_Dashboard::$page_slug );
			$rtbiz_hd_reports = new Rt_Reports( $page_slugs );

			$rtbiz_hd_accounts = new Rtbiz_HD_Accounts();
			$rtbiz_hd_contacts = new Rtbiz_HD_Contacts();
			$rtbiz_hd_tickets_operation = new Rtbiz_HD_Tickets_Operation();
			$rtbiz_hd_email_notification = new Rtbiz_HD_Email_Notification();
			$rtbiz_hd_auto_response = new Rtbiz_HD_Auto_Response();
			$rtbiz_hd_mailbox = new Rtbiz_HD_Mailbox();


			global $rtbiz_hd_setup_wizard;
			$rtbiz_hd_setup_wizard = new Rtbiz_HD_Setup_Wizard();

			//Setting
			global $rtbiz_hd_settings, $rtbiz_hd_import_operation, $rtbiz_hd_product_support, $rtbiz_hd_short_code, $rtbiz_hd_gravity_form_importer, $rtbiz_hd_logs;

			$rtbiz_hd_settings = new Rtbiz_HD_Settings();
			$rtbiz_hd_import_operation = new Rtbiz_HD_Import_Operation();

			$rtbiz_hd_gravity_form_importer = new Rtbiz_HD_Gravity_Form_Importer();
			$rtbiz_hd_logs = new Rtbiz_HD_Logs();

			$rtbiz_hd_product_support = new Rtbiz_HD_Product_Support();
			$rtbiz_hd_short_code = new Rtbiz_HD_Short_Code();

			global $Rtbiz_Hd_Help;
			$Rtbiz_Hd_Help = new Rtbiz_Hd_Help();

			// For ajax request register with WordPress
			$rtbiz_hd_contact_blacklist = new Rtbiz_HD_Ticket_Contacts_Blacklist();

			// migration class
			$rtbiz_hd_migration = new Rtbiz_HD_Migration();

			// Slack integration class.
			new Rtbiz_HD_Slack_Integration();

		}

		public function register_menu() {
			if ( rtbiz_hd_check_wizard_completed() ) {
				add_submenu_page( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type, __( 'Customers', 'rtbiz-helpdesk' ), __( 'Customers', 'rtbiz-helpdesk' ), rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'editor' ), esc_url( 'edit.php?post_type=' . rtbiz_get_contact_post_type() . '&contact_group=customer&module=' . RTBIZ_HD_TEXT_DOMAIN ) );
				add_submenu_page( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type, __( 'Staff', 'rtbiz-helpdesk' ), __( 'Staff', 'rtbiz-helpdesk' ), rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'editor' ), esc_url( 'edit.php?post_type=' . rtbiz_get_contact_post_type() . '&contact_group=staff&module=' . RTBIZ_HD_TEXT_DOMAIN ) );
				add_submenu_page( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type, __( '---Teams', 'rtbiz-helpdesk' ), __( '---Teams', 'rtbiz-helpdesk' ), rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'editor' ), esc_url( 'edit-tags.php?taxonomy=' . Rtbiz_Teams::$slug . '&post_type=' . Rtbiz_HD_Module::$post_type ) );
			} else {
				global $rtbiz_hd_setup_wizard;
				add_submenu_page( 'edit.php?post_type=' . esc_html( Rtbiz_HD_Module::$post_type ), __( 'Setup Wizard', 'rtbiz-helpdesk' ), __( 'Setup Wizard', 'rtbiz-helpdesk' ), rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'admin' ), Rtbiz_HD_Setup_Wizard::$page_slug, array(
					$rtbiz_hd_setup_wizard,
					'setup_wizard_ui',
				) );
			}
		}

		public function custom_pages_order( $menu_order ) {
			global $submenu, $menu;

			$rtbizMenuOrder = $this->get_custom_menu_order();

			//remove rtbiz menu
			foreach ( $menu as $key => $menu_item ) {
				if ( in_array( Rtbiz_Dashboard::$page_slug, $menu_item ) ) {
					unset( $menu[$key] );
				}
			}

			if ( isset( $submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type] ) && !empty( $submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type] ) ) {
				$module_menu = $submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type];
				unset( $submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type] );
				$submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type] = array();
				$new_index = 5;
				$wizard_completed = rtbiz_hd_check_wizard_completed();
				foreach ( $rtbizMenuOrder as $item ) {
					if ( $wizard_completed || (!$wizard_completed && Rtbiz_HD_Setup_Wizard::$page_slug == $item ) ) {
						foreach ( $module_menu as $p_key => $menu_item ) {
							$out = array_filter( $menu_item, function ( $in ) {
								return true !== $in;
							} );
							if ( in_array( $item, $out ) ) {
								$submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type][$new_index] = $menu_item;
								unset( $module_menu[$p_key] );
								$new_index += 5;
								break;
							}
						}
					}
				}
				foreach ( $module_menu as $p_key => $menu_item ) {
					if ( $wizard_completed && !in_array( esc_url( $menu_item[2] ), $rtbizMenuOrder ) ) {
						$submenu['edit.php?post_type=' . Rtbiz_HD_Module::$post_type][$new_index] = $menu_item;
						unset( $module_menu[$p_key] );
						$new_index += 5;
					}
				}
			}

			return $menu_order;
		}

		public function get_custom_menu_order() {
			// Set menu order
			global $rtbiz_hd_attributes;

			$rtbizMenuOrder = array(
				Rtbiz_HD_Dashboard::$page_slug,
				'edit.php?post_type=' . Rtbiz_HD_Module::$post_type,
				'post-new.php?post_type=' . Rtbiz_HD_Module::$post_type,
				esc_url( 'edit.php?post_type=' . rtbiz_get_contact_post_type() . '&contact_group=customer&module=' . RTBIZ_HD_TEXT_DOMAIN ),
				esc_url( 'edit.php?post_type=' . rtbiz_get_contact_post_type() . '&contact_group=staff&module=' . RTBIZ_HD_TEXT_DOMAIN ),
				esc_url( 'edit-tags.php?taxonomy=' . Rtbiz_Teams::$slug . '&post_type=' . Rtbiz_HD_Module::$post_type ),
				'edit-tags.php?taxonomy=' . Rt_Products::$product_slug . '&amp;post_type=' . Rtbiz_HD_Module::$post_type,
				$rtbiz_hd_attributes->attributes_page_slug,
				Rtbiz_HD_Settings::$page_slug,
				Rtbiz_HD_Setup_Wizard::$page_slug,
			);

			if ( !empty( Rtbiz::$access_control_slug ) ) {
				$rtbizMenuOrder = Rtiz::$access_control_slug;
			}
			return $rtbizMenuOrder;
		}

		public function plugin_action_links( $links ) {
			$links['get-started'] = '<a href="' . admin_url( 'admin.php?page=' . Rtbiz_HD_Dashboard::$page_slug ) . '">' . __( 'Get Started', 'rtbiz-helpdesk' ) . '</a>';
			$links['settings'] = '<a href="' . admin_url( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type . '&page=' . Rtbiz_HD_Settings::$page_slug ) . '">' . __( 'Settings', 'rtbiz-helpdesk' ) . '</a>';
			return $links;
		}

		public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
			if ( RTBIZ_HD_BASE_NAME == $plugin_file ) {
				$plugin_meta[] = '<a href="' . 'http://docs.rtcamp.com/rtbiz/' . '">' . __( 'Documentation', 'rtbiz-helpdesk' ) . '</a>';
				//$plugin_meta[] = '<a href="' . 'https://rtcamp.com/rtbiz/faq' . '">' . __( 'FAQ', RTBIZ_TEXT_DOMAIN ) . '</a>';
				$plugin_meta[] = '<a href="' . 'https://rtcamp.com/premium-support/' . '">' . __( 'Support', 'rtbiz-helpdesk' ) . '</a>';
			}
			return $plugin_meta;
		}

		public function database_update() {
			$updateDB = new RT_DB_Update( trailingslashit( RTBIZ_HD_PATH ) . 'rtbiz-helpdesk.php', trailingslashit( RTBIZ_HD_PATH . 'admin/schema/' ) );
			$updateDB->do_upgrade();
		}

		public function rtbiz_hd_welcome() {
			// fail if no activation redirect
			if ( !get_option( 'rtbiz_hd_activation_redirect', false ) ) {
				return;
			}

			// fail if activating from network, or bulk
			if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
				return;
			}

			if ( rtbiz_hd_check_wizard_completed() ) {
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type . '&page=' . Rtbiz_HD_Dashboard::$page_slug ) );
			} else {
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type . '&page=' . Rtbiz_HD_Setup_Wizard::$page_slug ) );
			}
			delete_option( 'rtbiz_hd_activation_redirect' );
			exit;
		}

		public function module_register( $modules ) {
			$modules[ rtbiz_sanitize_module_key( RTBIZ_HD_TEXT_DOMAIN ) ] = array(
				'label' => __( 'Helpdesk', 'rtbiz-helpdesk' ),
				'post_types' => array( Rtbiz_HD_Module::$post_type ),
				'team_support' => '',
				'product_support' => array( Rtbiz_HD_Module::$post_type ),
				'setting_option_name' => Rtbiz_HD_Settings::$hd_opt, // Use for setting page acl to add manage_options capability
				'setting_page_url' => admin_url( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type . '&page=rthd-settings' ), //
				'email_template_support' => array( Rtbiz_HD_Module::$post_type ),
			);

			return $modules;
		}

		function ajax_rtbiz_add_hd_error_page(){
			$response = array();
			$response['status'] = false;
			$page_title = 'Helpdesk Authentication Error';
			$page_content = '[Helpdesk Authentication Error]';
			$slug = 'helpdesk-authentication-error';
			$option = 'rtbiz_hd_helpdesk_authentication_error_page_id';
			$option_value = get_option( $option );
			if ( $option_value > 0 && get_post( $option_value ) ) {
				$response['status'] = false;
			} else {
				global $wpdb;

				$page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_type='page' AND post_name = %s AND post_content NOT LIKE %s LIMIT 1;", $slug, "%{$page_content}%" ) );

				if ( $page_found ) {
					$post_name = wp_unique_post_slug( $slug, '', 'publish', 'page', '' );
					$wpdb->update( $wpdb->posts, array( 'post_name' => $post_name, 'post_status' => 'draft' ), array( 'ID' => $page_found ) );
				}

				$page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_type='page' AND post_name = %s AND post_content LIKE %s LIMIT 1;", $slug, "%{$page_content}%" ) );
				if ( $page_found ) {
					if ( ! $option_value ) {
						update_option( $option, $page_found );
					}
					$response['status'] = true;
				} else {
					$page_data = array(
						'post_status'       => 'publish',
						'post_type'         => 'page',
						'post_author'       => 1,
						'post_name'         => $slug,
						'post_title'        => $page_title,
						'post_content'      => $page_content,
						'comment_status'    => 'closed'
					);
					$page_id = wp_insert_post( $page_data );

					if ( $option ) {
						update_option( $option, $page_id );
					}

					$response['status'] = true;
				}
			}
			echo json_encode( $response );
			die();
		}

		function add_error_page_notice() {
			$admin_cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'admin' );
			$is_admin = current_user_can( $admin_cap );
			if ( current_user_can( 'publish_pages' ) && $is_admin ) {
				$option = get_option( 'rtbiz_hd_helpdesk_authentication_error_page_id' );
				$page = get_post( $option );
				if ( rtbiz_hd_check_wizard_completed() && ( $option <= 0  || ! $page ) ) {
					$class   = "updated";
					$message = "<p><b>Welcome to Helpdesk</b> - You're almost ready to use Helpdesk</p><p class='submit-action'><a id='rthd-add-hd-error-page' class='btn button-primary' href='javascript:;' title='Install Helpdesh Pages'>Install <b>Helpdesk Authentication Error</b> page.</a></p>";
					echo "<div class=\"$class\"> <p>$message</p></div>";
				}
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
			$attrs = rthd_get_all_attributes();
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
			$postype = '';
			if ( isset( $_REQUEST['post_type'] ) ) {
				$postype = $_REQUEST['post_type'];
			}
			if ( empty( $postype ) && !empty( $_REQUEST['post_id'] ) ) {
				$postype = get_post_type( $_REQUEST['post_id'] );
			}
			if ( Rtbiz_HD_Module::$post_type == $postype ) {
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
		function handle_upload( $fileinfo ) {
			$postype = '';
			if ( isset( $_REQUEST['post_type'] ) ) {
				$postype = $_REQUEST['post_type'];
			}
			if ( empty( $postype ) && !empty( $_REQUEST['post_id'] ) ) {
				$postype = get_post_type( $_REQUEST['post_id'] );
			}
			if ( Rtbiz_HD_Module::$post_type == $postype ) {
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
		function custom_upload_dir( $args ) {
			$args['path'] = $args['basedir'] . '/' . RTBIZ_HD_TEXT_DOMAIN . $args['subdir'];
			$args['url'] = $args['baseurl'] . '/' . RTBIZ_HD_TEXT_DOMAIN . $args['subdir'];
			return $args;
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_styles() {

			/**
			 * This function is provided for demonstration purposes only.
			 *
			 * An instance of this class should be passed to the run() function
			 * defined in Plugin_Name_Loader as all of the hooks are defined
			 * in that particular class.
			 *
			 * The Plugin_Name_Loader will then create the relationship
			 * between the defined hooks and the functions defined in this
			 * class.
			 */
			global $post, $pagenow;
			$rtbiz_hd_post_type = 'post';
			if ( isset( $_GET['post'] ) ) {
				$rtbiz_hd_post_type = get_post_type( $_GET['post'] );
			} elseif ( isset( $_GET['post_type'] ) && ( 'post-new.php' == $pagenow || 'edit.php' == $pagenow ) ) {
				$rtbiz_hd_post_type = $_GET['post_type'];
			}

			// include this css everywhere
			wp_enqueue_style( RTBIZ_HD_TEXT_DOMAIN . 'common-css', RTBIZ_HD_URL . 'public/css/rthd-common.css', array(), RTBIZ_HD_VERSION, 'all' );

			if ( ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ) ) && Rtbiz_HD_Module::$post_type == $rtbiz_hd_post_type ) || ( in_array( $pagenow, array( 'admin.php', ) ) && Rtbiz_HD_Setup_Wizard::$page_slug == $_REQUEST['page'] ) ) {
				wp_enqueue_style( RTBIZ_HD_TEXT_DOMAIN . 'admin-css', RTBIZ_HD_URL . 'admin/css/admin.css', array(), RTBIZ_HD_VERSION, 'all' );
			}







			//          wp_enqueue_style( 'rthd-common-css', RT_HD_URL . 'app/assets/css/rthd-common.css', array(), RT_HD_VERSION, 'all' );
			//          wp_enqueue_style( 'rthd-admin-css', RT_HD_URL . 'app/assets/admin/css/admin.css', array(), RT_HD_VERSION );
		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_scripts() {

			/**
			 * This function is provided for demonstration purposes only.
			 *
			 * An instance of this class should be passed to the run() function
			 * defined in Plugin_Name_Loader as all of the hooks are defined
			 * in that particular class.
			 *
			 * The Plugin_Name_Loader will then create the relationship
			 * between the defined hooks and the functions defined in this
			 * class.
			 */
			global $post, $pagenow, $rtbiz_hd_setup_wizard;
			$rtbiz_hd_post_type = 'post';
			if ( isset( $_GET['post'] ) ) {
				$rtbiz_hd_post_type = get_post_type( $_GET['post'] );
			} elseif ( isset( $_GET['post_type'] ) && ( 'post-new.php' == $pagenow || 'edit.php' == $pagenow ) ) {
				$rtbiz_hd_post_type = $_GET['post_type'];
			}
			wp_enqueue_script( 'rthd-app-public-js', RTBIZ_HD_URL . 'public/js/helpdesk-shortcode-min.js', array( 'jquery' ), RTBIZ_HD_VERSION, true );

			if ( ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ) ) && Rtbiz_HD_Module::$post_type == $rtbiz_hd_post_type ) || ( in_array( $pagenow, array( 'admin.php' ) ) && Rtbiz_HD_Setup_Wizard::$page_slug == $_REQUEST['page'] ) ) {

				if ( isset( $post->post_type ) && $post->post_type == Rtbiz_HD_Module::$post_type ) {

					wp_enqueue_script( 'jquery-ui-timepicker-addon', RTBIZ_HD_URL . 'admin/js/vendors/jquery-ui-timepicker-addon.js', array(
						'jquery-ui-datepicker',
						'jquery-ui-slider',
							), RTBIZ_HD_VERSION, true );

					wp_enqueue_script( 'jquery-ui-datepicker' );
				}

				wp_enqueue_script( 'jquery-ui-autocomplete', '', array(
					'jquery-ui-widget',
					'jquery-ui-position',
						), '1.9.2' );

				//				wp_enqueue_media();

				wp_enqueue_script( RTBIZ_HD_TEXT_DOMAIN . 'admin-js', RTBIZ_HD_URL . 'admin/js/helpdesk-admin-min.js', array( 'jquery' ), RTBIZ_HD_VERSION, true );

				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . 'admin-js', 'rtbiz_hd_post_type', $rtbiz_hd_post_type );
				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . 'admin-js', 'rtbiz_hd_supported_extensions', implode( ',', rtbiz_hd_get_supported_extensions() ) );
			} else {
				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . 'admin-js', 'rtbiz_hd_user_edit', array( '' ) );
			}


			if ( isset( $rtbiz_hd_post_type ) && in_array( $rtbiz_hd_post_type, array( rtbiz_get_contact_post_type() ) ) ) {
				wp_enqueue_script( RTBIZ_HD_TEXT_DOMAIN . '-menu-hack-js', RTBIZ_HD_URL . 'admin/js/rt-custom-status.js', array( 'jquery' ), RTBIZ_HD_VERSION, true );

				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . '-menu-hack-js', 'rtbiz_hd_menu', Rtbiz_HD_Module::$post_type );

				$query_arg = '';
				if ( !empty( $_GET['contact_group'] ) ) {
					$query_arg = '&contact_group=' . $_GET['contact_group'] . '&module=' . RTBIZ_HD_TEXT_DOMAIN;
				} else {
					if ( isset( $_REQUEST['post'] ) ) {
						$user = rtbiz_get_wp_user_for_contact( $_REQUEST['post'] );
						if ( !empty( $user[0] ) && in_array( 'administrator', $user[0]->roles ) ) {
							$query_arg = '&contact_group=staff';
						} else {
							$is_staff_member = get_post_meta( $_REQUEST['post'], 'rtbiz_is_staff_member', true );
							if ( 'no' == $is_staff_member ) {
								$query_arg = '&contact_group=customer';
							} else if ( 'yes' == $is_staff_member ) {
								$query_arg = '&contact_group=staff';
							}
						}
						$query_arg .= '&module=' . RTBIZ_HD_TEXT_DOMAIN;
					}
				}
				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . '-menu-hack-js', 'rtbiz_hd_url', admin_url( 'edit.php?post_type=' . rtbiz_get_contact_post_type() . $query_arg ) );
			}

			if ( isset( $_REQUEST['taxonomy'] ) && isset( $_REQUEST['post_type'] ) && in_array( $_REQUEST['post_type'], array( Rtbiz_HD_Module::$post_type ) ) ) {
				wp_enqueue_script( RTBIZ_HD_TEXT_DOMAIN . '-menu-hack-js', RTBIZ_HD_URL . 'admin/js/rt-custom-status.js', array( 'jquery' ), RTBIZ_HD_VERSION, true );
				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . '-menu-hack-js', 'rtbiz_hd_menu', Rtbiz_HD_Module::$post_type );
				wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . '-menu-hack-js', 'rtbiz_hd_url', admin_url( 'edit-tags.php?taxonomy=' . $_REQUEST['taxonomy'] . '&post_type=' . $_REQUEST['post_type'] ) );
			}

			wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . 'admin-js', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
			wp_localize_script( RTBIZ_HD_TEXT_DOMAIN . 'admin-js', 'rtbiz_hd_dashboard_url', admin_url( 'edit.php?post_type=' . Rtbiz_HD_Module::$post_type . '&page=' . Rtbiz_HD_Dashboard::$page_slug . '&finish-wizard=yes' ) );
		}

	}

}
