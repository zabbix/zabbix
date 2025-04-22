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

window.host_wizard_edit = new class {

	STEP_WELCOME = 0;
	STEP_SELECT_TEMPLATE = 1;
	STEP_CREATE_HOST = 2;
	STEP_INSTALL_AGENT = 3;

	/**
	 * @type {Map<number, object>}
	 */
	#templates;
	#linked_templates;

	#current_step;
	#first_step;
	#last_step;

	/**
	 * @type {Overlay}
	 */
	#overlay;
	#dialogue;

	/**
	 * @type {HTMLButtonElement}
	 */
	#back_button;

	/**
	 * @type {HTMLButtonElement}
	 */
	#next_button;

	#template_step_welcome;
	#template_step_select_template;
	#template_step_create_host;
	#template_step_install_agent;

	#template_templates_section;
	#template_card;

	#form = {
		search_query: ''
	}

	init({templates, linked_templates, wizard_hide_welcome}) {
		this.#templates = templates.reduce((templates_map, template) => {
			return templates_map.set(template.templateid, template);
		}, new Map());

		this.#linked_templates = linked_templates;
		this.#first_step = wizard_hide_welcome ? this.STEP_SELECT_TEMPLATE : this.STEP_WELCOME;
		this.#last_step = this.STEP_INSTALL_AGENT;

		this.#template_step_welcome = new Template(
			document.getElementById('host-wizard-step-welcome').innerHTML
		);
		this.#template_step_select_template = new Template(
			document.getElementById('host-wizard-step-select-template').innerHTML
		);
		this.#template_step_create_host = new Template(
			document.getElementById('host-wizard-step-create-host').innerHTML
		);
		this.#template_step_install_agent = new Template(
			document.getElementById('host-wizard-step-install-agent').innerHTML
		);

		this.#template_templates_section = new Template(
			document.getElementById('host-wizard-templates-section').innerHTML
		);
		this.#template_card = new Template(
			document.getElementById('host-wizard-template-card').innerHTML
		);

		this.#overlay = overlays_stack.getById('host.wizard.edit');
		this.#dialogue = this.#overlay.$dialogue[0];

		this.#back_button = this.#dialogue.querySelector('.js-back');
		this.#back_button.addEventListener('click', () => this.#gotoBackStep());

		this.#next_button = this.#dialogue.querySelector('.js-next');
		this.#next_button.addEventListener('click', () => this.#gotoNextStep());

		this.#gotoStep(this.STEP_INSTALL_AGENT);
		//this.#gotoStep(this.#first_step);
		this.#updateForm();
	}

	#gotoBackStep() {
		this.#gotoStep(Math.max(this.#first_step, this.#current_step - 1));
	}

	#gotoNextStep() {
		this.#gotoStep(Math.min(this.#last_step, this.#current_step + 1));
	}

	#gotoStep(step) {
		this.#current_step = step;

		const step_render = {
			[this.STEP_WELCOME]: () => this.#renderWelcome(),
			[this.STEP_SELECT_TEMPLATE]: () => this.#renderSelectTemplate(),
			[this.STEP_CREATE_HOST]: () => this.#renderCreateHost(),
			[this.STEP_INSTALL_AGENT]: () => this.#renderInstallAgent()
		}[this.#current_step];

		if (step_render !== undefined) {
			this.#dialogue.querySelector('.step-form-body').replaceWith(step_render());
		}

		this.#overlay.unsetLoading();

		this.#back_button.toggleAttribute('disabled', this.#current_step === this.#first_step);
		this.#next_button.toggleAttribute('disabled', this.#current_step === this.#last_step);
	}

	#renderWelcome() {
		return this.#template_step_welcome.evaluateToElement();
	}

	#renderSelectTemplate() {
		let template_classes = Array.from(this.#templates)
			.filter(([templateid, template]) => {
				if (this.#form.search_query.trim() !== '') {
					return template.name.toLowerCase().includes(this.#form.search_query)
						|| template.description.toLowerCase().includes(this.#form.search_query)
						|| template.tags.some(({tag, value}) => {
							return tag.toLowerCase().includes(this.#form.search_query)
								|| value.toLowerCase().includes(this.#form.search_query);
						});
				}

				return true;
			})
			.reduce((map, [templateid, {tags}]) => {
				for (const {tag, value} of tags) {
					if (tag === 'class') {
						map.set(value, [...(map.get(value) || []), templateid]);
					}
				}

				return map
			}, new Map());

		template_classes = new Map([...template_classes.entries()].sort((a, b) => a[0].localeCompare(b[0])));

		const step = this.#template_step_select_template.evaluateToElement();
		const step_body = step.querySelector('#host-wizard-templates');

		let is_first = true;

		for (const [title, templateids] of template_classes) {
			const section = this.#template_templates_section.evaluateToElement({
				title: title.charAt(0).toUpperCase() + title.slice(1),
				count: templateids.length
			});

			if (is_first) {
				is_first = false;
			}
			else {
				section.classList.add('collapsed');
			}

			const card_list = section.querySelector('.templates-card-list');

			for (const templateid of templateids) {
				const card = this.#makeCard(this.#templates.get(templateid));

				card_list.appendChild(card)
			}

			step_body.appendChild(section);
		}

		return step;
	}

	#renderCreateHost() {
		return this.#template_step_create_host.evaluateToElement();
	}

	#renderInstallAgent() {
		return this.#template_step_install_agent.evaluateToElement();
	}

	#updateForm() {

	}

	#makeCard(template) {
		const card = this.#template_card.evaluateToElement({
			title: template.name
		});

		card.querySelector('.js-template-info-collapse').style.display = 'none';

		return card;
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
