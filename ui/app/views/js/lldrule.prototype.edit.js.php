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

window.lldrule_prototype_edit = new class {

	#dialogue;
	#test_rules;
	#footer;
	#form;
	#form_element;
	#initial_form_fields;
	#overlay;
	#return_url;
	#tabs;
	#testable_item_types;

	init({rules, test_rules, testable_item_types, lldrule, host, inherited_timeouts, field_switches, interface_types,
			return_url}) {
		this.#overlay = overlays_stack.getById('lldrule.prototype.edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#test_rules = test_rules;
		this.#testable_item_types = testable_item_types;
		this.#return_url = return_url;

		this.#initEvents();
		this.#initPopupListeners();

		ZABBIX.PopupManager.setReturnUrl(return_url);

		this.#tabs = {
			lldrule: new LldRuleEditPrototypeTab({
				container: document.getElementById('lldrule-prototype-tab'),
				field_switches: field_switches,
				interface_types: interface_types,
				lldrule: lldrule,
				host_interfaces: host.interfaces,
				readonly: (lldrule.templated || lldrule.discovered),
				inherited_timeouts: inherited_timeouts
			}),
			preprocessing: new ItemEditPreprocessingTab({
				container: document.getElementById('processing-tab'),
				preprocessing: lldrule.preprocessing,
				readonly: (lldrule.templated || lldrule.discovered),
				form: this.#form,
				test_rules: test_rules
			}),
			lldmacros: new LldRuleEditLldMacrosTab({
				container: document.getElementById('lldrule-macros-tab'),
				lld_macro_paths: lldrule.lld_macro_paths
			}),
			filters: new LldRuleEditLldFiltersTab({
				container: document.getElementById('lldrule-filters-tab'),
				conditions: lldrule.filter.conditions
			})
		}

		this.#tabs.lldrule.getContainer().addEventListener('update', () => {
			this.#update();
		});

		this.#tabs.preprocessing.getContainer().addEventListener('test.validated', () => {
			this.#overlay.unsetLoading();
			this.#update();
		});

		this.#update();

		this.#form_element.style.display = '';
		this.#overlay.recoverFocus();

		this.#form.findFieldByName('interfaceid')?.setChanged();
		this.#form.findFieldByName('type').setChanged();
		this.#form.validateChanges(['interfaceid', 'type']);
	}

	#formIsReady() {
		this.#initial_form_fields = this.#formGetAllValues();
	}

	#formGetAllValues() {
		window.lldoverrides.appendFormData(this.#form_element);
		this.#form.discoverAllFields();
		const fields = this.#form.getAllValues();

		Object.keys(fields.delay_flex).forEach(key => {
			let { schedule, period, type, delay } = fields.delay_flex[key];
			type = parseInt(type);

			if (type == <?= ITEM_DELAY_FLEXIBLE ?> && delay === '' && period === '') {
				delete fields.delay_flex[key];
			}

			if (type == <?= ITEM_DELAY_SCHEDULING ?> && schedule === '') {
				delete fields.delay_flex[key];
			}
		});

		return fields;
	}

	#initEvents() {
		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.#footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.#footer.querySelector('.js-test-item')?.addEventListener('click', (e) => this.#test(e.target));
		this.#footer.querySelector('.js-delete')?.addEventListener('click', () => this.#delete());
	}

	#update() {
		this.#footer.querySelector('.js-test-item')?.toggleAttribute('disabled', !this.#isTestableItem());
	}

	#isTestableItem() {
		const key = this.#form_element.querySelector('[name="key"]').value
		const type = parseInt(this.#form_element.querySelector('[name="type"]').value, 10);

		return type == <?= ITEM_TYPE_SIMPLE ?>
			? key.substring(0, 7) !== 'vmware.' && key.substring(0, 8) !== 'icmpping'
			: this.#testable_item_types.indexOf(type) != -1;
	}

	#delete() {
		if (window.confirm(<?= json_encode(_('Delete discovery prototype?')) ?>)) {
			this.#removePopupMessages();

			const fields = this.#form.getAllValues();

			const params = {
				action: 'lldrule.prototype.delete',
				context: fields.context,
				[CSRF_TOKEN_NAME]: fields[CSRF_TOKEN_NAME]
			}

			this.#post(zabbixUrl(params), {itemids: [fields.itemid]}, params.action);
		}
		else {
			this.#overlay.unsetLoading();
			this.#update();
		}
	}

	#clone() {
		const fields = this.#formGetAllValues();
		fields.clone = 1;

		Object.entries(fields).forEach(([key, value]) => {
			if (value === null) {
				delete fields[key];
			}
		})

		this.#overlay = ZABBIX.PopupManager.open('lldrule.prototype.edit', fields, {reuse_existing: false});
	}

	#submit() {
		this.#removePopupMessages();
		const fields = this.#formGetAllValues();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();
					this.#update();
					return;
				}

				const action = fields.itemid ? 'lldrule.prototype.update' : 'lldrule.prototype.create'

				this.#post(zabbixUrl({action}), fields, action);
			});
	}

	#test(button) {
		this.#tabs.preprocessing.test(true, true, button, -2);
	}

	#post(url, data, action, keep_open = false) {
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
				if (keep_open) {
					const message_box = makeMessageBox('good', response.success.messages, response.success.title)[0];

					this.#form_element.parentNode.querySelectorAll('.msg-good,.msg-bad,.msg-warning')
						.forEach(node => node.remove());
					this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
				}
				else {
					overlayDialogueDestroy(this.#overlay.dialogueid);

					const event_details = {action, ...response};

					if (action === 'lldrule.prototype.delete') {
						event_details.redirect_url = this.#return_url;
					}

					this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: event_details}));
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => {
				this.#overlay.unsetLoading();
				this.#update();
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

	#isConfirmed() {
		return JSON.stringify(this.#initial_form_fields) === JSON.stringify(this.#formGetAllValues())
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	}

	#initPopupListeners() {
		const subscriptions = [];

		for (const action of ['lldrule.edit', 'lldrule.prototype.edit']) {
			subscriptions.push(
				ZABBIX.EventHub.subscribe({
					require: {
						context: CPopupManager.EVENT_CONTEXT,
						event: CPopupManagerEvent.EVENT_OPEN,
						action
					},
					callback: ({data, event}) => {
						const field = this.#form.findFieldByName('itemid');

						if (!field || data.action_parameters.itemid === field.getValue()) {
							return;
						}

						if (!this.#isConfirmed()) {
							event.preventDefault();
						}
					}
				})
			);
		}

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: 'configuration.host.discovery.edit.overr',
					action: 'ready'
				},
				callback: () => {
					this.#formIsReady();
				}
			})
		);

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.#overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	}
};
