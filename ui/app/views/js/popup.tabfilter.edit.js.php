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
$('#custom_time').on('click', function () {
	let $calendars = $(this).closest('form').find('.calendar-control');

	$('input,button', $calendars).prop('disabled', $(this).is(':not(:checked)'));
});

function tabFilterFormAction(form_action, overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action'));

	$('<input/>', {type: 'hidden', name: 'form_action', value: form_action}).appendTo($form);
	overlay.setLoading();
	overlay.xhr = $.post(url.getUrl(), $form.serialize(), 'json')
		.done((response) => {
			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			if ('errors' in response) {
				$(response.errors).insertBefore($form);
			}
			else {
				overlayDialogueDestroy(overlay.dialogueid);
				overlay.element.dispatchEvent(new CustomEvent('popup.tabfilter', {detail: response}));
			}
		})
		.always(() => {
			overlay.unsetLoading();
		});
}
