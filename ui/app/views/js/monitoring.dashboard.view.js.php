<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

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
			->removeId()
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
			->removeId()
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_shares_#{id}')
	->toString()
?>
</script>

<script type="text/javascript">
	// Change dashboard settings.
	function dashbrd_config() {
		var dashboard = jQuery('.dashbrd-grid-container').data('dashboardGrid'),
			options = {
				dashboardid: <?= $data['dashboard']['dashboardid'] ?>,
				userid: dashboard['dashboard']['userid'],
				name: dashboard['dashboard']['name']
			};

		if (options.dashboardid == 0) {
			options.new = '1';
		}

		PopUp('dashboard.properties.edit', options, 'dashboard_properties', this);
	};

	/**
	 * @param {Overlay} overlay
	 */
	function dashbrdApplyProperties(overlay) {
		var dashboard = jQuery('.dashbrd-grid-container'),
			$form = overlay.$dialogue.find('form'),
			url = new Curl('zabbix.php', false),
			form_data = {};

		$form.trimValues(['#name']);
		form_data = $form.serializeJSON();
		url.setArgument('action', 'dashboard.properties.check');

		overlay.setLoading();
		overlay.xhr = jQuery.ajax({
			data: form_data,
			url: url.getUrl(),
			dataType: 'json',
			method: 'POST',
			complete: function() {
				overlay.unsetLoading();
			},
			success: function(response) {
				var errors = [];
				overlay.$dialogue.find('> .msg-good, > .msg-bad').remove();

				if (typeof response === 'object') {
					if ('errors' in response) {
						errors = response.errors;
					}
				}

				if (errors.length) {
					jQuery(errors).insertBefore($form);
				}
				else {
					dashboard.dashboardGrid('setDashboardData', {
						name: form_data['name'],
						userid: form_data['userid']
					});

					jQuery('#<?= ZBX_STYLE_PAGE_TITLE ?>').text(form_data['name']);
					jQuery('#dashboard-direct-link').text(form_data['name']);

					overlayDialogueDestroy(overlay.dialogueid);
				}
			}
		});
	}

	/**
	 * @param {Overlay} overlay
	 *
	 * @return {bool}
	 */
	function dashbrdConfirmSharing(overlay) {
		var $form = overlay.$dialogue.find('form'),
			url = new Curl('zabbix.php', false);

		url.setArgument('action', 'dashboard.share.update');

		overlay.setLoading();
		overlay.xhr = jQuery.ajax({
			url: url.getUrl(),
			data: $form.serializeJSON(),
			dataType: 'json',
			method: 'POST',
			complete: function() {
				overlay.unsetLoading();
			},
			success: function(response) {
				var errors = [],
					messages = [];

				overlay.$dialogue.find('> .msg-good, > .msg-bad').remove();

				if (typeof response === 'object') {
					if ('errors' in response) {
						errors = response.errors;
					}
					else if ('messages' in response) {
						messages = response.messages;
					}
				}

				if (errors.length) {
					jQuery(errors).insertBefore($form);
				}
				else {
					jQuery('.wrapper').find('> .msg-bad, > .msg-good').remove();

					if (messages.length) {
						jQuery('.wrapper main').before(messages);
					}
					overlayDialogueDestroy(overlay.dialogueid);
				}
			}
		});

		return false;
	}

	// Save changes and cancel editing dashboard.
	function dashbrd_save_changes() {
		// Update buttons on existing widgets to view mode.
		jQuery('.dashbrd-grid-container').dashboardGrid('saveDashboardChanges');
	};

	// Cancel editing dashboard.
	function dashbrd_cancel(e) {
		// To prevent going by href link.
		e.preventDefault();

		// Update buttons on existing widgets to view mode.
		jQuery('.dashbrd-grid-container').dashboardGrid('cancelEditDashboard');
	};

	// Add new widget.
	function dashbrd_add_widget() {
		jQuery('.dashbrd-grid-container').dashboardGrid('addNewWidget', this);
	};

	// Paste widget.
	function dashbrd_paste_widget() {
		jQuery('.dashbrd-grid-container').dashboardGrid('pasteWidget', null, null);
	};

	var showEditMode = function showEditMode() {
		jQuery('#dashbrd-control > li').hide().last().show();

		var ul = jQuery('#dashbrd-config').closest('ul');
		jQuery('#dashbrd-config', ul).click(dashbrd_config),
		jQuery('#dashbrd-add-widget', ul).click(dashbrd_add_widget),
		jQuery('#dashbrd-paste-widget').click(dashbrd_paste_widget),
		jQuery('#dashbrd-save', ul).click(dashbrd_save_changes),
		jQuery('#dashbrd-cancel', ul).click(dashbrd_cancel),

		// Hide filter with timeline.
		jQuery('.filter-btn-container, .filter-space').hide();
		timeControl.disableAllSBox();

		// Enable 'Paste widget' button.
		if (jQuery('.dashbrd-grid-container').dashboardGrid('isWidgetCopied')) {
			jQuery('#dashbrd-paste-widget').attr('disabled', false);
		}
		else {
			// Listen for local storage 'dashboard.copied_widget' update and enable 'Paste widget' button.
			var dashboard = jQuery('.dashbrd-grid-container').data('dashboardGrid');
			dashboard['storage'].onKeyUpdate('dashboard.copied_widget', function() {
				if (jQuery('.dashbrd-grid-container').dashboardGrid('isWidgetCopied')) {
					jQuery('#dashbrd-paste-widget').attr('disabled', false);
				}
			});
		}

		// Update buttons on existing widgets to edit mode.
		jQuery('.dashbrd-grid-container').dashboardGrid('setModeEditDashboard');
	};

	// Method to fill data in dashboard sharing form.
	jQuery.fn.fillDashbrdSharingForm = function(data) {
		if (typeof data.private !== 'undefined') {
			addPopupValues({'object': 'private', 'values': [data.private] });
		}

		if (typeof data.users !== 'undefined') {
			addPopupValues({'object': 'userid', 'values': data.users });
		}

		if (typeof data.userGroups !== 'undefined') {
			addPopupValues({'object': 'usrgrpid', 'values': data.userGroups });
		}
	};

	jQuery(document).ready(function($) {
		// Disable page refresh on time range change.
		timeControl.refreshPage = false;

		// Turn on edit dashboard.
		$('#dashbrd-edit').click(showEditMode);

		<?php if ($data['dashboard']['dashboardid'] == 0): ?>
			// When creating new dashboard, open it in edit mode, with opened properties popup.
			showEditMode();
			dashbrd_config();
		<?php endif; ?>
	});

	function dashboardAddMessages(messages) {
		$('main').before($(messages).addClass('msg-dashboard-js'));
	}

	function dashboardRemoveMessages() {
		jQuery('.msg-dashboard-js').remove();
		jQuery('.msg-good').remove();
	}

	// Function is in global scope, because it should be accessible by html onchange() attribute.
	function updateWidgetConfigDialogue() {
		jQuery('.dashbrd-grid-container').dashboardGrid('updateWidgetConfigDialogue');
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
		jQuery('#user_group_shares_' + usrgrpid).remove();
	}

	function removeUserShares(userid) {
		jQuery('#user_shares_' + userid).remove();
	}

	/**
	 * Find and refresh widget responsible for launching the "Update problem" popup after it was submitted.
	 *
	 * @param {String} type      Widget type to search for.
	 * @param {object} response  The response object from the "acknowledge.create" action.
	 * @param {object} overlay   The overlay object of the "Update problem" popup form.
	 */
	function refreshWidgetOnAcknowledgeCreate(type, response, overlay) {
		var handle_selector = '.dashbrd-grid-widget-content',
			handle = overlay.trigger_parents.filter(handle_selector).get(0);

		if (!handle) {
			var dialogue = overlay.trigger_parents.filter('.overlay-dialogue');
			if (dialogue.length) {
				var dialogue_overlay = overlays_stack.getById(dialogue.data('hintboxid'));
				if (dialogue_overlay && dialogue_overlay.type === 'hintbox') {
					handle = dialogue_overlay.element.closest(handle_selector);
				}
			}
		}

		if (handle) {
			var widgets = $('.dashbrd-grid-container').dashboardGrid('getWidgetsBy', 'type', type);
			widgets.forEach(widget => {
				if ($.contains(widget.container[0], handle)) {
					for (var i = overlays_stack.length - 1; i >= 0; i--) {
						var hintbox = overlays_stack.getById(overlays_stack.stack[i]);
						if (hintbox.type === 'hintbox') {
							hintbox_handle = hintbox.element.closest(handle_selector);
							if ($.contains(widget.container[0], hintbox_handle)) {
								hintBox.hideHint(hintbox.element, true);
							}
						}
					}

					clearMessages();
					addMessage(makeMessageBox('good', [], response.message, true, false));
					$('.dashbrd-grid-container').dashboardGrid('refreshWidget', widget.uniqueid);
				}
			});
		}
	}
</script>
