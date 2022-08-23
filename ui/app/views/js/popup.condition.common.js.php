<?php declare(strict_types = 0);
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
?>

window.condition_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('condition');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
	}

	submit() {
		let curl = new Curl('zabbix.php', false);
		//fields.action = 'conditions.validate';
		const fields = getFormFields(this.form);
		curl.setArgument('action', 'popup.condition.actions');
		curl.setArgument('validate', '1');
		//curl.setArgument('action', 'conditions.validate');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('condition.dialogue.submit', {detail: response}));
		});
	}

	_post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];
				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	validate(overlay) {
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
				// else {
				//	submit(response, overlay);
				// }
			});
	}

	selectServices() {
		const overlay = PopUp('popup.services', {title: t('Services')}, {dialogueid: 'services'});

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			const data = [];

			for (const service of e.detail) {
				data.push({id: service.serviceid, name: service.name});
			}

			$('#service-new-condition').multiSelect('addData', data);
		});
	}

}
