<?php
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

$('.overlay-dialogue-body #filter_custom_time').on('change', function() {
	let $calendars = $(this).closest('form').find('.calendar-control');

	$('input,button', $calendars).prop('disabled', !$(this).is(':checked'));
});

function tabFilterDelete(overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action')),
		form_data = $form.serializeJSON();

	url.setArgument('action', 'popup.tabfilter.delete');
	url.setArgument('idx', form_data['idx']);
	url.setArgument('idx2', form_data['idx2']);

	overlay.setLoading();
	overlay.xhr = $.post(url.getUrl(), null, 'json')
		.done((response) => {
			const properties = {detail: {idx2: form_data['idx2']}, bubbles: true};

			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			if ('errors' in response) {
				$(response.errors).insertBefore($form);
			}
			else {
				overlayDialogueDestroy(overlay.dialogueid);
				overlay.element.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_DELETE, properties));

				clearMessages();
				addMessage(makeMessageBox('good', [], <?= json_encode(_('Filter deleted')) ?>));
			}
		})
		.always(() => {
			overlay.unsetLoading();
		});
}

function tabFilterUpdate(overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action')),
		form_data = $form.serializeJSON();

	form_data.filter_name = form_data.filter_name.trim();

	overlay.setLoading();
	overlay.xhr = $.post(url.getUrl(), form_data, 'json')
		.done((response) => {
			const properties = {detail: response, bubbles: true};

			overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			if ('errors' in response) {
				$(response.errors).insertBefore($form);
			}
			else {
				overlayDialogueDestroy(overlay.dialogueid);
				overlay.element.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_UPDATE, properties));
			}
		})
		.always(() => {
			overlay.unsetLoading();
		});
}
