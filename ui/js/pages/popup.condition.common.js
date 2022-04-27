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
 * Add popup inputs to main form and submit.
 *
 * @param {object} response
 * @param {Overlay} overlay
 */
function submitConditionPopup(response, overlay) {
	var form_name = response.form.name,
		form_param = response.form.param,
		input_name = response.form.input_name,
		inputs = response.inputs,
		cond_dialogueid = jQuery(document.forms['popup.condition'])
			.closest('[data-dialogueid]')
			.data('dialogueid');

	if (!cond_dialogueid) {
		return false;
	}

	for (var i in inputs) {
		if (inputs.hasOwnProperty(i) && inputs[i] !== null) {
			if (Array.isArray(inputs[i])) {
				for (var j in inputs[i]) {
					if (inputs[i].hasOwnProperty(j)) {
						create_var(form_name, input_name + '[' + i + ']' + '[' + j + ']', inputs[i][j], false);
					}
				}
			}
			else {
				create_var(form_name, input_name + '[' + i + ']', inputs[i], false);
			}
		}
	}

	// XHR has finished, but the state is still considered as loading at form submission.
	overlay.setLoading();
	submitFormWithParam(form_name, form_param, '1');
}

/**
 * Validate popup form.
 *
 * @param {Overlay} overlay
 */
function validateConditionPopup(overlay) {
	if (window.operation_popup && window.operation_popup.overlay.$dialogue.is(':visible')) {
		return window.operation_popup.view.operation_condition.onConditionPopupSubmit(overlay);
	}

	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action'));

	url.setArgument('validate', 1);

	overlay.setLoading();
	overlay.xhr = jQuery.ajax({
		url: url.getUrl(),
		data: $form.serialize(),
		dataType: 'json',
		method: 'POST'
	});

	overlay.xhr
		.always(function() {
			overlay.unsetLoading();
		})
		.done(function(response) {
			overlay.$dialogue.find('.msg-bad').remove();

			if ('error' in response) {
				const message_box = makeMessageBox('bad', response.error.messages, response.error.title);

				message_box.insertBefore($form);
			}
			else {
				submitConditionPopup(response, overlay);
			}
		});
}

function selectServices() {
	const overlay = PopUp('popup.services', {title: t('Services')}, {dialogueid: 'services'});

	overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
		const data = [];

		for (const service of e.detail) {
			data.push({id: service.serviceid, name: service.name});
		}

		$('#service-new-condition').multiSelect('addData', data);
	});
}
