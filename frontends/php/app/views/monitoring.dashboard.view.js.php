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
	var dashbrd_config = function() {
		// TODO VM: dashboard configuration dialogue should be opened here
		// Update buttons on existing widgets to view mode
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
	};

	// Save changes and cancel editing dashboard
	var dashbrd_save_changes = function() {
		// Update buttons on existing widgets to view mode
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
		// dashboardButtonsSetView() will be called in case of success of ajax in function 'saveDashboardChanges'
	};

	// Cancel editing dashboard
	var dashbrd_cancel = function(e) {
		e.preventDefault(); // To prevent going by href link

		// Update buttons on existing widgets to view mode
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('cancelEditDashboard');
		dashboardButtonsSetView();
	};

	// Add new widget
	var dashbrd_add_widget = function() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('addNewWidget');
	};

	jQuery(document).ready(function($) {
		// Turn on edit dashboard
		$('#dashbrd-edit').click(function() {
			var btn_conf = $('<button>')
				.addClass('<?= ZBX_STYLE_BTN_ALT ?>')
				.attr('id','dashbrd-config')
				.attr('type','button')
				.append(
					$('<span>').addClass('<?= ZBX_STYLE_PLUS_ICON ?>') // TODO VM: replace by cog icon
				)
				.click(dashbrd_config);

			var btn_add = $('<button>')
				.addClass('<?= ZBX_STYLE_BTN_ALT ?>')
				.attr('id','dashbrd-add-widget')
				.attr('type','button')
				.append(
					$('<span>').addClass('<?= ZBX_STYLE_PLUS_ICON ?>'),
					'<?= _('Add widget') ?>'
				)
				.click(dashbrd_add_widget);

			var btn_save = $('<button>')
				.attr('id','dashbrd-save')
				.attr('type','button')
				.append('<?= _('Save changes') ?>')
				.click(dashbrd_save_changes);

			var btn_cancel = $('<a>')
				.attr('id','dashbrd-cancel')
				.attr('href', '#') // TODO VM: (?) needed for style, but adds # at URL, when clicked. Probably better to create new class with same styles
				.append('<?= _('Cancel') ?>')
				.click(dashbrd_cancel);

			var btn_edit_disabled = $('<button>')
				.attr('disabled','disabled')
				.attr('type','button')
				.append('<?= _('Edit dashboard') ?>');

			$(this).closest('li').hide();
			$('#groupid', $(this).closest('ul')).closest('li').hide();
			$('#hostid', $(this).closest('ul')).closest('li').hide();

			$(this).closest('ul').before(
				$('<span>')
					.addClass('<?= ZBX_STYLE_DASHBRD_EDIT ?>')
					.append($('<ul>')
						.append($('<li>').append(btn_conf))
						.append($('<li>').append(btn_add))
						.append($('<li>').append(btn_save))
						.append($('<li>').append(btn_cancel))
						.append($('<li>'))
						.append($('<li>').append(btn_edit_disabled))
					)
			);

			// Update buttons on existing widgets to edit mode
			$('.dashbrd-grid-widget-container').dashboardGrid('setModeEditDashboard');
		});

		var	form = jQuery('form[name="dashboard_sharing_form"]');

		// overwrite submit action to AJAX call
		form.submit(function(event) {
			var	me = this;
			event.preventDefault();

			jQuery.ajax({
				data: jQuery(me).serialize(), // get the form data
				type: jQuery(me).attr('method'),
				url: jQuery(me).attr('action'),
				success: function (response) {
					dashboardRemoveMessages();
					if (typeof response === 'object') {
						if ('messages' in response && response.messages.length > 0) {
							dashbaordAddMessages(response.messages.join());
						}
					}
					else if (typeof response === 'string' && response.indexOf('Access denied') !== -1) {
						alert('<?= _('You need permission to perform this action!') ?>');
					}
				},
				error: function (response) {
					alert('<?= _('Something went wrong. Please try again later!') ?>')
				}
			});
		});
	});

	// fill the form with actual data
	jQuery.fn.fillForm = function(data) {
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

	// will be called by setModeViewDashboard() method in dashboard.grid.js
	function dashboardButtonsSetView() {
		var $form = jQuery('.article .header-title form');
		jQuery('.dashbrd-edit', $form).remove();
		jQuery('#dashbrd-edit', $form).closest('li').show();
		jQuery('#groupid', $form).closest('li').show();
		jQuery('#hostid', $form).closest('li').show();
	}

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
