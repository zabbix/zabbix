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
	url.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('tabfilter')) ?>);

	overlay.setLoading();
	overlay.xhr = $.post(url.getUrl(), null, 'json')
		.done((response) => {
			if ('error' in response) {
				overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

				const message_box = makeMessageBox('bad', response.error.messages, response.error.title);

				message_box.insertBefore($form);

				return;
			}

			overlayDialogueDestroy(overlay.dialogueid);

			overlay.element.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_DELETE,
				{detail: {idx2: form_data['idx2']}, bubbles: true}
			));

			clearMessages();
			addMessage(makeMessageBox('good', [], <?= json_encode(_('Filter deleted')) ?>));
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
			if ('error' in response) {
				overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

				const message_box = makeMessageBox('bad', response.error.messages, response.error.title);

				message_box.insertBefore($form);

				return;
			}

			overlayDialogueDestroy(overlay.dialogueid);

			overlay.element.dispatchEvent(new CustomEvent(TABFILTERITEM_EVENT_UPDATE, {detail: response, bubbles: true}));
		})
		.always(() => {
			overlay.unsetLoading();
		});
}
