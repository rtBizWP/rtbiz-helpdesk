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
 * Description of RT_HD_Admin_Meta_Boxes
 *
 * @since rt-Helpdesk 0.1
 */

if ( ! class_exists( 'RT_Meta_Box_Ticket_Contacts_Blacklist' ) ) {
	class RT_Meta_Box_Ticket_Contacts_Blacklist {

        /**
         * Hook for ajax
         *
         * @since 0.1
         */
        public function __construct() {
            add_action( 'wp_ajax_rthd_show_blacklisted_confirmation', array( $this, 'show_blacklisted_confirmation' ) );
            add_action( 'wp_ajax_rthd_add_blacklisted_contact', array( $this, 'add_blacklisted_contact' ) );
            add_action( 'wp_ajax_rthd_remove_blacklisted_contact', array( $this, 'remove_blacklisted_contact' ) );
        }

		/**
		 * Output the metabox
		 *
		 * @since rt-Helpdesk 0.1
		 *
		 * @param $post
		 */
		public static function ui( $post ) {
            $blocklist_action = 'remove_blacklisted';
            if ( isset( $post->ID ) ) {
                $blocklist_action = ( 'true' != get_post_meta( $post->ID, '_rtbiz_hd_is_blocklised', true )) ? 'blacklisted_confirmation' : 'remove_blacklisted';
            }
            $class = 'rthd-hide-row';
            if ( 'remove_blacklisted' == $blocklist_action ){
                $blacklistedEmail = rthd_get_blacklist_emails();
                $arrContactsEmail = array();
                $contacts = rt_biz_get_post_for_contact_connection( $post->ID, Rt_HD_Module::$post_type );
                foreach( $contacts as $contact ) {
                    $arrContactsEmail[] = get_post_meta($contact->ID, Rt_Entity::$meta_key_prefix . Rt_Contact::$primary_email_key, true);
                }
                $contacts =  array_intersect( $blacklistedEmail, $arrContactsEmail );
                $class = '';
            }
			?>
            <div id="contacts-blacklist-container" class="row_group <?php echo $class; ?>"><?php
                if ( 'remove_blacklisted' == $blocklist_action ){ ?>
                    <ui class="blacklist_contacts_list"><?php
                        foreach( $contacts as $email ){ ?>
                            <li><?php echo $email; ?></li>
                        <?php } ?>
                    </ui><?php
                }
                ?></div>
			<div id="contacts-blacklist-action" class="row_group">
			<a href="#" data-action="<?php echo $blocklist_action; ?>"  data-postid="<?php echo isset( $post->ID ) ? $post->ID : '0' ; ?>" class="button" id="rthd_ticket_contacts_blacklist">
                <?php if ( 'remove_blacklisted' == $blocklist_action ){
                    _e( 'Remove', RT_HD_TEXT_DOMAIN );
                }else{
                    _e( 'Blacklist', RT_HD_TEXT_DOMAIN );
                } ?>
            </a>
			</div>
            <p class="description">Note : Added ticket contacts as blacklist.</p>
            <?php
		}

        /**
         * Ajax request for show confirmation for block listed contact of given ticket before contacts are block listed
         */
        function show_blacklisted_confirmation(){
            if( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ){
                return;
            }
            $reponse = array();
            $reponse['status'] = false;
            $ticket_data = $_POST;
            $contacts = rt_biz_get_post_for_contact_connection( $ticket_data['post_id'], Rt_HD_Module::$post_type );
            ob_start(); ?>
            <ui class="blacklist_contacts_list"><?php
                foreach( $contacts as $contact ){
                    $email = get_post_meta( $contact->ID, Rt_Entity::$meta_key_prefix.Rt_Contact::$primary_email_key, true );?>
                    <li><?php echo $email; ?></li>
                <?php } ?>
            </ui>
            <div class="confirmation-container">
                <p>Are you sure to blacklist above lised contact?</p>
                <a href="#" data-action="blacklisted_contact"  data-postid="<?php echo $ticket_data['post_id']; ?>" class="button" id="rthd_ticket_contacts_blacklist_yes"><?php _e( 'Yes', RT_HD_TEXT_DOMAIN ); ?></a>
                <a href="#" data-action="blacklisted_contact_no"  data-postid="<?php echo $ticket_data['post_id']; ?>" class="button" id="rthd_ticket_contacts_blacklist_no"><?php _e( 'No', RT_HD_TEXT_DOMAIN ); ?></a>
            </div>
            <?php $reponse['confirmation_ui'] = ob_get_clean();
            $reponse['status'] = true;
            echo json_encode( $reponse );
            die( 0 );
        }

        /**
         * Ajax request for add blacklist email into blacklist email list
         */
        function add_blacklisted_contact(){
            if( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ){
                return;
            }
            $reponse = array();
            $reponse['status'] = false;
            $contacts = rt_biz_get_post_for_contact_connection( $_POST['post_id'], Rt_HD_Module::$post_type );
            $arrContactsEmail = array();
            $blacklistedEmail = rthd_get_blacklist_emails();
            foreach( $contacts as $contact ) {
                $arrContactsEmail[] = get_post_meta($contact->ID, Rt_Entity::$meta_key_prefix . Rt_Contact::$primary_email_key, true);
            }
            if ( ! empty( $arrContactsEmail ) ){
                if ( ! empty( $blacklistedEmail ) ) {
                    $arrContactsEmail = array_merge( $blacklistedEmail, $arrContactsEmail);
                }
            }
            $arrContactsEmail = array_unique( $arrContactsEmail );
            $arrContactsEmail = implode( "\n", $arrContactsEmail );
            if ( !empty( $arrContactsEmail ) ){
                rthd_set_redux_settings( 'rthd_blacklist_emails_textarea', $arrContactsEmail );
                update_post_meta( $_POST['post_id'], '_rtbiz_hd_is_blocklised', 'true' );
                ob_start(); ?>
                <a href="#" data-action="remove_blacklisted"  data-postid="<?php echo $_POST['post_id']; ?>" class="button" id="rthd_ticket_contacts_blacklist">
                    <?php _e( 'Remove', RT_HD_TEXT_DOMAIN ); ?>
                </a>
                <?php $reponse['remove_ui'] = ob_get_clean();
                $reponse['status'] = true;
            }
            echo json_encode( $reponse );
            die( 0 );
        }

        /**
         * Ajax request for remove blacklist email into blacklist email list
         */
        function remove_blacklisted_contact(){
            if( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ){
                return;
            }
            $reponse = array();
            $reponse['status'] = false;
            $contacts = rt_biz_get_post_for_contact_connection( $_POST['post_id'], Rt_HD_Module::$post_type );
            $arrContactsEmail = array();
            $blacklistedEmail = rthd_get_blacklist_emails();
            foreach( $contacts as $contact ) {
                $arrContactsEmail[] = get_post_meta($contact->ID, Rt_Entity::$meta_key_prefix . Rt_Contact::$primary_email_key, true);
            }
            if ( ! empty( $arrContactsEmail ) ){
                if ( ! empty( $blacklistedEmail ) ) {
                    $arrContactsEmail = array_diff( $blacklistedEmail, $arrContactsEmail);
                }
            }
            $arrContactsEmail = array_unique( $arrContactsEmail );
            $arrContactsEmail = implode( "\n", $arrContactsEmail );

            rthd_set_redux_settings( 'rthd_blacklist_emails_textarea', $arrContactsEmail );
            update_post_meta( $_POST['post_id'], '_rtbiz_hd_is_blocklised', 'false' );
            ob_start(); ?>
            <a href="#" data-action="blacklisted_confirmation"  data-postid="<?php echo $_POST['post_id']; ?>" class="button" id="rthd_ticket_contacts_blacklist">
                <?php _e( 'Blacklist', RT_HD_TEXT_DOMAIN ); ?>
            </a>
            <?php $reponse['addBlacklist_ui'] = ob_get_clean();

            $reponse['status'] = true;
            echo json_encode( $reponse );
            die( 0 );
        }

	}
}