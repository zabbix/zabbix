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
			->onClick('window.dashboard_share.removeUserGroupShares("#{usrgrpid}");')
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
			->onClick('window.dashboard_share.removeUserShares("#{id}");')
			->removeId()
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_shares_#{id}')
	->toString()
?>
</script>

<script>
	function initializeDashboardShare(data) {
		window.dashboard_share = new dashboardShareSingleton(data);
		window.dashboard_share.live();
	}

	function dashboardShareSingleton(data) {
		this.data = data;
	}

	dashboardShareSingleton.prototype.live = function() {
		this.addPopupValues({'object': 'private', 'values': [this.data.private] });
		this.addPopupValues({'object': 'userid', 'values': this.data.users });
		this.addPopupValues({'object': 'usrgrpid', 'values': this.data.userGroups });
	};

	/**
	 * @param {Overlay} overlay
	 */
	dashboardShareSingleton.prototype.submit = function(overlay) {
		var $form = overlay.$dialogue.find('form'),
			url = new Curl('zabbix.php', false);

		clearMessages();

		url.setArgument('action', 'dashboard.share.update');

		overlay.setLoading();
		overlay.xhr = $.ajax({
			url: url.getUrl(),
			data: $form.serializeJSON(),
			dataType: 'json',
			method: 'POST'
		});

		overlay.xhr
			.always(() => overlay.unsetLoading())
			.done((response) => {
				$form.prevAll('.msg-good, .msg-bad').remove();

				if ('errors' in response) {
					$(response.errors).insertBefore($form);
				}
				else if ('messages' in response) {
					addMessage(response.messages);

					overlayDialogueDestroy(overlay.dialogueid);
				}
			});
	};

	dashboardShareSingleton.prototype.removeUserGroupShares = function(usrgrpid) {
		$('#user_group_shares_' + usrgrpid).remove();
	}

	dashboardShareSingleton.prototype.removeUserShares = function(userid) {
		$('#user_shares_' + userid).remove();
	}

	dashboardShareSingleton.prototype.addPopupValues = function(list) {
		var	i,
			tpl,
			container;

		for (i = 0; i < list.values.length; i++) {
			var	value = list.values[i];

			if (empty(value)) {
				continue;
			}

			if (typeof value.permission === 'undefined') {
				if ($('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
					value.permission = <?= PERM_READ ?>;
				}
				else {
					value.permission = <?= PERM_READ_WRITE ?>;
				}
			}

			switch (list.object) {
				case 'private':
					$('input[name=private][value=' + value + ']').prop('checked', true);

					break;

				case 'usrgrpid':
					if ($('#user_group_shares_' + value.usrgrpid).length) {
						continue;
					}

					tpl = new Template($('#user_group_row_tpl').html());

					container = $('#user_group_list_footer');
					container.before(tpl.evaluate(value));

					$('#user_group_' + value.usrgrpid + '_permission_' + value.permission + '').prop('checked', true);

					break;

				case 'userid':
					if ($('#user_shares_' + value.id).length) {
						continue;
					}

					tpl = new Template($('#user_row_tpl').html());

					container = $('#user_list_footer');
					container.before(tpl.evaluate(value));

					$('#user_' + value.id + '_permission_' + value.permission + '').prop('checked', true);

					break;
			}
		}
	};

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		window.dashboard_share.addPopupValues(list);
	}
</script>
