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

/**
 * @param {Overlay} overlay
 */
function submitAcknowledge(overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl('zabbix.php', false),
		form_data = {},
		posted_elements;

	$form.trimValues(['[name=message]']);

	posted_elements = jQuery('[name=message], input:visible, input[type=hidden]', $form)
		.filter(':not([name^=eventids])')
		.serializeArray();
	posted_elements.map((entry) => {
		form_data[entry.name] = entry.value;
	});
	form_data.eventids = chkbxRange.selectedIds;

	url.setArgument('action', 'popup.acknowledge.create');

	overlay.xhr = sendAjaxData(url.getUrl(), {
		data: form_data,
		dataType: 'json',
		method: 'POST',
		beforeSend: function() {
			overlay.setLoading();
		},
		complete: function() {
			overlay.unsetLoading();
		}
	}).done(function(response) {
		overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

		if ('errors' in response) {
			jQuery(response.errors).insertBefore($form);
		}
		else {
			overlayDialogueDestroy(overlay.dialogueid);
			$.publish('acknowledge.create', [response, overlay]);
		}
	});
}
