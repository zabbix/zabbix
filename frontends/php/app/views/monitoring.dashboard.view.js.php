<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
<?= (new CRow([
	new CCol([
		(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
		'#{name}'
	]),
	new CCol(
		(new CRadioButtonList('userGroups[#{usrgrpid}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_group_#{usrgrpid}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('removeUserGroupShares("#{usrgrpid}");')
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_group_shares_#{usrgrpid}')
	->toString()
?>
</script>

<script type="text/x-jquery-tmpl" id="user_row_tpl">
<?= (new CRow([
	new CCol([
		(new CTextBox('users[#{id}][userid]', '#{id}'))->setAttribute('type', 'hidden'),
		'#{name}',
	]),
	new CCol(
		(new CRadioButtonList('users[#{id}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_#{id}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_#{id}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('removeUserShares("#{id}");')
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_shares_#{id}')
	->toString()
?>
</script>

<script type="text/javascript">
	// Change dashboard settings
	function dashbrd_config() {
		var form = jQuery('form[name="dashboard_form"]');
		showDialogForm(
			form,
			{"title": "<?= _('Dashboard Properties') ?>", "action_title": "<?= _('Apply') ?>"},
			{"name": form.data('data').name, "owner": form.data('data').owner}
		);
	};

	// Save changes and cancel editing dashboard
	function dashbrd_save_changes() {
		// Update buttons on existing widgets to view mode
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
	};

	// Cancel editing dashboard
	function dashbrd_cancel(e) {
		e.preventDefault(); // To prevent going by href link

		// Update buttons on existing widgets to view mode
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('cancelEditDashboard');
	};

	// Add new widget
	function dashbrd_add_widget() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('addNewWidget');
	};

	var showEditMode = function showEditMode() {
		var edit_button = jQuery('#dashbrd-edit');
		var btn_conf = jQuery('<button>')
			.addClass('<?= ZBX_STYLE_BTN_WIDGET_EDIT ?>')
			.attr('id', 'dashbrd-config')
			.attr('type', 'button')
			.append(
				jQuery('<span>').addClass('<?= ZBX_STYLE_PLUS_ICON ?>') // TODO VM: replace by cog icon
			)
			.click(dashbrd_config);

		var btn_add = jQuery('<button>')
			.addClass('<?= ZBX_STYLE_BTN_ALT ?>')
			.attr('id', 'dashbrd-add-widget')
			.attr('type', 'button')
			.append(
				jQuery('<span>').addClass('<?= ZBX_STYLE_PLUS_ICON ?>'),
				'<?= _('Add widget') ?>'
			)
			.click(dashbrd_add_widget);

		var btn_save = jQuery('<button>')
			.attr('id', 'dashbrd-save')
			.attr('type', 'button')
			.append('<?= _('Save changes') ?>')
			.click(dashbrd_save_changes);

		var btn_cancel = jQuery('<a>')
			.attr('id', 'dashbrd-cancel')
			.attr('href', '#') // TODO VM: (?) needed for style, but adds # at URL, when clicked. Probably better to create new class with same styles
			.append('<?= _('Cancel') ?>')
			.click(dashbrd_cancel);

		var btn_edit_disabled = jQuery('<button>')
			.attr('disabled', 'disabled')
			.attr('type', 'button')
			.append('<?= _('Edit dashboard') ?>');

		edit_button.closest('li').hide();
		jQuery('#groupid', edit_button.closest('ul')).closest('li').hide();
		jQuery('#hostid', edit_button.closest('ul')).closest('li').hide();
		jQuery('#dashbrd-actions').closest('li').hide();

		edit_button.closest('ul').before(
			jQuery('<span>')
				.addClass('<?= ZBX_STYLE_DASHBRD_EDIT ?>')
				.append(jQuery('<ul>')
					.append(jQuery('<li>').append(btn_conf))
					.append(jQuery('<li>').append(btn_add))
					.append(jQuery('<li>').append(btn_save))
					.append(jQuery('<li>').append(btn_cancel))
					.append(jQuery('<li>'))
					.append(jQuery('<li>').append(btn_edit_disabled))
				)
		);

		// Update buttons on existing widgets to edit mode
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('setModeEditDashboard');
	};

	function initSharingForm() {
		var	sharing_form = jQuery('form[name="dashboard_sharing_form"]');

		// overwrite submit action to AJAX call
		sharing_form.submit(function(event) {
			var	me = this;

			event.preventDefault();

			function saveErrors(errors) {
				jQuery(me).data('errors', errors);
			}

			jQuery.ajax({
				async: false, // waiting errors
				data: jQuery(me).serialize(), // get the form data
				type: jQuery(me).attr('method'),
				url: jQuery(me).attr('action'),
				success: function (response) {
					var errors = [];
					if (typeof response === 'object') {
						if ('errors' in response) {
							errors = response.errors;
						}
					}
					else if (typeof response === 'string' && response.indexOf('Access denied') !== -1) {
						errors.push('<?= _('You need permission to perform this action!') ?>');
					}
					saveErrors(errors);
				},
				error: function (response) {
					saveErrors(['<?= _('Something went wrong. Please try again later!') ?>']);
				}
			});
		});
	};

	// fill the form with actual data
	jQuery.fn.fillForm = function(data) {
		if (typeof data.name) {
			this.find('#name').val(data.name);
		}
		if (typeof data.owner) {
			// this method should remove previous selected data because option selectedLimit = 1
			this.find('#userid').multiSelect('addData', data.owner);
		}
		if (typeof data.private !== 'undefined') {
			addPopupValues({'object': 'private', 'values': [data.private] });
		}

		if (typeof data.users !== 'undefined') {
			removeUserShares();
			addPopupValues({'object': 'userid', 'values': data.users });
		}

		if (typeof data.userGroups !== 'undefined') {
			removeUserGroupShares();
			addPopupValues({'object': 'usrgrpid', 'values': data.userGroups });
		}
	};

	function initEditForm(dashboard) {
		var edit_form = jQuery('form[name="dashboard_form"]');

		function save_previous_form_state(form) {
			var userElement = form.find('#userid'),
				owner;

			if (typeof userElement.data('multiSelect') !== 'undefined') {
				owner = userElement.multiSelect('getData');
				owner = owner[0];
			}
			owner = owner || userElement.data().defaultOwner;
			form.data('data', {"name": form.find('#name').val(), "owner": owner});
		};

		save_previous_form_state(edit_form);

		edit_form.submit(function(event) {
			var me = this,
				errors = [],
				form = jQuery(me),
				formData = JSON.parse(form.formToJSON());

			// cancel original event to prevent form submitting
			event.preventDefault();

			if (!formData['userid']) {
				errors.push('<?= _('Owner cannot be empty!') ?>');
			}
			if (!formData['name']) {
				errors.push('<?= _('Name cannot be empty!') ?>');
			}
			form.data('errors', errors);

			if (errors.length > 0) {
				return false;
			}
			save_previous_form_state(form);

			dashboard.dashboardGrid(
				"setDashboardData", {"name": formData['name'], "userid": formData['userid']}
			);
			jQuery('div.article h1').text(form.data('data').name);
		});
	};

	jQuery(document).ready(function($) {
		// Turn on edit dashboard
		$('#dashbrd-edit').click(showEditMode);
		initSharingForm();
		initEditForm(jQuery('.dashbrd-grid-widget-container'));
	});

	function dashbaordAddMessages(messages) {
		var $message_div = jQuery('<div>').attr('id','dashbrd-messages');
		$message_div.append(messages);
		jQuery('.article').prepend($message_div);
	}

	function dashboardRemoveMessages() {
		jQuery('#dashbrd-messages').remove();
	}

	// Function is in global scope, because it should be accessable by html onchange() attribute
	function updateWidgetConfigDialogue() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('updateWidgetConfigDialogue');
	}

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var	i,
			tpl,
			container;

		for (i = 0; i < list.values.length; i++) {
			var	value = list.values[i];

			if (empty(value)) {
				continue;
			}

			if (typeof value.permission === 'undefined') {
				if (jQuery('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
					value.permission = <?= PERM_READ ?>;
				}
				else {
					value.permission = <?= PERM_READ_WRITE ?>;
				}
			}

			switch (list.object) {
				case 'private':
					jQuery('input[name=private][value=' + value + ']').prop('checked', 'checked');
					break;

				case 'usrgrpid':
					if (jQuery('#user_group_shares_' + value.usrgrpid).length) {
						continue;
					}

					tpl = new Template(jQuery('#user_group_row_tpl').html());

					container = jQuery('#user_group_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_group_' + value.usrgrpid + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;

				case 'userid':
					if (jQuery('#user_shares_' + value.id).length) {
						continue;
					}

					tpl = new Template(jQuery('#user_row_tpl').html());

					container = jQuery('#user_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_' + value.id + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;
			}
		}
	}

	function removeUserGroupShares(usrgrpid) {
		if (typeof usrgrpid === 'undefined') {
			jQuery("[id^='user_group_shares']").remove();
		}
		else {
			jQuery('#user_group_shares_' + usrgrpid).remove();
		}
	}

	function removeUserShares(userid) {
		if (typeof userid === 'undefined') {
			jQuery("[id^='user_shares']").remove();
		}
		else {
			jQuery('#user_shares_' + userid).remove();
		}
	}
</script>
