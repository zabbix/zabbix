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

window.copy_popup = new class {
	init({form_name, action}) {
		this.form_name = form_name;
		this.overlay = overlays_stack.getById('copy');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.curl = new Curl('zabbix.php');
		this.curl.setArgument('action', action);

		$('#copy_type').on('change', this.changeTargetType);

		this.changeTargetType();
	}

	changeTargetType() {
		let $multiselect = $('<div>', {
			id: 'copy_targetids',
			class: 'multiselect',
			css: {
				width: '<?= ZBX_TEXTAREA_MEDIUM_WIDTH ?>px'
			},
			'aria-required': true
		}),
		helper_options = {
			id: 'copy_targetids',
			name: 'copy_targetids[]',
			objectOptions: {
				editable: true
			},
			popup: {
				parameters: {
					dstfld1: 'copy_targetids',
					writeonly: 1,
					multiselect: 1
					}
				}
		};

		switch ($('#copy_type').find('input[name=copy_type]:checked').val()) {
			case '<?= COPY_TYPE_TO_HOST_GROUP ?>':
				helper_options.object_name = 'hostGroup';
				helper_options.popup.parameters.srctbl = 'host_groups';
				helper_options.popup.parameters.srcfld1 = 'groupid';
				break;

			case '<?= COPY_TYPE_TO_HOST ?>':
				helper_options.object_name = 'hosts';
				helper_options.popup.parameters.srctbl = 'hosts';
				helper_options.popup.parameters.srcfld1 = 'hostid';
				break;

			case '<?= COPY_TYPE_TO_TEMPLATE ?>':
				helper_options.object_name = 'templates';
				helper_options.popup.parameters.srctbl = 'templates';
				helper_options.popup.parameters.srcfld1 = 'hostid';
				helper_options.popup.parameters.srcfld2 = 'host';
				break;

			case '<?= COPY_TYPE_TO_TEMPLATE_GROUP ?>':
				helper_options.object_name = 'templateGroup';
				helper_options.popup.parameters.srctbl = 'template_groups';
				helper_options.popup.parameters.srcfld1 = 'groupid';
				break;
		}

		$('#copy_targets').html($multiselect);

		$multiselect.multiSelectHelper(helper_options);
	}

	submit() {
		const fields = getFormFields(this.form);

		this.overlay.setLoading();

		this._post(this.curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	}

	_post(url, data, success_callback) {
		fetch(this.curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}
				else if('success' in response) {
					addMessage(makeMessageBox('good', [], response.success.title, true, false));
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
};
