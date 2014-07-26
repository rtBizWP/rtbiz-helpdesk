<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) )
	exit;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Rt_HD_Module
 *
 * @author udit
 */
if( !class_exists( 'Rt_HD_Module' ) ) {
	class Rt_HD_Module {

		var $post_type = 'rt_ticket';
		// used in mail subject title - to detect whether it's a Helpdesk mail or not. So no translation
		var $name = 'Helpdesk';
		var $labels = array();
		var $statuses = array();
		var $custom_menu_order = array();

		public function __construct() {
			$this->get_custom_labels();
			$this->get_custom_statuses();
			$this->get_custom_menu_order();
			add_action( 'init', array( $this, 'init_hd' ) );
			$this->hooks();
		}

		function db_ticket_table_update() {
			global $wpdb;
			$table_name = rthd_get_ticket_table_name();
			$db_table_name = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			$updateDB = new RT_DB_Update( trailingslashit( RT_HD_PATH ) . 'index.php', trailingslashit( RT_HD_PATH_SCHEMA ) );
			if ( $updateDB->check_upgrade() || $db_table_name != $table_name ) {
				$this->create_database_table();
			}
		}

		function create_database_table() {

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			global $rt_hd_attributes_relationship_model, $rt_hd_attributes_model;
			$relations = $rt_hd_attributes_relationship_model->get_relations_by_post_type( $this->post_type );
			$table_name = rthd_get_ticket_table_name();
			$sql = "CREATE TABLE {$table_name} (\n"
					. "id BIGINT(20) NOT NULL AUTO_INCREMENT,\n"
					. "post_id BIGINT(20),\n"
					. "post_title TEXT,\n"
					. "post_content TEXT,\n"
					. "assignee BIGINT(20),\n"
					. "date_create TIMESTAMP NOT NULL,\n"
					. "date_create_gmt TIMESTAMP NOT NULL,\n"
					. "date_update TIMESTAMP NOT NULL,\n"
					. "date_update_gmt TIMESTAMP NOT NULL,\n"
					. "date_closing TIMESTAMP NOT NULL,\n"
					. "date_closing_gmt TIMESTAMP NOT NULL,\n"
					. "post_status VARCHAR(20),\n"
					. "user_created_by BIGINT(20),\n"
					. "user_updated_by BIGINT(20),\n"
					. "user_closed_by BIGINT(20),\n"
					. "last_comment_id BIGINT(20),\n"
					. "flag VARCHAR(3),\n"
					. str_replace( '-', '_', rthd_attribute_taxonomy_name( 'closing_reason' ) )." TEXT,\n";

			foreach ( $relations as $relation ) {
				$attr = $rt_hd_attributes_model->get_attribute( $relation->attr_id );
				$attr_name = str_replace('-', '_', rthd_attribute_taxonomy_name($attr->attribute_name));
				$sql .= "{$attr_name} TEXT,\n";
			}

			$settings = rthd_get_settings();
			if ( isset( $settings['attach_contacts'] ) && $settings['attach_contacts'] == 'yes' ) {
				$contact_name = rt_biz_get_person_post_type();
				$sql .= "{$contact_name} TEXT,\n";
			}
			if ( isset( $settings['attach_accounts'] ) && $settings['attach_accounts'] == 'yes' ) {
				$contact_name = rt_biz_get_organization_post_type();
				$sql .= "{$contact_name} TEXT,\n";
			}

			$sql .= "PRIMARY KEY  (id)\n"
				. ") CHARACTER SET utf8 COLLATE utf8_general_ci;";

			dbDelta($sql);
		}

		function init_hd() {
			$menu_position = 31;
			$this->register_custom_post( $menu_position );
			$this->register_custom_statuses();

			$settings = rthd_get_settings();
			if ( isset( $settings['attach_contacts'] ) && $settings['attach_contacts'] == 'yes' ) {
				rt_biz_register_person_connection( $this->post_type, $this->labels['name'] );
			}
			if ( isset( $settings['attach_accounts'] ) && $settings['attach_accounts'] == 'yes' ) {
				rt_biz_register_organization_connection( $this->post_type, $this->labels['name'] );
			}

			global $rt_hd_closing_reason;
			$rt_hd_closing_reason->closing_reason( rthd_post_type_name( $this->labels['name'] ) );

			$this->db_ticket_table_update();
		}

		function hooks() {
			add_action( 'admin_menu', array( $this, 'register_custom_pages' ), 1 );
			add_filter( 'custom_menu_order', array($this, 'custom_pages_order') );
			add_action( 'admin_init', array( $this, 'add_post_link' ) );
			add_action( 'admin_init', array( $this, 'native_list_view_link' ) );
			add_filter( 'get_edit_post_link', array( $this, 'ticket_edit_link' ), 10, 3 );
			add_filter( 'post_row_actions', array( $this, 'post_row_action' ), 10, 2 );

			add_action( 'rt_attributes_relations_added', array( $this, 'create_database_table' ) );
			add_action( 'rt_attributes_relations_updated', array( $this, 'create_database_table' ) );
			add_action( 'rt_attributes_relations_deleted', array( $this, 'create_database_table' ) );

			add_action( 'rt_attributes_relations_added', array( $this, 'update_ticket_table' ), 10, 2 );
			add_action( 'rt_attributes_relations_updated', array( $this, 'update_ticket_table' ), 10, 2 );
			add_action( 'rt_attributes_relations_deleted', array( $this, 'update_ticket_table' ), 10, 2 );
		}

		function update_ticket_table( $attr_id, $post_types ) {
			if ( in_array( $this->post_type, $post_types ) ) {
				$updateDB = new RT_DB_Update( trailingslashit( RT_HD_PATH ) . 'index.php', trailingslashit( RT_HD_PATH_SCHEMA ) );
				delete_option( $updateDB->db_version_option_name );
			}
		}

		function native_list_view_link() {
			global $rt_hd_attributes;
			if ( strpos( $_SERVER["REQUEST_URI"], 'edit.php' ) > 0 &&
				strpos( $_SERVER['REQUEST_URI'], 'page='.$rt_hd_attributes->attributes_page_slug ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-gravity-import' ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-gravity-mapper' ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-settings' ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-logs' ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-user-settings' ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'post_type='.$this->post_type ) &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-add-'.$this->post_type ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-'.$this->post_type.'-dashboard' ) === false &&
				strpos( $_SERVER['REQUEST_URI'], 'page=rthd-all-'.$this->post_type ) === false ) {
				wp_redirect( add_query_arg( 'page', 'rthd-all-'.$this->post_type ), 200 );
			}
		}

		function add_post_link() {
			if ( strpos( $_SERVER["REQUEST_URI"], 'post-new.php?post_type='.$this->post_type ) > 0 ) {
				wp_redirect( admin_url( 'edit.php?post_type=' . $this->post_type.'&page=rthd-add-'.$this->post_type ), 200 );
			}
		}

		function ticket_edit_link( $editlink, $postID, $context ) {
			$post_type = get_post_type( $postID );
			if ( $post_type != $this->post_type ) {
				return $editlink;
			}
			return admin_url( "edit.php?post_type={$post_type}&page=rthd-add-{$post_type}&{$post_type}_id=" . $postID );
		}

		function post_row_action( $action, $post ) {
			$post_type = get_post_type( $post );
			if ( $post_type != $this->post_type ) {
				return $action;
			}
			$title = __( 'Edit' );
			$action['edit'] = "<a href='" . admin_url("edit.php?post_type={$this->post_type}&page=rthd-add-{$this->post_type}&{$this->post_type}_id=" . $post->ID) . "' title='" . $title . "'>" . $title . "</a>";
			return $action;
		}

		function register_custom_pages() {
			global $rt_hd_dashboard;

			$author_cap = rt_biz_get_access_role_cap( RT_HD_TEXT_DOMAIN, 'author' );

			$screen_id = add_submenu_page( 'edit.php?post_type='.$this->post_type, __( 'Dashboard' ), __( 'Dashboard' ), $author_cap, 'rthd-'.$this->post_type.'-dashboard', array( $this, 'dashboard' ) );
			$rt_hd_dashboard->add_screen_id( $screen_id );
			$rt_hd_dashboard->setup_dashboard();

			/* Metaboxes for dashboard widgets */
			add_action( 'add_meta_boxes', array( $this, 'add_dashboard_widgets' ) );

			$filter = add_submenu_page( 'edit.php?post_type='.$this->post_type, __( ucfirst( $this->labels['all_items'] ) ), __( ucfirst( $this->labels['all_items'] ) ), $author_cap, 'rthd-all-'.$this->post_type, array( $this, 'custom_page_list_view' ) );
			add_action( "load-$filter", array( $this, 'add_screen_options' ) );

			$screen_id = add_submenu_page( 'edit.php?post_type='.$this->post_type, __('Add ' . ucfirst( $this->labels['name'] ) ), __('Add ' . ucfirst( $this->labels['name'] ) ), $author_cap, 'rthd-add-'.$this->post_type, array( $this, 'custom_page_ui' ) );
			add_action( 'admin_footer-'.$screen_id, array( $this, 'footer_scripts' ) );

			add_filter( 'set-screen-option', array( $this,'tickets_table_set_option' ), 10, 3 );
		}

		function footer_scripts() { ?>
			<script>postboxes.add_postbox_toggles(pagenow);</script>
		<?php }

		function tickets_table_set_option($status, $option, $value) {
			return $value;
		}

		function add_screen_options() {

			$option = 'per_page';
			$args = array(
				'label' => $this->labels['all_items'],
				'default' => 10,
				'option' => $this->post_type.'_per_page',
			);
			add_screen_option($option, $args);
			new Rt_HD_Tickets_List_View();
		}

		function custom_pages_order( $menu_order ) {
			global $submenu;
			global $menu;
			if ( isset( $submenu['edit.php?post_type='.$this->post_type] ) && !empty( $submenu['edit.php?post_type='.$this->post_type] ) ) {
				$module_menu = $submenu['edit.php?post_type='.$this->post_type];
				unset($submenu['edit.php?post_type='.$this->post_type]);
				unset($module_menu[5]);
				unset($module_menu[10]);
				$new_index = 5;
				foreach ( $this->custom_menu_order as $item ) {
					foreach ( $module_menu as $p_key => $menu_item ) {
						if ( in_array( $item, $menu_item ) ) {
							$submenu['edit.php?post_type='.$this->post_type][$new_index] = $menu_item;
							unset ( $module_menu[$p_key] );
							$new_index += 5;
							break;
						}
					}
				}
				foreach( $module_menu as $p_key => $menu_item ) {
					$menu_item[0]= '--- '.$menu_item[0];
					$submenu['edit.php?post_type='.$this->post_type][$new_index] = $menu_item;
					unset ( $module_menu[$p_key] );
					$new_index += 5;
				}
			}
			return $menu_order;
		}

		function custom_page_ui() {
			$args = array();
			rthd_get_template('admin/add-ticket.php', $args);
		}

		function custom_page_list_view() {
			$args = array(
				'post_type' => $this->post_type,
				'labels' => $this->labels,
			);
			rthd_get_template( 'admin/list-view.php', $args );
		}

		function register_custom_post( $menu_position ) {
			$hd_logo_url = rthd_get_logo_url();

			$args = array(
				'labels' => $this->labels,
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true, // Show the UI in admin panel
				'menu_icon' => $hd_logo_url,
				'menu_position' => $menu_position,
				'supports' => array('title', 'editor', 'comments', 'custom-fields'),
				'capability_type' => $this->post_type,
			);
			register_post_type( $this->post_type, $args );
		}

		function register_custom_statuses() {
			foreach ($this->statuses as $status) {

				register_post_status($status['slug'], array(
					'label' => $status['slug']
					, 'protected' => true
					, '_builtin' => false
					, 'label_count' => _n_noop("{$status['name']} <span class='count'>(%s)</span>", "{$status['name']} <span class='count'>(%s)</span>"),
				));
			}
		}

        function get_custom_menu_order(){
			global $rt_hd_attributes;
            $this->custom_menu_order = array(
                'rthd-'.$this->post_type.'-dashboard',
				'rthd-all-'.$this->post_type,
				'rthd-add-'.$this->post_type,
				'rthd-gravity-import',
				'rthd-gravity-mapper',
				'rthd-logs',
				'rthd-settings',
				'rthd-user-settings',
				$rt_hd_attributes->attributes_page_slug,
            );
        }

		function get_custom_labels() {
			$menu_label = rthd_get_menu_label();
			$this->labels = array(
				'name' => __( 'Ticket' ),
				'singular_name' => __( 'Ticket' ),
				'menu_name' => $menu_label,
				'all_items' => __( 'Tickets' ),
				'add_new' => __( 'Add Ticket' ),
				'add_new_item' => __( 'Add Ticket' ),
				'new_item' => __( 'Add Ticket' ),
				'edit_item' => __( 'Edit Ticket' ),
				'view_item' => __( 'View Ticket' ),
				'search_items' => __( 'Search Tickets' ),
			);
			return $this->labels;
		}

		function get_custom_statuses() {
			$this->statuses = array(
				array(
					'slug' => 'unanswered',
					'name' => __( 'Unanswered' ),
					'description' => __( 'Ticket is unanswered. It needs to be replied. The default state.' ),
				),
				array(
					'slug' => 'answered',
					'name' => __( 'Answered' ),
					'description' => __( 'Ticket is answered. Expecting further communication from client' ),
				),
				array(
					'slug' => 'trash',
					'name' => __( 'Resolved' ),
					'description' => __( 'Ticket is archived/closed. Client can re-open if they wish to.' ),
				),
			);
			return $this->statuses;
		}

		function dashboard() {
			global $rt_hd_dashboard;
			$rt_hd_dashboard->ui( $this->post_type );
		}

		function add_dashboard_widgets() {
			global $rt_hd_dashboard;

			/* Pie Chart - Progress Indicator (Post status based) */
			add_meta_box( 'rthd-tickets-by-status', __( 'Status wise Tickets' ), array( $this, 'tickets_by_status' ), $rt_hd_dashboard->screen_id, 'column1' );
			/* Line Chart for Closed::Won */
			add_meta_box( 'rthd-daily-tickets', __( 'Daily Tickets' ), array( $this, 'daily_tickets' ), $rt_hd_dashboard->screen_id, 'column2' );
			/* Load by Team (Matrix/Table) */
			add_meta_box( 'rthd-team-load', __( 'Team Load' ), array( $this, 'team_load' ), $rt_hd_dashboard->screen_id, 'column3' );
			/* Top Accounts */
			add_meta_box( 'rthd-top-accounts', __( 'Top Accounts' ), array( $this, 'top_accounts' ), $rt_hd_dashboard->screen_id, 'column4' );
			/* Top Clients */
			add_meta_box( 'rthd-top-clients', __( 'Top Clients' ), array( $this, 'top_clients' ), $rt_hd_dashboard->screen_id, 'column4' );
		}

		/**
		 * Status wise A single pie will show ticket and amount both: 11 Tickets worth $5555
		 */
		function tickets_by_status() {
			global $rt_hd_dashboard, $wpdb;
			$table_name = rthd_get_ticket_table_name();
			$post_statuses = array();
			foreach ( $this->statuses as $status ) {
				$post_statuses[$status['slug']] = $status['name'];
			}

			$query = "SELECT post_status, COUNT(id) AS rthd_count FROM {$table_name} WHERE 1=1 GROUP BY post_status";
			$results = $wpdb->get_results($query);
			$data_source = array();
			$cols = array( __('Ticket Status'), __( 'Count' ) );
			$rows = array();
			foreach ( $results as $item ) {
				$post_status = ( isset( $post_statuses[$item->post_status] ) ) ? $post_statuses[$item->post_status] : '';
				if ( !empty( $post_status ) ) {
					$rows[] = array( $post_status, ( !empty( $item->rthd_count ) ) ? floatval( $item->rthd_count ) : 0 );
				}
			}
			$data_source['cols'] = $cols;
			$data_source['rows'] = $rows;

			$rt_hd_dashboard->charts[] = array(
				'id' => 1,
				'chart_type' => 'pie',
				'data_source' => $data_source,
				'dom_element' => 'rthd_hd_pie_tickets_by_status',
				'options' => array(
					'title' => __( 'Status wise Tickets' ),
				),
			);
		?>
    		<div id="rthd_hd_pie_tickets_by_status"></div>
		<?php
		}

		function team_load() {
			global $rt_hd_dashboard, $wpdb;
            $table_name = rthd_get_ticket_table_name();
			$post_statuses = array();
			foreach ( $this->statuses as $status ) {
				$post_statuses[$status['slug']] = $status['name'];
			}

			$query = "SELECT assignee, post_status, COUNT(ID) AS rthd_ticket_count FROM {$table_name} WHERE 1=1 GROUP BY assignee, post_status";
            $results = $wpdb->get_results($query);

			$table_matrix = array();
			foreach ($results as $item) {
				if ( isset( $table_matrix[$item->assignee] ) ) {
					if( isset( $post_statuses[$item->post_status] ) ) {
						$table_matrix[$item->assignee][$item->post_status] = $item->rthd_ticket_count;
					}
				} else {
					foreach ($post_statuses as $key => $status) {
						$table_matrix[$item->assignee][$key] = 0;
					}
					if ( isset( $post_statuses[$item->post_status] ) ) {
						$table_matrix[$item->assignee][$item->post_status] = $item->rthd_ticket_count;
					}
				}
			}

			$data_source = array();
            $cols[] = array(
				'type' => 'string',
				'label' => __('Users'),
			);
			foreach ( $post_statuses as $status ) {
				$cols[] = array(
					'type' => 'number',
					'label' => $status,
				);
			}

			$rows = array();
			foreach ( $table_matrix as $user => $item ) {

				$temp = array();
				foreach ( $item as $status => $count ) {
					$temp[] = intval($count);
				}
				$user = get_user_by('id', $user);
				$url = add_query_arg(
					array(
						'post_type' => $this->post_type,
						'page' => 'rthd-all-'.$this->post_type,
						'assignee' => $user->ID,
					),
					admin_url( 'edit.php' )
				);
				if ( !empty( $user ) ) {
					array_unshift($temp, '<a href="'.$url.'">'.$user->display_name.'</a>');
				}
				$rows[] = $temp;
			}

            $data_source['cols'] = $cols;
			$data_source['rows'] = $rows;

            $rt_hd_dashboard->charts[] = array(
                'id' => 2,
                'chart_type' => 'table',
                'data_source' => $data_source,
                'dom_element' => 'rthd_hd_table_team_load',
                'options' => array(
                    'title' => __( 'Team Load' ),
                ),
            );
		?>
            <div id="rthd_hd_table_team_load"></div>
        <?php
		}

		function top_accounts() {
			global $rt_hd_dashboard, $wpdb;
			$table_name = rthd_get_ticket_table_name();
			$account = rt_biz_get_organization_post_type();

			$query = "SELECT acc.ID AS account_id, acc.post_title AS account_name "
						. ( ( isset( $wpdb->p2p ) ) ? ", COUNT( ticket.ID ) AS account_tickets " : ' ' )
					. "FROM {$wpdb->posts} AS acc "
					. ( ( isset( $wpdb->p2p ) ) ? "JOIN {$wpdb->p2p} AS p2p ON acc.ID = p2p.p2p_to " : ' ' )
					. ( ( isset( $wpdb->p2p ) ) ? "JOIN {$table_name} AS ticket ON ticket.post_id = p2p.p2p_from " : ' ' )
					. "WHERE 2=2 "
					. ( ( isset( $wpdb->p2p ) ) ? "AND p2p.p2p_type = '{$this->post_type}_to_{$account}' " : ' ' )
					. "AND acc.post_type = '{$account}' "
					. "GROUP BY acc.ID "
					. ( ( isset( $wpdb->p2p ) ) ? "ORDER BY account_tickets DESC " : ' ' )
					. "LIMIT 0 , 10";

			$results = $wpdb->get_results($query);

			$data_source = array();
            $cols = array(
				array(
					'type' => 'string',
					'label' => __( 'Account Name' ),
				),
				array(
					'type' => 'number',
					'label' => __( 'Number of Tickets' ),
				),
			);

			$rows = array();
			foreach ( $results as $item ) {
				$url = add_query_arg(
					array(
						'post_type' => $this->post_type,
						'page' => 'rthd-all-'.$this->post_type,
						$account => $item->account_id,
					),
					admin_url( 'edit.php' )
				);
				$rows[] = array(
					'<a href="'.$url.'">'.$item->account_name.'</a>',
					intval($item->account_tickets),
				);
			}

			$data_source['cols'] = $cols;
			$data_source['rows'] = $rows;

            $rt_hd_dashboard->charts[] = array(
                'id' => 3,
                'chart_type' => 'table',
                'data_source' => $data_source,
                'dom_element' => 'rthd_hd_table_top_accounts',
                'options' => array(
                    'title' => __( 'Top Accounts' ),
                ),
            );
		?>
            <div id="rthd_hd_table_top_accounts"></div>
        <?php
		}

		function top_clients() {
			global $rt_hd_dashboard, $wpdb;
			$table_name = rthd_get_ticket_table_name();
			$contact = rt_biz_get_person_post_type();
			$account = rt_biz_get_organization_post_type();

			$query = "SELECT contact.ID AS contact_id, contact.post_title AS contact_name "
						. ( ( isset( $wpdb->p2p ) ) ? ", COUNT( ticket.ID ) AS contact_tickets " : ' ' )
					. "FROM {$wpdb->posts} AS contact "
					. ( ( isset( $wpdb->p2p ) ) ? "JOIN {$wpdb->p2p} AS p2p_lc ON contact.ID = p2p_lc.p2p_to " : ' ' )
					. ( ( isset( $wpdb->p2p ) ) ? "JOIN {$table_name} AS ticket ON ticket.post_id = p2p_lc.p2p_from " : ' ' )
					. ( ( isset( $wpdb->p2p ) ) ? "LEFT JOIN {$wpdb->p2p} AS p2p_ac ON contact.ID = p2p_ac.p2p_to AND p2p_ac.p2p_type = '{$account}_to_{$contact}'  " : ' ' )
					. "WHERE 2=2 "
					. ( ( isset( $wpdb->p2p ) ) ? "AND p2p_lc.p2p_type = '{$this->post_type}_to_{$contact}' " : ' ' )
					. "AND contact.post_type = '{$contact}' "
					. ( ( isset( $wpdb->p2p ) ) ? "AND p2p_ac.p2p_type IS NULL " : ' ' )
					. "GROUP BY contact.ID "
					. ( ( isset( $wpdb->p2p ) ) ? "ORDER BY contact_tickets DESC " : ' ' )
					. "LIMIT 0 , 10";

			$results = $wpdb->get_results($query);

			$data_source = array();
            $cols = array(
				array(
					'type' => 'string',
					'label' => __( 'Contact Name' ),
				),
				array(
					'type' => 'number',
					'label' => __( 'Number of Tickets' ),
				),
			);

			$rows = array();
			foreach ( $results as $item ) {
				$url = add_query_arg(
					array(
						'post_type' => $this->post_type,
						'page' => 'rthd-all-'.$this->post_type,
						$contact => $item->contact_id,
					),
					admin_url( 'edit.php' )
				);
				$rows[] = array(
					'<a href="'.$url.'">'.$item->contact_name.'</a>',
					intval($item->contact_tickets),
				);
			}

			$data_source['cols'] = $cols;
			$data_source['rows'] = $rows;

            $rt_hd_dashboard->charts[] = array(
                'id' => 4,
                'chart_type' => 'table',
                'data_source' => $data_source,
                'dom_element' => 'rthd_hd_table_top_clients',
                'options' => array(
                    'title' => __( 'Top Clients' ),
                ),
            );
		?>
            <div id="rthd_hd_table_top_clients"></div>
        <?php
		}

		function daily_tickets() {
			global $rt_hd_dashboard, $rt_hd_ticket_history_model;
			$post_statuses = array();
			foreach ( $this->statuses as $status ) {
				$post_statuses[$status['slug']] = $status['name'];
			}
			$current_date = new DateTime();
			$first_date = date( 'Y-m-d', strtotime( 'first day of this month', $current_date->format('U') ) );
			$last_date = date( 'Y-m-d', strtotime( 'last day of this month', $current_date->format('U') ) );

			$args = array(
				'type' => 'post_status',
				'update_time' => array(
					'compare' => '>=',
					'value' => array($first_date),
				),
				'update_time' => array(
					'compare' => '<=',
					'value' => array($last_date),
				),
			);
			$history = $rt_hd_ticket_history_model->get($args, false, false, 'update_time asc');

			$month_map = array();
			$i = 0;
			$first_date = strtotime($first_date);
			$last_date = strtotime($last_date);
			do {
				$current_date = strtotime( '+'.$i++.' days', $first_date );

				foreach ($post_statuses as $slug => $status) {
					$month_map[$current_date][$slug] = 0;
				}

				$dt_obj = DateTime::createFromFormat( 'U', $current_date );
				foreach ( $history as $item ) {
					$update_time = new DateTime( $item->update_time );
					if ( $dt_obj->format('Y-m-d') === $update_time->format('Y-m-d')  ) {
						if ( isset( $month_map[$current_date][$item->new_value] ) ) {
							$month_map[$current_date][$item->new_value]++;
						}
					}
				}
			} while ( $current_date < $last_date );

			$data_source = array();
            $cols[0] = __( 'Daily Tickets' );
			foreach ( $post_statuses as $status ) {
				$cols[] = $status;
			}
			$rows = array();
			foreach ( $month_map as $date => $tickets ) {
				$temp = array();
				$dt_obj = DateTime::createFromFormat( 'U', $date );
				$temp[] = $dt_obj->format('j M, Y');
				foreach ( $tickets as $ticket ) {
					$temp[] = $ticket;
				}
				$rows[] = $temp;
			}

			$data_source['cols'] = $cols;
			$data_source['rows'] = $rows;

			$rt_hd_dashboard->charts[] = array(
                'id' => 5,
                'chart_type' => 'line',
                'data_source' => $data_source,
                'dom_element' => 'rthd_hd_line_daily_tickets',
                'options' => array(
                    'title' => __( 'Daily Tickets' ),
                ),
            );

		?>
    		<div id="rthd_hd_line_daily_tickets"></div>
		<?php
		}

	}
}