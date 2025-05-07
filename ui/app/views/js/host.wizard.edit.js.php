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

	HOST_ENCRYPTION_PSK = 2;

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

	#interface_names_long_titles = {
		[this.INTERFACE_TYPE_AGENT]: <?= json_encode(_('Agent interface')) ?>,
		[this.INTERFACE_TYPE_SNMP]: <?= json_encode(_('Simple Network Management Protocol (SNMP) interface')) ?>,
		[this.INTERFACE_TYPE_IPMI]: <?= json_encode(_('Intelligent Platform Management Interface (IPMI)')) ?>,
		[this.INTERFACE_TYPE_JMX]: <?= json_encode(_('Java Management Extensions (JMX) interface')) ?>
	};

	#interface_names_short_titles = {
		[this.INTERFACE_TYPE_AGENT]: <?= json_encode(_('Agent')) ?>,
		[this.INTERFACE_TYPE_SNMP]: <?= json_encode(_('SNMP')) ?>,
		[this.INTERFACE_TYPE_IPMI]: <?= json_encode(_('IPMI')) ?>,
		[this.INTERFACE_TYPE_JMX]: <?= json_encode(_('JMX')) ?>
	};

	/** @type {Map<number, object>} */
	#templates;
	#linked_templates;

	#source_hostid = null;
	#host = null;

	#selected_templateid = null;
	#template = null;

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

	#csrf_token;

	#data = {
		do_not_show_welcome: 0,
		tls_required: true,
		template_search_query: '',
		template_selected: null,
		data_collection: ZBX_TEMPLATE_DATA_COLLECTION_ANY,
		agent_mode: ZBX_TEMPLATE_AGENT_MODE_ANY,
		show_templates: ZBX_TEMPLATE_SHOW_ANY,
		monitoring_os: 'linux',
		monitoring_os_distribution: 'windows-new',
		interface_required: {},
		interface_default: {
			[this.INTERFACE_TYPE_AGENT]: {
				type: this.INTERFACE_TYPE_AGENT,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_AGENT]
			},
			[this.INTERFACE_TYPE_SNMP]: {
				type: this.INTERFACE_TYPE_SNMP,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_SNMP],
				// the details should not differ from master branch
				details: {
					version: this.SNMP_V2C,
					community: '{$SNMP_COMMUNITY}',
					max_repetitions: 10,
					contextname: '',
					securityname: '',
					securitylevel: this.ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
					authprotocol: this.ITEM_SNMPV3_AUTHPROTOCOL_MD5,
					authpassphrase: '',
					privprotocol: this.ITEM_SNMPV3_PRIVPROTOCOL_DES,
					privpassphrase: '',
					bulk: 1
				}
			},
			[this.INTERFACE_TYPE_IPMI]: {
				type: this.INTERFACE_TYPE_IPMI,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_IPMI]
			},
			[this.INTERFACE_TYPE_JMX]: {
				type: this.INTERFACE_TYPE_JMX,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[this.INTERFACE_TYPE_JMX]
			}
		},
		host: null,
		groups: [],
		templates: [],
		tls_psk: '',
		tls_psk_identity: '',
		ipmi_authtype: -1,
		ipmi_password: '',
		ipmi_privilege: 2,
		ipmi_username: '',
		interfaces: {},
		macros: {}
	}

	#updating_locked = true;

	async init({templates, linked_templates, wizard_show_welcome, source_hostid, csrf_token}) {
		this.#templates = templates.reduce((templates_map, template) => {
			return templates_map.set(template.templateid, template);
		}, new Map());
		this.#linked_templates = linked_templates;
		this.#data.do_not_show_welcome = wizard_show_welcome == 1 ? 0 : 1;
		this.#source_hostid = source_hostid;
		this.#csrf_token = csrf_token;

		this.#initViewTemplates();

		this.#overlay = overlays_stack.getById('host.wizard.edit');
		this.#dialogue = this.#overlay.$dialogue[0];

		this.#data = this.#initReactiveData(this.#data, this.#onFormDataChange.bind(this));

		this.#dialogue.addEventListener('input', this.#onInputChange.bind(this));

		this.#dialogue.addEventListener('click', ({target}) => {
			if (target.classList.contains('js-tls-key-change')) {
				this.#data.tls_required = true;
			}
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
			this.#onBeforeNextStep()
				.finally(() => {
					this.#overlay.setLoading();
					this.#updateStepsQueue();
				})
				.then(() => {
					this.#gotoStep(Math.min(this.#current_step + 1, this.#steps_queue.length - 1));
				})
				.catch(() => {
					this.#gotoStep(this.#current_step);
				});
		});

		this.#create_button = this.#dialogue.querySelector('.js-create');
		this.#create_button.style.display = 'none';
		this.#create_button.addEventListener('click', () => {
			this.#onBeforeNextStep()
				.finally(() => {
					this.#overlay.setLoading();
					this.#updateStepsQueue();
				})
				.then(() => {
					this.#gotoStep(Math.min(this.#current_step + 1, this.#steps_queue.length - 1));
				})
				.catch(() => {
					this.#gotoStep(this.#current_step);
				});
		});

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
			step_add_host_interface_agent: tmpl('host-wizard-step-add-host-interface-agent'),
			step_add_host_interface_snmp: tmpl('host-wizard-step-add-host-interface-snmp'),
			step_add_host_interface_ipmi: tmpl('host-wizard-step-add-host-interface-ipmi'),
			step_add_host_interface_jmx: tmpl('host-wizard-step-add-host-interface-jmx'),
			step_readme: tmpl('host-wizard-step-readme'),
			step_configure_host: tmpl('host-wizard-step-configure-host'),
			step_configuration_finish: tmpl('host-wizard-step-configuration-finish'),
			step_complete: tmpl('host-wizard-step-complete'),

			templates_section: tmpl('host-wizard-templates-section'),
			card: tmpl('host-wizard-template-card'),
			tag: tmpl('host-wizard-template-tag'),
			tags_more: tmpl('host-wizard-template-tags-more'),

			install_agent_readme_linux: tmpl('host-wizard-step-install-agent-os-linux'),
			install_agent_readme_windows_new: tmpl('host-wizard-step-install-agent-os-windows-new'),
			install_agent_readme_windows_old: tmpl('host-wizard-step-install-agent-os-windows-old'),
			install_agent_readme_other: tmpl('host-wizard-step-install-agent-os-other'),

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
		this.#current_step = step;
		this.#updating_locked = true;

		let show_back_button = true;
		let show_next_button = true;
		let show_create_button = false;
		let show_finish_button = false;

		let allow_back_button = this.#current_step > 0;

		switch (this.#steps_queue[step]) {
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

		this.#next_button.removeAttribute('disabled');
		this.#updating_locked = false;

		this.#updateForm();
		this.#updateFields();
		this.#updateProgress();

		const allow_next_button = !this.#next_button.hasAttribute('disabled');

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
		const view = this.#view_templates.step_welcome.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderSelectTemplate() {
		const view = this.#view_templates.step_select_template.evaluateToElement();

		view.querySelector('.js-show-templates').style.display = this.#host !== null ? '' : 'none';

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderCreateHost() {
		const view = this.#view_templates.step_create_host.evaluateToElement({
			template_name: this.#templates.get(this.#data.template_selected)?.name
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);

		const host_ms = jQuery("#host", view).multiSelect().on('change', (_, {options, values}) => {
			this.#setValueByName(this.#data, options.name,
				Object.keys(values.selected).length ? Object.values(values.selected)[0] : null
			);
		});

		if (this.#data.host !== null) {
			host_ms.multiSelect('addData', [this.#data.host]);
		}

		const groups_ms = jQuery("#groups_", view).multiSelect().on('change', (_, {options, values}) => {
			this.#setValueByName(this.#data, options.name, Object.values(values.selected));
		});

		if (this.#data.groups.length) {
			groups_ms.multiSelect('addData', this.#data.groups)
		}
	}

	#renderInstallAgent() {
		const view = this.#view_templates.step_install_agent.evaluateToElement({
			template_name: this.#templates.get(this.#data.template_selected)?.name
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderAddHostInterface() {
		const interface_required = Object.entries(this.#data.interface_required)
			.filter(([_, required]) => required)
			.map(([type]) => Number(type));

		const interfaces_long = [];
		const interfaces_short = [];

		for (const interface_type of interface_required) {
			interfaces_long.push(this.#interface_names_long_titles[Number(interface_type)]);
			interfaces_short.push(this.#interface_names_short_titles[Number(interface_type)]);
		}

		const interface_templates = {
			[this.INTERFACE_TYPE_AGENT]: this.#view_templates.step_add_host_interface_agent,
			[this.INTERFACE_TYPE_IPMI]: this.#view_templates.step_add_host_interface_ipmi,
			[this.INTERFACE_TYPE_SNMP]: this.#view_templates.step_add_host_interface_snmp,
			[this.INTERFACE_TYPE_JMX]: this.#view_templates.step_add_host_interface_jmx
		};

		const view = this.#view_templates.step_add_host_interface.evaluateToElement({
			template_name: this.#templates.get(this.#data.template_selected)?.name,
			host_name: this.#data.host.isNew ? this.#data.host.id : this.#data.host.name,
			interfaces_long: interfaces_long.join(' / '),
			interfaces_short: interfaces_short.join('/')
		});

		interface_required.forEach((interface_type, row_index) => {
			const host_interface = this.#host?.interfaces.find(
				host_interface => Number(host_interface.type) === interface_type
			);

			this.#data.interfaces[row_index] = host_interface || this.#data.interface_default[interface_type];
			view.appendChild(interface_templates[interface_type].evaluateToElement({row_index}));
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderReadme() {
		const view = this.#view_templates.step_readme.evaluateToElement();
		const substep_counter = this.#steps_queue.includes(this.STEP_README)
			&& this.#steps_queue.includes(this.STEP_CONFIGURE_HOST);

		view.querySelector('.sub-step-counter').style.display = substep_counter ? '' : 'none';

		view.querySelector(`.${ZBX_STYLE_MARKDOWN}`).innerHTML = this.#template.readme;

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderConfigureHost() {
		const view = this.#view_templates.step_configure_host.evaluateToElement();
		const substep_counter = this.#steps_queue.includes(this.STEP_README)
			&& this.#steps_queue.includes(this.STEP_CONFIGURE_HOST);
		const macros_list = view.querySelector('.js-host-macro-list');

		view.querySelector('.sub-step-counter').style.display = substep_counter ? '' : 'none';

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

		if (this.#template.templateid !== this.#selected_templateid) {
			this.#selected_templateid = this.#template.templateid;

			this.#data.macros = Object.fromEntries(this.#template.macros.map((template_macro, index) => {
				return [index, {
					type: template_macro.type,
					macro: template_macro.macro,
					value: (this.#host?.macros.find(({macro}) => macro === template_macro.macro))?.value
						|| template_macro.value,
					description: template_macro.description
				}]
			}));
		}

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);

		jQuery(".macro-input-group", view).macroValue();
		jQuery('.input-secret', view).inputSecret();
	}

	#renderConfigurationFinish() {
		const view = this.#view_templates.step_configuration_finish.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderComplete() {
		const view = this.#view_templates.step_complete.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#onBeforeNextStep() {
		switch (this.#steps_queue[this.#current_step]) {
			case this.STEP_WELCOME:
				return this.#disableWelcomeStep();

			case this.STEP_SELECT_TEMPLATE:
				return this.#source_hostid !== null ? this.#loadWizardConfig() : Promise.resolve();

			case this.STEP_CREATE_HOST:
				return this.#loadWizardConfig();

			case this.STEP_CONFIGURATION_FINISH:
				return this.#saveHost();

			default:
				return Promise.resolve();
		}
	}

	#loadWizardConfig() {
		const hostid = this.#source_hostid !== null
			? this.#source_hostid
			: this.#data.host && !this.#data.host.isNew
				? this.#data.host.id
				: null;

		const url_params = objectToSearchParams({
			action: 'host.wizard.get',
			templateid: this.#data.template_selected,
			...(hostid !== null && {hostid})
		});
		const get_url = new URL(`zabbix.php?${url_params}`, location.href);

		return fetch(get_url.href)
			.then(response => response.json())
			.then(response => {
				this.#template = response.template;
				this.#host = response.host;

				if (this.#host !== null) {
					this.#data.host = {
						...this.#data.host,
						id: this.#host.hostid,
						name: this.#host.name,
						isNew: false
					}
				}

				this.#data.tls_required = response.agent_interface_required
					&& (this.#host === null
						|| (this.#host.tls_connect !== this.HOST_ENCRYPTION_PSK && !this.#host.tls_in_psk));

				this.#data.interface_required = {
					[this.INTERFACE_TYPE_AGENT]: response.agent_interface_required,
					[this.INTERFACE_TYPE_IPMI]: response.ipmi_interface_required,
					[this.INTERFACE_TYPE_JMX]: response.jmx_interface_required,
					[this.INTERFACE_TYPE_SNMP]: response.snmp_interface_required
				}
			});
	}

	#saveHost() {
		const submit_url = new URL('zabbix.php', location.href);
		submit_url.searchParams.set('action', this.#data.host.isNew ? 'host.wizard.create' : 'host.wizard.update');

		return fetch(submit_url.href, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				[this.#data.host.isNew ? 'host' : 'hostid']: this.#data.host.id,
				groups: this.#data.groups.map(ms_group => (ms_group.isNew ? {new: ms_group.id} : ms_group.id)),
				templates: [this.#selected_templateid],
				...(this.#data.tls_required && {
					tls_psk: this.#data.tls_psk,
					tls_psk_identity: this.#data.tls_psk_identity
				}),
				interfaces: Object.values(this.#data.interfaces)
					.filter(({type}) => this.#data.interface_required[type]),
				ipmi_authtype: this.#data.ipmi_authtype,
				ipmi_privilege: this.#data.ipmi_privilege,
				ipmi_username: this.#data.ipmi_username,
				ipmi_password: this.#data.ipmi_password,
				macros: Object.values(this.#data.macros),

				[CSRF_TOKEN_NAME]: this.#csrf_token
			})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(exception => {
				this.#ajaxExceptionHandler(exception);

				return Promise.reject();
			});
	}

	#ajaxExceptionHandler(exception) {
		const step_form = this.#dialogue.querySelector('.step-form');

		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		} else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		const message_element = this.#dialogue.querySelector('.msg-bad');

		if (message_element === null) {
			step_form.parentNode.insertBefore(message_box, step_form);
		}
		else {
			message_element.replaceWith(message_element);
		}
	}

	#updateStepsQueue() {
		const template_loaded = this.#template !== null;
		this.#steps_queue = [];

		if (this.#data.do_not_show_welcome === 0) {
			this.#steps_queue.push(this.STEP_WELCOME);
		}

		this.#steps_queue.push(this.STEP_SELECT_TEMPLATE);

		if (this.#source_hostid === null) {
			this.#steps_queue.push(this.STEP_CREATE_HOST);
		}

		if (template_loaded && this.#isRequiredInstallAgent()) {
			this.#steps_queue.push(this.STEP_INSTALL_AGENT);
		}

		if (template_loaded && this.#isRequiredAddHostInterface()) {
			this.#steps_queue.push(this.STEP_ADD_HOST_INTERFACE);
		}

		if (template_loaded && this.#template.readme !== '') {
			this.#steps_queue.push(this.STEP_README);
		}

		if (template_loaded && this.#template.macros.length) {
			this.#steps_queue.push(this.STEP_CONFIGURE_HOST);
		}

		this.#steps_queue.push(this.STEP_CONFIGURATION_FINISH, this.STEP_COMPLETE);
	}

	#updateProgress() {
		const template_loaded = this.#template !== null;
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
				label: t('Selected host'),
				info: '<TODO: Template name>', // TODO VM: update
				visible: this.#source_hostid !== null,
				steps: [this.STEP_WELCOME]
			},
			{
				label: t('Select a template'),
				info: this.#templates.get(this.#data.template_selected)?.name,
				visible: true,
				steps: [this.STEP_SELECT_TEMPLATE]
			},
			{
				label: t('Create or select a host'),
				info: this.#data.host?.name,
				visible: this.#source_hostid === null,
				steps: [this.STEP_CREATE_HOST]
			},
			{
				label: t('Install Zabbix agent'),
				visible: template_loaded && this.#isRequiredInstallAgent(),
				steps: [this.STEP_INSTALL_AGENT]
			},
			{
				label: t('Add host interface'),
				visible: template_loaded && this.#isRequiredAddHostInterface(),
				steps: [this.STEP_ADD_HOST_INTERFACE]
			},
			{
				label: t('Configure host'),
				visible: template_loaded,
				steps: [this.STEP_README, this.STEP_CONFIGURE_HOST, this.STEP_CONFIGURATION_FINISH]
			},
			{
				label: t('A few more steps'),
				visible: !template_loaded,
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
		switch (this.#steps_queue[this.#current_step]) {
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

				if (!path || path === 'template_selected') {
					if (this.#data.template_selected !== null) {
						this.#updateProgress();
					}

					this.#next_button.toggleAttribute('disabled', this.#data.template_selected === null);

				}
				break;

			case this.STEP_CREATE_HOST:
				const is_host_new = this.#data.host?.isNew || false;

				if (!path || path === 'host' || path === 'groups') {
					if (this.#data.host !== null) {
						this.#updateProgress();
					}

					this.#dialogue.querySelector('.js-host-groups-label')
						.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, is_host_new)

					this.#next_button.toggleAttribute('disabled',
						this.#data.host === null || (is_host_new && !this.#data.groups.length)
					);
				}

				break;

			case this.STEP_INSTALL_AGENT:
				if (!path || path === 'tls_psk_identity' || path === 'tls_psk') {
					this.#next_button.toggleAttribute('disabled', this.#data.tls_required
						&& (this.#data.tls_psk_identity.trim() === '' || this.#data.tls_psk.trim() === '')
					);
				}

				if (!path && this.#data.tls_psk.trim() === '' && this.#data.tls_required) {
					this.#data.tls_psk = this.#generatePSK();
				}

				for (const element of this.#dialogue.querySelectorAll('.js-tls-exists')) {
					element.style.display = !this.#data.tls_required ? '' : 'none';
				}

				for (const element of this.#dialogue.querySelectorAll('.js-tls-input')) {
					element.style.display = this.#data.tls_required ? '' : 'none';
				}

				if (!path || path === 'monitoring_os' || path === 'monitoring_os_distribution'
						|| path === 'tls_psk_identity' || path === 'tls_psk') {
					const windows_distribution_select = this.#dialogue.querySelector('.js-windows-distribution-select');
					windows_distribution_select.style.display = this.#data.monitoring_os === 'windows' ? '' : 'none';

					const readme_template = (() => {
						switch (this.#data.monitoring_os) {
							case 'linux':
								return this.#view_templates.install_agent_readme_linux;
							case 'windows':
								return this.#data.monitoring_os_distribution === 'windows-new'
									? this.#view_templates.install_agent_readme_windows_new
									: this.#view_templates.install_agent_readme_windows_old;
							default:
								return this.#view_templates.install_agent_readme_other;
						}
					})();

					const option_prefix = this.#data.monitoring_os === 'linux' ? '--' : '-';
					let psk = this.#data.tls_psk_identity !== ''
						? option_prefix + 'psk-identity ' + this.#data.tls_psk_identity
						: option_prefix + 'psk-identity-stdin';

					psk += ' ';
					psk += this.#data.tls_psk !== ''
						? option_prefix + 'psk ' + this.#data.tls_psk
						: option_prefix + 'psk-stdin';

					this.#dialogue.querySelector('.js-install-agent-readme').innerHTML = readme_template.evaluate({
						psk: psk
					});
				}
				break;

			case this.STEP_ADD_HOST_INTERFACE:
				const interfaces_view = this.#dialogue.querySelectorAll('.js-host-interface');

				for (const [row_index, field] of Object.entries(this.#data.interfaces)) {
					if (field.type === this.INTERFACE_TYPE_SNMP) {
						const visible_fields = {
							'js-snmp-community': false,
							'js-snmp-repetition-count': false,
							'js-snmpv3-contextname': false,
							'js-snmpv3-securityname': false,
							'js-snmpv3-securitylevel': false,
							'js-snmpv3-authprotocol': false,
							'js-snmpv3-authpassphrase': false,
							'js-snmpv3-privprotocol': false,
							'js-snmpv3-privpassphrase': false
						};

						switch (Number(field.details.version)) {
							case this.SNMP_V1:
								visible_fields['js-snmp-community'] = true;
								break;

							case this.SNMP_V2C:
								visible_fields['js-snmp-community'] = true;
								visible_fields['js-snmp-repetition-count'] = true;
								break;

							case this.SNMP_V3:
								visible_fields['js-snmpv3-contextname'] = true;
								visible_fields['js-snmpv3-securityname'] = true;
								visible_fields['js-snmpv3-securitylevel'] = true;
								visible_fields['js-snmp-repetition-count'] = true;

								switch (Number(field.details.securitylevel)) {
									case this.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV:
										visible_fields['js-snmpv3-authprotocol'] = true;
										visible_fields['js-snmpv3-authpassphrase'] = true;
										break;

									case this.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV:
										visible_fields['js-snmpv3-authprotocol'] = true;
										visible_fields['js-snmpv3-authpassphrase'] = true;
										visible_fields['js-snmpv3-privprotocol'] = true;
										visible_fields['js-snmpv3-privpassphrase'] = true;
										break;
								}
								break;
						}

						for (const [selector, is_visible] of Object.entries(visible_fields)) {
							interfaces_view[row_index].querySelector(`.${selector}`).style.display = is_visible
								? ''
								: 'none';
						}
					}
				}
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

	#isRequiredInstallAgent() {
		return this.#data.interface_required[this.INTERFACE_TYPE_AGENT]
			&& (!this.#host?.interfaces.some(({interface_type}) => interface_type === this.INTERFACE_TYPE_AGENT));
	}

	#isRequiredAddHostInterface() {
		return Object.entries(this.#data.interface_required).some(([required_type, is_required]) => {
			return is_required
				&& !this.#host?.interfaces.some(({interface_type}) => interface_type === required_type);
		});
	}

	#generatePSK() {
		const array = new Uint8Array(32);
		window.crypto.getRandomValues(array);

		return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
	}

	#makeCardListSections() {
		let template_classes = Array.from(this.#templates)
			.filter(([_, template]) => {
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
				let found = false;

				for (const { tag, value } of tags) {
					if (tag === 'class') {
						map.set(value, [...(map.get(value) || []), templateid]);
						found = true;
					}
				}

				if (!found) {
					map.set('other', [...(map.get('other') || []), templateid]);
				}

				return map;
			}, new Map());

		template_classes = new Map([...template_classes.entries()].sort((a, b) => {
			if (a[0] === 'other') return 1;
			if (b[0] === 'other') return -1;

			return a[0].localeCompare(b[0]);
		}));

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

		const tag = ({tag, value}) => {
			const tag_value = [tag, value].filter(val => val !== '').join(': ');

			return this.#view_templates.tag.evaluateToElement({tag_value, hint_tag_value: escapeHtml(tag_value)});
		}

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
			const tag_element = tag(template.tags[i]);
			temp_tag_list.appendChild(tag_element);

			if (temp_tag_list.scrollHeight > temp_tag_list.clientHeight) {
				temp_tag_list.removeChild(tag_element);
				all_fits = false;
				break;
			}
		}

		if (!all_fits) {
			temp_tag_list.appendChild(this.#view_templates.tags_more.evaluateToElement({
				tag_values: template.tags.map(tag_value => tag(tag_value).outerHTML).join('')
			}));

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

	#makeMacroFieldText({type, macro, config}, row_index) {
		switch (type) {
			case this.MACRO_TYPE_SECRET:
				return this.#view_templates.macro_field_secret.evaluateToElement({
					row_index,
					label: config.label,
					macro
				});
			case this.MACRO_TYPE_VAULT:
				return this.#view_templates.macro_field_vault.evaluateToElement({
					row_index,
					label: config.label,
					macro
				});
			default:
				return this.#view_templates.macro_field_text.evaluateToElement({
					row_index,
					label: config.label,
					macro
				});
		}
	}

	#makeMacroFieldList(macro_entry, row_index) {
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
		return this.#view_templates.macro_field_checkbox.evaluateToElement({
			row_index,
			label: macro.config.label,
			macro: macro.macro,
			value: macro.config.options[0].checked,
			unchecked_value: macro.config.options[0].unchecked
		});
	}

	#disableWelcomeStep() {
		if (this.#data.do_not_show_welcome === 0) {
			return Promise.resolve();
		}

		updateUserProfile('web.host.wizard.show.welcome', 0, []);

		return Promise.reject();
	}

	#onFormDataChange(path, new_value, old_value) {
		if (this.#updating_locked) {
			return;
		}

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

		this.#setValueByName(this.#data, target.name, value);
	}

	#initReactiveData(target_object, on_change_callback) {
		const proxy_cache = new WeakMap();

		const createProxy = (obj, path = []) => {
			if (proxy_cache.has(obj)) {
				return proxy_cache.get(obj);
			}

			const proxy = new Proxy(obj, {
				get(target, property, receiver) {
					const value = Reflect.get(target, property, receiver);

					if (typeof value === 'object' && value !== null) {
						if (proxy_cache.has(value)) {
							return proxy_cache.get(value);
						}

						return createProxy(value, [...path, property]);
					}

					return value;
				},
				set(target, property, value, receiver) {
					const old_value = target[property];
					const result = Reflect.set(target, property, value, receiver);

					if (old_value !== value) {
						on_change_callback([...path, String(property)].join('.'), value, old_value);
					}

					return result;
				}
			});

			proxy_cache.set(obj, proxy);

			return proxy;
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
