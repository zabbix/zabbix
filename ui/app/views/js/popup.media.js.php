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

jQuery(document).ready(function($) {
	$('#email_send_to').dynamicRows({template: '#email_send_to_table_row', allow_empty: true});

	// Show/hide multiple "Send to" inputs and single "Send to" input and populate hidden "type" field.
	$('#mediatypeid')
		.on('change', function() {
			var mediatypes_by_type = <?= json_encode($data['mediatypes']) ?>,
				mediatypeid = $(this).val();

			if (mediatypes_by_type[mediatypeid] == <?= MEDIA_TYPE_EMAIL ?>) {
				$('#mediatype_send_to').hide();
				$('#mediatype_email_send_to').show();
			}
			else {
				$('#mediatype_send_to').show();
				$('#mediatype_email_send_to').hide();
			}

			$('.focusable', $(this)).toggleClass('red', $(`li[value="${mediatypeid}"]`, $(this)).hasClass('red'));

		})
		.trigger("change");

	document.getElementById('media_form').style.display = '';

	overlays_stack.end().centerDialog();
	overlays_stack.end().recoverFocus();
});

/**
 * Send media form data to server for validation before adding them to user media tab.
 *
 * @param {Overlay} overlay
 */
function validateMedia(overlay) {
	var $form = overlay.$dialogue.find('form');

	$form.trimValues(['#period', '#sendto', 'input[name^="sendto_emails"]']);

	overlay.setLoading();
	overlay.xhr = jQuery.ajax({
		url: $form.attr('action'),
		data: $form.serialize(),
		success: function(ret) {
			overlay.$dialogue.find('.msg-bad, .msg-good').remove();

			if ('error' in ret) {
				const message_box = makeMessageBox('bad', ret.error.messages, ret.error.title);

				message_box.insertBefore($form);

				overlay.unsetLoading();
			}
			else {
				add_media(ret.dstfrm, ret.media, ret.mediatypeid, ret.sendto, ret.period, ret.active, ret.severity);
			}
		},
		dataType: 'json',
		type: 'post'
	});
}
