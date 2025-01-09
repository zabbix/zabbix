<?php
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

window.popup_generic = {
	init() {
		cookie.init();
		chkbxRange.init();
	},

	setPopupOpenerFieldValues(entries) {
		Object.entries(entries).forEach(([element_id, set_value]) => {
			const target_element = document.getElementById(element_id);

			if (target_element !== null) {
				target_element.value = set_value;
			}
		});
	},

	initGroupsFilter() {
		var overlay = overlays_stack.end();

		jQuery('.multiselect', overlay.$dialogue).each(function (i, ms) {
			jQuery(ms).on('change', {overlay: overlay}, function (e) {
				const groups = jQuery(this).multiSelect('getData').map((item) => item.id);
				const parameters = groups.length
					? {groupid: groups[0]}
					: {filter_groupid_rst: 1, group: undefined, groupid: undefined};

				PopUp(e.data.overlay.action, {...e.data.overlay.options, ...parameters}, {
					dialogueid: e.data.overlay.dialogueid
				});
			});
		});
	},

	initTemplategroupsFilter() {
		const overlay = overlays_stack.end();

		jQuery('.multiselect', overlay.$dialogue).each(function (i, ms) {
			jQuery(ms).on('change', {overlay: overlay}, function (e) {
				const groups = jQuery(this).multiSelect('getData').map((item) => item.id);
				const parameters = groups.length
					? {templategroupid: groups[0]}
					: {filter_groupid_rst: 1, templategroup: undefined, templategroupid: undefined};

				PopUp(e.data.overlay.action, {...e.data.overlay.options, ...parameters}, {
					dialogueid: e.data.overlay.dialogueid
				});
			});
		});
	},

	initHostsFilter() {
		var overlay = overlays_stack.end();

		jQuery('.multiselect', overlay.$dialogue).each(function (i, ms) {
			jQuery(ms).on('change', {overlay: overlay}, function (e) {
				const hosts = jQuery(this).multiSelect('getData').map((item) => item.id);
				const parameters = hosts.length
					? {hostid: hosts[0]}
					: {filter_hostid_rst: 1, host: undefined, hostid: undefined, host_pattern: undefined};

				PopUp(e.data.overlay.action, {...e.data.overlay.options, ...parameters}, {
					dialogueid: e.data.overlay.dialogueid
				});
			});
		});
	},

	initTemplatesFilter() {
		const overlay = overlays_stack.end();

		jQuery('.multiselect', overlay.$dialogue).each(function (i, ms) {
			jQuery(ms).on('change', {overlay: overlay}, function (e) {
				const templates = jQuery(this).multiSelect('getData').map((item) => item.id);
				const parameters = templates.length
					? {templateid: templates[0]}
					: {filter_templateid_rst: 1, templateid: undefined};

				PopUp(e.data.overlay.action, {...e.data.overlay.options, ...parameters}, {
					dialogueid: e.data.overlay.dialogueid
				});
			});
		});
	},

	initHelpItems() {
		$('#itemtype').on('change', (e) => {
			reloadPopup(e.target.closest('form'));
		});
	},

	closePopup(e) {
		e.preventDefault();

		const $sender = jQuery(e.target).removeAttr('onclick');

		overlayDialogueDestroy($sender.closest('[data-dialogueid]').attr('data-dialogueid'));
	}
};
