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


ob_start(); ?>
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
		url = new Curl($form.attr('action'));

	$form.trimValues(['#sendto', '#subject', '#message']);

	jQuery.ajax({
		url: url.getUrl(),
		data: $form.serialize(),
		beforeSend: function() {
			$form_fields.prop('disabled', true);

			jQuery('<span></span>')
				.addClass('preloader')
				.insertAfter($submit_btn)
				.css({
					'display': 'inline-block',
					'margin': '0 10px -8px'
				});

			$submit_btn
				.attr('disabled', true)
				.hide();
		},
		success: function(ret) {
			$form.parent().find('.msg-bad, .msg-good').remove();

			if (typeof ret.messages !== 'undefined') {
				jQuery(ret.messages).insertBefore($form);
				$form.parent().find('.link-action').click();
			}

			$form_fields.prop('disabled', false);

			jQuery('.preloader').remove();
			$submit_btn
				.attr('disabled', false)
				.show();
		},
		dataType: 'json',
		type: 'post'
	});
}
<?php return ob_get_clean(); ?>
