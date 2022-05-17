<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

			if (document.querySelectorAll(`[data-triggerid="${value.triggerid}"]`).length > 0) {
				continue;
			}

			let curl;
			if (list.object === 'deptrigger_prototype') {
				curl = new Curl('trigger_prototypes.php', false);
				curl.setArgument('form', 'update');
				curl.setArgument('parent_discoveryid', '<?= $data['parent_discoveryid'] ?>');
				curl.setArgument('triggerid', value.triggerid);
				curl.setArgument('context', '<?= $data['context'] ?>');
			}
			else {
				curl = new Curl('triggers.php', false);
				curl.setArgument('form', 'update');
				curl.setArgument('triggerid', value.triggerid);
				curl.setArgument('context', '<?= $data['context'] ?>');
			}

			document
				.querySelector('#dependency-table tr:last-child')
				.insertAdjacentHTML('afterend', tmpl.evaluate({
					triggerid: value.triggerid,
					name: value.name,
					url: curl.getUrl()
				}));
		}
	}

	function removeDependency(triggerid) {
		jQuery('#dependency_' + triggerid).remove();
		jQuery('#dependencies_' + triggerid).remove();
	}
</script>
