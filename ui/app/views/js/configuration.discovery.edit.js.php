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

window.drule_edit_popup = new class {

	init({druleid, dchecks, drule}) {
		this.overlay = overlays_stack.getById('discovery.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.druleid = druleid;
		this.dchecks = dchecks;
		this.drule = drule;
		this.dcheckid = getUniqueId();
		this.available_device_types = [<?= SVC_AGENT ?>, <?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>, <?= SVC_SNMPv3 ?>];

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'discovery.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		document.getElementById('discovery_by').addEventListener('change', () => this.#updateForm());

		// Append existing discovery checks to check table.
		if (typeof dchecks === 'object') {
			dchecks = Object.values(dchecks);
		}
		for (const dcheck of dchecks) {
			this.#addCheck(dcheck);
		}

		this.#addRadioButtonValues(drule);
		this.#initActionButtons();
		this.#updateForm();
		this.form.style.display = '';
		this.overlay.recoverFocus();
	}

	#initActionButtons() {
		this.dialogue.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-check-add')) {
				this.#editCheck();
			}
			else if (e.target.classList.contains('js-remove')) {
				this.#removeDCheckRow(e.target.closest('tr').id);
				e.target.closest('tr').remove();
			}
			else if (e.target.classList.contains('js-edit')) {
				this.#editCheck(e.target.closest('tr'));
			}
		});

		const max_sessions = this.form.querySelector('#concurrency_max_type');

		max_sessions.onchange = () => {
			this.#updateForm();
		};

		max_sessions.dispatchEvent(new Event('change'));
	}

	#updateForm() {
		const discovery_by = this.form.querySelector('[name="discovery_by"]:checked').value;

		this.form.querySelector('.js-field-proxy').style.display = discovery_by == <?= ZBX_DISCOVERY_BY_PROXY ?>
			? ''
			: 'none';

		const concurrency_max_type = this.form.querySelector('[name="concurrency_max_type"]:checked').value;
		const concurrency_max = this.form.querySelector('#concurrency_max');
		const is_custom = concurrency_max_type == <?= ZBX_DISCOVERY_CHECKS_CUSTOM ?>;

		concurrency_max.classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>', !is_custom);
		if (is_custom) {
			concurrency_max.focus();
		}
	}

	#updateCheck(row, input) {
		delete input.dchecks;
		input.warning = row.querySelector('.btn-icon')?.getAttribute('data-hintbox-contents');

		this.#addCheck(input, row, true);
		row.remove();
		this.#updateCheckWarningIcon(input);
		this.#addInputFields(input);
	}

	#editCheck(row = null) {
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
				this.#updateCheck(row, e.detail);
			}
			else {
				this.dcheckid = getUniqueId()
				this.#addCheck(e.detail, null);
			}
		});
	}

	#addRadioButtonValues(drule) {
		jQuery('input:radio[name="uniqueness_criteria"][value='+jQuery.escapeSelector(drule.uniqueness_criteria)+']')
			.attr('checked', 'checked');
		jQuery('input:radio[name="host_source"][value='+jQuery.escapeSelector(drule.host_source)+']')
			.attr('checked', 'checked');
		jQuery('input:radio[name="name_source"][value='+jQuery.escapeSelector(drule.name_source)+']')
			.attr('checked', 'checked');

		document.querySelectorAll('#host_source, #name_source').forEach((element) => {
			element.addEventListener('change', (e) => {
				this.#updateRadioButtonValues(e);
			});
		});
	}

	#updateRadioButtonValues(event) {
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

	#addCheck(input, row = null, update = false) {
		delete input.dchecks;

		if (update === false) {
			if (typeof input.host_source === 'undefined') {
				const checked_host_source = document.querySelector('[name="host_source"]:checked:not([data-id])');
				input.host_source = checked_host_source === null
					? '<?= ZBX_DISCOVERY_DNS ?>'
					: checked_host_source.value;
			}

			if (typeof input.name_source === 'undefined') {
				const checked_name_source = document.querySelector('[name="name_source"]:checked:not([data-id])');
				input.name_source = checked_name_source === null
					? '<?= ZBX_DISCOVERY_UNSPEC ?>'
					: checked_name_source.value;
			}
		}
		else {
			if (this.available_device_types.includes(parseInt(input.type))) {
				input.host_source = document.querySelector('input[name=host_source]:checked').value;
				input.name_source = document.querySelector('input[name=name_source]:checked').value;
				input.uniqueness_criteria = document.querySelector('input[name=uniqueness_criteria]:checked').value;
			}
		}

		if (input.type == <?= SVC_ICMPPING ?>) {
			input.allow_redirect = typeof input.allow_redirect === 'undefined' ? 0 : input.allow_redirect;
		}

		const template = new Template(document.getElementById('dcheck-row-tmpl').innerHTML);

		if (row !== null) {
			row.insertAdjacentHTML('afterend', template.evaluate(input));
			this.#addInputFields(input);
		}
		else {
			document
				.querySelector('#dcheckList tbody')
				.insertAdjacentHTML('beforeend', template.evaluate(input));

			this.#updateCheckWarningIcon(input);
			this.#addInputFields(input);
		}

		this.#updateRadioButtonRows(input, update, row);
	}

	#updateCheckWarningIcon(input) {
		const row = document.getElementById(`dcheckRow_${input.dcheckid}`);
		const warning_icon = row.querySelector('.btn-icon');

		if (input.dcheckid.includes('new')) {
			warning_icon.remove();
		}

		this.dchecks.forEach(dcheck => {
			if (dcheck.dcheckid === input.dcheckid) {
				dcheck.warning === '' ? warning_icon.remove() : row.querySelector('.js-remove').disabled = true;
			}
		});
	}

	#addInputFields(input) {
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

	#updateRadioButtonRows(input, update, row = null) {
		const templates = {
			unique_template: ['#unique-row-tmpl', '#device-uniqueness-list', 'uniqueness_criteria_', 'ip'],
			host_template: ['#host-source-row-tmpl', '#host_source', 'host_source_', 'chk_dns'],
			name_template: ['#name-source-row-tmpl', '#name_source', 'name_source_', 'chk_host']
		};

		const need_to_add_row = this.available_device_types.includes(parseInt(input.type));

		for (const [template, list, key, def] of Object.values(templates)) {
			if (need_to_add_row) {
				if (update === false) {
					const template_html = document.querySelector(template).innerHTML;

					document.querySelector(list)
						.insertAdjacentHTML('beforeend', new Template(template_html).evaluate(input));
				}
				else {
					const template_html = document.querySelector(template).innerHTML;
					const list_item = document.querySelector(`${list} input[value$="${input.dcheckid}"]`)
						?.closest('li');

					if (list_item) {
						list_item.outerHTML = new Template(template_html).evaluate(input);
					}
					else {
						document.querySelector(list).insertAdjacentHTML('beforeend',
							new Template(template_html).evaluate(input)
						);
					}
				}
			}
			else {
				const dcheck_checkbox = document.querySelector(`${list} input[value$="${input.dcheckid}"]`);

				if (dcheck_checkbox !== null) {
					if (dcheck_checkbox.checked) {
						document.querySelector(`#${key}${def}`).checked = true;
					}

					dcheck_checkbox.closest('li')?.remove();
				}
			}
		}

		if (update === false) {
			this.#addRadioButtonValues(this.drule);
		}
		else {
			this.#addRadioButtonValues(input);

			const setInputSource = (field, default_value) => {
				const checked_source = document.querySelector(`input[name="${field}"]:checked`);

				if (typeof checked_source.dataset.id !== 'undefined') {
					if (checked_source.dataset.id === input.dcheckid) {
						input[field] = <?= ZBX_DISCOVERY_VALUE ?>;
					}
					else {
						input[field] = default_value;
					}
				}
				else {
					input[field] = checked_source.value;
				}
			};

			setInputSource('host_source', <?= ZBX_DISCOVERY_DNS ?>);
			setInputSource('name_source', <?= ZBX_DISCOVERY_UNSPEC ?>);

			delete input.uniqueness_criteria;
		}
	}

	#removeDCheckRow(dcheckid) {
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

		// Remove all warning icons and enable all Remove buttons in Checks table.
		const table = document.getElementById('dcheckList');

		table.querySelectorAll('.js-remove').forEach(element => element.disabled = false);
		table.querySelectorAll('.btn-icon').forEach(element => element.remove());

		this.overlay.setProperties({title, buttons});
		this.overlay.unsetLoading();
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'discovery.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('discovery')) ?>);

		this.#post(curl.getUrl(), {druleids: [this.druleid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		['name', 'iprange', 'delay'].forEach(
			field => fields[field] = fields[field].trim()
		);

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.druleid === null ? 'discovery.create' : 'discovery.update');

		this.#post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
		});
	}

	#post(url, data, success_callback) {
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
