/**
 * Created by spock on 21/4/15.
 */
jQuery( document ).ready(function ($) {

	var wizard;
	var skip_step = false;
	var next_page_skip = false;
	var imported_users = 0;
	var rthdSetup = {
		init: function () {
			rthdSetup.setup_wizard();
			rthdSetup.search_users();
			rthdSetup.add_user_single();
			rthdSetup.assingee_page();
			rthdSetup.acl_save();
			rthdSetup.on_connected_store_change();
			rthdSetup.hide_notice();
			rthdSetup.delete_product();
		},

		bind_enter_event: function (textbox, button) {
			jQuery( document ).on('keypress', textbox, function (e) {
				if (e.keyCode == 13) {
					button.click();
				}
			});
		},
		hide_notice: function () {
			setTimeout(function () {
				jQuery( ".rthd-hide-notice-setup-wizard" ).hide();
			}, 10000);
		},
		setup_wizard: function () {
			wizard = jQuery( "#wizard" ).steps({
				headerTag: "h3",
				bodyTag: "fieldset",
				transitionEffect: 1,
				forceMoveForward: true,
				//titleTemplate: "#title#",
				//enableAllSteps: true,
				onStepChanging: function (event, currentIndex, newIndex) {
					//alert("moving to "+newIndex+" from "+ currentIndex);
					if (skip_step) {
						skip_step = false;
						return true;
					}

					if (currentIndex == 1) {
						// save product selection and sync products
						skip_step = false;
						return rthdSetup.connect_store();
					}
					// active this after screen is fixed
					if (currentIndex === 0) {
						//save support form
						return rthdSetup.support_page();
					}
					// save assingee
					if (currentIndex == 3) {
						rthdSetup.save_assignee();
						return false;
					}
					// get assignee UI
					if (currentIndex == 2) {
						rthdSetup.get_assingee_ui();
						return false;
					}

					//
					if (currentIndex == 4) {
						//rthdSetup.get_acl_view();
						return rthdSetup.outbound_mail_setup();
					}

					return true;
				},
				onStepChanged: function (event, currentIndex, priorIndex) {

					rthdSetup.custom_page_action( currentIndex );

					//alert("on step changed moved to "+currentIndex+" from "+ priorIndex);
					return true;
				},
				onFinishing: function (event, currentIndex) {
					//alert("on finishing changed moved to "+currentIndex);
					return true;
				},
				onFinished: function (event, currentIndex) {
					window.location.replace( rtbiz_hd_dashboard_url );
				}
			});
		},
		on_connected_store_change: function () {
			jQuery( document ).on('change', 'input:checkbox[name=rthd-wizard-store]', function (e) {
				if (jQuery( this ).val() == 'custom' && jQuery( this ).is( ':checked' )) {
					jQuery( '.rthd-wizard-store-custom-div' ).show();
				}
				if ( ! jQuery( this ).is( ':checked' )) {
					jQuery( '.rthd-wizard-store-custom-div' ).hide();
				}
			});

			jQuery( document ).on('click', '#rthd-setup-store-new-team-submit', function (e) {
				var new_term = jQuery( '#rthd-setup-store-new-team' ).val();
				if (new_term.length !== 0 && ! new_term.trim()) {
					return;
				}
				$.ajax({
					url: ajaxurl,
					dataType: "json",
					type: 'post',
					data: {
						action: 'rtbiz_hd_add_new_product',
						product: new_term
					},
					success: function (data) {
						if (data.status) {
							jQuery( 'table.rthd-setup-wizard-new-product' ).append( '<tr id="li-' + data.term_id + '"><td>' + new_term + '</td><td><a href="" class="rthd-delete-product" id="' + data.term_id + '"><span class="dashicons dashicons-dismiss"></span></a></td></tr>' );
							jQuery( '#rthd-setup-store-new-team' ).val( '' );
						} else if ( data.product_exists ) {
							alert(data.product_exists);
						}
					}
				});
			});
		},
		delete_product: function() {
			jQuery( document ).on('click', '.rthd-delete-product', function (e) {
				e.preventDefault();
				var term_id = jQuery( this ).attr( 'id' );

				jQuery.ajax({
					url: ajaxurl,
					dataType: 'json',
					type: 'post',
					data: {
						action: 'rtbiz_hd_delete_product',
						term_id: term_id
					},
					success: function (data) {
						if (data.status) {
							jQuery( '#li-' + term_id ).remove();
						}
					},
					error: function (xhr, textStatus, errorThrown) {
//						jQuery( '#contacts-blacklist-container' ).html( "Some error with ajax request!!" ).show();
					}
				});
			});
		},
		search_users: function () {
			AutocomepleteTextBox = jQuery( '#rthd-user-autocomplete' );
			if (jQuery( ".rthd-user-autocomplete" ).length > 0) {
				jQuery( ".rthd-user-autocomplete" ).autocomplete({
					source: function (request, response) {
						$.ajax({
							url: ajaxurl,
							dataType: "json",
							type: 'post',
							data: {
								action: 'rtbiz_hd_search_non_hd_user_by_name',
								maxRows: 10,
								query: request.term
							},
							success: function (data) {
								if (data.hasOwnProperty( 'have_access' )) {
									// email have access so no need of popup to asking for adding user
									jQuery( '.rthd-warning' ).html( '<strong>' + AutocomepleteTextBox.val() + '</strong> Already have helpdesk access' );
									jQuery( '.rthd-warning' ).show();
									response();
								} else if (data.hasOwnProperty( 'show_add' )) {

									jQuery( '.rthd-warning' ).html( 'Hey, Looks like <strong>' + AutocomepleteTextBox.val() + '</strong> is not in your system, would you like to add?' );
									jQuery( '.rthd-importer-add-contact' ).show();
									jQuery( '#rthd-new-user-email' ).val( AutocomepleteTextBox.val() );
									jQuery( '.rthd-warning' ).show();
									response();
								} else {
									response($.map(data, function (item) {
										return {
											id: item.id,
											imghtml: item.imghtml,
											label: item.label,
											editlink: item.editlink
										};
									}));
									jQuery( '.rthd-warning' ).hide();
									jQuery( '.rthd-importer-add-contact' ).hide();
								}
							}
						});
					}, minLength: 2,
					select: function (event, ui) {
						rthdSetup.give_user_helpdesk_access( false, ui.item.id );
						//if (jQuery("#imported-user-auth-" + ui.item.id).length < 1) {
						//    jQuery(".rthd-setup-list-users").append("<li id='imported-user-auth-" + ui.item.id + "' class='contact-list' >" + ui.item.imghtml + "<a href='#removeUser' class='delete_row'>×</a><br/><a class='rthd-setup-user-title heading' target='_blank' href='" + ui.item.editlink + "'>" + ui.item.label + "</a><input type='hidden' class='rthd-import-selected-users' name='import_users[]' value='" + ui.item.id + "' /></li>")
						//}
						jQuery( ".rthd-user-autocomplete" ).val( '' );
						return false;
					}
				}).data( 'ui-Autocomplete' )._renderItem = function (ul, item) {
					return $( '<li></li>' ).data( 'ui-autocomplete-item', item ).append( '<a>' + item.imghtml + '&nbsp;' + item.label + '</a>' ).appendTo( ul );
				};

				jQuery( document ).on("click", "a[href='#removeUser']", function (e) {
					e.preventDefault();
					if ( jQuery( this ).attr("disabled") != "disabled" ) {
						jQuery( this ).attr("disabled","disabled");
						that = this;
						var requestArray = {};
						requestArray.action = 'rtbiz_hd_remove_user';
						requestArray.userid = jQuery( this ).next( '.rthd-import-selected-users' ).val();
						jQuery.ajax({
							url: ajaxurl,
							dataType: "json",
							type: 'post',
							data: requestArray,
							success: function (data) {
								if (data.status) {
									jQuery( that ).parent().parent().remove();
									// Decrease import users count by 1
//									imported_users -= 1;
//									jQuery( '#rthd-all-import-message' ).html( imported_users + ' Users Added' );
								} else {
									jQuery( this ).removeAttr("disabled");
								}
							}
						});
					}
				});
			}

			if (jQuery( '#rthd-add-user-domain' ).length > 0) {
				jQuery( '#rthd-add-user-domain' ).autocomplete({
					source: function (request, response) {
						$.ajax({
							url: ajaxurl,
							dataType: "json",
							type: 'post',
							data: {
								action: 'rtbiz_hd_search_domain',
								maxRows: 10,
								query: request.term
							},
							success: function (data) {
								response($.map(data, function (item) {
									return {
										name: item,
										value: item
									};
								}));
							}
						});
					},
					select: function (event, ui) {
						jQuery( '#rthd-add-user-domain' ).val( ui.item.value );
						rthdSetup.import_domain_users( true );
					}
				});
			}

		},
		add_user_single: function () {
			jQuery( '.rthd-importer-add-contact' ).click(function () {
				rthdSetup.give_user_helpdesk_access( jQuery( '#rthd-new-user-email' ).val(), false );
			});

			jQuery( '#rthd-get-domain-count-users' ).click(function () {
				rthdSetup.import_domain_users( true );
			});

			$( '#rthd-add-user-domain' ).on("keypress", function (e) {
				if (e.keyCode == 13) {
					rthdSetup.import_domain_users( true );
					e.preventDefault();
					return false;
				}
			});

			jQuery( '#rthd-import-domain-users' ).click(function () {
				rthdSetup.import_domain_users( false );
			});

			jQuery( '#rthd-add-all-users' ).click(function (e) {
				jQuery( '#rthd-setup-import-users-progress' ).show();
				rthdSetup.import_all_users( 0 );
			});

			rthdSetup.bind_enter_event( '#rthd-setup-wizard-support-page-new', jQuery( 'a[href="#next"]' ) );

			jQuery( '#rthd-setup-wizard-support-page' ).on('change', function (e) {
				val = jQuery( this ).val();
				if (val == -1) {
					jQuery( '.rthd-setup-wizard-support-page-new-div' ).show();
				} else {
					if (jQuery( '.rthd-setup-wizard-support-page-new-div' ).is( ":visible" )) {
						jQuery( '.rthd-setup-wizard-support-page-new-div' ).hide();
					}
				}
			});
		},
		support_page: function () {
			var requestArray = {};
			requestArray.action = 'rtbiz_hd_setup_support_page';
			val = Number(jQuery( '#rthd-setup-wizard-support-page' ).val());
			if ( val === 0 || ( val == -1 && jQuery( '#rthd-setup-wizard-support-page-new' ).val().length === 0 && ! jQuery( '#rthd-setup-wizard-support-page-new' ).val().trim() )) {
				var strconfirm = confirm( 'Do you want to skip this step ?' );
				if (strconfirm === true) {
					return true;
				} else {
					jQuery( '.rthd-support-process' ).hide();
					return false;
				}

			} else if (val == -1) {
				if (jQuery( '.rthd-setup-wizard-support-page-new-div' ).is( ":visible" )) {
					requestArray.new_page = jQuery( '#rthd-setup-wizard-support-page-new' ).val();
					requestArray.page_action = 'add';
				}
			} else {
				requestArray.old_page = val;
			}
			if (val !== 0) {
				jQuery( '.rthd-support-process' ).show();
				jQuery.ajax({
					url: ajaxurl,
					dataType: "json",
					type: 'post',
					data: requestArray,
					success: function (data) {
						if (data.status) {
							jQuery( '.rthd-support-process' ).hide();
							skip_step = true;
							jQuery( '.wizard' ).steps( 'next' );
						}
					}
				});
			}
		},
		import_all_users: function (last_user) {
			var requestArray = {};
			requestArray.action = 'rtbiz_hd_import_all_users';
			requestArray.nonce = jQuery( '#import_all_users' ).val();
			requestArray.import = true;
			requestArray.last_import = last_user;
			jQuery( '#rthd-import-all-spinner' ).show();
			jQuery( '#rthd-all-import-message' ).html( '' );
			jQuery.ajax({
				url: ajaxurl,
				dataType: "json",
				type: 'post',
				data: requestArray,
				success: function (data) {
					if (data.status) {
						var remain = jQuery( '#rthd-setup-import-all-count' ).val();
						remain = parseInt( remain ) - parseInt( data.imported_count );
						imported_users = parseInt( data.imported_count );
						var progressbar = jQuery( '#rthd-setup-import-users-progress' ).val();
						progressbar = parseInt( progressbar ) + parseInt( data.imported_count );
						jQuery( '#rthd-setup-import-users-progress' ).val( progressbar );
						if (data.hasOwnProperty( 'imported_users' )) {
							$.each(data.imported_users, function (i, user) {
								last_user = user.id;
								rthdSetup.add_contact_to_list( user.id, user.label, user.imghtml, user.editlink );
							});
						}
						if (data.remain_import > 0) {
							jQuery( '#rthd-setup-import-all-count' ).val( remain );
							rthdSetup.import_all_users( last_user );
						} else {
							jQuery( '#rthd-import-all-spinner' ).hide();
							jQuery( '#rthd-setup-import-users-progress' ).hide();
							if (imported_users === 0 || ! data.imported_count) {
								jQuery( '#rthd-all-import-message' ).html( 'No Users Found' );
							} else {
								jQuery( '#rthd-all-import-message' ).html( imported_users + ' Users Added' );
							}
						}
					}
				}
			});
		},
		import_domain_users: function (get_count) {
			var requestArray = {};
			jQuery( '#rthd-domain-import-spinner' ).show();
			requestArray.action = 'rtbiz_hd_domain_user_import';
			requestArray.count = get_count;
			requestArray.domain_query = jQuery( '#rthd-add-user-domain' ).val();
			requestArray.nonce = jQuery( '#import_domain' ).val();

			jQuery.ajax({
				url: ajaxurl,
				dataType: "json",
				type: 'post',
				data: requestArray,
				success: function (data) {
					if (data.status) {
						if (data.hasOwnProperty( 'count' )) {
							jQuery( '#rthd-domain-import-message' ).html( 'Found ' + data.count + ' Users' );
						} else {
							if (data.hasOwnProperty( 'imported_users' )) {
								$.each(data.imported_users, function (i, user) {
									rthdSetup.add_contact_to_list( user.id, user.label, user.imghtml, user.editlink );
								});
							}
							if (data.imported_all) {
								if (data.hasOwnProperty( 'imported_users' )) {
									jQuery( '#rthd-domain-import-message' ).html( 'Imported ' + ( data.imported_users.length ) + ' Users' );
								} else {
									jQuery( '#rthd-domain-import-message' ).html( 'No Users to Add' );
								}
							} else {
								if (data.hasOwnProperty( 'not_imported_users' )) {
									jQuery( '#rthd-domain-import-message' ).html( 'Could not import ' + ( data.not_imported_users.length ) );
								}
							}
						}
						jQuery( '#rthd-domain-import-message' ).show();
					}
					jQuery( '#rthd-domain-import-spinner' ).hide();
				}
			});
		},
		give_user_helpdesk_access: function (email, id) {
			jQuery( '#rthd-autocomplete-page-spinner' ).show();
			var requestArray = {};
			if (email !== false) {
				requestArray.email = email;
			}
			if (id !== false) {
				requestArray.ID = id;
			}
			requestArray.action = 'rtbiz_hd_create_contact_with_hd_access';
			jQuery.ajax({
				url: ajaxurl,
				dataType: "json",
				type: 'post',
				data: requestArray,
				success: function (data) {
					if (jQuery( '.rthd-warning' ).is( ':visible' )) {
						jQuery( '.rthd-warning' ).html( '' );
						jQuery( '.rthd-warning' ).hide();
						jQuery( '#rthd-user-autocomplete' ).val( '' );
					}
					if (jQuery( '.rthd-importer-add-contact' ).is( ':visible' )) {
						jQuery( '.rthd-importer-add-contact' ).hide();
					}
					rthdSetup.add_contact_to_list( data.id, data.label, data.imghtml, data.editlink );
					jQuery( '#rthd-autocomplete-page-spinner' ).hide();
				}
			});
		},
		add_contact_to_list: function (id, label, imghtml, editlink) {
			if ( ! jQuery( '.rthd_selected_user' ).is( ':visible' )) {
				jQuery( '.rthd_selected_user' ).show();
			}
			if (jQuery( "#imported-user-auth-" + id ).length < 1) {
				//jQuery(".rthd-setup-list-users").append("<li id='imported-user-auth-" + id + "' class='contact-list' >" + imghtml + "<a href='#removeUser' class='delete_row'>×</a><br/><a class='rthd-setup-user-title heading' target='_blank' href='" + editlink + "'>" + label + "</a><input type='hidden' class='rthd-import-selected-users' name='import_users[]' value='" + id + "' /></li>")
				//jQuery(".rthd-setup-list-users").append("<li id='imported-user-auth-" + id + "' class='contact-list' >" + imghtml + "<a class='rthd-setup-user-title heading' target='_blank' href='" + editlink + "'>" + label + "</a> <input type='hidden' class='rthd-import-selected-users' name='import_users[]' value='" + id + "' /></li>")
				//jQuery( ".rthd-setup-list-users" ).append( "<li id='imported-user-auth-" + id + "' class='contact-list' >" + imghtml + "<a class='rthd-setup-user-title heading' target='_blank' href='" + editlink + "'>" + label + "</a> <a href='#removeUser' class='delete_row'>×</a> <input type='hidden' class='rthd-import-selected-users' name='import_users[]' value='" + id + "' /></li>" )
				jQuery( ".rthd-setup-list-users" ).append( "<tr id='imported-user-auth-" + id + "' class='contact-list' > <td>" + imghtml + "<a class='rthd-setup-user-title heading' target='_blank' href='" + editlink + "'>" + label + "</a></td><td><input type='radio' class='rt-hd-setup-acl' data-id='" + id + "' name='ACL_" + id + "' value='30'></td><td><input type='radio' class='rt-hd-setup-acl' data-id='" + id + "' name='ACL_" + id + "' value='20'></td><td><input type='radio' class='rt-hd-setup-acl' data-id='" + id + "' name='ACL_" + id + "' value='10' checked></td><td><img class='helpdeskspinner' src='" + adminurl + "images/spinner.gif'/> <a href='#removeUser' class='delete_row_user'><span class='dashicons dashicons-dismiss'></span></a> <input type='hidden' class='rthd-import-selected-users' name='import_users[]' value='" + id + "' /></td></tr>" );
			}
		},
		connect_store: function () {
			var selected = [];
			jQuery( "input:checkbox[name=rthd-wizard-store]:checked" ).each(function () {
				if ($( this ).val() != 'custom') {
					selected.push( $( this ).val() );
				}
			});
			if (selected.length > 0) {
				var requestArray = {};
				requestArray.store = selected;
				requestArray.action = 'rtbiz_hd_product_sync';
				jQuery( '.rthd-store-process' ).show();
				jQuery.ajax({
					url: ajaxurl,
					dataType: "json",
					type: 'post',
					data: requestArray,
					success: function (data) {
						if (data.status) {
							skip_step = true;
							jQuery( '.wizard' ).steps( 'next' );
						}
					}
				});
			} else {
				return true;
			}
		},
		get_assingee_ui: function () {
			jQuery( '.rthd-team-setup-loading' ).show();

			jQuery.ajax({
				url: ajaxurl,
				dataType: "json",
				type: 'post',
				data: {
					action: 'rtbiz_hd_default_assignee_ui'
				},
				success: function (data) {
					if (data.status) {
						jQuery( '#rthd-setup-set-assignee-ui' ).html( data.html );
						jQuery( '.rthd-team-setup-loading' ).hide();
						skip_step = true;
						jQuery( '.wizard' ).steps( 'next' );
					}
				}
			});
		},
		save_assignee: function () {
			jQuery( '.rthd-assignee-process' ).show();
			var requestArray = [];
			jQuery( '.rthd-setup-assignee' ).each(function () {
				var temp = {};
				temp.term_ID = jQuery( this ).attr( 'data' );
				temp.user_ID = jQuery( this ).val();
				requestArray.push( temp );
			});
			jQuery.ajax({
				url: ajaxurl,
				dataType: "json",
				type: 'post',
				data: {
					action: 'rtbiz_hd_default_assignee_save',
					assignee: requestArray,
					default_assignee: jQuery( '#rthd_product-default' ).val()
				},
				success: function (data) {
					if (data.status) {
						jQuery( '.rthd-assignee-process' ).show();
					}
					skip_step = true;
					jQuery( '.wizard' ).steps( 'next' );
				}
			});
		},
		assingee_page: function () {
			jQuery( '#rthd_product-default' ).on('change', function (e) {
				jQuery( '.rthd-setup-assignee' ).val( jQuery( this ).val() );
			});
		},
		custom_page_action: function (currentIndex) {
			/* if (currentIndex == 3 && jQuery('.rthd-setup-assignee').length == 0) {
             jQuery('div.actions a[href="#next"]').hide();
             setTimeout(function () {
             jQuery('div.actions a[href="#next"]').show();
             skip_step = true;
             jQuery('.wizard').steps('next');
             return true;
             }, 2000);
             }*/
		},
		outbound_mail_setup: function () {
			if (jQuery( '#mailbox-list>.rtmailbox-row' ).length > 0) {
				jQuery( '.rthd-mailbox-setup-process' ).show();
				//jQuery('div.actions a[href="#next"]').parent().after('<li id="rthd_spinner"><img src="' + adminurl + 'images/spinner.gif"/></li>')
				jQuery.ajax({
					url: ajaxurl,
					dataType: "json",
					type: 'post',
					data: {
						action: 'rtbiz_hd_outboud_mail_setup_ui'
					},
					success: function (data) {
						if (data.status) {
							jQuery( '#wizard-p-4' ).html( data.html );
						}
						//jQuery('div.actions li#rthd_spinner').remove();
						jQuery( '.rthd-outbound-setup-process' ).hide();
					}
				});
			} else if (jQuery( '#rthd_outound_sub-action' ).val() === 'rtbiz_hd_save_outound_setup') {
				//jQuery('div.actions a[href="#next"]').parent().after('<li id="rthd_spinner"><img src="' + adminurl + 'images/spinner.gif"/></li>')
				jQuery( '.rthd-outbound-setup-process' ).show();
				jQuery.ajax({
					url: ajaxurl,
					dataType: "json",
					type: 'post',
					data: {
						action: 'rtbiz_hd_save_outound_setup',
						data: jQuery( '#rthd_outgoing_mailbox_setup_container' ).find( "select,textarea, input" ).serialize()
					},
					success: function (data) {
						if (data.status) {
							jQuery( '#wizard-p-4' ).html( data.html );
							skip_step = true;
							jQuery( '.wizard' ).steps( 'next' );
						} else {
							alert( data.error );
						}
						//jQuery('div.actions li#rthd_spinner').remove();
						jQuery( '.rthd-outbound-setup-process' ).hide();
					}
				});
			} else {
				var strconfirm = confirm( 'Mailbox is not configured. Do you want to skip this step?' );
				if (strconfirm === true) {
					return true;
				} else {
					return false;
				}
			}
		},
		acl_save: function () {
			jQuery( document ).on('change', 'input.rt-hd-setup-acl:radio', function (e) {
				var userid = jQuery( this ).data( 'id' );
				var spinner = jQuery( '#ACL_' + userid + ' td:last .helpdeskspinner' );
				spinner.show();
				jQuery.ajax({
					url: ajaxurl,
					dataType: "json",
					type: 'post',
					data: {
						action: 'rtbiz_hd_change_acl',
						permission: jQuery( this ).val(),
						userid: userid
					},
					success: function (data) {
						spinner.hide();
						if (data.status) {
						} else {
							e.preventDefault();
						}
					}
				});
			});
		},
		get_acl_view: function () {
			jQuery.ajax({
				url: ajaxurl,
				dataType: "json",
				type: 'post',
				data: {
					action: 'rtbiz_hd_user_with_hd_role_list'
				},
				success: function (data) {
					if (data.status) {
						jQuery( '.rthd-ACL-change' ).html( data.html );
					}
				}
			});
		}
	};
	rthdSetup.init();
});
