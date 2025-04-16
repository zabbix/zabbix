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

const HostWizardStep = {
	WELCOME: 0,
	SELECT_TEMPLATE: 1,
	CREATE_HOST: 2
}

window.host_wizard_edit = new class {
	#templates;
	#linked_templates;
	#current_step;

	#overlay;
	#dialogue;

	#back_button;
	#next_button;

	#template_step_welcome;
	#template_step_select_template;
	#template_templates_section;
	#template_card;

	#step_from_body;

	init({templates, linked_templates, wizard_hide_welcome}) {
		this.#templates = templates;
		this.#linked_templates = linked_templates;
		this.#current_step = wizard_hide_welcome ? HostWizardStep.SELECT_TEMPLATE : HostWizardStep.WELCOME;

		this.#template_step_welcome = new Template(
			document.getElementById('host-wizard-step-welcome').innerHTML
		);
		this.#template_step_select_template = new Template(
			document.getElementById('host-wizard-step-select-template').innerHTML
		);
		this.#template_templates_section = new Template(
			document.getElementById('host-wizard-templates-section').innerHTML
		);
		this.#template_card = new Template(
			document.getElementById('host-wizard-template-card').innerHTML
		);

		this.#overlay = overlays_stack.getById('host.wizard.edit');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#step_from_body = this.#dialogue.querySelector('.step-form-body');

		this.#back_button = this.#dialogue.querySelector('.js-back').addEventListener('click', () => {
			const first_step = wizard_hide_welcome ? HostWizardStep.SELECT_TEMPLATE : HostWizardStep.WELCOME;

			this.#current_step = Math.max(first_step, this.#current_step - 1);
			this.#back_button.style.display = this.#current_step === first_step ? 'none' : '';
		});

		this.#next_button = this.#dialogue.querySelector('.js-next').addEventListener('click', () => {
			const last_step = Math.max(...Object.values(HostWizardStep));

			this.#current_step = Math.min(last_step, this.#current_step + 1);
			this.#next_button.style.display = this.#current_step === last_step ? 'none' : '';
		});

		this.#updateStep();
		this.#updateForm();
	}

	#updateStep() {
		const step_render = {
			HostWizardStep.WELCOME: () => this.#renderWelcome(),
			HostWizardStep.SELECT_TEMPLATE: () => this.#renderSelectTemplate(),
			HostWizardStep.CREATE_HOST: () => this.#renderCreateHost()
		}[this.#current_step];

		if (step_render !== undefined) {
			this.#step_from_body.replaceWith(step_render());
		}
	}

	#renderWelcome() {
		return this.#template_step_welcome.evaluateToElement();
	}

	#renderSelectTemplate() {
		const content = this.#template_step_select_template.evaluateToElement();

		return content;
	}

	#renderCreateHost() {
		return this.#template_step_welcome.evaluateToElement();
	}

	#updateForm() {

	}












	showMultistepForm(wizard_hide_welcome) {
		if (!wizard_hide_welcome) {
			this.showStep('welcomeStep');
		}
		else {
			this.showStep('selectTemplates');
		}
	}

	showStep(step) {
		switch (step) {
			case 'welcomeStep':
				this.showStepWelcome();
				break;
			case 'selectTemplates':
				this.showStepSelectTemplates();
				break;
		}
	}

	showStepWelcome() {

	}

	showStepSelectTemplates() {

	}

/*

	init({templates, linked_templates, old_template_count, wizard_hide_welcome}) {
		this.overlay = overlays_stack.getById('host.wizard.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.templates = templates;
		this.linked_templates = linked_templates;
		this.old_template_count = old_template_count;
		this.wizard_hide_welcome = wizard_hide_welcome;

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'host.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);


		this.initial_form_fields = getFormFields(this.form);
		this.initEvents(); // TODO VM: do we need it?
		this.initPopupListeners(); // TODO VM: do we need it?
	},

	initEvents() {
	},

	initPopupListeners() {
		const subscriptions = [];

		for (const action of ['template.edit', 'proxy.edit', 'item.edit']) {
			subscriptions.push(
				ZABBIX.EventHub.subscribe({
					require: {
						context: CPopupManager.EVENT_CONTEXT,
						event: CPopupManagerEvent.EVENT_OPEN,
						action
					},
					callback: ({event}) => {
						if (!this.isConfirmed()) {
							event.preventDefault();
						}
					}
				})
			);
		}

		subscriptions.push(
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_END_SCRIPTING,
					action: this.overlay.dialogueid
				},
				callback: () => ZABBIX.EventHub.unsubscribeAll(subscriptions)
			})
		);
	},

/
	preprocessFormFields(fields, is_clone) {
		this.trimFields(fields);
		fields.status = fields.status || <?= HOST_STATUS_NOT_MONITORED ?>;

		if (this.form.querySelector('#change_psk')) {
			delete fields.tls_psk_identity;
			delete fields.tls_psk;
		}

		if ('tags' in fields) {
			for (const key in fields.tags) {
				const tag = fields.tags[key];

				if (tag.automatic == <?= ZBX_TAG_AUTOMATIC ?> && !is_clone) {
					delete fields.tags[key];
				}
				else {
					delete tag.automatic;
				}
			}
		}

		return fields;
	},

	trimFields(fields) {
		const fields_to_trim = ['host', 'visiblename', 'description', 'ipmi_username', 'ipmi_password',
			'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk'];
		for (const field of fields_to_trim) {
			if (field in fields) {
				fields[field] = fields[field].trim();
			}
		}

		if ('interfaces' in fields) {
			for (const key in fields.interfaces) {
				const host_interface = fields.interfaces[key];
				host_interface.ip = host_interface.ip.trim();
				host_interface.dns = host_interface.dns.trim();
				host_interface.port = host_interface.port.trim();

				if ('details' in host_interface) {
					const details = host_interface.details;
					details.authpassphrase = details.authpassphrase.trim();
					details.community = details.community.trim();
					details.contextname = details.contextname.trim();
					details.privpassphrase = details.privpassphrase.trim();
					details.securityname = details.securityname.trim();
				}
			}
		}

		if ('macros' in fields) {
			for (const key in fields.macros) {
				const macro = fields.macros[key];
				macro.macro = macro.macro.trim();

				if ('value' in macro) {
					macro.value = macro.value.trim();
				}
				if ('description' in macro) {
					macro.description = macro.description.trim();
				}
			}
		}

		if ('host_inventory' in fields) {
			for (const key in fields.host_inventory) {
				fields.host_inventory[key] = fields.host_inventory[key].trim();
			}
		}

		if ('tags' in fields) {
			for (const key in fields.tags) {
				const tag = fields.tags[key];
				tag.tag = tag.tag.trim();
				tag.value = tag.value.trim();
			}
		}
	},

	isConfirmed() {
		return JSON.stringify(this.initial_form_fields) === JSON.stringify(getFormFields(this.form))
			|| window.confirm(<?= json_encode(_('Any changes made in the current form will be lost.')) ?>);
	},

	submit() {
		this.removePopupMessages();

		const fields = this.preprocessFormFields(getFormFields(this.form), false);
		const curl = new Curl(this.form.getAttribute('action'));

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(this.ajaxExceptionHandler)
			.finally(() => {
				this.overlay.unsetLoading();
			});
	},

	removePopupMessages() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	},

	ajaxExceptionHandler: (exception) => {
		const form = host_edit_popup.form;

		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		form.parentNode.insertBefore(message_box, form);
	}
	*/
}
