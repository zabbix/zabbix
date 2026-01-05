<?php
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

window.lldrule_edit = new class {

	#dialogue;
	#footer;
	#form;
	#form_element;
	#overlay;
	#tabs;
	#testable_item_types;

	init({rules, test_rules, testable_item_types, lldrule, host, field_switches, interface_types, return_url}) {
		this.#overlay = overlays_stack.getById('lldrule.edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);
		this.#footer = this.#overlay.$dialogue.$footer[0];
		this.#testable_item_types = testable_item_types;

		this.#initEvents();
		this.#update();

		ZABBIX.PopupManager.setReturnUrl(return_url);

		this.#tabs = {
			lldrule: new LldRuleEditLldRuleTab({
				container: document.getElementById('lldrule-tab'),
				field_switches: field_switches,
				interface_types: interface_types,
				lldrule: lldrule,
				host_interfaces: host.interfaces
			}),
			preprocessing: new ItemEditPreprocessingTab({
				container: document.getElementById('processing-tab'),
				preprocessing: lldrule.preprocessing,
				readonly: lldrule.readonly,
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
		this.#form.reload(rules);
	}

	#initEvents() {
		this.#footer.querySelector('.js-submit').addEventListener('click', () => this.#submit());
		this.#footer.querySelector('.js-clone')?.addEventListener('click', () => this.#clone());
		this.#footer.querySelector('.js-execute-item')?.addEventListener('click', () => this.#execute());
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
		if (window.confirm(<?= json_encode(_('Delete discovery rule?')) ?>)) {
			this.#removePopupMessages();

			const fields = this.#form.getAllValues();

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'lldrule.delete');
			curl.setArgument('context', fields.context);
			curl.setArgument(CSRF_TOKEN_NAME, fields[CSRF_TOKEN_NAME]);

			this.#post(curl.getUrl(), {itemids: [fields.itemid]});
		}
		else {
			this.#overlay.unsetLoading();
		}
	}

	#clone() {
		window.lldoverrides.appendFormData(this.#form_element);
		this.#form.discoverAllFields();

		const fields = this.#form.getAllValues();
		fields.clone = 1;

		Object.entries(fields).forEach(([key, value]) => {
			if (value === null) {
				delete fields[key];
			}
		})

		this.#overlay = ZABBIX.PopupManager.open('lldrule.edit', fields, {reuse_existing: false});
	}

	#submit() {
		this.#removePopupMessages();
		window.lldoverrides.appendFormData(this.#form_element);
		this.#form.discoverAllFields();
		const fields = this.#form.getAllValues();
		this.#overlay.unsetLoading();

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', fields.itemid ? 'lldrule.update' : 'lldrule.create');

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();
					return;
				}

				this.#post(curl.getUrl(), fields);
			});
	}

	#execute() {
		const fields = this.#form.getAllValues();

		const data = {
			discovery_rule: 1,
			itemids: [fields.itemid]
		};

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'item.execute');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('item')) ?>);

		this.#post(curl.getUrl(), data, true);
	}

	#test(button) {
		this.#tabs.preprocessing.test(true, true, button, -2);
	}

	#post(url, data, keep_open = false) {
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
					const action = (new Curl(url)).getArgument('action');

					overlayDialogueDestroy(this.#overlay.dialogueid);

					this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: {action, ...response}}));
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.#overlay.unsetLoading());

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
