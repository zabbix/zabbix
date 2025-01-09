<?php declare(strict_types = 0);
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

window.copy_popup = new class {

	init({action}) {
		this.overlay = overlays_stack.getById('copy');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.curl = new Curl('zabbix.php');
		this.curl.setArgument('action', action);

		for (const element of document.querySelectorAll('input[name="copy_type"]')) {
			element.addEventListener('change', () => {
				this.changeTargetType(parseInt(element.value));
			});
		}

		this.changeTargetType();
	}

	changeTargetType(copy_type = <?= COPY_TYPE_TO_TEMPLATE_GROUP ?>) {
		const copy_targets = document.getElementById('copy_targets');
		copy_targets.innerHTML = '';

		const multiselect = document.createElement('div');
		multiselect.id = 'copy_targetids';
		multiselect.classList.add('multiselect');
		multiselect.style.width = '<?= ZBX_TEXTAREA_MEDIUM_WIDTH ?>px';
		multiselect.setAttribute('aria-required', 'true');

		copy_targets.appendChild(multiselect);

		const helper_options = {
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

		switch (copy_type) {
			case <?= COPY_TYPE_TO_HOST_GROUP ?>:
				helper_options.object_name = 'hostGroup';
				helper_options.popup.parameters.srctbl = 'host_groups';
				helper_options.popup.parameters.srcfld1 = 'groupid';

				break;

			case <?= COPY_TYPE_TO_HOST ?>:
				helper_options.object_name = 'hosts';
				helper_options.popup.parameters.srctbl = 'hosts';
				helper_options.popup.parameters.srcfld1 = 'hostid';
				break;

			case <?= COPY_TYPE_TO_TEMPLATE ?>:
				helper_options.object_name = 'templates';
				helper_options.popup.parameters.srctbl = 'templates';
				helper_options.popup.parameters.srcfld1 = 'hostid';
				helper_options.popup.parameters.srcfld2 = 'host';
				break;

			case <?= COPY_TYPE_TO_TEMPLATE_GROUP ?>:
				helper_options.object_name = 'templateGroup';
				helper_options.popup.parameters.srctbl = 'template_groups';
				helper_options.popup.parameters.srcfld1 = 'groupid';
				break;
		}

		jQuery('#copy_targetids')
			.multiSelectHelper(helper_options)
			.on('change', () => {
				jQuery('#copy_targetids').multiSelect('setDisabledEntries',
					[...this.form.querySelectorAll('[name^="copy_targetids["]')].map((input) => input.value)
				);
			});
	}

	submit() {
		const fields = getFormFields(this.form);

		this.overlay.setLoading();
		this._post(this.curl.getUrl(), fields);
	}

	_post(url, data) {
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
				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
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
}
