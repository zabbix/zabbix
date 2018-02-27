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

<script type="text/x-jquery-tmpl" id="edit_dashboard_control">
<?= (new CSpan([
	new CList([
		(new CButton('dashbrd-config'))->addClass(ZBX_STYLE_BTN_DASHBRD_CONF),
		(new CButton('dashbrd-add-widget', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add widget')]))
			->addClass(ZBX_STYLE_BTN_ALT),
		(new CButton('dashbrd-save', _('Save changes'))),
		(new CLink(_('Cancel'), '#'))->setId('dashbrd-cancel'),
		''
	])
]))
	->addClass(ZBX_STYLE_DASHBRD_EDIT)
	->toString()
?>
</script>

<script type="text/javascript">
	// Change dashboard settings.
	function dashbrd_config() {
		var form = jQuery('form[name="dashboard_form"]');

		showDialogForm(
			form,
			{
				'title': <?= CJs::encodeJson(_('Dashboard properties')) ?>,
				'action_title': <?= CJs::encodeJson(_('Apply')) ?>
			},
			{
				'name': form.data('data').name,
				'owner': form.data('data').owner
			},
			this
		);
	};

	// Save changes and cancel editing dashboard.
	function dashbrd_save_changes() {
		// Update buttons on existing widgets to view mode.
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
	};

	// Cancel editing dashboard.
	function dashbrd_cancel(e) {
		// To prevent going by href link.
		e.preventDefault();

		// Update buttons on existing widgets to view mode.
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('cancelEditDashboard');
	};

	// Add new widget.
	function dashbrd_add_widget() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('addNewWidget', this);
	};

	var showEditMode = function showEditMode() {
		jQuery('#dashbrd-edit').closest('ul')
			.hide()
			.before(jQuery('#edit_dashboard_control').html())

		var ul = jQuery('#dashbrd-config').closest('ul');
		jQuery('#dashbrd-config', ul).click(dashbrd_config),
		jQuery('#dashbrd-add-widget', ul).click(dashbrd_add_widget),
		jQuery('#dashbrd-save', ul).click(dashbrd_save_changes),
		jQuery('#dashbrd-cancel', ul).click(dashbrd_cancel),

		// Update buttons on existing widgets to edit mode.
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('setModeEditDashboard');

		// Hide filter with timeline.
		jQuery('.filter-btn-container, #filter-space').hide();
		timeControl.removeAllSBox();
	};

	// This method is related to forms: "sharing", "dashboard properties".
	jQuery.fn.fillForm = function(data) {
		if (typeof data.name) {
			this.find('#name').val(data.name);
		}
		if ('owner' in data) {
			if ('id' in data.owner) {
				this.find('#userid').multiSelect('addData', data.owner);
			} else {
				this.find('#userid').multiSelect('clean');
			}
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

	jQuery(document).ready(function($) {
		// Turn on edit dashboard.
		$('#dashbrd-edit').click(showEditMode);

		var $norm_mode_btn = $('.btn-dashbrd-normal');
		if ($norm_mode_btn.length) {
			$(window).on('mousemove keyup scroll', function() {
				clearTimeout($norm_mode_btn.data('timer'));
				$norm_mode_btn
					.removeClass('hidden')
					.data('timer', setTimeout(function() {
						$norm_mode_btn.addClass('hidden');
					}, 2000));
			}).trigger('mousemove');
		}
	});

	function dashboardAddMessages(messages) {
		var $message_div = jQuery('<div>').attr('id','dashbrd-messages');
		$message_div.append(messages);
		jQuery('.article').prepend($message_div);
	}

	function dashboardRemoveMessages() {
		jQuery('#dashbrd-messages').remove();
		jQuery('.msg-good').remove();
	}

	// Function is in global scope, because it should be accessible by html onchange() attribute.
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
