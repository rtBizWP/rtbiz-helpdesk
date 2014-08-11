<?php

/**
 * rt-helpdesk Functions
 *
 * Helper functions for rt-helpdesk
 *
 * @author udit
 */
function rthd_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {

	if ( $args && is_array( $args ) )
		extract( $args );

	$located = rthd_locate_template( $template_name, $template_path, $default_path );

	do_action( 'rthd_before_template_part', $template_name, $template_path, $located, $args );

	include( $located );

	do_action( 'rthd_after_template_part', $template_name, $template_path, $located, $args );
}

function rthd_locate_template( $template_name, $template_path = '', $default_path = '' ) {

	global $rt_wp_hd;
	if ( ! $template_path ) {
		$template_path = $rt_wp_hd->templateURL;
	}
	if ( ! $default_path ) {
		$default_path = RT_HD_PATH_TEMPLATES;
	}

	// Look within passed path within the theme - this is priority
	$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name
			)
	);

	// Get default template
	if ( ! $template )
		$template = $default_path . $template_name;

	// Return what we found
	return apply_filters( 'rthd_locate_template', $template, $template_name, $template_path );
}

function rthd_sanitize_taxonomy_name( $taxonomy ) {
	$taxonomy = strtolower( stripslashes( strip_tags( $taxonomy ) ) );
	$taxonomy = preg_replace( '/&.+?;/', '', $taxonomy ); // Kill entities
	$taxonomy = str_replace( array( '.', '\'', '"' ), '', $taxonomy ); // Kill quotes and full stops.
	$taxonomy = str_replace( array( ' ', '_' ), '-', $taxonomy ); // Replace spaces and underscores.

	return $taxonomy;
}

function rthd_attribute_taxonomy_name( $name ) {
	return 'rt_' . rthd_sanitize_taxonomy_name( $name );
}

function rthd_post_type_name( $name ) {
	return 'rt_' . rthd_sanitize_taxonomy_name( $name );
}

function rthd_get_all_attributes( $attribute_store_as = '' ) {
	global $rt_hd_attributes_model;
	$attrs = $rt_hd_attributes_model->get_all_attributes();

	if ( empty( $attribute_store_as ) ) {
		return $attrs;
	}

	$newAttr = array();
	foreach ( $attrs as $attr ) {
		if ( $attr->attribute_store_as == $attribute_store_as )
			$newAttr[] = $attr;
	}

	return $newAttr;
}

function rthd_get_attributes( $post_type, $attribute_store_as = '' ) {
	global $rt_hd_attributes_relationship_model, $rt_hd_attributes_model;
	$relations = $rt_hd_attributes_relationship_model->get_relations_by_post_type( $post_type );
	$attrs = array();

	foreach ( $relations as $relation ) {
		$attrs[] = $rt_hd_attributes_model->get_attribute( $relation->attr_id );
	}

	if ( empty( $attribute_store_as ) ) {
		return $attrs;
	}

	$newAttr = array();
	foreach ( $attrs as $attr ) {
		if ( $attr->attribute_store_as == $attribute_store_as )
			$newAttr[] = $attr;
	}
	return $newAttr;
}

/* * ********* Post Term To String **** */

function rthd_post_term_to_string( $postid, $taxonomy, $termsep = ',' ) {
	$termsArr = get_the_terms( $postid, $taxonomy );
	$tmpStr = '';
	if ( $termsArr ) {
		$sep = '';
		foreach ( $termsArr as $tObj ) {
			$tmpStr .= $sep . $tObj->name;
			$sep = $termsep;
		}
	}
	return $tmpStr;
}

/* * ********* Post Term To String **** */

function rthd_extract_key_from_attributes( $attr ) {
	return $attr->attribute_name;
}

function rthd_is_system_email( $email ) {
	$settings = rthd_get_settings();
	if ( isset( $settings[ 'system_email' ] ) && $email == $settings[ 'system_email' ] ) {
		return true;
	}
	return false;
}

function rthd_get_all_system_emails() {
	$emails = array();
	$settings = rthd_get_settings();
	if ( isset( $settings[ 'system_email' ] ) && ! empty( $settings[ 'system_email' ] ) ) {
		$emails[] = $settings[ 'system_email' ];
	}
	return $emails;
}

function rthd_get_all_participants( $ticket_id ) {
	$ticket = get_post( $ticket_id );
	$participants = array();
	if ( isset( $ticket->post_author ) ) {
		$participants[] = $ticket->post_author;
	}
	$subscribers = get_post_meta( $ticket_id, 'subscribe_to', true );
	$participants = array_merge( $participants, $subscribers );

//	TODO
//	$contacts = wp_get_post_terms( $ticket_id, rthd_attribute_taxonomy_name( 'contacts' ) );
//	foreach ( $contacts as $contact ) {
//		$user_id = get_term_meta( $contact->term_id, 'user_id', true );
//		if(!empty($user_id)) {
//			$participants[] = $user_id;
//		}
//	}

	$comments = get_comments( array( 'order' => 'DESC', 'post_id' => $ticket_id, 'post_type' => $ticket->post_type ) );
	foreach ( $comments as $comment ) {
		$p = '';
		$to = get_comment_meta( $comment->comment_ID, '_email_to', true );
		if ( ! empty( $to ) )
			$p .= $to . ',';
		$cc = get_comment_meta( $comment->comment_ID, '_email_cc', true );
		if ( ! empty( $cc ) )
			$p .= $cc . ',';
		$bcc = get_comment_meta( $comment->comment_ID, '_email_bcc', true );
		if ( ! empty( $bcc ) )
			$p .= $bcc;

		if ( ! empty( $p ) ) {
			$p_arr = explode( ',', $p );
			$p_arr = array_unique( $p_arr );
			$all_p = array_merge( $all_p, $p_arr );
		}
	}
	$all_p = array_unique( $all_p );
	foreach ( $all_p as $p ) {
		$user = get_user_by( 'email', $p );
		if ( $user ) {
			$participants[] = $user->ID;
		}
	}
	return array_unique( $participants );
}

