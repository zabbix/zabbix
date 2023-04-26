<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

window.drule_edit_popup = new class {

	init({druleid, dchecks, drule}) {
		this.overlay = overlays_stack.getById('discoveryForm');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.druleid = druleid;
		this.drule = drule;
		this.dcheckid = getUniqueId();

		// Append existing discovery checks to check table.
		if (typeof dchecks === 'object') {
			dchecks = Object.values(dchecks);
		}
		for (const dcheck of dchecks) {
			this._addCheck(dcheck);
		}

		this._addRadioButtonValues(drule);
		this._initActionButtons();
	}

	_initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-check-add')) {
				this._editCheck();
			}
			else if (e.target.classList.contains('js-remove')) {
				this._removeDCheckRow(e.target.closest('tr').id);
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-edit')) {
				this._editCheck(e.target.closest('tr'));
			}
		});
	}

	_updateCheck(row, input) {
		delete input.dchecks;

		this._addCheck(input, row, true);
		row.remove();
		this._addInputFields(input);
	}

	_editCheck(row = null) {
		let params = {
			dcheckid: this.dcheckid
		};

		if (row !== null) {
			params = {
				dcheckid: this.dcheckid,
				update: 1
			};

			const hidden_inputs = row.querySelectorAll('input[type="hidden"]');

			for (let i = 0; i < hidden_inputs.length; i++) {
				const input = hidden_inputs[i];
				const name = input.getAttribute('name').match(/\[([^\]]+)]$/);

				if (name) {
					params[name[1]] = input.value;
				}
			}
		}

		const overlay = PopUp('discovery.check.edit', params, {
			dialogueid: 'discovery-check',
			dialogue_class: 'modal-popup-medium'
		});

		overlay.$dialogue[0].addEventListener('check.submit', (e) => {
			if (row !== null) {
				this._updateCheck(row, e.detail);
			}
			else {
				this.dcheckid = getUniqueId()
				this._addCheck(e.detail, null);
			}
		});
	}

	_addRadioButtonValues(drule) {
		jQuery('input:radio[name="uniqueness_criteria"][value='+jQuery.escapeSelector(drule.uniqueness_criteria)+']')
			.attr('checked', 'checked');
		jQuery('input:radio[name="host_source"][value='+jQuery.escapeSelector(drule.host_source)+']')
			.attr('checked', 'checked');
		jQuery('input:radio[name="name_source"][value='+jQuery.escapeSelector(drule.name_source)+']')
			.attr('checked', 'checked');

		document.querySelectorAll('#host_source, #name_source').forEach((element) => {
			element.addEventListener('change', (e) => {
				this._updateRadioButtonValues(e);
			});
		});
	}

	_updateRadioButtonValues(event) {
		let target = event.target;
		let name = target.getAttribute('name');

		if (typeof target.dataset.id !== 'undefined') {
			document.querySelectorAll(`[name^=dchecks][name$="[${name}]"]`)
				.forEach(function (dcheck) {
					dcheck.value = (name === 'name_source') ? <?= ZBX_DISCOVERY_UNSPEC ?> : <?= ZBX_DISCOVERY_DNS ?>;
				});

			document.querySelector(`[name="dchecks[${target.dataset.id}][${name}]"]`);

			document
				.querySelector(`[name="dchecks[${target.dataset.id}][${name}]"]`).value = <?= ZBX_DISCOVERY_VALUE ?>;
		}
		else {
			document.querySelectorAll(`[name^=dchecks][name$="[${name}]"]`)
				.forEach(function (dcheck) {
					dcheck.value = target.value;
				});
		}
	}

	_addCheck(input, row = null, update = false) {
		delete input.dchecks;

		if (update === false) {
			if (typeof input.host_source === 'undefined') {
				const checked_host_source = document.querySelector('[name="host_source"]:checked:not([data-id])');
				input.host_source = checked_host_source === null ? '<?= ZBX_DISCOVERY_DNS ?>' : checked_host_source.value;
			}

			if (typeof input.name_source === 'undefined') {
				const checked_name_source = document.querySelector('[name="name_source"]:checked:not([data-id])');
				input.name_source = checked_name_source  === null
					? '<?= ZBX_DISCOVERY_UNSPEC ?>'
					: checked_name_source.value;
			}
		}
		else {
			input.host_source = document.querySelector('input[name=host_source]:checked').value;
			input.name_source = document.querySelector('input[name=name_source]:checked').value;
			input.uniqueness_criteria = document.querySelector('input[name=uniqueness_criteria]:checked').value;
		}

		const template = new Template(document.getElementById('dcheck-row-tmpl').innerHTML);

		if (row !== null) {
			row.insertAdjacentHTML('afterend', template.evaluate(input));
			this._addInputFields(input);

		}
		else {
			document
				.querySelector('#dcheckList tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(input));

			this._addInputFields(input);
		}

		let available_device_types = [<?= SVC_AGENT ?>, <?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>, <?= SVC_SNMPv3 ?>];

		if (available_device_types.includes(parseInt(input.type))) {
			this._addRadioButtonRows(input, update, row);
		}
	}

	_addInputFields(input) {
		for (let field_name in input) {
			if (input.hasOwnProperty(field_name)) {
				const input_element = document.createElement('input');
				input_element.name = `dchecks[${input.dcheckid}][${field_name}]`;
				input_element.type = 'hidden';
				input_element.value = input[field_name];

				const dcheck_cell = document.getElementById(`dcheckCell_${input.dcheckid}`);
				dcheck_cell.appendChild(input_element);
			}
		}
	}

	_addRadioButtonRows(input, update, row = null) {
		if (update === false) {
			const templates = {
				unique_template: ['#unique-row-tmpl', '#device-uniqueness-list'],
				host_template: ['#host-source-row-tmpl', '#host_source'],
				name_template: ['#name-source-row-tmpl', '#name_source']
			};

			for (const [template, element] of Object.values(templates)) {
				const template_html = document.querySelector(template).innerHTML;

				document.querySelector(element)
					.insertAdjacentHTML('beforeend', new Template(template_html).evaluate(input));
			}
		}
		else {
			document.querySelector(`#device-uniqueness-list input[value="${input.dcheckid}"]`).closest('li')
				.outerHTML = new Template(document.querySelector('#unique-row-tmpl').innerHTML).evaluate(input);

			document.querySelector(`#host_source input[value="_${input.dcheckid}"]`).closest('li')
				.outerHTML = new Template(document.querySelector('#host-source-row-tmpl').innerHTML).evaluate(input);

			document.querySelector(`#name_source input[value="_${input.dcheckid}"]`).closest('li')
				.outerHTML = new Template(document.querySelector('#name-source-row-tmpl').innerHTML).evaluate(input);
		}

		if (update === false) {
			this._addRadioButtonValues(this.drule);
		}
		else {
			this._addRadioButtonValues(input);

			input.host_source = row.children[0].querySelector('input[name*="host_source"]').value;
			input.name_source = row.children[0].querySelector('input[name*="name_source"]').value;
			delete input.uniqueness_criteria;
		}
	}

	_removeDCheckRow(dcheckid) {
		dcheckid = dcheckid.substring(dcheckid.indexOf('_') + 1);

		const elements = {
			uniqueness_criteria_: 'ip',
			host_source_: 'chk_dns',
			name_source_: 'chk_host'
		};

		for (const [key, def] of Object.entries(elements)) {
			const obj = document.querySelector(`#${key}${dcheckid}`);

			if (obj !== null) {
				if (obj.checked) {
					document.querySelector(`#${key}${def}`).checked = true;
				}

				document.querySelector(`#${key}row_${dcheckid}`).remove();
			}
		}
	}

	clone({title, buttons}) {
		this.druleid = null;

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'discovery.delete');
		curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
			<?= json_encode(CCsrfTokenHelper::get('discovery'), JSON_THROW_ON_ERROR) ?>
		);

		this._post(curl.getUrl(), {druleids: [this.druleid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		['name', 'iprange', 'delay'].forEach(
			field => fields[field] = fields[field].trim()
		);

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.druleid === null ? 'discovery.create' : 'discovery.update');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
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
}
