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

/**
 * Send media type test data to server and get a response.
 *
 * @param {Overlay} overlay
 */
function mediatypeTestSend(overlay) {
	var $form = overlay.$dialogue.find('form'),
		$form_fields = $form.find('#sendto, #subject, #message'),
		data = $form.serialize(),
		url = new Curl($form.attr('action'));

	$form.trimValues(['#sendto', '#subject', '#message']);
	$form_fields.prop('disabled', true);
	$('#mediatypetest_log').addClass('<?= ZBX_STYLE_DISABLED ?>');

	overlay.setLoading();
	overlay.xhr = jQuery.ajax({
		url: url.getUrl(),
		data: data,
		success: function(ret) {
			overlay.$dialogue.find('.msg-bad, .msg-good').remove();

			if (typeof ret.messages !== 'undefined') {
				jQuery(ret.messages).insertBefore($form);
			}

			if ('response' in ret) {
				jQuery('#webhook_response_value', $form).val(ret.response.value);
				jQuery('#webhook_response_type', $form).text(ret.response.type);
			}

			if ('debug' in ret) {
				$('#mediatypetest_log').removeClass('disabled');
				sessionStorage.setItem('mediatypetest', JSON.stringify(ret.debug));
			}

			overlay.unsetLoading();
			$form_fields.prop('disabled', false);
		},
		error: function(request, status, error) {
			if (request.status == 200) {
				overlay.unsetLoading();
				$form_fields.prop('disabled', false);
				alert(error);
			}
			else if (window.document.forms['mediatypetest_form']) {
				var request = this,
					retry = function() {
						if (window.document.forms['mediatypetest_form']) {
							overlay.xhr = jQuery.ajax(request);
						}
					};

				// Retry with 2s interval.
				setTimeout(retry, 2000);
			}
		},
		dataType: 'json',
		type: 'post'
	});
}

function openLogPopup(opener) {
	if ($(opener).hasClass('<?= ZBX_STYLE_DISABLED ?>')) {
		return;
	}

	var debug = JSON.parse(sessionStorage.getItem('mediatypetest')||'null'),
		$content = $('<div>'),
		$logitems = $('<div>', {class: 'logitems'}),
		$footer = $('<div>', {class: 'logtotalms'});

	if (debug) {
		debug.log.forEach(function (entry) {
			$('<pre>').text(entry.ms + ' ' + entry.level + ' ' + entry.message).appendTo($logitems);
		});
		$footer.text(<?= json_encode(_('Time elapsed:')) ?> + " " + debug.ms + 'ms');
		$content.append($logitems);
	}

	overlayDialogue({
		'title': <?= json_encode(_('Media type test log')) ?>,
		'content': $content,
		'class': 'modal-popup modal-popup-generic debug-modal position-middle',
		'footer': $footer,
		'buttons': [
			{
				'title': <?= json_encode(_('Ok')) ?>,
				'cancel': true,
				'focused': true,
				'action': () => {}
			}
		]
	}, opener);
}

sessionStorage.removeItem('mediatypetest');