function rthd_get_ticket_table_name() {

	global $wpdb;
	return $wpdb->prefix . 'rt_wp_hd_ticket_index';
}

function rthd_get_user_ids( $user ) {
	return $user->ID;
}

function rt_update_post_term_count( $terms, $taxonomy ) {
	global $wpdb;

	$object_types = ( array ) $taxonomy->object_type;

	foreach ( $object_types as &$object_type )
		list( $object_type ) = explode( ':', $object_type );

	$object_types = array_unique( $object_types );

	if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) ) {
		unset( $object_types[ $check_attachments ] );
		$check_attachments = true;
	}

	if ( $object_types )
		$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );

	foreach ( ( array ) $terms as $term ) {
		$count = 0;

		// Attachments can be 'inherit' status, we need to base count off the parent's status if so
		if ( $check_attachments )
			$count += ( int ) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->term_relationships.object_id  AND post_type = 'attachment' AND term_taxonomy_id = %d", $term ) );

		if ( $object_types )
			$count += ( int ) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id  AND post_type IN ('" . implode( "', '", $object_types ) . "') AND term_taxonomy_id = %d", $term ) );

		do_action( 'edit_term_taxonomy', $term, $taxonomy );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
		do_action( 'edited_term_taxonomy', $term, $taxonomy );
	}
}

/**
 * Function to encrypt or decrypt the given value
 * @param string
 * @return string
 */
function rthd_encrypt_decrypt( $string ) {

	$string_length = strlen( $string );
	$encrypted_string = "";

	/**
	 * For each character of the given string generate the code
	 */
	for ( $position = 0; $position < $string_length; $position ++ ) {
		$key = ( ( $string_length + $position ) + 1 );
		$key = ( 255 + $key ) % 255;
		$get_char_to_be_encrypted = substr( $string, $position, 1 );
		$ascii_char = ord( $get_char_to_be_encrypted );
		$xored_char = $ascii_char ^ $key;  //xor operation
		$encrypted_char = chr( $xored_char );
		$encrypted_string .= $encrypted_char;
	}

	/**
	 * Return the encrypted/decrypted string
	 */
	return $encrypted_string;
}

// wp1_text_diff
function rthd_text_diff( $left_string, $right_string, $args = null ) {
	$defaults = array( 'title' => '', 'title_left' => '', 'title_right' => '' );
	$args = wp_parse_args( $args, $defaults );

	$left_string = normalize_whitespace( $left_string );
	$right_string = normalize_whitespace( $right_string );
	$left_lines = explode( "\n", $left_string );
	$right_lines = explode( "\n", $right_string );

	$renderer = new Rt_HD_Email_Diff();
	$text_diff = new Text_Diff( $left_lines, $right_lines );
	$diff = $renderer->render( $text_diff );

	if ( ! $diff )
		return '';

	$r = "<table class='diff' style='width: 100%;background: white;margin-bottom: 1.25em;border: solid 1px #dddddd;border-radius: 3px;margin: 0 0 18px;'>\n";
	$r .= "<col class='ltype' /><col class='content' /><col class='ltype' /><col class='content' />";

	if ( $args[ 'title' ] || $args[ 'title_left' ] || $args[ 'title_right' ] )
		$r .= "<thead>";
	if ( $args[ 'title' ] )
		$r .= "<tr class='diff-title'><th colspan='4'>{$args[ 'title' ]}</th></tr>\n";
	if ( $args[ 'title_left' ] || $args[ 'title_right' ] ) {
		$r .= "<tr class='diff-sub-title'>\n";
		$r .= "\t<td></td><th>{$args[ 'title_left' ]}</th>\n";
		$r .= "\t<td></td><th>{$args[ 'title_right' ]}</th>\n";
		$r .= "</tr>\n";
	}
	if ( $args[ 'title' ] || $args[ 'title_left' ] || $args[ 'title_right' ] )
		$r .= "</thead>\n";
	$r .= "<tbody>\n$diff\n</tbody>\n";
	$r .= "</table>";
	return $r;
}

function rthd_get_settings() {
	$default = array(
		'attach_contacts' => 'yes',
		'attach_accounts' => 'yes',
		'system_email' => '',
		'outbound_emails' => '',
	);
	$settings = get_site_option( 'rt_helpdesk_settings', $default );
	return $settings;
}

function rthd_update_settings( $key, $value ) {

}

function rthd_get_menu_label() {
	$menu_label = get_site_option( 'rthd_menu_label', __( 'rtHelpdesk' ) );
	return $menu_label;
}

function rthd_update_menu_label( $menu_label ) {
	update_site_option( 'rthd_menu_label', $menu_label );
}

function rthd_get_logo_url() {
	$logo_url = get_site_option( 'rthd_logo_url', RT_HD_URL.'app/assets/img/hd-16X16.png' );
	return $logo_url;
}

function rthd_update_logo_url( $logo_url ) {
	update_site_option( 'rthd_logo_url', $logo_url );
}
