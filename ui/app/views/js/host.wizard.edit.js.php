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
	#template_tag;
	#template_tag_more;

	#form_data;

	#form_default_data = {
		template_search_query: '',
		data_collection: ZBX_TEMPLATE_DATA_COLLECTION_ANY,
		agent_mode: ZBX_TEMPLATE_AGENT_MODE_ANY,
		show_templates: ZBX_TEMPLATE_SHOW_ANY,
		tls_psk: '',
		tls_psk_identity: '',
		monitoring_os: -1,
		monitoring_os_distribution: -1
	};

	#sections_expanded = new Map();

	init({templates, linked_templates, wizard_hide_welcome}) {
		this.#initViewTemplates();

		this.#templates = templates.reduce((templates_map, template) => {
			return templates_map.set(template.templateid, template);
		}, new Map());
		this.#linked_templates = linked_templates;
		this.#first_step = wizard_hide_welcome ? this.STEP_SELECT_TEMPLATE : this.STEP_WELCOME;
		this.#last_step = this.STEP_INSTALL_AGENT;

		this.#overlay = overlays_stack.getById('host.wizard.edit');
		this.#dialogue = this.#overlay.$dialogue[0];

		this.#form_data = this.#initReactiveData(this.#form_default_data, (property, value) => {
			this.#updateForm(property, value);
		});

		this.#dialogue.addEventListener('input', ({target}) => {
			if (target.name in this.#form_data) {
				this.#form_data[target.name] = target.value;
			}
		});

		this.#dialogue.addEventListener('click', ({target}) => {
			if (target.classList.contains('js-generate-pre-shared-key')) {
				this.#form_data.tls_psk = this.#generatePSK();
			}
		});

		this.#back_button = this.#dialogue.querySelector('.js-back');
		this.#back_button.addEventListener('click', () => this.#gotoBackStep());

		this.#next_button = this.#dialogue.querySelector('.js-next');
		this.#next_button.addEventListener('click', () => this.#gotoNextStep());

		this.#gotoStep(this.STEP_SELECT_TEMPLATE);
		//this.#gotoStep(this.#first_step);
	}

	#initViewTemplates() {
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
		this.#template_tag = new Template(
			document.getElementById('host-wizard-template-tag').innerHTML
		);
		this.#template_tag_more = new Template(
			document.getElementById('host-wizard-template-tag-more').innerHTML
		);
	}

	#gotoBackStep() {
		this.#gotoStep(Math.max(this.#first_step, this.#current_step - 1));
	}

	#gotoNextStep() {
		this.#gotoStep(Math.min(this.#last_step, this.#current_step + 1));
	}

	#gotoStep(step) {
		this.#current_step = step;

		switch (this.#current_step) {
			case this.STEP_WELCOME:
				this.#renderWelcome();
				break;
			case this.STEP_SELECT_TEMPLATE:
				this.#renderSelectTemplate();
				break;
			case this.STEP_CREATE_HOST:
				this.#renderCreateHost();
				break;
			case this.STEP_INSTALL_AGENT:
				this.#renderInstallAgent()
		}

		this.#back_button.toggleAttribute('disabled', this.#current_step === this.#first_step);
		this.#next_button.toggleAttribute('disabled', this.#current_step === this.#last_step);

		this.#updateForm();

		this.#overlay.unsetLoading();
	}

	#renderWelcome() {
		const step = this.#template_step_welcome.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderSelectTemplate() {
		const step = this.#template_step_select_template.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderCreateHost() {
		const step = this.#template_step_create_host.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);

		jQuery("#hostid, #groups_", step).multiSelect();
	}

	#renderInstallAgent() {
		const step = this.#template_step_install_agent.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#updateForm(property, value) {
		if (property !== undefined) {
			this.#updateField(property, value);
		}
		else {
			for (const [property, value] of Object.entries(this.#form_data)) {
				this.#updateField(property, value);
			}
		}

		switch (this.#current_step) {
			case this.STEP_WELCOME:
				this.#renderWelcome();
				break;
			case this.STEP_SELECT_TEMPLATE:
				const step_body = document.querySelector('.js-templates');

				step_body.innerHTML = '';
				for (const section of this.#getCardListSections()) {
					step_body.appendChild(section);
				}
				break;
			case this.STEP_CREATE_HOST:
				this.#renderCreateHost();
				break;
			case this.STEP_INSTALL_AGENT:
				const windows_distribution_select = this.#dialogue.querySelector('.js-windows-distribution-select');

				if (windows_distribution_select !== null) {
					windows_distribution_select.style.display = this.#form_data.monitoring_os === 'windows' ? '' : 'none';
				}
				break;
		}
	}

	#updateField(property, value) {
		const field = this.#dialogue.querySelector(`[name="${property}"]`);

		if (field === null) {
			return;
		}

		switch (field.type) {
			case 'checkbox':
				field.checked = Boolean(value);
				break;
			case 'radio':
				for (const radio of this.#dialogue.querySelectorAll(`[name="${property}"]`)) {
					radio.checked = radio.value == value;
				}
				break;
			default:
				field.value = value;
		}
	}

	#initReactiveData(initial_data, on_change_callback) {
		return new Proxy(initial_data, {
			set(target, property, value) {
				if (target[property] !== value) {
					target[property] = value;
					on_change_callback(property, value);
				}

				return true;
			}
		});
	}

	#generatePSK() {
		const array = new Uint8Array(32);
		window.crypto.getRandomValues(array);

		return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
	}

	#getCardListSections() {
		let template_classes = Array.from(this.#templates)
			.filter(([templateid, template]) => {
				if (this.#form_data.data_collection != ZBX_TEMPLATE_DATA_COLLECTION_ANY
						&& !template.data_collection.includes(Number(this.#form_data.data_collection))) {
					return false;
				}

				if (this.#form_data.agent_mode != ZBX_TEMPLATE_AGENT_MODE_ANY
						&& !template.agent_mode.includes(Number(this.#form_data.agent_mode))) {
					return false;
				}

				if (this.#form_data.template_search_query.trim() !== '') {
					return template.name.toLowerCase().includes(this.#form_data.template_search_query)
						|| template.description.toLowerCase().includes(this.#form_data.template_search_query)
						|| template.tags.some(({tag, value}) => {
							return tag.toLowerCase().includes(this.#form_data.template_search_query)
								|| value.toLowerCase().includes(this.#form_data.template_search_query);
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

		const sections = [];

		for (const [title, templateids] of template_classes) {
			const section = this.#template_templates_section.evaluateToElement({
				title: title.charAt(0).toUpperCase() + title.slice(1),
				count: templateids.length
			});

			const expanded = this.#sections_expanded.size === 0 || !!this.#sections_expanded.get(title);
			this.#sections_expanded.set(title, expanded);

			if (!expanded && !section.classList.contains(ZBX_STYLE_COLLAPSED)) {
				toggleSection(section.querySelector('.toggle'));
			}

			const card_list = section.querySelector('.templates-card-list');

			for (const templateid of templateids) {
				const card = this.#makeCard(this.#templates.get(templateid));

				card_list.appendChild(card)
			}

			section.addEventListener('expand', () => this.#sections_expanded.set(title, true));
			section.addEventListener('collapse', () => this.#sections_expanded.set(title, false));

			sections.push(section);
		}

		return sections;
	}

	#makeCard(template) {
		const card = this.#template_card.evaluateToElement({
			title: template.name
		});

		const tags_list = card.querySelector(`.${ZBX_STYLE_TAGS_LIST}`);

		/**
		 * @type {HTMLDivElement}
		 */
		const temp_tag_list = tags_list.cloneNode(false);

		temp_tag_list.style.visibility = 'hidden';
		temp_tag_list.style.position = 'absolute';
		temp_tag_list.style.width = '195px';
		temp_tag_list.style.maxHeight = '40px';
		temp_tag_list.style.pointerEvents = 'none';
		temp_tag_list.style.zIndex = '-1';

		document.body.appendChild(temp_tag_list);

		let all_fits = true;

		for (let i = 0; i < template.tags.length; i++) {
			const tag_element = this.#template_tag.evaluateToElement(template.tags[i]);

			temp_tag_list.appendChild(tag_element);

			if (temp_tag_list.scrollHeight > temp_tag_list.clientHeight) {
				temp_tag_list.removeChild(tag_element);
				all_fits = false;
				break;
			}
		}

		if (!all_fits) {
			temp_tag_list.appendChild(this.#template_tag_more.evaluateToElement());

			if (temp_tag_list.scrollHeight > temp_tag_list.clientHeight) {
				const tags = temp_tag_list.querySelectorAll(`.${ZBX_STYLE_TAG}`);

				temp_tag_list.removeChild(tags[tags.length - 1]);
			}
		}

		tags_list.innerHTML = temp_tag_list.innerHTML;

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
