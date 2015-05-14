<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Rt_HD_Module' ) ) {
	/**
	 * Class Rt_HD_Module
	 * Register rtbiz-HelpDesk CPT [ Ticket ] & statuses
	 * Define connection with other post type [ contact, company ]
	 *
	 * @since  0.1
	 *
	 * @author udit
	 */
	class Rt_HD_Module {

		/**
		 * @var string Stores Post Type
		 *
		 * @since 0.1
		 */
		static $post_type = 'rtbiz_hd_ticket';
		/**
		 * @var string used in mail subject title - to detect whether it's a Helpdesk mail or not. So no translation
		 *
		 * @since 0.1
		 */
		static $name = 'Helpdesk';
		/**
		 * @var array Labels for rtbiz-HelpDesk CPT [ Ticket ]
		 *
		 * @since 0.1
		 */
		var $labels = array();
		/**
		 * @var array statuses for rtbiz-HelpDesk CPT [ Ticket ]
		 *
		 * @since 0.1
		 */
		var $statuses = array();
		/**
		 * @var array Menu order for rtbiz-HelpDesk
		 *
		 * @since 0.1
		 */
		var $custom_menu_order = array();

		/**
		 * initiate class local Variables
		 *
		 * @since 0.1
		 */
		public function __construct() {
			$this->get_custom_labels();
			$this->get_custom_statuses();
			$this->get_custom_menu_order();
			add_action( 'init', array( $this, 'init_hd' ) );
			add_action( 'p2p_init', array( $this, 'create_connection' ) );
			add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_ticket_update_messages' ), 10, 2);
			add_filter( 'post_updated_messages', array( $this, 'ticket_updated_messages' ), 10, 2 );

			$this->hooks();
		}

		function ticket_updated_messages( $messages ){
			//			$post             = get_post();
			//			$post_type        = get_post_type( $post );
			//			$post_type_object = get_post_type_object( $post_type );

			$messages[ self::$post_type ] = array(
				0  => '', // Unused. Messages start at index 1.
				1  => __( 'Ticket updated.', RT_HD_TEXT_DOMAIN ),
				2  => __( 'Custom field updated.', RT_HD_TEXT_DOMAIN ),
				3  => __( 'Custom field deleted.', RT_HD_TEXT_DOMAIN ),
				4  => __( 'Ticket updated.', RT_HD_TEXT_DOMAIN ),
				/* translators: %s: date and time of the revision */
				5  => isset( $_GET['revision'] ) ? sprintf( __( 'Ticket restored to revision from %s', RT_HD_TEXT_DOMAIN ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6  => __( 'Ticket published.', RT_HD_TEXT_DOMAIN ),
				7  => __( 'Ticket saved.', RT_HD_TEXT_DOMAIN ),
				8  => __( 'Ticket submitted.', RT_HD_TEXT_DOMAIN ),
				10 => __( 'Ticket draft updated.', RT_HD_TEXT_DOMAIN )
			);

			/*if ( $post_type_object->publicly_queryable ) {
				$permalink = get_permalink( $post->ID );

				$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View Ticket', RT_HD_TEXT_DOMAIN ) );
				$messages[ $post_type ][1] .= $view_link;
				$messages[ $post_type ][6] .= $view_link;
				$messages[ $post_type ][9] .= $view_link;

				$preview_permalink = esc_url( add_query_arg( 'preview', 'true', $permalink ) );
				$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview Ticket', RT_HD_TEXT_DOMAIN ) );
				$messages[ $post_type ][8]  .= $preview_link;
				$messages[ $post_type ][10] .= $preview_link;
			}*/

			return $messages;
		}

		/**
		 * Filter the bulk action updated messages for ticket.
		 * @param $bulk_messages
		 * @param $bulk_counts
		 *
		 * @return $bulk_messages
		 */
		function bulk_ticket_update_messages( $bulk_messages, $bulk_counts ){
			$bulk_messages[self::$post_type] = array(
				'updated'   => _n( '%s ticket updated.', '%s tickets updated.', $bulk_counts['updated'] ),
				'locked'    => _n( '%s ticket not updated, somebody is editing it.', '%s tickets not updated, somebody is editing them.', $bulk_counts['locked'] ),
				'deleted'   => _n( '%s ticket permanently deleted.', '%s tickets permanently deleted.', $bulk_counts['deleted'] ),
				'trashed'   => _n( '%s ticket moved to the Trash.', '%s tickets moved to the Trash.', $bulk_counts['trashed'] ),
				'untrashed' => _n( '%s ticket restored from the Trash.', '%s tickets restored from the Trash.', $bulk_counts['untrashed'] ),
			);
			return $bulk_messages;
		}

		/**
		 * get rtbiz-HelpDesk CPT [ Ticket ] labels
		 *
		 * @since 0.1
		 *
		 * @return array
		 */
		function get_custom_labels() {
			$settings     = rthd_get_redux_settings();
			$this->labels = array(
				'name'          => __( 'Ticket', RT_HD_TEXT_DOMAIN ),
				'singular_name' => __( 'Ticket', RT_HD_TEXT_DOMAIN ),
				'menu_name'     => 'Helpdesk',
				'all_items'     => __( 'Tickets', RT_HD_TEXT_DOMAIN ),
				'add_new'       => __( 'Add Ticket', RT_HD_TEXT_DOMAIN ),
				'add_new_item'  => __( 'Add Ticket', RT_HD_TEXT_DOMAIN ),
				'new_item'      => __( 'Add Ticket', RT_HD_TEXT_DOMAIN ),
				'edit_item'     => __( 'Edit Ticket', RT_HD_TEXT_DOMAIN ),
				'view_item'     => __( 'View Ticket', RT_HD_TEXT_DOMAIN ),
				'search_items'  => __( 'Search Tickets', RT_HD_TEXT_DOMAIN ),
			);

			return $this->labels;
		}

		/**
		 * get rtbiz-HelpDesk CPT [ Ticket ] statuses
		 *
		 * @since 0.1
		 *
		 * @return array
		 */
		function get_custom_statuses() {
			$this->statuses = array(
				array(
					'slug'        => 'hd-unanswered',
					'name'        => __( 'Unanswered', RT_HD_TEXT_DOMAIN ),
					'description' => __( 'Ticket is unanswered. It needs to be replied. The default state.', RT_HD_TEXT_DOMAIN ),
				),
				array(
					'slug'        => 'hd-answered',
					'name'        => __( 'Answered', RT_HD_TEXT_DOMAIN ),
					'description' => __( 'Ticket is answered. Expecting further communication from client', RT_HD_TEXT_DOMAIN ),
				),
				array(
					'slug'        => 'hd-archived',
					'name'        => __( 'Solved', RT_HD_TEXT_DOMAIN ),
					'description' => __( 'Ticket is archived. Client can re-open if they wish to.', RT_HD_TEXT_DOMAIN ),
				),
			);

			return $this->statuses;
		}

		/**
		 * get menu order for rtbiz-HelpDesk
		 *
		 * @since 0.1
		 */
		function get_custom_menu_order() {
			global $rt_hd_attributes;
			$this->custom_menu_order = array(
				'rthd-' . self::$post_type . '-dashboard',
                'edit.php?post_type=' . self::$post_type,
                'post-new.php?post_type=' . self::$post_type,
				esc_url( 'edit.php?post_type=' . rt_biz_get_contact_post_type() . '&rt_contact_group=customer' ),
				esc_url( 'edit.php?post_type=' . rt_biz_get_contact_post_type() . '&rt_contact_group=staff' ),
				esc_url( 'edit-tags.php?taxonomy=' . RT_Departments::$slug . '&post_type=' . self::$post_type ),
				'edit-tags.php?taxonomy=' . Rt_Offerings::$offering_slug . '&amp;post_type=' . self::$post_type,
                $rt_hd_attributes->attributes_page_slug,
                Redux_Framework_Helpdesk_Config::$page_slug,
				'rthd-setup-wizard',
                /*'edit-tags.php?taxonomy=' . Rt_Contact::$user_category_taxonomy . '&post_type=' . self::$post_type,
                'edit.php?post_type=' . rt_biz_get_company_post_type(),*/
			);

            if ( ! empty( Rt_Biz::$access_control_slug ) ) {
               $this->custom_menu_order[] = Rt_Biz::$access_control_slug;
            }

			return $this->custom_menu_order;
		}

		/**
		 * create ticket to ticket connection
		 */
		function create_connection(){
			p2p_register_connection_type( array(
				'name' => self::$post_type . '_to_' . self::$post_type,
				'from' => self::$post_type,
				'to' => self::$post_type,
				'reciprocal' => true,
				'title' => 'Related ' . $this->labels['all_items'],
			) );


		}

		/**
		 * @param $args
		 * @param $ctype
		 * @param $post
		 *
		 * @return mixed
		 * p2p hook for hiding staff member and creator from connected contacts meta box
		 * p2p hook for making id serachable in related ticket box
		 */
		function p2p_hook_for_rthd_post_filter( $args, $ctype, $post  ){
			global $wpdb, $rt_biz_acl_model;
			// hide staff member and creator of ticket from connected contacts
			if ( $ctype->name == self::$post_type.'_to_'.rt_biz_get_contact_post_type() ) {
				$exclude = array();
				// ACL
				$result  =$wpdb->get_col("SELECT p2p_from FROM ".$wpdb->prefix."p2p WHERE p2p_type = '".rt_biz_get_contact_post_type()."_to_user' AND p2p_to in (SELECT DISTINCT(userid) FROM ".$rt_biz_acl_model->table_name." where module = '".RT_HD_TEXT_DOMAIN."' and permission != 0 )");
				$exclude = array_merge($exclude, $result );
				// Ticket Creator
				$creator = get_post_meta( $post->ID, '_rtbiz_hd_created_by', true );
				$contact = rt_biz_get_contact_for_wp_user($creator);
				if ( ! empty( $contact ) ){
					$exclude[]=$contact[0]->ID;
				}
				// Exclude Admins
				$admins = get_users(array(
						'fields' => 'ID',
				        'role'   => 'administrator',
				          ));
				foreach ( $admins as $admin ){
					// get contact and add it in exclude list
					$contact = rt_biz_get_contact_for_wp_user( $admin );
					if ( ! empty( $contact ) ){
						$exclude[]=$contact[0]->ID;
					}
				}
				$exclude = array_filter($exclude);
				$exclude = array_unique( $exclude );
				$args['p2p:exclude'] = array_merge($args['p2p:exclude'], $exclude);
			}
			// related ticket - ticket id searchable
			elseif ( $ctype->name == self::$post_type.'_to_'.self::$post_type ){
				// check if search string is number
				if ( ! empty($args['p2p:search']) && is_numeric($args['p2p:search']) ){
					// if it is number then search it in post ID
					$args['post__in'] = array( $args['p2p:search'] );
					$args['p2p:search'] = '';
				}
			}
			return $args;
		}


		/**
		 * @param $title
		 * @param $post
		 * @param $ctype
		 *
		 * @return string
		 *
		 * Related tickets - p2p - Append post id in post title
		 */
		function p2p_hook_for_changing_post_title( $title, $post, $ctype ){
			if ( $ctype->name == self::$post_type.'_to_'.self::$post_type ){
				$title = '[#'.$post->ID.'] '.$title;
			}
			return $title;
		}

		/**
		 * register rtbiz-HelpDesk CPT [ Ticket ] & define connection with other post type [ contact, company ]
		 *
		 * @since 0.1
		 */
		function init_hd() {
			$menu_position = 32;
			$this->register_custom_post( $menu_position );

			foreach ( $this->statuses as $status ) {
				$this->register_custom_statuses( $status );
			}

			rt_biz_register_contact_connection( self::$post_type, $this->labels['name'] );

			//rt_biz_register_company_connection( self::$post_type, $this->labels['name'] );

			$this->db_ticket_table_update();
		}

		/**
		 * Register CPT ( ticket )
		 *
		 * @since 0.1
		 *
		 * @param $menu_position
		 *
		 * @return object|\WP_Error
		 */
		function register_custom_post( $menu_position ) {

			$logo = apply_filters( 'rthd_helpdesk_logo', RT_HD_URL . 'app/assets/img/hd-16X16.png' );

			$args = array(
				'labels'             => $this->labels,
				'public'             => true,
				'publicly_queryable' => true,
				'has_archive'        => true,
				'rewrite'            => array(
					'slug'       => strtolower( $this->labels['name'] ),
				    'with_front' => false,
				),
				'show_ui'            => true, // Show the UI in admin panel
				'menu_icon'          => $logo,
				'menu_position'      => $menu_position,
				'supports'           => array( 'title', 'editor', 'comments', 'revisions' ),
				'capability_type'    => self::$post_type,
				'map_meta_cap'    => true,
			);

			return register_post_type( self::$post_type, $args );
		}

		/**
		 * Register Custom statuses for CPT ( ticket )
		 *
		 * @since 0.1
		 *
		 * @param $status
		 *
		 * @return array|object|string
		 */
		function register_custom_statuses( $status ) {

			return register_post_status( $status['slug'], array(
				'label'       => $status['name'],
				'public'      => true,
				'exclude_from_search' => false,
				'label_count' => _n_noop( "{$status['name']} <span class='count'>(%s)</span>", "{$status['name']} <span class='count'>(%s)</span>" ),
			) );

		}

		/**
		 * update table
		 *
		 * @since 0.1
		 */
		function db_ticket_table_update() {
			global $wpdb;
			$table_name    = rthd_get_ticket_table_name();
			$db_table_name = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			$updateDB      = new RT_DB_Update( trailingslashit( RT_HD_PATH ) . 'rtbiz-helpdesk.php', trailingslashit( RT_HD_PATH_SCHEMA ) );
			if ( $updateDB->check_upgrade() || $db_table_name != $table_name ) {
				$this->create_database_table();
			}
		}

		/**
		 * create database table
		 *
		 * @since 0.1
		 */
		function create_database_table() {

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			global $rt_hd_attributes_relationship_model, $rt_hd_attributes_model;
			$relations  = $rt_hd_attributes_relationship_model->get_relations_by_post_type( self::$post_type );
			$table_name = rthd_get_ticket_table_name();
			$sql        = "CREATE TABLE {$table_name} (\n" . "id BIGINT(20) NOT NULL AUTO_INCREMENT,\n" . "post_id BIGINT(20),\n" . "post_title TEXT,\n" . "post_content TEXT,\n" . "assignee BIGINT(20),\n" . "date_create TIMESTAMP NOT NULL DEFAULT 0,\n" . "date_create_gmt TIMESTAMP NOT NULL DEFAULT 0,\n" . "date_update TIMESTAMP NOT NULL DEFAULT 0,\n" . "date_update_gmt TIMESTAMP NOT NULL DEFAULT 0,\n" . "post_status VARCHAR(20),\n" . "user_created_by BIGINT(20),\n" . "user_updated_by BIGINT(20),\n" . "last_comment_id BIGINT(20),\n" . "flag VARCHAR(3),\n";

			foreach ( $relations as $relation ) {
				$attr      = $rt_hd_attributes_model->get_attribute( $relation->attr_id );
				if ( 'taxonomy' === $attr->attribute_store_as ){
					$attr_name = str_replace( '-', '_', rtbiz_post_type_name( $attr->attribute_name ) );
				} else {
					$attr_name = str_replace( '-', '_', rthd_attribute_taxonomy_name( $attr->attribute_name ) );
				}
				$sql .= "{$attr_name} TEXT,\n";
			}

			$contact_name = rt_biz_get_contact_post_type();
			$sql .= "{$contact_name} TEXT,\n";

			$contact_name = rt_biz_get_company_post_type();
			$sql .= "{$contact_name} TEXT,\n";

			$sql .= 'PRIMARY KEY  (id) ) CHARACTER SET utf8 COLLATE utf8_general_ci;';

			dbDelta( $sql );
		}

		/**
		 * set hooks
		 *
		 * @since 0.1
		 */
		function hooks() {
			add_filter( 'custom_menu_order', array( $this, 'custom_pages_order' ) );

			// p2p hook for removing staff member from connected contacts metabox
			// p2p hook for searching ticket id in related ticket
			add_filter( 'p2p_connectable_args', array( $this, 'p2p_hook_for_rthd_post_filter' ), 10, 3 );
			add_filter( 'p2p_candidate_title', array( $this, 'p2p_hook_for_changing_post_title' ), 10, 3 );
			add_filter( 'p2p_connected_title', array( $this, 'p2p_hook_for_changing_post_title' ), 10, 3 );

			add_action( 'rt_attributes_relations_added', array( $this, 'create_database_table' ) );
			add_action( 'rt_attributes_relations_updated', array( $this, 'create_database_table' ) );
			add_action( 'rt_attributes_relations_deleted', array( $this, 'create_database_table' ) );

			add_action( 'rt_attributes_relations_added', array( $this, 'update_ticket_table' ), 10, 2 );
			add_action( 'rt_attributes_relations_updated', array( $this, 'update_ticket_table' ), 10, 1 );
			add_action( 'rt_attributes_relations_deleted', array( $this, 'update_ticket_table' ), 10, 1 );

			add_action( 'wp_before_admin_bar_render', array( $this, 'ticket_chnage_action_publish_update' ), 11 );
			if ( rthd_get_redux_adult_filter() && isset( $_GET['post_type'] ) && $_GET['post_type'] == self::$post_type ) {
				add_action( 'parse_query', array( $this, 'adult_post_filter' ) );
			}
		}


		/**
		 * Filter adult pref
		 * @param $query
		 */
		function adult_post_filter( $query ){

			if ( is_admin() && $query->query['post_type'] == self::$post_type && strpos($_SERVER[ 'REQUEST_URI' ], '/wp-admin/edit.php') !== false ) {
				$qv = &$query->query_vars;

				$current_user = get_current_user_id();
				$pref = rthd_get_user_adult_preference( $current_user );
				if ( 'yes' == $pref ){
					$meta_q = array(
						'relation' => 'OR',
						array(
							'key'     => '_rthd_ticket_adult_content',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_rthd_ticket_adult_content',
							'value'   => 'yes',
							'compare' => '!='
						),
					);
					$qv['meta_query'] = array_merge( empty( $qv['meta_query'] ) ? array() : $qv['meta_query'], $meta_q );
				}
			}
		}

		/**
		 * Update ticket table
		 *
		 * @since 0.1
		 *
		 * @param $attr_id
		 * @param $post_types
		 */

		function update_ticket_table( $attr_id, $post_types = array() ) {
			if ( isset( $post_types ) && in_array( self::$post_type, $post_types ) ) {
				$updateDB = new RT_DB_Update( trailingslashit( RT_HD_PATH ) . 'rtbiz-helpdesk.php', trailingslashit( RT_HD_PATH_SCHEMA ) );
				delete_option( $updateDB->db_version_option_name );
			}
		}

		/**
		 * Change the publish action to update on Cpt-ticket add/edit page
		 *
		 * @since 0.1
		 *
		 * @global type $pagenow
		 * @global type $post
		 */
		function ticket_chnage_action_publish_update() {
			global $pagenow, $post;
			if ( get_post_type() == self::$post_type && (  'post.php' === $pagenow ||'edit.php' === $pagenow || 'post-new.php' === $pagenow || 'edit' == ( isset( $_GET['action'] ) && $_GET['action'] ) ) ) {
				if ( ! isset( $post ) ) {
					return;
				}
				echo '
				<script>
				jQuery(document).ready(function($){
					$("#publishing-action").html("<span class=\"spinner\"> <\/span><input name=\"original_publish\" type=\"hidden\" id=\"original_publish\" value=\"Update\"><input type=\"submit\" id=\"save-publish\" class=\"button button-primary button-large\" value=\"Update\" ><\/input>");
					$(".save-post-status").click(function(){
						$("#publish").hide();
						$("#publishing-action").html("<span class=\"spinner\"><\/span><input name=\"original_publish\" type=\"hidden\" id=\"original_publish\" value=\"Update\"><input type=\"submit\" id=\"save-publish\" class=\"button button-primary button-large\" value=\"Update\" ><\/input>");
					});
					$("#save-publish").click(function(){
						$("#publish").click();
					});
					$("#post-status-select").removeClass("hide-if-js");
				});
				</script>';
			}
		}

		/**
		 * Customize menu item order
		 *
		 * @since 0.1
		 *
		 * @param $menu_order
		 *
		 * @return mixed
		 */
		function custom_pages_order( $menu_order ) {
            global $submenu;
            global $menu;

			unset( $submenu[ Rt_Biz::$dashboard_slug ] );
			foreach( $menu as $key => $menu_item ){
				if ( in_array( Rt_Biz::$dashboard_slug, $menu_item ) ) {
					unset( $menu[ $key ] );
				}
			}

            if ( isset( $submenu[ 'edit.php?post_type=' . self::$post_type ] ) && ! empty( $submenu[ 'edit.php?post_type=' . self::$post_type ] ) ) {
                $module_menu = $submenu[ 'edit.php?post_type=' . self::$post_type ];
                unset( $submenu[ 'edit.php?post_type=' . self::$post_type ] );
                $submenu[ 'edit.php?post_type=' . self::$post_type ] = array();
                $new_index = 5;
                foreach ( $this->custom_menu_order as $item ) {
	                if ( rthd_check_wizard_completed() || ( ! rthd_check_wizard_completed() && 'rthd-setup-wizard' == $item ) ) {
		                foreach ( $module_menu as $p_key => $menu_item ) {
			                $out = array_filter( $menu_item, function ( $in ) {
				                return true !== $in;
			                } );
			                if ( in_array( $item, $out ) ) {
				                $submenu[ 'edit.php?post_type=' . self::$post_type ][ $new_index ] = $menu_item;
				                unset( $module_menu[ $p_key ] );
				                $new_index += 5;
				                break;
			                }
		                }
	                }
                }
                /*foreach ( $module_menu as $p_key => $menu_item ) {
	                if ( rthd_check_wizard_completed() || ( ! rthd_check_wizard_completed() && in_array( 'rthd-setup-wizard', $menu_item ) ) ) {
		                $submenu[ 'edit.php?post_type=' . self::$post_type ][ $new_index ] = $menu_item;
		                unset( $module_menu[ $p_key ] );
		                $new_index += 5;
	                }
                }*/
            }

            return $menu_order;
		}
	}
}
