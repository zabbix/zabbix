<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


ob_start(); ?>
/**
 * Send HTTP test step form data to server for validation before adding it to web scenario tab.
 *
 * @param {string} formname		form name that is sent to server for validation
 * @param {string} dialogueid	(optional) id of overlay dialogue.
 */
function validateHttpStep(formname, dialogueid) {
	var form = window.document.forms[formname],
		url = new Curl(jQuery(form).attr('action')),
		dialogueid = dialogueid || null;

	jQuery(form).trimValues(['#name', '#url', '#timeout', '#required', '#status_codes']);

	url.setArgument('validate', 1);

	jQuery.ajax({
		url: url.getUrl(),
		data: jQuery(form).serialize(),
		success: function(ret) {
			jQuery(form).parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore(jQuery(form));
			}
			else {
				if (typeof ret.params.stepid !== 'undefined') {
					update_httpstep(ret.dstfrm, ret.list_name, ret.params);
				}
				else {
					add_httpstep(ret.dstfrm, ret.params);
				}

				if (dialogueid) {
					overlayDialogueDestroy(dialogueid);
				}
			}
		},
		dataType: 'json',
		type: 'post'
	});
}
<?php return ob_get_clean(); ?>
