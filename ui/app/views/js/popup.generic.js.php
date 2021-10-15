<?php
/*
 ** Zabbix
 ** Copyright (C) 2001-2021 Zabbix SIA
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

window.popup_generic = {
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
				var groups = jQuery(this).multiSelect('getData').map(i => i.id),
					options = groups.length ? {groupid: groups[0]} : {filter_groupid_rst: 1, groupid: []};

				new_opts = jQuery.extend(e.data.overlay.options, options);
				PopUp(e.data.overlay.action, new_opts, e.data.overlay.dialogueid);
			});
		});
	},

	initHostsFilter() {
		var overlay = overlays_stack.end();
		jQuery('.multiselect', overlay.$dialogue).each(function (i, ms) {
			jQuery(ms).on('change', {overlay: overlay}, function (e) {
				var hosts = jQuery(this).multiSelect('getData').map(i => i.id),
					options = hosts.length ? {hostid: hosts[0]} : {filter_hostid_rst: 1, hostid: []};

				new_opts = jQuery.extend(e.data.overlay.options, options);
				PopUp(e.data.overlay.action, new_opts, e.data.overlay.dialogueid);
			});
		});
	},

	initHelpItems() {
		$('#itemtype').on('change', (e) => {
			reloadPopup(e.target.closest('form'));
		});
	},

	setEmpty(sender, reset_fields) {
		this.setPopupOpenerFieldValues(reset_fields)
		overlayDialogueDestroy(jQuery(sender).closest('[data-dialogueid]').attr('data-dialogueid'));

		return false;
	},

	closePopup(sender) {
		const $sender = jQuery(sender).removeAttr('onclick');
		overlayDialogueDestroy($sender.closest('[data-dialogueid]').attr('data-dialogueid'));

		return false;
	},

	init() {
		cookie.init();
		chkbxRange.init();
	}
};
