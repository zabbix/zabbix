<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
					prototype: prototype,
					trigger_url: this.constructTriggerUrl(value.triggerid, prototype === '1'),
					action: prototype === '1' ? 'trigger.prototype.edit' : 'trigger.edit'
				}));
		}
	}

	function constructTriggerUrl(triggerid, is_prototype) {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'popup');
		url.setArgument('popup', is_prototype ? 'trigger.prototype.edit' : 'trigger.edit');
		url.setArgument('triggerid', triggerid);
		url.setArgument('context', '<?= $data['context'] ?>');

		if (is_prototype) {
			url.setArgument('parent_discoveryid', '<?= $data['parent_discoveryid'] ?>');
		}

		return url.getUrl();
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
		}
	})
</script>
