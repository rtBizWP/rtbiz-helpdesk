<?php
if ( ! class_exists( 'Rtbiz_HD_Slack_Integration' ) ) {

	/**
	 * Class Rtbiz_HD_Slack_Integration
	 *
	 * Slack Integration specific functionalities of the plugin
	 * depends on slack <https://wordpress.org/plugins/slack/> plugin
	 */
	class Rtbiz_HD_Slack_Integration {

		private $slack_name = '';

		/**
		 * Rtbiz_HD_Slack_Integration constructor.
		 */
		function __construct() {
			add_action( 'admin_notices', array( $this, 'check_slack_integration_plugin' ) );

			add_filter( 'rtbiz_contact_meta_fields', array( $this, 'show_slack_channel_field' ) );
			add_filter( 'slack_get_events', array( $this, 'slack_get_events' ) );

			add_action( 'rt_hd_after_new_support_ticket_saved', array( $this, 'schedule_cron_for_new_ticket' ) );
			add_action( 'rt_hd_ajax_after_new_ticket_followup', array( $this, 'unschedule_cron_on_new_followup' ), 10, 2 );
			add_action( 'rt_hd_ajax_after_new_ticket_followup', array( $this, 'handle_ticket_followup' ), 10, 2 );
			add_action( 'init', array( $this, 'register_schedule_action_callback' ) );
		}


		/**
		 * Add reminder slack ID field on contact edit page.
		 *
		 * @param array $meta_fields Meta field configuration array.
		 *
		 * @return array
		 */
		public function show_slack_channel_field( $meta_fields ) {
			$meta_fields[] = array(
				'key'             => 'reminder_slack_id',
				'text'            => __( 'Reminder slack ID' ),
				'label'           => __( 'Reminder slack ID' ),
				'is_multiple'     => false,
				'type'            => 'text',
				'name'            => 'contact_meta[reminder_slack_id]',
				'description'     => __( 'Current active slack ID for reminders' ),
				'hide_for_client' => true,
				'category'        => 'Contact',
			);

			return $meta_fields;
		}

		/**
		 * Register action callbacks for cron scheduled events.
		 */
		public function register_schedule_action_callback() {
			$new_posts = get_option( 'rt_hd_si_new_tickets' );
			if ( empty( $new_posts ) ) {
				return;
			}

			foreach ( $new_posts as $post ) {
				add_action( 'rt_hd_si_new_ticket_cron_' . $post, array( $this, 'handle_schedule_event' ) );
			}
		}

		/**
		 * Handle scheduled event for post.
		 *
		 * @param int $post_id Post ID for which event is being handled.
		 */
		public function handle_schedule_event( $post_id ) {
			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				return;
			}

			$contact = rtbiz_get_contact_for_wp_user( $post->post_author );
			if ( empty( $contact ) ) {
				return;
			}

			if ( is_array( $contact ) ) {
				$contact = $contact[0];
			}

			$slack_id = Rtbiz_Entity::get_meta( $contact->ID, 'reminder_slack_id', true );
			if ( empty( $slack_id ) ) {
				return;
			}

			$this->slack_name = $slack_id;

			add_filter( 'slack_channel_name', array( $this, 'change_slack_channel_name' ) );
			do_action( 'rt_hd_si_reminder_for_new_ticket', $post );
			remove_filter( 'slack_channel_name', array( $this, 'change_slack_channel_name' ) );
		}

		/**
		 * Return changed slack name.
		 *
		 * @return string
		 */
		public function change_slack_channel_name() {
			return $this->slack_name;
		}

		/**
		 * Unschedule event if staff note or public note is added.
		 *
		 * @param array $comment New comment array.
		 * @param int   $post_id Post ID of new comment.
		 */
		public function unschedule_cron_on_new_followup( $comment, $post_id ) {
			if ( $comment['comment_type'] != Rtbiz_HD_Import_Operation::$FOLLOWUP_STAFF && $comment['comment_type'] == Rtbiz_HD_Import_Operation::$FOLLOWUP_PUBLIC ) {
				return;
			}

			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				return;
			}

			$this->handle_ticket_followup( $comment, $post );

			$time = wp_next_scheduled( 'rt_hd_si_new_ticket_cron_' . $post->ID, array( 'post_id' => $post->ID ) );
			if ( $time ) {
				wp_unschedule_event( $time, 'rt_hd_si_new_ticket_cron_' . $post->ID, array( 'post_id' => $post->ID ) );
			}

			$new_posts = get_option( 'rt_hd_si_new_tickets' );
			if ( ! empty( $new_posts[$post->ID] ) ) {
				unset( $new_posts[$post->ID] );
				if ( empty( $new_posts ) ) {
					delete_option( 'rt_hd_si_new_tickets' );
				} else {
					update_option( 'rt_hd_si_new_tickets', $new_posts );
				}
			}
		}


		/**
		 * Schedule cron event for new ticket created.
		 *
		 * @param $post_id
		 *
		 */
		public function schedule_cron_for_new_ticket( $post_id ) {
			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				return;
			}

			if ( ! wp_next_scheduled( 'rt_hd_si_new_ticket_cron_' . $post->ID, array( 'post_id' => $post->ID ) ) ) {
				wp_schedule_event( strtotime( '+2 days', time() ), 'daily', 'rt_hd_si_new_ticket_cron_' . $post->ID, array( 'post_id' => $post->ID ) );
			}

			$new_posts = get_option( 'rt_hd_si_new_tickets' );
			if ( empty( $new_posts ) ) {
				$new_posts = array();
			}
			$new_posts[$post->ID] = $post->ID;
			update_option( 'rt_hd_si_new_tickets', $new_posts );
		}

		/**
		 * Handle new ticket folloups and create action for staff & public notes.
		 *
		 * @param array      $comment Comment data in array.
		 * @param int|object $post    Post ID or post object.
		 */
		public function handle_ticket_followup( $comment, $post ) {
			if ( ! is_object( $post ) ) {
				$post = get_post( $post );
				if ( empty( $post ) ) {
					return;
				}
			}

			if ( $post->post_type !== Rtbiz_HD_Module::$post_type ) {
				return;
			}

			if ( $comment['comment_type'] == Rtbiz_HD_Import_Operation::$FOLLOWUP_STAFF ) {
				do_action( 'rt_hd_si_new_staff_note', $comment, $post );
			}

			if ( $comment['comment_type'] == Rtbiz_HD_Import_Operation::$FOLLOWUP_PUBLIC ) {
				do_action( 'rt_hd_si_new_public_note', $comment, $post );
			}
		}

		/**
		 * Check for slack plugin, show notice info if not active.
		 */
		public function check_slack_integration_plugin() {
			if ( ! is_plugin_active( 'slack/slack.php' ) ) {
				?>
				<div class="notice-info settings-error notice is-dismissible">
					<p><?php echo '<strong>' . esc_html__( 'rtBiz Helpdesk', 'rtbiz-helpdesk' ) . ': </strong>' . esc_html__( 'Install', 'rtbiz-helpdesk' ) . ' <a href="https://wordpress.org/plugins/slack/" target="_blank">' . esc_html__( 'Slack', 'rtbiz-helpdesk' ) . '</a> ' . esc_html__( 'to enable slack notifications.', 'rtbiz-helpdesk' ); ?></p>
				</div>
				<?php
			}
		}

		/**
		 * Return event parameters of new ticket creation.
		 *
		 * @return array
		 */
		public function rthd_new_ticket() {

			return array(
				'action'      => 'rt_hd_after_new_support_ticket_saved',
				'description' => esc_html__( 'Helpdesk - When new helpdesk ticket is created', 'rtbiz-helpdesk' ),
				'default'     => false,
				'message'     => function ( $post_id ) {
					$post = get_post( $post_id );
					if ( empty( $post ) ) {
						return false;
					}

					if ( class_exists( 'Rtbiz_HD_Module' ) ) {
						if ( $post->post_type !== Rtbiz_HD_Module::$post_type ) {
							return false;
						}
					} elseif ( $post->post_type !== 'ticket' ) {
						return false;
					}

					$message = sprintf( 'New ticket: <%s|%s>', get_post_type_archive_link( $post->post_type ) . '/' . $post->ID, $post->post_title );

					$author = get_post_meta( $post->ID, '_rtbiz_hd_created_by', true );
					if ( ! empty( $author ) ) {
						$author = rtbiz_hd_get_user_id_by_contact_id( $author );
						if ( ! empty( $author ) ) {
							$author = get_userdata( $author );
							if ( ! empty( $author ) ) {
								$message .= sprintf( ' by %s', $author->data->display_name );
							}
						}
					}

					return apply_filters( 'rt_hd_si_new_support_page_ticket_slack_message', $message );
				},
			);

		}

		/**
		 * Return event parameters of new ticket creation.
		 *
		 * @return array
		 */
		public function rthd_new_ticket_assignee_reminder() {

			return array(
				'action'      => 'rt_hd_si_reminder_for_new_ticket',
				'description' => esc_html__( 'Helpdesk - After 2+ days of no reply on new ticket', 'rtbiz-helpdesk' ),
				'default'     => false,
				'message'     => function ( $post ) {
					$date = new DateTime( $post->post_date );
					$diff = $date->diff( new DateTime() );

					$message = sprintf( 'Reminder for <%s|%s>.', get_post_type_archive_link( $post->post_type ) . '/' . $post->ID, $post->post_title );

					if ( $diff->s >= 2 ) {
						$message .= sprintf( ' It\'s been %d days since no Staff Note / Public Note.', $diff->s );
					}

					return apply_filters( 'rt_hd_si_new_support_page_ticket_slack_message', $message );
				},
			);

		}

		/**
		 * Return event parameters of ticket author change.
		 *
		 * @return array
		 */
		public function rthd_ticket_author_change() {

			return array(
				'action'      => 'rt_hd_ajax_front_end_after_ticket_assignee_changed',
				'description' => esc_html__( 'Helpdesk - When helpdesk ticket author changes', 'rtbiz-helpdesk' ),
				'default'     => false,
				'message'     => function ( $post_id, $old_author, $new_author ) {
					$post = get_post( $post_id );
					if ( empty( $post ) ) {
						return false;
					}

					$old_author   = get_userdata( $old_author );
					$new_author   = get_userdata( $new_author );
					$current_user = '';
					if ( $old_author->ID === get_current_user_id() ) {
						$current_user = $old_author->data->display_name;
					} elseif ( $new_author->ID === get_current_user_id() ) {
						$current_user = $new_author->data->display_name;
					} else {
						$current_user = get_userdata( get_current_user_id() )->data->display_name;
					}

					$message = sprintf(
						'Ticket <%s|%s> assignee changed from *%s* to *%s* by *%s*',
						get_post_type_archive_link( $post->post_type ) . '/' . $post->ID,
						$post->post_title,
						$old_author->data->display_name,
						$new_author->data->display_name,
						$current_user
					);

					return apply_filters( 'rt_hd_si_ticket_author_change_slack_message', $message );
				},
			);

		}

		/**
		 * Return event parameters of new staff note creation.
		 *
		 * @return array
		 */
		public function rthd_new_staff_note() {

			return array(
				'action'      => 'rt_hd_si_new_staff_note',
				'description' => esc_html__( 'Helpdesk - When new staff note is added on helpdesk ticket', 'rtbiz-helpdesk' ),
				'default'     => false,
				'message'     => function ( $comment, $post ) {
					$author = get_comment_author( $comment['comment_id'] );

					$post_archive_link = get_post_type_archive_link( $post->post_type );
					$message           = sprintf(
						'New *<%s|Staff Note>* on *<%s|%s>* ticket by *%s*',
						$post_archive_link . '/' . $post->ID . '#comment-' . $comment['comment_id'],
						$post_archive_link . '/' . $post->ID,
						$post->post_title,
						$author
					);

					return apply_filters( 'rt_hd_si_new_staff_note_slack_message', $message );
				},
			);

		}

		/**
		 * Return event parameters of new public note creation.
		 *
		 * @return array
		 */
		public function rthd_new_public_note() {

			return array(
				'action'      => 'rt_hd_si_new_public_note',
				'description' => esc_html__( 'Helpdesk - When new public note is added on helpdesk ticket', 'rtbiz-helpdesk' ),
				'default'     => false,
				'message'     => function ( $comment, $post ) {
					$author = get_comment_author( $comment['comment_id'] );

					$post_archive_link = get_post_type_archive_link( $post->post_type );
					$message           = sprintf(
						'New *<%s|Public Note>* on *<%s|%s>* ticket by *%s*',
						$post_archive_link . '/' . $post->ID . '#comment-' . $comment['comment_id'],
						$post_archive_link . '/' . $post->ID,
						$post->post_title,
						$author
					);

					return apply_filters( 'rt_hd_si_new_staff_note_slack_message', $message );
				},
			);

		}

		/**
		 * Return event parameters of ticket status change.
		 *
		 * @return array
		 */
		public function rthd_ticket_status_changed() {

			return array(
				'action'      => 'rt_hd_ajax_front_end_after_ticket_status_changed',
				'description' => esc_html__( 'Helpdesk - When helpdesk ticket status changed', 'rtbiz-helpdesk' ),
				'default'     => false,
				'message'     => function ( $post_id, $old_status, $new_status ) {
					$old_term = get_term_by( 'slug', $old_status, 'rtbiz_helpdesk_post_status' );
					$new_term = get_term_by( 'slug', $new_status, 'rtbiz_helpdesk_post_status' );

					$post = get_post( $post_id );
					if ( empty( $post ) ) {
						return false;
					}

					$user = get_userdata( get_current_user_id() );
					if ( empty( $user ) ) {
						return false;
					}

					$message = sprintf(
						'%s changed status of <%s|%s> ticket from %s to %s',
						$user->data->display_name,
						get_post_type_archive_link( $post->post_type ) . '/' . $post->ID,
						$post->post_title,
						( ! empty( $old_term ) ) ? $old_term->name : $old_status,
						( ! empty( $new_term ) ) ? $new_term->name : $new_status
					);

					return apply_filters( 'rthd_ticket_status_changed_slack_message', $message );
				},
			);

		}

		/**
		 * Register events for slack integration plugin.
		 *
		 * @param array $events Array of events.
		 *
		 * @return array
		 */
		public function slack_get_events( $events ) {
			$events['rt_hd_ajax_front_end_after_ticket_status_changed']   = $this->rthd_ticket_status_changed();
			$events['rt_hd_after_new_support_ticket_saved']               = $this->rthd_new_ticket();
			$events['rt_hd_si_new_staff_note']                            = $this->rthd_new_staff_note();
			$events['rt_hd_si_new_public_note']                           = $this->rthd_new_public_note();
			$events['rt_hd_ajax_front_end_after_ticket_assignee_changed'] = $this->rthd_ticket_author_change();
			$events['rt_hd_si_reminder_for_new_ticket']                   = $this->rthd_new_ticket_assignee_reminder();

			return $events;
		}

	}

}
