<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


return <<<'JS'
/**
 * Send media type test data to server and get a response.
 *
 * @param {string} formname  Form name that is sent to server for validation.
 */
function mediatypeTestSend(formname) {
	var form = window.document.forms[formname],
		$form = jQuery(form),
		$form_fields = $form.find('#sendto, #subject, #message'),
		$submit_btn = jQuery('.submit-test-btn'),
		data = $form.serialize(),
		url = new Curl($form.attr('action'));

	$form.trimValues(['#sendto', '#subject', '#message']);

	$form_fields.prop('disabled', true);

	jQuery('<span></span>')
		.addClass('preloader')
		.insertAfter($submit_btn)
		.css({
			'display': 'inline-block',
			'margin': '0 10px -8px'
		});

	$submit_btn
		.prop('disabled', true)
		.hide();

	jQuery.ajax({
		url: url.getUrl(),
		data: data,
		success: function(ret) {
			$form.parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.messages !== 'undefined') {
				jQuery(ret.messages).insertBefore($form);
				$form.parent().find('.link-action').click();
			}

			$form_fields.prop('disabled', false);

			jQuery('.preloader').remove();
			$submit_btn
				.prop('disabled', false)
				.show();
		},
		error: function(request, status, error) {
			if (request.status == 200) {
				alert(error);
			}
			else if (window.document.forms[formname]) {
				var request = this,
					retry = function() {
						jQuery.ajax(request);
					};

				// Retry with 2s interval.
				setTimeout(retry, 2000);
			}
		},
		dataType: 'json',
		type: 'post'
	});
}
JS;
