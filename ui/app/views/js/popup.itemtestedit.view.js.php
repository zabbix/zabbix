<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

window.itemtestedit_view_popup = new class {

	#overlay;
	#dialogue;
	#footer;
	#form;
	#form_element;
	#rules_get_value;
	#is_item_testable = false;
	#show_prev = false;
	#show_snmp_form = false;
	#interface_address_enabled = false;
	#interface_port_enabled = false;

	init({rules, rules_get_value, is_item_testable, show_prev, show_snmp_form, interface_address_enabled,
			interface_port_enabled}) {
		this.#overlay = overlays_stack.getById('item-test');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#rules_get_value = rules_get_value;
		this.#is_item_testable = is_item_testable;
		this.#show_prev = show_prev;
		this.#show_snmp_form = show_snmp_form;
		this.#interface_address_enabled = interface_address_enabled;
		this.#interface_port_enabled = interface_port_enabled;

		this.#form.discoverAllFields();

		if (this.#show_prev) {
			this.#form.findFieldByName('upd_last').getField().value = Math.ceil(+new Date() / 1000);
		}

		this.#initEvents();
		this.#update();
		this.#form.discoverAllFields();
		this.#form_element.style.display = '';
		this.#overlay.recoverFocus();
	}

	#initEvents() {
		this.#form.findFieldByName('not_supported')?.getField().addEventListener('change', (e) => {
			const get_value_checked = this.#form.findFieldByName('get_value').getField().checked;

			$(this.#form.findFieldByName('value').getField())
				.multilineInput(get_value_checked || e.target.checked ? 'setReadOnly' : 'unsetReadOnly');
			const runtime_error_field = this.#form.findFieldByName('runtime_error')?.getField();

			if (runtime_error_field) {
				$(runtime_error_field)
					.multilineInput(!get_value_checked && e.target.checked ? 'unsetReadOnly' : 'setReadOnly');
			}
		});

		this.#form.findFieldByName('test_with')?.getField().addEventListener('change', () => this.#update());
		this.#form.findFieldByName('get_value')?.getField().addEventListener('change', () => this.#update());
		this.#form.findFieldByName('interface[details][version]')?.getField()
			.addEventListener('change', () => this.#update());
		this.#form.findFieldByName('interface[details][securitylevel]')?.getField()
			.addEventListener('change', () => this.#update());

		this.#form_element.querySelectorAll('.js-copy-button').forEach(button => {
			button.addEventListener('click', this.#onCopyButtonClick);
		});

		this.#form_element.querySelector('.js-get-value-submit')?.addEventListener('click', () => this.#getValue());
		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.#dialogue.addEventListener('dialogue.close', () => this.#onClose());
	}

	#update() {
		if (!this.#is_item_testable) {
			return;
		}

		const get_value_checked = this.#form.findFieldByName('get_value').getField().checked;

		for (const element of this.#form_element.querySelectorAll('#test_with input')) {
			element.disabled = !get_value_checked;
		}

		this.#form_element.querySelector('.js-test-with-proxy').style
			.display = this.#form.findFieldByName('test_with').getValue() == 0 ? 'none' : '';

		const not_supported_field = this.#form.findFieldByName('not_supported');

		if (not_supported_field) {
			not_supported_field.getField().disabled = get_value_checked;
			not_supported_field.getField().dispatchEvent(new Event('change'));
		}
		else {
			$(this.#form.findFieldByName('value').getField())
				.multilineInput(get_value_checked ? 'setReadOnly' : 'unsetReadOnly');
		}

		const value_warning = this.#form_element.querySelector('#value_warning');
		value_warning.style.display = !get_value_checked && value_warning.classList.contains('js-retrieved')
			? '' :
			'none';

		if (this.#show_prev) {
			$(this.#form.findFieldByName('prev_value').getField())
				.multilineInput(get_value_checked ? 'setReadOnly' : 'unsetReadOnly');

			this.#form.findFieldByName('prev_time').getField().readOnly = get_value_checked;
		}

		const proxy_field = this.#form.findFieldByName('proxyid');

		if (proxy_field) {
			$(proxy_field.getField()).multiSelect(get_value_checked ? 'enable' : 'disable');
		}

		const interface_address_field = this.#form.findFieldByName('interface[address]');

		if (interface_address_field) {
			interface_address_field.getField().disabled = !get_value_checked || !this.#interface_address_enabled;
		}

		const interface_port_field = this.#form.findFieldByName('interface[port]');

		if (interface_port_field) {
			interface_port_field.getField().disabled = !get_value_checked || !this.#interface_port_enabled;
		}

		this.#footer.querySelector('.js-submit').innerText = get_value_checked
			? <?= json_encode(_('Get value and test')) ?>
			: <?= json_encode(_('Test')) ?>;

		this.#form_element
			.querySelectorAll(
				'.js-host-address-row, .js-test-with-row, .js-get-value-row, [class*=js-popup-row-snmp]'
			).forEach(row => row.style.display = get_value_checked ? '' : 'none');


		if (get_value_checked && this.#show_snmp_form) {
			const snmp_version = this.#form.findFieldByName('interface[details][version]').getValue();

			const show_row_classnames = ['js-popup-row-snmp-version'];

			if (snmp_version == '<?= SNMP_V1 ?>') {
				show_row_classnames.push('js-popup-row-snmp-community');
			}
			else if (snmp_version == '<?= SNMP_V2C ?>') {
				show_row_classnames.push('js-popup-row-snmp-community', 'js-popup-row-snmp-max-repetition');
			}
			else {
				show_row_classnames.push('js-popup-row-snmpv3-contextname', 'js-popup-row-snmpv3-securityname',
					'js-popup-row-snmpv3-securitylevel', 'js-popup-row-snmp-max-repetition'
				);

				const snmp_security_level = this.#form.findFieldByName('interface[details][securitylevel]').getValue();

				if (snmp_security_level == '<?= ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV ?>') {
					show_row_classnames.push('js-popup-row-snmpv3-authprotocol', 'js-popup-row-snmpv3-authpassphrase');
				}
				else if (snmp_security_level == '<?= ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV ?>') {
					show_row_classnames.push('js-popup-row-snmpv3-authprotocol', 'js-popup-row-snmpv3-authpassphrase',
						'js-popup-row-snmpv3-privprotocol', 'js-popup-row-snmpv3-privpassphrase'
					);
				}
			}

			this.#form_element.querySelectorAll('[class*=js-popup-row-snmp]')
				.forEach(row => {
					let visible = false;

					row.classList.forEach(classname => {
						visible = visible || show_row_classnames.includes(classname);
					});

					row.style.display = visible ? '' : 'none'
				});
		}
	}

	#onCopyButtonClick(e) {
		writeTextClipboard(e.target.dataset.result);
		e.target.focus();
	}

	#onClose () {
		const fields = this.#getFormFields(false);

		const remember_values = {
			value: fields.value,
			not_supported: this.#form.findFieldByName('not_supported')?.getField().checked ? 1 : 0,
			eol: fields.eol,
			macros: fields.macros
		};

		if (this.#is_item_testable) {
			if ('runtime_error' in fields) {
				remember_values.runtime_error = fields.runtime_error;
			}

			remember_values.get_value = fields.get_value;
			remember_values.test_with = this.#form.findFieldByName('test_with').getField()
				.querySelector('input:checked').value;
			remember_values.proxyid = fields.proxyid ? fields.proxyid: 0;
			remember_values.interfaceid = fields.interfaceid ? fields.interfaceid : 0;

			if (this.#interface_address_enabled) {
				remember_values.address = this.#form.findFieldByName('interface[address]').getField().value;
			}

			if (this.#interface_port_enabled) {
				remember_values.port = this.#form.findFieldByName('interface[port]').getField().value;
			}

			if (fields.interface && fields.interface.details) {
				remember_values.interface_details = fields.interface.details;
			}
		}

		if (this.#show_prev) {
			remember_values.prev_value = fields.prev_value;
			remember_values.prev_time = fields.prev_time;
		}

		this.#dialogue.dispatchEvent(new CustomEvent('itemtest.close', {detail: remember_values}));
	}

	#setLoading() {
		const get_value_button = this.#form_element.querySelector('.js-get-value-submit');

		if (get_value_button) {
			get_value_button.disabled = true;
		}
		this.#overlay.setLoading();
	}

	#unsetLoading() {
		const get_value_button = this.#form_element.querySelector('.js-get-value-submit');

		if (get_value_button) {
			get_value_button.classList.remove('is-loading');
			get_value_button.disabled = false;
		}

		this.#form.unlock();
		this.#overlay.unsetLoading();
	}

	#getFormFields(with_get_value) {
		const fields = this.#form.getAllValues();

		if (this.#show_prev && with_get_value) {
			fields.time_change = fields.upd_prev !== ''
				? parseInt(fields.upd_last) - parseInt(fields.upd_prev)
				: Math.ceil(+new Date() / 1000) - parseInt(fields.upd_last);
		}

		return fields;
	}

	#getValue() {
		this.#removePopupMessages();
		const get_value_button = this.#form_element.querySelector('.js-get-value-submit');
		const fields = this.#getFormFields(true);

		if (get_value_button) {
			get_value_button.classList.add('is-loading');
		}

		this.#setLoading();
		const field_names = ['item_type', 'test_type', 'test_with', 'proxyid',
			'interface[address]', 'interface[port]', 'interface[details][version]',
			'interface[details][community]', 'interface[details][max_repetitions]', 'interface[details][securityname]',
			'interface[details][securitylevel]', 'interface[details][authprotocol]',
			'interface[details][authpassphrase]', 'interface[details][privprotocol]',
			'interface[details][privpassphrase]'
		];

		field_names.forEach(field_name => {
			const field = this.#form.findFieldByName(field_name);

			if (field) {
				field.setChanged();
			}
		});

		this.#form.validateFieldsForAction(field_names, this.#rules_get_value, null)
			.then((result) => {
				if (!result) {
					this.#unsetLoading();
					return;
				}

				this.#post(zabbixUrl({action: 'popup.itemtest.getvalue'}), fields, (response) => {
					this.#processGetValueResult(response, fields.upd_last);
				});
			})
	}

	#submit() {
		this.#removePopupMessages();
		this.#cleanPreviousTestResults();
		const fields = this.#getFormFields(this.#form.findFieldByName('get_value')?.getField().checked ? true : false);

		this.#form.lock();
		this.#setLoading();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#unsetLoading();
					return;
				}

				this.#post(zabbixUrl({action: 'popup.itemtest.send'}), fields, (response) => {
					this.#processItemPreprocessingTestResults(response.steps ?? []);
					this.#processGetValueResult(response, fields.upd_last);

					if ('not_supported' in response && this.#form.findFieldByName('not_supported')) {
						const not_supported_field = this.#form.findFieldByName('not_supported');
						not_supported_field.getField().checked = response.not_supported != 0;
						not_supported_field.getField().dispatchEvent(new Event('change'));
					}

					if ('runtime_error' in response && this.#form.findFieldByName('runtime_error')) {
						$(this.#form.findFieldByName('runtime_error').getField())
							.multilineInput('value', response.runtime_error);
					}

					if (response.final !== undefined) {
						const result = this.#makeStepResult(response.final);
						const template = new Template(this.#form_element.querySelector('#final-result-row').innerHTML);

						const result_row = template.evaluateToElement({action: '', mode: 'final'});

						result_row.querySelector('.final-result-action').innerHTML = response.final.action;
						result_row.querySelector('.final-result-result').innerHTML = result.innerHTML;

						if (response.final.error === undefined && response.final.result) {
							const copy_button = result_row.querySelector('.js-copy-button');
							copy_button.dataset.result = response.final.result;
							copy_button.style.display = '';
						}

						this.#form_element.querySelector('.item-final-result > div').append(result_row);

						if (response.mapped_value !== undefined) {
							const mapped_value = this.#makeStepResult({result: response.mapped_value});
							const mapping_row = template.evaluateToElement({
								action: <?= json_encode(_('Result with value map applied')) ?>,
								mode: 'mapped'
							});

							mapping_row.querySelector('.final-result-action').classList.add('<?= ZBX_STYLE_GREY ?>');
							mapping_row.querySelector('.final-result-result').innerHTML = mapped_value.innerHTML;

							if (response.final.error === undefined && response.final.result) {
								const copy_button = mapping_row.querySelector('.js-copy-button');
								copy_button.dataset.result = response.final.result;
								copy_button.style.display = '';
							}

							this.#form_element.querySelector('.item-final-result > div').append(mapping_row);
						}

						this.#form_element.querySelector('.js-final-result').style.display = '';
						this.#form_element.querySelector('.item-final-result').style.display = '';

						this.#form_element.querySelectorAll('.item-final-result .js-copy-button').forEach(button => {
							button.addEventListener('click', this.#onCopyButtonClick);
						})
					}
				});
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

				if ('form_errors' in response) {
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				success_callback(response);
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.#unsetLoading());
	}

	#processGetValueResult(response, last_updated) {
		if (this.#show_prev && response.prev_value !== undefined) {
			$(this.#form.findFieldByName('prev_value').getField())
				.multilineInput('value', response.prev_value);
			this.#form.findFieldByName('prev_time').getField().value = response.prev_time;
			this.#form.findFieldByName('upd_prev').getField().value = last_updated;
			this.#form.findFieldByName('upd_last').getField().value = Math.ceil(+new Date() / 1000);
		}

		if ('value' in response) {
			$(this.#form.findFieldByName('value').getField()).multilineInput('value', response.value);
		}

		if ('value_warning' in response) {
			const value_warning = this.#form_element.querySelector('#value_warning');
			value_warning.classList.add('js-retrieved');
			value_warning.dataset.hintboxHtml = response.value_warning;
			value_warning.style.display = '';
		}

		if (response.eol !== undefined) {
			this.#form.findFieldByName('eol').getField()
				.querySelector(`input[value="${response.eol}"]`).checked = true;
		}
	}

	#processItemPreprocessingTestResults(steps) {
		const tmpl_gray_label = new Template(this.#form_element.querySelector('#preprocessing-gray-label').innerHTML);
		const tmpl_act_done = new Template(
			this.#form_element.querySelector('#preprocessing-step-action-done').innerHTML
		);

		steps.forEach((step, i) => {
			const result = step.result;
			const row = this.#form_element.querySelector(`.js-preprocessing-test-step[data-index="${i}"]`);

			if (step.action === <?= ZBX_PREPROC_FAIL_DEFAULT ?>) {
				step.action = null;
			}
			else if (step.action === <?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>) {
				step.action = tmpl_gray_label.evaluateToElement({label: <?= json_encode(_('Discard value')) ?>});
			}
			else if (step.action === <?= ZBX_PREPROC_FAIL_SET_VALUE ?>) {
				step.result = step.result === '' ? <?= json_encode(_('<empty string>')) ?> : step.result;
				step.action = tmpl_act_done.evaluateToElement({
					action_name: <?= json_encode(_('Set value to')) ?>,
					failed: step.result,
					failed_hint: escapeHtml(step.result)
				});
			}
			else if (step.action === <?= ZBX_PREPROC_FAIL_SET_ERROR ?>) {
				step.action = tmpl_act_done.evaluateToElement({
					action_name: <?= json_encode(_('Set error to')) ?>,
					failed: step.failed,
					failed_hint: escapeHtml(step.failed)
				});
			}

			if (step.error === undefined && result) {
				const copy_button = row.querySelector('.js-copy-button');
				copy_button.dataset.result = result;
				copy_button.closest('tr').classList.add('display-icon');
				copy_button.style.display = '';
			}

			step.result = this.#makeStepResult(step);

			if (step.action !== undefined && step.action !== null) {
				row.querySelector('.js-preproc-step-name').append(
					tmpl_gray_label.evaluateToElement({label: <?= json_encode(_('Custom on fail')) ?>})
				);
			}
			else {
				step.action = '';
			}

			row.querySelector('.js-preproc-step-result').append(step.result, step.action);
		});
	}

	#makeStepResult(step) {
		if (step.error !== undefined) {
			const template = new Template(this.#form_element.querySelector('#preprocessing-step-error-icon').innerHTML);

			return template.evaluateToElement({
				error: escapeHtml(step.error) || <?= json_encode(_('<empty string>')) ?>
			});
		}

		if (step.result === undefined || step.result === null || step.result === '') {
			const template = new Template(
				this.#form_element.querySelector('#preprocessing-step-result-empty').innerHTML
			);

			return template.evaluateToElement({
				result: step.result === ''
					? <?= json_encode(_('<empty string>')) ?>
					: <?= json_encode(_('No value')) ?>
			});
		}

		let template_name = 'preprocessing-step-result-default';

		if (step.warning !== undefined) {
			template_name = 'preprocessing-step-result-warning';
		}
		else if (step.result.indexOf("\n") != -1 || step.result.length > 25) {
			template_name = 'preprocessing-step-result'
		}

		const template = new Template(this.#form_element.querySelector(`#${template_name}`).innerHTML);

		return template.evaluateToElement({
			result: step.result, result_hint: escapeHtml(step.result), warning: step.warning
		});
	}

	#cleanPreviousTestResults() {
		this.#form_element.querySelectorAll('.js-preproc-step-result').forEach(element => {
			element.innerHTML = '';
		});

		this.#form_element.querySelectorAll('.js-preproc-step-name > div').forEach(element => {
			element.remove();
		});

		if (this.#form_element.querySelector('.js-final-result')) {
			this.#form_element.querySelector('.js-final-result').style.display = 'none';
			this.#form_element.querySelector('.item-final-result > div').innerHTML = '';
			this.#form_element.querySelector('.item-final-result').style.display = 'none';
		}

		this.#form_element.querySelectorAll('preprocessing-test-form .result-copy > .js-copy-button')
			.forEach(element => {
				element.style.display = 'none';
				element.closest('tr').classList.remove('display-icon');
			});
	}

	#removePopupMessages() {
		for (const el of this.#form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}
};
