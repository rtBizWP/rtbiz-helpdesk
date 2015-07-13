<?php
/**
 * Don't load this file directly!
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of class-RT_HD_Short_Code
 *
 * @author Dipesh
 */
if ( !class_exists( 'Rtbiz_HD_Short_Code' ) ) {

	/**
	 * Class RT_HD_Short_Code
	 */
	class Rtbiz_HD_Short_Code {

		var $LIMIT = 10;

		/**
		 * Constructor
		 */
		public function __construct() {
			add_shortcode( 'rtbiz_hd_support_form', array( $this, 'support_form' ) );
			add_shortcode( 'rtbiz_hd_tickets', array( $this, 'get_tickets' ) );
			add_action( 'wp_ajax_rtbiz_hd_load_more_ticket_shortcode', array( $this, 'load_more_ticket_shortcode' ) );
		}

		function load_more_ticket_shortcode() {
			$response = array( 'status' => false );
			if ( !isset( $_POST['short_code_param'] ) || empty( $_POST['short_code_param'] ) ) {
				$response['msg'] = 'Shortcode param missing';
				echo json_encode( $response );
				die();
			}
			if ( !isset( $_POST['offset'] ) || empty( $_POST['offset'] ) ) {
				$response['msg'] = 'offset param missing';
				echo json_encode( $response );
				die();
			}
			$response['status'] = true;
			$response['html'] = $this->ticket_shortcode_render_ui( $_POST['short_code_param'], $_POST['offset'], $this->LIMIT, false );
			echo json_encode( $response );
			die();
		}

		/**
		 * Short code callback for Display Support Form
		 *
		 * @since 0.1
		 *
		 * [rtbiz_hd_support_form]
		 */
		function support_form( $attr ) {

			$arg_shortcode = shortcode_atts( array(
				'title' => 'yes',
					), $attr );

			global $rtbiz_hd_product_support;

			ob_start();
			$rtbiz_hd_product_support->check_active_plugin();
			wp_enqueue_style( 'helpdesk-style', RTBIZ_HD_URL . 'public/css/rthd-main.css', false, RTBIZ_HD_VERSION, 'all' );
			wp_enqueue_script( 'rthd-support-form', RTBIZ_HD_URL . 'public/js/helpdesk-support-min.js', array( 'jquery' ), RTBIZ_HD_VERSION, true );
			wp_localize_script( 'rthd-support-form', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
			$product_option = '';

			if ( is_user_logged_in() ) {
				$post_id = $rtbiz_hd_product_support->save_support_form();
				if ( !empty( $post_id ) && is_int( $post_id ) ) {
					?>
					<div id="info" class="success rthd-notice">Your support request has been submitted. We will get back to you for
						your query soon.
					</div>
					<?php
				}

				global $rtbiz_products;
				$terms = array();
				if ( isset( $rtbiz_products ) ) {
					add_filter( 'get_terms', array( $rtbiz_products, 'product_filter' ), 10, 3 );
					$terms = get_terms( Rt_Products::$product_slug, array( 'hide_empty' => 0 ) );
					remove_filter( 'get_terms', array( $rtbiz_products, 'product_filter' ), 10, 3 );
				}
				$product_exists = false;
				$wrong_user_flag = false;
				$loggedin_id = get_current_user_id();

				$product_ids = array();

				if ( isset( $_REQUEST['order_id'] ) ) {
					// check in woo orders
					if ( $rtbiz_hd_product_support->isWoocommerceActive ) {
						$order = new WC_Order( $_REQUEST['order_id'] );
						if ( !empty( $order ) && 'shop_order' == $order->post->post_type ) {
							if ( $loggedin_id == $order->get_user_id() ) {
								$items = $order->get_items();
								$product_ids = wp_list_pluck( $items, 'product_id' );
								$wrong_user_flag = false;
							} else {
								$wrong_user_flag = true;
							}
						} else {
							$order = array();
						}
					}
					// check in edd orders
					if ( $rtbiz_hd_product_support->iseddActive && empty( $order ) ) {
						$payment = get_post( $_REQUEST['order_id'] );
						if ( !empty( $payment ) && $loggedin_id == $payment->post_author ) {
							if ( 'edd_payment' == $payment->post_type ) {
								$items = edd_get_payment_meta_downloads( $payment->ID );
								$product_ids = wp_list_pluck( $items, 'id' );
								$wrong_user_flag = false;
							}
						} else {
							$wrong_user_flag = true;
						}
					}
				}

				foreach ( $terms as $tm ) {
					$term_product_id = '';
					// skip items if not from orders
					if ( !empty( $product_ids ) ) {
						$term_product_id = Rt_Lib_Taxonomy_Metadata\get_term_meta( $tm->term_id, Rt_Products::$term_product_id_meta_key, true );
						if ( !in_array( $term_product_id, $product_ids ) ) {
							continue;
						}
					}
					$product_option .= '<option value="' . $tm->term_id . '" ' . ( (!empty( $_REQUEST['product_id'] ) && $term_product_id == $_REQUEST['product_id'] ) ? 'selected="selected"' : '' ) . '> ' . $tm->name . '</option>';
					$product_exists = true;
				}

				if ( $wrong_user_flag ) {
					echo '<span> You have not placed this order, Please login from account that placed this order. </span>';
				} else {
					rtbiz_hd_get_template( 'support-form.php', array(
						'product_exists' => $product_exists,
						'product_option' => $product_option,
						'show_title' => $arg_shortcode['title'],
					) );
				}
			} else {
				?>
				<div id="info" class="error rthd-notice">You're not logged in. Please <a href="<?php echo wp_login_url( get_permalink() ); ?>" title="Login">Login</a> first to create support ticket.
				</div>
				<?php
			}
			return apply_filters( 'rtbiz_hd_support_form_shorcode', ob_get_clean(), $attr );
		}

		/**
		 * Short code to display list of tickets in table format with Title, last updated, status, and link
		 *
		 * @since 0.1
		 *
		 * @param $atts
		 *
		 * @return string
		 */
		function get_tickets( $atts ) {

			$arg_shortcode = shortcode_atts(
					array(
				'userid' => '',
				'email' => '',
				'orderid' => '',
				'show_support_form_link' => 'no',
				'fav' => false,
				'title' => 'yes',
					), $atts );

			$args = array(
				'post_type' => Rtbiz_HD_Module::$post_type,
				'post_status' => 'any',
				'nopaging' => true,
			);
			?>

			<script>
				var rthd_shortcode_params = <?php echo json_encode( $arg_shortcode ); ?>;
			</script>

			<?php
			return $this->ticket_shortcode_render_ui( $arg_shortcode, 0, $this->LIMIT, true );
		}

		function ticket_shortcode_render_ui( $arg_shortcode, $top, $limit, $first ) {
			global $rtbiz_hd_module, $current_user, $redux_helpdesk_settings;
			$cap = rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'author' );

			if ( !empty( $arg_shortcode['email'] ) && empty( $arg_shortcode['userid'] ) ) {
				if ( '{{logged_in_user}}' == $arg_shortcode['email'] ) {
					$arg_shortcode['userid'] = $current_user;
				} else {
					$person = rtbiz_get_contact_by_email( $arg_shortcode['email'] );
					$arg_shortcode['userid'] = rtbiz_get_wp_user_for_contact( $person->ID );
				}
				if ( is_object( $arg_shortcode['userid'] ) ) {
					$arg_shortcode['userid'] = $arg_shortcode['userid']->ID;
				}
			}

			if ( !empty( $arg_shortcode['userid'] ) ) {
				$is_staff = user_can( $arg_shortcode['userid'], $cap );
			} else {
				$is_staff = user_can( $current_user, $cap );
			}

			// if user can not access Helpdesk don't show him fav tickets
			if ( !empty( $arg_shortcode['fav'] ) && ( true === $arg_shortcode['fav'] || 'true' == $arg_shortcode['fav'] ) ) {
				if ( !$is_staff ) {
					return '';
				}
			}
			$tickets = array();
			$count = 0;
			$oder_shortcode = false;
			if ( !empty( $arg_shortcode['userid'] ) ) {
				if ( !empty( $arg_shortcode['fav'] ) && ( true === $arg_shortcode['fav'] || 'true' == $arg_shortcode['fav'] ) ) {
					$tickets = rtbiz_hd_get_tickets( 'favourite', $arg_shortcode['userid'], $top, $limit, false );
					if ( empty( $tickets ) ) {
						$tickets = array();
					} else {
						$count = $tickets->found_posts;
						$tickets = $tickets->posts;
					}
				} elseif ( $is_staff ) {
					// get fav tickets
					$fav = rtbiz_hd_get_tickets( 'favourite', $arg_shortcode['userid'], 0, 0, true );
					if ( empty( $fav ) )
						$fav = array();
					else
						$fav = $fav->posts;

					// get assigned tickets
					$tickets = rtbiz_hd_get_tickets( 'assignee', $arg_shortcode['userid'], 0, 0, true );
					if ( empty( $tickets ) )
						$tickets = array();
					else
						$tickets = $tickets->posts;
					$tickets = array_udiff( $tickets, $fav, 'rtbiz_hd_compare_wp_post' );
					$tickets = array_merge( $fav, $tickets );
					$count = count( $tickets );
					$tickets = array_slice( $tickets, $top, $limit, true );
				} else {
					$tickets = rtbiz_hd_get_tickets( 'created_by', $arg_shortcode['userid'], $top, $limit, false );
					if ( empty( $tickets ) ) {
						$tickets = array();
					} else {
						$count = $tickets->found_posts;
						$tickets = $tickets->posts;
					}
				}
			} elseif ( !empty( $arg_shortcode['orderid'] ) ) {
				$tickets = rtbiz_hd_get_tickets( 'order', $arg_shortcode['orderid'], $top, $limit, false );
				if ( empty( $tickets ) ) {
					$tickets = array();
				} else {
					$count = $tickets->found_posts;
					$tickets = $tickets->posts;
				}
				$oder_shortcode = true;
			}

			if ( !empty( $arg_shortcode['fav'] ) && ( true === $arg_shortcode['fav'] || 'true' === $arg_shortcode['fav'] ) && empty( $tickets ) ) {
				return '';
			}
			?>
			<?php
			ob_start();
			if ( $first ) {
				?>
				<div class="rthd-ticket-list-header"> <?php
					if ( !empty( $arg_shortcode['fav'] ) ) {
						if ( 'yes' === $arg_shortcode['title'] ) {
							?>
							<h2 class="rthd-ticket-list-title"><?php _e( 'Favourite Tickets', RTBIZ_HD_TEXT_DOMAIN );
						echo ( empty( $tickets ) ) ? '' : ' <span class="rthd-count">(<span class="rthd-current-count">' . count( $tickets ) . '</span> of <span class="rthd-count-total">' . $count . '</span>)</span>';
							?></h2>
							<?php
						}

						if ( 'yes' == $arg_shortcode['show_support_form_link'] ) {
							global $redux_helpdesk_settings;
							if ( isset( $redux_helpdesk_settings['rthd_support_page'] ) && !empty( $redux_helpdesk_settings['rthd_support_page'] ) ) {
								$page = get_post( $redux_helpdesk_settings['rthd_support_page'] );
								$link = get_permalink( $page->ID );
								?>
								<a class="clearfix" href="<?php echo $link; ?>">
									<button class=""><?php _e( 'Create New Ticket', RTBIZ_HD_TEXT_DOMAIN ) ?></button>
								</a>
								<?php
							}
						}
					} else {
						if ( 'yes' === $arg_shortcode['title'] ) {
							?>
							<h2 class="rthd-ticket-list-title"><?php _e( 'Tickets', RTBIZ_HD_TEXT_DOMAIN );
						echo ( empty( $tickets ) ) ? '' : ' <span class="rthd-count">(<span class="rthd-current-count">' . count( $tickets ) . '</span> of <span class="rthd-count-total">' . $count . '</span>)</span>';
							?></h2><?php
						}
						if ( 'yes' == $arg_shortcode['show_support_form_link'] ) {
							if ( isset( $redux_helpdesk_settings['rthd_support_page'] ) && !empty( $redux_helpdesk_settings['rthd_support_page'] ) ) {
								$page = get_post( $redux_helpdesk_settings['rthd_support_page'] );
								$link = get_permalink( $page->ID );
								if ( $oder_shortcode ) {
									$link = add_query_arg( array( 'order_id' => $arg_shortcode['orderid'] ), $link );
								}
								?>
								<a class="clearfix" href="<?php echo $link; ?>">
									<button
										class="btn button-primary btn-primary"><?php _e( 'Create New Ticket', RTBIZ_HD_TEXT_DOMAIN ) ?></button>
								</a>
								<?php
							}
						}
						if ( empty( $tickets ) && !$is_staff ) {
							echo '<p>' . __( 'You have not created any tickets yet. Create one now.', RTBIZ_HD_TEXT_DOMAIN ) . '</p>';
						} else if ( empty( $tickets ) ) {
							echo '<p>' . __( 'No tickets found', RTBIZ_HD_TEXT_DOMAIN ) . '</p>';
						}
					}
					//			echo '<div class="rthd-ticket-list">';
					//			printf( '<p>'._n( 'One Ticket Found', '%d Tickets Found', count( $tickets ), 'my-RT_BIZ_HD_TEXT_DOMAIN-domain' ). '</p>', count( $tickets ) );

					echo '</div>';
					if ( $is_staff && !empty( $arg_shortcode['userid'] ) ) {
						$fav_staff_tickets = rtbiz_hd_get_user_fav_ticket( $arg_shortcode['userid'] );
						if ( !empty( $fav ) ) {
							?>
							<p> <?php _e( 'Your favourite tickets are highlighted below.' ); ?></p>
							<?php
						}
					}

					if ( is_admin() && !empty( $tickets ) && $oder_shortcode ) {
						?>
						<p> <?php _e( 'Below are the all the tickets created by this customer. The tickets for this order are highlighted.', RTBIZ_HD_TEXT_DOMAIN ); ?></p>
					<?php
				}
			}
			if ( !empty( $tickets ) ) {
				if ( $first ) {
					?>
						<table class="wp-list-table striped widefat shop_table my_account_orders rthd_ticket_short_code">
							<thead>
								<tr>
									<th>Ticket ID</th>
									<th>Title</th>
									<th>Last Updated</th>
									<th>Status</th>
							<?php echo ( $is_staff ) ? '<th>Edit</th>' : ''; ?>
								</tr>
							</thead>
							<?php
						}

						foreach ( $tickets as $ticket ) {
							$highlight_class = '';

							if ( $is_staff && !empty( $fav_staff_tickets ) ) {
								if ( in_array( $ticket->ID, $fav_staff_tickets ) ) {
									$highlight_class = 'rthd_highlight_row';
								}
							}
							if ( $oder_shortcode && is_admin() ) {
								$hd_order_by = get_post_meta( $ticket->ID, '_rtbiz_hd_order_id', true );
								$order_by = $arg_shortcode['orderid'];

								if ( $hd_order_by == $order_by ) {
									$highlight_class = 'rthd_highlight_row';
								}
							}
							$date = new DateTime( $ticket->post_modified );
							?>
							<tr class="<?php echo $highlight_class; ?>">
								<td><a class="support"
									   href="<?php echo esc_url( ( rtbiz_hd_is_unique_hash_enabled() ) ? rtbiz_hd_get_unique_hash_url( $ticket->ID ) : get_post_permalink( $ticket->ID )  ); ?>">
										#<?php echo esc_attr( $ticket->ID ) ?> </a></td>
								<td><?php echo $ticket->post_title; ?></td>
								<td> <?php echo esc_attr( human_time_diff( $date->format( 'U' ), current_time( 'timestamp' ) ) ) . esc_attr( __( ' ago' ) ) ?> </td>
								<td>
								<?php
								if ( !empty( $ticket->post_status ) ) {
									echo rtbiz_hd_status_markup( $ticket->post_status );
								}
								?>
								</td>
								<?php if ( current_user_can( rtbiz_get_access_role_cap( RTBIZ_HD_TEXT_DOMAIN, 'editor' ) ) || $ticket->post_author == $current_user->ID ) { ?>
									<td>
										<a class="support" target="_blank"
										   href="<?php echo get_edit_post_link( $ticket->ID ); ?>"><span
												class="dashicons dashicons-edit"></span></a>
									</td>
					<?php } ?>
							</tr>
					<?php
					}
					if ( $first ) {
						?>
						</table>
						<img class="rthd-ticket-short-code-loader helpdeskspinner"
							 src="<?php echo admin_url() . 'images/spinner.gif'; ?>" />
						<?php
					}
				}
				$html_content = ob_get_clean();
				return $html_content;
			}

		}

	}
