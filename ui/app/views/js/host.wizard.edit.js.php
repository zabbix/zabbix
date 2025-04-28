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
	STEP_ADD_HOST_INTERFACE = 4;
	STEP_README = 5;
	STEP_CONFIGURE_HOST = 6;
	STEP_CONFIGURATION_FINISH = 7;
	STEP_COMPLETE = 8;

	TEMPLATE_DATA_COLLECTION_ANY = -1;
	TEMPLATE_DATA_COLLECTION_AGENT_BASED = 0;
	TEMPLATE_DATA_COLLECTION_AGENTLESS = 1;

	TEMPLATE_AGENT_MODE_ANY = -1;
	TEMPLATE_AGENT_MODE_ACTIVE = 0;
	TEMPLATE_AGENT_MODE_PASSIVE = 0;

	TEMPLATE_SHOW_ANY = -1;
	TEMPLATE_SHOW_LINKED = 0;
	TEMPLATE_SHOW_NOT_LINKED = 1;

	INTERFACE_TYPE_AGENT = 1;
	INTERFACE_TYPE_SNMP = 2;
	INTERFACE_TYPE_IPMI = 3;
	INTERFACE_TYPE_JMX = 4;

	SNMP_V1 = 1;
	SNMP_V2C = 2;
	SNMP_V3 = 3;

	SNMP_BULK_ENABLED = 1;

	INTERFACE_SECONDARY = 0;
	INTERFACE_PRIMARY = 1;
	INTERFACE_USE_IP = 1;

	ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV = 0
	ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV = 1;
	ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV = 2;
	ITEM_SNMPV3_AUTHPROTOCOL_MD5 = 0;
	ITEM_SNMPV3_PRIVPROTOCOL_DES = 0;

	DEFAULT_PORTS = {
		[this.INTERFACE_TYPE_AGENT]: 10050,
		[this.INTERFACE_TYPE_SNMP]: 161,
		[this.INTERFACE_TYPE_IPMI]: 623,
		[this.INTERFACE_TYPE_JMX]: 12345
	}

	DISCOVERY_STATE_MANUAL = 0x3;

	MACRO_TYPE_TEXT = 'TEXT';
	MACRO_TYPE_SECRET = 'SECRET_TEXT';
	MACRO_TYPE_VAULT = 'VAULT';

	WIZARD_FIELD_NOCONF = 0;
	WIZARD_FIELD_TEXT = 1;
	WIZARD_FIELD_LIST = 2;
	WIZARD_FIELD_CHECKBOX = 3;

	#view_templates;

	/** @type {Map<number, object>} */
	#templates;
	#linked_templates;

	#templateid;
	#template;
	#host;

	/** @type {number} */
	#current_step = 0;

	/** @type {Array} */
	#steps_queue;

	/** @type {Overlay} */
	#overlay;

	/** @type {HTMLDivElement} */
	#dialogue;

	/** @type {HTMLButtonElement} */
	#back_button;

	/** @type {HTMLButtonElement} */
	#next_button;

	/** @type {HTMLButtonElement} */
	#create_button;

	/** @type {HTMLButtonElement} */
	#finish_button;

	#sections_expanded = new Map();

	#data = {
		do_not_show_welcome: 0,
		template_search_query: '',
		template_selected: null,
		data_collection: ZBX_TEMPLATE_DATA_COLLECTION_ANY,
		agent_mode: ZBX_TEMPLATE_AGENT_MODE_ANY,
		show_templates: ZBX_TEMPLATE_SHOW_ANY,
		monitoring_os: 'linux',
		monitoring_os_distribution: -1,
		agent_install_required: false,
		interface_required: {
			[this.INTERFACE_TYPE_AGENT]: false,
			[this.INTERFACE_TYPE_SNMP]: false,
			[this.INTERFACE_TYPE_IPMI]: false,
			[this.INTERFACE_TYPE_JMX]: false
		},

		hostid: null,
		groups: [],
		templates: [],
		tls_psk: '',
		tls_psk_identity: '',
		ipmi_authtype: 0,
		ipmi_password: 'psswd',
		ipmi_privilege: 4,
		ipmi_username: 'username',
		interfaces: {
			[this.INTERFACE_TYPE_AGENT]: {
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_AGENT],
				type: this.INTERFACE_TYPE_AGENT
			},
			[this.INTERFACE_TYPE_SNMP]: {
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_SNMP],
				type: this.INTERFACE_TYPE_SNMP,
				// the details should not differ from master branch
				details: {
					version: this.SNMP_V3,
					community: '',
					max_repetitions: 10,
					contextname: 'context-name',
					securityname: 'sec-name',
					securitylevel: 2,
					authprotocol: 0,
					authpassphrase: 'auth-passphr',
					privprotocol: 0,
					privpassphrase: 'priv-passpht',
					bulk: 1
				}
			},
			[this.INTERFACE_TYPE_IPMI]: {
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_IPMI],
				type: this.INTERFACE_TYPE_IPMI
			},
			[this.INTERFACE_TYPE_JMX]: {
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_JMX],
				type: this.INTERFACE_TYPE_JMX
			}
		},

		macros: {}
	}

	async init({templates, linked_templates, wizard_hide_welcome}) {
		this.#templates = templates.reduce((templates_map, template) => {
			return templates_map.set(template.templateid, template);
		}, new Map());
		this.#linked_templates = linked_templates;

		this.#data.do_not_show_welcome = wizard_hide_welcome;

		this.#initViewTemplates();

		this.#overlay = overlays_stack.getById('host.wizard.edit');
		this.#dialogue = this.#overlay.$dialogue[0];

		this.#data = this.#initReactiveData(this.#data, this.#onFormDataChange.bind(this));

		this.#dialogue.addEventListener('input', this.#onInputChange.bind(this));

		this.#dialogue.addEventListener('click', ({target}) => {
			if (target.classList.contains('js-generate-pre-shared-key')) {
				this.#data.tls_psk = this.#generatePSK();
			}
		});

		this.#back_button = this.#dialogue.querySelector('.js-back');
		this.#back_button.addEventListener('click', () => {
			this.#gotoStep(Math.max(this.#current_step - 1, 0));
		});

		this.#next_button = this.#dialogue.querySelector('.js-next');
		this.#next_button.addEventListener('click', () => {
			this.#onBeforeNextStep().then(() => {
				this.#updateStepsQueue();
				this.#gotoStep(Math.min(this.#current_step + 1, this.#steps_queue.length - 1));
			});
		});

		this.#create_button = this.#dialogue.querySelector('.js-create');
		this.#create_button.style.display = 'none';

		this.#finish_button = this.#dialogue.querySelector('.js-finish');
		this.#finish_button.style.display = 'none';

		this.#updateStepsQueue();
		this.#gotoStep(this.#current_step);
	}

	#initViewTemplates() {
		const tmpl = (id) => (new Template(document.getElementById(id).innerHTML));

		this.#view_templates = {
			step_welcome: tmpl('host-wizard-step-welcome'),
			step_select_template: tmpl('host-wizard-step-select-template'),
			step_create_host: tmpl('host-wizard-step-create-host'),
			step_install_agent: tmpl('host-wizard-step-install-agent'),
			step_add_host_interface: tmpl('host-wizard-step-add-host-interface'),
			step_readme: tmpl('host-wizard-step-readme'),
			step_configure_host: tmpl('host-wizard-step-configure-host'),
			step_configuration_finish: tmpl('host-wizard-step-configuration-finish'),
			step_complete: tmpl('host-wizard-step-complete'),

			templates_section: tmpl('host-wizard-templates-section'),
			card: tmpl('host-wizard-template-card'),

			macro_field_checkbox: tmpl('host-wizard-macro-field-checkbox'),
			macro_field_select: tmpl('host-wizard-macro-field-select'),
			macro_field_radio: tmpl('host-wizard-macro-field-radio'),
			macro_field_text: tmpl('host-wizard-macro-field-text'),
			macro_field_secret: tmpl('host-wizard-macro-field-secret'),
			macro_field_vault: tmpl('host-wizard-macro-field-vault'),

			progress: new Template(`
				<div class="progress"></div>
			`),
			progress_step: new Template(`
				<div class="progress-step">#{label}</div>
			`),
			progress_step_info: new Template(`
				<div class="progress-info">#{info}</div>
			`),
			tag: new Template(`
				<span class="${ZBX_STYLE_TAG}">#{tag}: #{value}</span>
			`),
			tag_more: new Template(`
				<button type="button" class="${ZBX_STYLE_BTN_ICON} ${ZBX_ICON_MORE}"></button>
			`),
			description: new Template(`
				<div class="${ZBX_STYLE_FORM_DESCRIPTION} ${ZBX_STYLE_MARKDOWN}">#{description}</div>
			`),
			radio_item: new Template(`
				<li>
					<input type="radio" id="#{id}" name="#{name}" value="#{value}">
					<label for="#{id}">#{label}</label>
				</li>
			`)
		}
	}

	#gotoStep(step) {
		console.log('GoTo to step:', step, this.#steps_queue[step])

		this.#overlay.setLoading();

		if (this.#current_step === this.STEP_WELCOME && this.#data.do_not_show_welcome) {
			this.#disableWelcomeStep();
		}

		this.#current_step = step;

		let show_back_button = true;
		let show_next_button = true;
		let show_create_button = false;
		let show_finish_button = false;

		let allow_back_button = this.#current_step > 0;
		let allow_next_button = true;

		switch (this.#steps_queue[step]) {
			case this.STEP_WELCOME:
				this.#renderWelcome();
				break;
			case this.STEP_SELECT_TEMPLATE:
				allow_next_button = this.#data.template_selected !== null;
				this.#renderSelectTemplate();
				break;
			case this.STEP_CREATE_HOST:
				allow_next_button = this.#data.hostid !== null;
				this.#renderCreateHost();
				break;
			case this.STEP_INSTALL_AGENT:
				this.#renderInstallAgent();
				break;
			case this.STEP_ADD_HOST_INTERFACE:
				this.#renderAddHostInterface();
				break;
			case this.STEP_README:
				this.#renderReadme();
				break;
			case this.STEP_CONFIGURE_HOST:
				this.#renderConfigureHost();
				break;
			case this.STEP_CONFIGURATION_FINISH:
				this.#renderConfigurationFinish();

				show_next_button = false;
				show_create_button = true;
				break;
			case this.STEP_COMPLETE:
				this.#renderComplete();

				show_back_button = false;
				show_next_button = false;
				show_finish_button = true;
				break;
		}

		this.#updateForm();
		this.#updateFields();
		this.#updateProgress();

		setTimeout(() => {
			this.#overlay.unsetLoading();

			this.#back_button.style.display = show_back_button ? '' : 'none';
			this.#next_button.style.display = show_next_button ? '' : 'none';
			this.#create_button.style.display = show_create_button ? '' : 'none';
			this.#finish_button.style.display = show_finish_button ? '' : 'none';

			this.#back_button.toggleAttribute('disabled', !allow_back_button);
			this.#next_button.toggleAttribute('disabled', !allow_next_button);
		});
	}

	#renderWelcome() {
		const step = this.#view_templates.step_welcome.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderSelectTemplate() {
		const step = this.#view_templates.step_select_template.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderCreateHost() {
		const step = this.#view_templates.step_create_host.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);

		const hostid_ms = jQuery("#hostid", step).multiSelect().on('change', (_, {options, values}) => {
			this.#setValueByName(this.#data, options.name,
				Object.keys(values.selected).length ? Object.values(values.selected)[0] : null
			);
		});

		if (this.#data.hostid !== null) {
			hostid_ms.multiSelect('addData', [this.#data.hostid]);
		}

		const groups_ms = jQuery("#groups_", step).multiSelect().on('change', (_, {options, values}) => {
			this.#setValueByName(this.#data, options.name, Object.values(values.selected));
		});

		if (this.#data.groups.length) {
			groups_ms.multiSelect('addData', this.#data.groups)
		}
	}

	#renderInstallAgent() {
		const step = this.#view_templates.step_install_agent.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderAddHostInterface() {
		const step = this.#view_templates.step_add_host_interface.evaluateToElement();

		for (const [interface_type, required] of Object.entries(this.#data.interface_required)) {
			console.log(required, step.querySelector(`.js-host-interface-${interface_type}`))
			step.querySelector(`.js-host-interface-${interface_type}`).style.display = required ? '' : 'none';
		}

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderReadme() {
		const step = this.#view_templates.step_readme.evaluateToElement();

		step.querySelector(`.${ZBX_STYLE_MARKDOWN}`).innerHTML = this.#template.readme;

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderConfigureHost() {
		const step = this.#view_templates.step_configure_host.evaluateToElement();
		const macros_list = step.querySelector('.js-host-macro-list');

		this.#template.macros.forEach((macro, row_index) => {
			const {field, description} = this.#makeMacroField(macro, row_index);

			if (field !== null) {
				macros_list.appendChild(field);

				if (description !== null) {
					description.classList.add(ZBX_STYLE_GRID_COLUMN_LAST);
					macros_list.appendChild(description);
				}
			}
		});

		if (this.#template.templateid !== this.#templateid) {
			this.#templateid = this.#template.templateid;

			this.#data.macros = Object.fromEntries(
				this.#template.macros.map((macro, index) => [index, {
					type: macro.type,
					macro: macro.macro,
					value: macro.value,
					description: macro.description,
					discovery_state: this.DISCOVERY_STATE_MANUAL
				}])
			);
		}

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);

		jQuery(".macro-input-group", step).macroValue();
		jQuery('.input-secret', step).inputSecret();
	}

	#renderConfigurationFinish() {
		const step = this.#view_templates.step_configuration_finish.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#renderComplete() {
		const step = this.#view_templates.step_complete.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(step);
	}

	#onBeforeNextStep() {

		console.log(this.#data.hostid)

		switch (this.#steps_queue[this.#current_step]) {
			case this.STEP_CREATE_HOST:
				const host = this.#data.hostid;

				return this.#loadWizardConfig({
					templateid: this.#data.template_selected,
					hostid: host && !host.isNew ? host.id : null
				});

			default:
				return Promise.resolve();
		}
	}

	#loadWizardConfig({templateid, hostid}) {
		const url_params = objectToSearchParams({
			action: 'host.wizard.get',
			templateid,
			...(hostid !== null && {hostid})
		})
		const get_url = new URL(`zabbix.php?${url_params}`, location.href);

		return fetch(get_url.href)
			.then(response => response.json())
			.then(response => {
				this.#data.agent_install_required = response.agent_install_required;

				this.#data.interface_required = {
					[this.INTERFACE_TYPE_AGENT]: response.agent_interface_required,
					[this.INTERFACE_TYPE_SNMP]: response.snmp_interface_required,
					[this.INTERFACE_TYPE_IPMI]: response.ipmi_interface_required,
					[this.INTERFACE_TYPE_JMX]: response.jmx_interface_required
				};

				this.#template = response.template;
				this.#host = response.host;
			});
	}

	#updateStepsQueue() {
		this.#steps_queue = [];

		if (!this.#data.do_not_show_welcome) {
			this.#steps_queue.push(this.STEP_WELCOME);
		}

		this.#steps_queue.push(this.STEP_SELECT_TEMPLATE, this.STEP_CREATE_HOST);

		if (this.#data.agent_install_required) {
			this.#steps_queue.push(this.STEP_INSTALL_AGENT);
		}

		if (Object.values(this.#data.interface_required).some(required => required)) {
			this.#steps_queue.push(this.STEP_ADD_HOST_INTERFACE);
		}

		if (this.#template?.readme) {
			this.#steps_queue.push(this.STEP_README);
		}

		console.log(this.#template);

		if (Object.keys(this.#template?.macros || {}).length) {
			this.#steps_queue.push(this.STEP_CONFIGURE_HOST);
		}

		this.#steps_queue.push(this.STEP_CONFIGURATION_FINISH, this.STEP_COMPLETE);

		console.log('Steps queue:', this.#steps_queue)
	}

	#updateProgress() {
		let progress = this.#dialogue.querySelector(`.${ZBX_STYLE_OVERLAY_DIALOGUE_HEADER} .progress`);

		if (this.#steps_queue[this.#current_step] === this.STEP_WELCOME) {
			if (progress !== null) {
				progress.remove();
			}

			return;
		}

		if (progress === null) {
			progress = this.#view_templates.progress.evaluateToElement();
		}
		else {
			progress.innerHTML = '';
		}

		const progress_labels = [
			{
				label: t('Select a template'),
				info: this.#template?.name,
				visible: true,
				steps: [this.STEP_SELECT_TEMPLATE]
			},
			{
				label: t('Create or select a host'),
				info: this.#data.hostid?.name,
				visible: true,
				steps: [this.STEP_CREATE_HOST]
			},
			{
				label: t('Install Zabbix agent'),
				visible: this.#data.agent_install_required,
				steps: [this.STEP_INSTALL_AGENT]
			},
			{
				label: t('Add host interface'),
				visible: this.#host && Object.values(this.#data.interface_required).some(required => required),
				steps: [this.STEP_ADD_HOST_INTERFACE]
			},
			{
				label: t('Configure host'),
				visible: this.#host,
				steps: [this.STEP_README, this.STEP_CONFIGURE_HOST, this.STEP_CONFIGURATION_FINISH]
			},
			{
				label: t('A few more steps'),
				visible: !this.#host,
				steps: []
			}
		];

		for (const {label, info, steps} of progress_labels.filter(({visible}) => visible)) {
			const progress_step = progress.appendChild(
				this.#view_templates.progress_step.evaluateToElement({label: label + (info ? ':' : '')})
			);

			if (info !== undefined) {
				progress_step.appendChild(
					this.#view_templates.progress_step_info.evaluateToElement({info})
				);
			}

			progress_step.classList.toggle('progress-step-complete',
				steps.length && Math.max(...steps) < this.#steps_queue[this.#current_step]
			);
			progress_step.classList.toggle('progress-step-current',
				steps.includes(this.#steps_queue[this.#current_step])
			);
			progress_step.classList.toggle('progress-step-disabled', !steps.length);
		}

		this.#dialogue.querySelector(`.${ZBX_STYLE_OVERLAY_DIALOGUE_HEADER}`).appendChild(progress);
	}

	#updateForm(path, new_value, old_value) {
		switch (this.#current_step) {
			case this.STEP_WELCOME:
				break;

			case this.STEP_SELECT_TEMPLATE:
				if (!path
					|| ['template_search_query', 'data_collection', 'agent_mode', 'show_templates'].includes(path)
				) {
					const step_body = document.querySelector('.js-templates');
					step_body.innerHTML = '';

					for (const section of this.#makeCardListSections()) {
						step_body.appendChild(section);
					}
				}

				if (path === 'template_selected') {
					this.#next_button.toggleAttribute('disabled', new_value === null);
				}
				break;

			case this.STEP_CREATE_HOST:
				if (path === 'hostid') {
					this.#next_button.toggleAttribute('disabled', new_value === null);
				}
				break;

			case this.STEP_INSTALL_AGENT:
				const windows_distribution_select = this.#dialogue.querySelector('.js-windows-distribution-select');

				if (windows_distribution_select !== null) {
					windows_distribution_select.style.display = this.#data.monitoring_os === 'windows' ? '' : 'none';
				}
				break;

			case this.STEP_CONFIGURE_HOST:
				break;
		}
	}

	#updateFields() {
		for (const {name, value} of this.#getInputsData(this.#data)) {
			this.#updateField(name, value);
		}
	}

	#updateField(name, value) {
		const field = this.#dialogue.querySelector(`[name="${name}"]`);

		if (field === null) {
			return;
		}

		switch (field.type) {
			case 'checkbox':
				field.checked = field.value == value;
				break;
			case 'radio':
				for (const radio of this.#dialogue.querySelectorAll(`[name="${name}"]`)) {
					radio.checked = radio.value == value;
				}
				break;
			default:
				field.value = value;
		}
	}

	#generatePSK() {
		const array = new Uint8Array(32);
		window.crypto.getRandomValues(array);

		return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
	}

	#makeCardListSections() {
		let template_classes = Array.from(this.#templates)
			.filter(([templateid, template]) => {
				if (this.#data.data_collection != ZBX_TEMPLATE_DATA_COLLECTION_ANY
						&& !template.data_collection.includes(Number(this.#data.data_collection))) {
					return false;
				}

				if (this.#data.agent_mode != ZBX_TEMPLATE_AGENT_MODE_ANY
						&& !template.agent_mode.includes(Number(this.#data.agent_mode))) {
					return false;
				}

				const query = this.#data.template_search_query.toLowerCase();

				if (this.#data.template_search_query.trim() !== '') {
					return template.name.toLowerCase().includes(query)
						|| template.description.toLowerCase().includes(query)
						|| template.tags.some(({tag, value}) => {
							return tag.toLowerCase().includes(query)
								|| value.toLowerCase().includes(query);
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
			const section = this.#view_templates.templates_section.evaluateToElement({
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
				card_list.appendChild(
					this.#makeCard(this.#templates.get(templateid), templateid === this.#data.template_selected)
				);
			}

			section.addEventListener('expand', () => this.#sections_expanded.set(title, true));
			section.addEventListener('collapse', () => this.#sections_expanded.set(title, false));

			sections.push(section);
		}

		return sections;
	}

	#makeCard(template, checked) {
		const card = this.#view_templates.card.evaluateToElement({
			...template,
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
			const tag_element = this.#view_templates.tag.evaluateToElement(template.tags[i]);

			temp_tag_list.appendChild(tag_element);

			if (temp_tag_list.scrollHeight > temp_tag_list.clientHeight) {
				temp_tag_list.removeChild(tag_element);
				all_fits = false;
				break;
			}
		}

		if (!all_fits) {
			temp_tag_list.appendChild(this.#view_templates.tag_more.evaluateToElement());

			if (temp_tag_list.scrollHeight > temp_tag_list.clientHeight) {
				const tags = temp_tag_list.querySelectorAll(`.${ZBX_STYLE_TAG}`);

				temp_tag_list.removeChild(tags[tags.length - 1]);
			}
		}

		tags_list.innerHTML = temp_tag_list.innerHTML;
		temp_tag_list.remove();

		card.querySelector('.js-template-info-collapse').style.display = 'none';
		card.querySelector('input[type=radio').checked = checked;

		return card;
	}

	#makeMacroField(macro, row_index) {
		const field_view = (() => {
			switch (Number(macro.config.type)) {
				case this.WIZARD_FIELD_TEXT:
					return this.#makeMacroFieldText(macro, row_index);
				case this.WIZARD_FIELD_LIST:
					return this.#makeMacroFieldList(macro, row_index);
				case this.WIZARD_FIELD_CHECKBOX:
					return this.#makeMacroFieldCheckbox(macro, row_index);
				default:
					return null;
			}
		})();

		const description_view = macro.config.description
			? this.#view_templates.description.evaluateToElement({
				description: macro.config.description
			})
			: null;

		return {field: field_view, description: description_view};
	}

	#makeMacroFieldText(macro, row_index) {
		console.log('#makeMacroFieldText', row_index, macro);

		switch (macro.type) {
			case this.MACRO_TYPE_SECRET:
				return this.#view_templates.macro_field_secret.evaluateToElement({
					index: row_index,
					label: macro.config.label,
					macro: macro.macro
				});
			case this.MACRO_TYPE_VAULT:
				return this.#view_templates.macro_field_vault.evaluateToElement({
					index: row_index,
					label: macro.config.label,
					macro: macro.macro
				});
			default:
				return this.#view_templates.macro_field_text.evaluateToElement({
					index: row_index,
					label: macro.config.label,
					macro: macro.macro
				});
		}
	}

	#makeMacroFieldList(macro_entry, row_index) {
		console.log('#makeMacroFieldList', macro_entry);

		const { label, options } = macro_entry.config;
		const { macro, value } = macro_entry;

		if (options.length > 5) {
			const field_select = this.#view_templates.macro_field_select.evaluateToElement({
				index: row_index,
				label,
				macro,
				value
			});
			const select = field_select.querySelector('z-select');

			select.setAttribute('data-options', JSON.stringify(
				options.map(option => ({label: option.text, value: option.value}))
			));

			return field_select;
		}
		else {
			const field_radio = this.#view_templates.macro_field_radio.evaluateToElement({label, macro, value});
			const radio_list = field_radio.querySelector('.radio-list-control');

			radio_list.innerHTML = '';

			options.forEach((option, index) => {
				const radio = this.#view_templates.radio_item.evaluateToElement({
					id: `macros_${row_index}_value_${index}`,
					name: `macros[${row_index}][value]`,
					label: option.text,
					value: option.value
				});

				radio_list.appendChild(radio);
			});

			return field_radio;
		}
	}

	#makeMacroFieldCheckbox(macro, row_index) {
		console.log('#makeMacroFieldCheckbox', row_index, macro)

		return this.#view_templates.macro_field_checkbox.evaluateToElement({
			index: row_index,
			label: macro.config.label,
			macro: macro.macro,
			value: macro.config.options[0].checked,
			unchecked_value: macro.config.options[0].unchecked
		});
	}

	#disableWelcomeStep() {
		// TODO call profile update
		console.log('Update profile: disable welcome step')
	}

	#onFormDataChange(path, new_value, old_value) {
		console.log('Data changed', {path, new_value, old_value}, this.#data);

		this.#updateField(this.#pathToInputName(path), new_value);
		this.#updateForm(path, new_value, old_value);
	}

	#onInputChange({target}) {
		if (!target.name) {
			return;
		}

		const value = target.type === 'checkbox'
			? (target.checked ? target.value : target.getAttribute('unchecked-value'))
			: target.value;

		console.log(`Input "${target.name}" changed: `, value);

		this.#setValueByName(this.#data, target.name, value);
	}

	#initReactiveData(target_object, on_change_callback) {
		const createProxy = (obj, path = []) => {
			return new Proxy(obj, {
				get(target, property, receiver) {
					const value = Reflect.get(target, property, receiver);
					if (typeof value === 'object' && value !== null) {
						return createProxy(value, [...path, property]);
					}
					return value;
				},
				set(target, property, value, receiver) {
					const old_value = target[property];
					const result = Reflect.set(target, property, value, receiver);

					if (old_value !== value) {
						on_change_callback([...path, property].join('.'), value, old_value);
					}

					return result;
				}
			});
		};

		return createProxy(target_object);
	}

	#getInputsData(data, parent_key = '') {
		return Object.entries(data).reduce((fields, [key, value]) => {
			const full_key = parent_key ? `${parent_key}[${key}]` : key;

			if (Array.isArray(value)) {
				return fields.concat(
					value.flatMap((item, index) =>
						(item && typeof item === 'object')
							? this.#getInputsData(item, `${full_key}[${index}]`)
							: { name: `${full_key}[${index}]`, value: item }
					)
				);
			}

			if (value !== null && typeof value === 'object') {
				return fields.concat(this.#getInputsData(value, full_key));
			}

			return fields.concat({ name: full_key, value });
		}, []);
	}

	#setValueByName(data, name, new_value) {
		const path = this.#parseInputName(name);
		let current = data;

		for (let i = 0; i < path.length - 1; i++) {
			if (current[path[i]] === undefined) {
				current[path[i]] = typeof path[i+1] === 'number' ? [] : {};
			}
			current = current[path[i]];
		}
		current[path[path.length - 1]] = new_value;
	}

	#parseInputName(name) {
		const parts = [];

		name.replace(/\[([^\]]*)\]/g, '.$1').split('.').forEach(part => {
			if (part !== '') {
				parts.push(isNaN(part) ? part : Number(part));
			}
		});

		return parts;
	}

	#pathToInputName(path) {
		const [first, ...rest] = path.split('.');

		return first + rest.map(p => `[${p}]`).join('');
	}
}
