<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
<script>
	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		if (!isset('object', list)) {
			return false;
		}

		const tmpl = new Template($('#dependency-row-tmpl').html());

		for (var i = 0; i < list.values.length; i++) {
			const value = list.values[i];

			if (document.querySelectorAll(`#dependency-table [data-triggerid="${value.triggerid}"]`).length > 0) {
				continue;
			}

			const prototype = (list.object === 'deptrigger_prototype') ? '1' : '0';

			document
				.querySelector('#dependency-table tr:last-child')
				.insertAdjacentHTML('afterend', tmpl.evaluate({
					triggerid: value.triggerid,
					parent_discoveryid: '<?= $data['parent_discoveryid'] ?>',
					context: '<?= $data['context'] ?>',
					name: value.name,
					prototype: prototype
				}));
		}
	}

	function removeDependency(triggerid) {
		jQuery('#dependency_' + triggerid).remove();
		jQuery('#dependencies_' + triggerid).remove();
	}

	document.getElementById('massupdate-form').addEventListener('click', (e) => {
		if (e.target.classList.contains('js-edit-dependency')) {
			e.preventDefault();

			if (!window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>)) {
				return;
			}

			const massupdate_overlay = overlays_stack.end();
			overlayDialogueDestroy(massupdate_overlay.dialogueid);

			const trigger_data = {
				triggerid: e.target.dataset.triggerid,
				parent_discoveryid: e.target.dataset.parent_discoveryid,
				context: e.target.dataset.context
			};

			const action = (e.target.dataset.prototype === '0') ? 'trigger.edit' : 'trigger.prototype.edit';

			const overlay = PopUp(action, trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.success.title);

				if ('messages' in e.detail.success) {
					postMessageDetails('success', e.detail.success.messages);
				}

				location.href = location.href;
			});
		}
	})
</script>
