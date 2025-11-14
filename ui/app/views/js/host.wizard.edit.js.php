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

	DEFAULT_PORTS = {
		[<?= INTERFACE_TYPE_AGENT ?>]: 10050,
		[<?= INTERFACE_TYPE_SNMP ?>]: 161,
		[<?= INTERFACE_TYPE_IPMI ?>]: 623,
		[<?= INTERFACE_TYPE_JMX ?>]: 12345
	}

	#view_templates;

	#filter_hints = {
		'data_collection_1': <?= json_encode(_('Data is collected by Zabbix agent, a lightweight software component installed on your monitoring target.')) ?>,
		'data_collection_2': <?= json_encode(_('Data is collected by Zabbix server or proxy using standard protocols (e.g., SNMP, ICMP) or remote access methods (e.g., SSH).')) ?>,
		'agent_mode_1': <?= json_encode(_('Zabbix agent initiates connections to Zabbix server or proxy to send data. Recommended for monitoring targets behind a firewall.')) ?>,
		'agent_mode_2': <?= json_encode(_('Zabbix server or proxy initiates connections to Zabbix agent to request data. Recommended for networks without a firewall or with open firewall ports.')) ?>
	}

	#interface_names_long_titles = {
		[<?= INTERFACE_TYPE_AGENT ?>]: <?= json_encode(_('Agent interface')) ?>,
		[<?= INTERFACE_TYPE_SNMP ?>]: <?= json_encode(_('Simple Network Management Protocol (SNMP) interface')) ?>,
		[<?= INTERFACE_TYPE_IPMI ?>]: <?= json_encode(_('Intelligent Platform Management Interface (IPMI)')) ?>,
		[<?= INTERFACE_TYPE_JMX ?>]: <?= json_encode(_('Java Management Extensions (JMX) interface')) ?>
	}

	#interface_names_short_titles = {
		[<?= INTERFACE_TYPE_AGENT ?>]: <?= json_encode(_('Agent')) ?>,
		[<?= INTERFACE_TYPE_SNMP ?>]: <?= json_encode(_('SNMP')) ?>,
		[<?= INTERFACE_TYPE_IPMI ?>]: <?= json_encode(_('IPMI')) ?>,
		[<?= INTERFACE_TYPE_JMX ?>]: <?= json_encode(_('JMX')) ?>
	}

	/** @type {Map<number, object>} */
	#templates;
	#linked_templates;

	#source_host = null;
	#host = null;

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

	#show_cancel_screen = false;

	#sections_collapsed = new Set();
	#template_cards = new Map();

	#csrf_token;

	#data = {
		do_not_show_welcome: 0,
		tls_required: true,
		tls_warning: false,
		install_agent_required: true,
		template_search_query: '',
		selected_template: null,
		selected_subclasses: {},
		show_info_by_template: null,
		data_collection: <?= ZBX_TEMPLATE_DATA_COLLECTION_ANY ?>,
		agent_mode: <?= ZBX_TEMPLATE_AGENT_MODE_ANY ?>,
		show_templates: <?= ZBX_TEMPLATE_SHOW_LINKED ?>,
		monitoring_os: 'linux',
		monitoring_os_distribution: 'windows-new',
		interface_required: [],
		interface_default: {
			[<?= INTERFACE_TYPE_AGENT ?>]: {
				type: <?= INTERFACE_TYPE_AGENT ?>,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[<?= INTERFACE_TYPE_AGENT ?>]
			},
			[<?= INTERFACE_TYPE_SNMP ?>]: {
				type: <?= INTERFACE_TYPE_SNMP ?>,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[<?= INTERFACE_TYPE_SNMP ?>],
				// the details should not differ from master branch
				details: {
					version: <?= SNMP_V2C ?>,
					community: '{$SNMP_COMMUNITY}',
					max_repetitions: 10,
					contextname: '',
					securityname: '',
					securitylevel: <?= ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV ?>,
					authprotocol: <?= ITEM_SNMPV3_AUTHPROTOCOL_MD5 ?>,
					authpassphrase: '',
					privprotocol: <?= ITEM_SNMPV3_PRIVPROTOCOL_DES ?>,
					privpassphrase: '',
					bulk: 1
				}
			},
			[<?= INTERFACE_TYPE_IPMI ?>]: {
				type: <?= INTERFACE_TYPE_IPMI ?>,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[<?= INTERFACE_TYPE_IPMI ?>]
			},
			[<?= INTERFACE_TYPE_JMX ?>]: {
				type: <?= INTERFACE_TYPE_JMX ?>,
				address: '127.0.0.1',
				port: this.DEFAULT_PORTS[<?= INTERFACE_TYPE_JMX ?>]
			}
		},
		host: null,
		host_new: null,
		groups: [],
		groups_new: [],
		templates: [],
		agent_script_server_host: null,
		tls_psk: null,
		tls_psk_identity: null,
		ipmi_authtype: null,
		ipmi_password: null,
		ipmi_privilege: null,
		ipmi_username: null,
		interfaces: {},
		macros: {}
	}

	#validation_rules = {
		[this.STEP_CREATE_HOST]: {
			host: {
				type: 'object',
				asterisk: true,
				required: () => this.#data.host_new === null
			},
			host_new: {
				base_path: 'host',
				type: 'object',
				fields: {
					id: {
						regex: /^<?= ZBX_PREG_HOST_FORMAT ?>$/,
						maxlength: <?= DB::getFieldLength('hosts', 'host') ?>
					}
				}
			},
			groups: {
				type: 'array',
				asterisk: () => this.#data.host_new !== null,
				required: () => this.#data.host_new !== null && !this.#data.groups_new.length
			},
			groups_new: {
				base_path: 'groups',
				type: 'array',
				required: () => this.#data.host_new !== null && !this.#data.groups.length,
				fields: {
					id: {
						regex: /^(?!\/)(?!.*\/\/)(?!.*\/$).+$/,
						maxlength: <?= DB::getFieldLength('hstgrp', 'name') ?>
					}
				}
			}
		},
		[this.STEP_INSTALL_AGENT]: {
			agent_script_server_host: {
				regex: /^[^`']*$/
			},
			tls_psk: {
				required: () => this.#data.tls_required,
				minlength: 32,
				maxlength: <?= DB::getFieldLength('hosts', 'tls_psk') ?>,
				regex: /^([a-fA-F0-9]{2})+$/
			},
			tls_psk_identity: {
				required: () => this.#data.tls_required,
				maxlength: <?= DB::getFieldLength('hosts', 'tls_psk_identity') ?>,
				regex: /^<?= ZBX_PREG_PSK_IDENTITY_FORMAT ?>$/
			}
		},
		[this.STEP_ADD_HOST_INTERFACE]: {},
		[this.STEP_CONFIGURE_HOST]: {}
	}

	#validation_errors = {
		[this.STEP_CREATE_HOST]: {},
		[this.STEP_INSTALL_AGENT]: {},
		[this.STEP_ADD_HOST_INTERFACE]: {},
		[this.STEP_CONFIGURE_HOST]: {}
	}

	#macro_reset_list = {};

	#data_update_locked = false;
	#form_update_locked = false;
	#pending_form_update = false;

	async init({templates, linked_templates, wizard_show_welcome, source_host, agent_script_server_host, csrf_token}) {
		this.#templates = templates.reduce((templates_map, template) => {
			return templates_map.set(template.templateid, template);
		}, new Map());
		this.#linked_templates = linked_templates;
		this.#data.do_not_show_welcome = wizard_show_welcome === 1 ? 0 : 1;
		this.#source_host = source_host;
		this.#csrf_token = csrf_token;
		this.#data.agent_script_server_host = agent_script_server_host;

		this.#initViewTemplates();

		this.#overlay = overlays_stack.getById('host.wizard.edit');
		this.#dialogue = this.#overlay.$dialogue[0];

		this.#data = this.#initReactiveData(this.#data, this.#onFormDataChange.bind(this));

		this.#dialogue.addEventListener('input', this.#onInputChange.bind(this));
		this.#dialogue.addEventListener('focusout', this.#onInputBlur.bind(this));
		this.#dialogue.addEventListener('mousedown', () => this.#form_update_locked = true);
		this.#dialogue.addEventListener('mouseup', () => {
			this.#form_update_locked = false;

			if (this.#pending_form_update) {
				this.#pending_form_update = false;
				this.#updateForm(null);
			}
		});

		this.#dialogue.addEventListener('dialogue.cancel', () => {
			if (this.#getCurrentStep() === this.STEP_COMPLETE) {
				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: {
					redirect_latest: false
				}}));
			}
			else {
				if (this.#show_cancel_screen) {
					this.#show_cancel_screen = false;

					this.#gotoStep(this.#current_step);
				}
				else {
					this.#show_cancel_screen = true;

					this.#renderCancelScreen();
				}
			}
		});

		this.#dialogue.addEventListener('click', ({target}) => {
			if (target.classList.contains('js-tls-key-change')) {
				this.#data.tls_required = true;
				this.#data.tls_psk_identity = this.#generatePSKIdentity();
			}

			if (target.classList.contains('js-generate-pre-shared-key')) {
				this.#data.tls_psk = this.#generatePSK();
			}

			if (target.classList.contains('js-template-info-toggle')) {
				this.#data.show_info_by_template = this.#data.show_info_by_template !== target.template
					? target.template
					: null;
			}

			if (target.classList.contains('js-cancel')) {
				if (this.#data.selected_template === null) {
					overlayDialogueDestroy(this.#overlay.dialogueid, Overlay.prototype.CLOSE_BY_USER);
				}

				this.#show_cancel_screen = true;

				this.#renderCancelScreen();
			}

			if (target.classList.contains('js-cancel-no')) {
				this.#show_cancel_screen = false;

				this.#gotoStep(this.#current_step);
			}

			if (target.classList.contains('js-cancel-yes')) {
				overlayDialogueDestroy(this.#overlay.dialogueid, Overlay.prototype.CLOSE_BY_USER);
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
					if (this.#getCurrentStep() !== this.STEP_COMPLETE) {
						this.#gotoStep(Math.min(this.#current_step + 1, this.#steps_queue.length - 1));
					}
					else {
						if (this.#data.host_new !== null) {
							const return_url = new URL('zabbix.php', location.href);

							return_url.searchParams.set('action', 'latest.view');
							return_url.searchParams.set('hostids[]', this.#data.host_new.id);
							return_url.searchParams.set('filter_set', '1');

							ZABBIX.PopupManager.setReturnUrl(return_url.href);
						}

						overlayDialogueDestroy(this.#overlay.dialogueid);

						this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: {
							redirect_latest: this.#data.host_new !== null,
							hostid: this.#data.host_new?.id
						}}));
					}
				})
				.catch(() => {
					this.#gotoStep(this.#current_step);
				});
		});

		const return_url = new URL('zabbix.php', location.href);

		return_url.searchParams.set('action', 'host.list');

		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.#updateStepsQueue();
		this.#gotoStep(this.#current_step);
	}

	#initViewTemplates() {
		const tmpl = id => new Template(document.getElementById(id).innerHTML);

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
			step_configure_host_macros_section: tmpl('host-wizard-step-configure-host-macros-section'),
			step_configure_host_macros_collapsible_section: tmpl('host-wizard-step-configure-host-macros-collapsible-section'),
			step_configuration_finish: tmpl('host-wizard-step-configuration-finish'),
			step_complete: tmpl('host-wizard-step-complete'),
			cancel_screen: tmpl('host-wizard-cancel-screen'),

			templates_section: tmpl('host-wizard-templates-section'),
			templates_section_no_found: tmpl('host-wizard-templates-section-no-found'),
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
			macro_field_description: new Template(`
				<div class="${ZBX_STYLE_FORM_DESCRIPTION}">
					<div class="${ZBX_STYLE_FORM_FIELDS_HINT}">#{macro}</div>
				</div>
			`),

			progress: new Template(`
				<div class="progress"></div>
			`),
			progress_step: new Template(`
				<div class="progress-step">#{label}</div>
			`),
			progress_step_info: new Template(`
				<div class="progress-info" title="#{info}">#{info}</div>
			`),
			subclass_filter_item: new Template(`
				<button type="button" class="template-subfilter-item">#{label}</button>
			`),
			warning: new Template(`
				<span class="warning zi-triangle-warning">#{message}</span>
			`),
			error: new Template(`
				<span class="error">#{message}</span>
			`),
			markdown: new Template(`
				<div class="${ZBX_STYLE_MARKDOWN}">#{text}</div>
			`),
			radio_item: new Template(`
				<li>
					<input type="radio" id="#{id}" name="#{name}" value="#{value}" />
					<label for="#{id}" title="#{label}">#{label}</label>
				</li>
			`),
			template_info: new Template(`
				<div class="template-info-item">
					<span class="title">#{title}:</span>
					<span class="content">#{content}</span>
				</div>
			`)
		}
	}

	#gotoStep(queue_index) {
		this.#current_step = queue_index;
		this.#data_update_locked = true;

		switch (this.#getCurrentStep()) {
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
				break;

			case this.STEP_COMPLETE:
				this.#renderComplete();
				break;
		}

		this.#data_update_locked = false;

		this.#validateStep();
		this.#updateFields();
		this.#updateForm();
		this.#updateFieldsAsterisk();
		this.#updateProgress();
		this.#updateDialogButton();

		requestAnimationFrame(() => {
			const next_button_disabled = this.#next_button.hasAttribute('disabled');

			this.#overlay.unsetLoading();

			this.#next_button.toggleAttribute('disabled', next_button_disabled);

			const field_with_error = this.#dialogue.querySelector('.field-has-error');

			if (field_with_error !== null) {
				field_with_error.scrollIntoView({block: 'center', behavior: 'auto'});
			}
			else {
				this.#dialogue.querySelector('.overlay-dialogue-body').scrollTop = 0;
			}
		});
	}

	#getCurrentStep() {
		return this.#steps_queue[this.#current_step];
	}

	#renderWelcome() {
		const view = this.#view_templates.step_welcome.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderSelectTemplate() {
		const view = this.#view_templates.step_select_template.evaluateToElement();

		for (const [id, hint] of Object.entries(this.#filter_hints)) {
			const label = view.querySelector(`label[for="${id}"]`);

			if (label !== null) {
				label.setAttribute('title', hint);
			}
		}

		view.querySelector('.js-show-templates')
			.style.display = this.#source_host !== null && this.#linked_templates.length ? '' : 'none';

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderCreateHost() {
		const view = this.#view_templates.step_create_host.evaluateToElement({
			template_name: this.#getSelectedTemplate()?.name
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);

		const host_ms = jQuery('#host', view).multiSelect().on('change', (e, detail) => {
			if (!detail) {
				return;
			}

			const value = Object.keys(detail.values.selected).length ? Object.values(detail.values.selected)[0] : null;

			this.#setValueByName(this.#data, detail.options.name, value?.isNew ? null : value);
			this.#setValueByName(this.#data, detail.options.newItemName, value?.isNew ? value : null);
		});

		if (this.#data.host !== null) {
			host_ms.multiSelect('addData', [this.#data.host]);
		}

		if (this.#data.host_new !== null) {
			host_ms.multiSelect('addData', [this.#data.host_new]);
		}

		const groups_ms = jQuery('#groups_', view).multiSelect().on('change', (_, detail) => {
			if (!detail) {
				return;
			}

			const {groups, groups_new} = Object.values(detail.values.selected).reduce((result, value) => {
				if (value.isNew) {
					result.groups_new.push(value);
				}
				else {
					result.groups.push(value);
				}
				return result;
			}, {groups: [], groups_new: []});

			this.#setValueByName(this.#data, detail.options.name, groups);
			this.#setValueByName(this.#data, detail.options.newItemName, groups_new);
		});

		groups_ms.multiSelect('addData', [...this.#data.groups, ...this.#data.groups_new]);

		for (const [path, multiselect] of Object.entries({host: host_ms[0], groups: groups_ms[0]})) {
			const wrapper = multiselect.closest('.<?= CMultiSelect::ZBX_STYLE_CLASS ?>');

			multiselect.setAttribute('data-name', path);

			wrapper.addEventListener('focusout', () => {
				setTimeout(() => {
					let overlay = overlays_stack.end()?.element;
					overlay = overlay instanceof jQuery ? overlay[0] : overlay;

					if (!wrapper.contains(document.activeElement) && !wrapper.contains(overlay)) {
						this.#activateFieldValidation(path);

						const error = this.#validateField(path);
						const alias_error = this.#validateFieldAliases(path);

						if (error !== null || alias_error !== null) {
							this.#updateForm(path);
						}
					}
				});
			});
		}
	}

	#renderInstallAgent() {
		const view = this.#view_templates.step_install_agent.evaluateToElement({
			template_name: this.#getSelectedTemplate()?.name
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderAddHostInterface() {
		const interfaces_long = [];
		const interfaces_short = [];

		for (const interface_type of this.#data.interface_required) {
			interfaces_long.push(this.#interface_names_long_titles[interface_type]);
			interfaces_short.push(this.#interface_names_short_titles[interface_type]);
		}

		const interface_templates = {
			[<?= INTERFACE_TYPE_AGENT ?>]: this.#view_templates.step_add_host_interface_agent,
			[<?= INTERFACE_TYPE_IPMI ?>]: this.#view_templates.step_add_host_interface_ipmi,
			[<?= INTERFACE_TYPE_SNMP ?>]: this.#view_templates.step_add_host_interface_snmp,
			[<?= INTERFACE_TYPE_JMX ?>]: this.#view_templates.step_add_host_interface_jmx
		};

		const view = this.#view_templates.step_add_host_interface.evaluateToElement({
			template_name: this.#getSelectedTemplate()?.name,
			host_name: this.#getVisibleHostName(),
			interfaces_long: interfaces_long.join(' / '),
			interfaces_short: interfaces_short.join('/')
		});

		this.#data.interface_required.forEach((interface_type, row_index) => {
			view.appendChild(interface_templates[interface_type].evaluateToElement({row_index}));
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderReadme() {
		const view = this.#view_templates.step_readme.evaluateToElement({
			template_name: this.#getSelectedTemplate()?.name
		});
		const substep_counter = this.#steps_queue.includes(this.STEP_README)
			&& this.#steps_queue.includes(this.STEP_CONFIGURE_HOST);

		view.querySelector('.sub-step-counter').style.display = substep_counter ? '' : 'none';

		view.querySelector('.js-readme').appendChild(this.#makeMarkdown(this.#template.readme));

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderConfigureHost() {
		const view = this.#view_templates.step_configure_host.evaluateToElement();
		const substep_counter = this.#steps_queue.includes(this.STEP_README)
			&& this.#steps_queue.includes(this.STEP_CONFIGURE_HOST);

		const sections = new Map([['', this.#view_templates.step_configure_host_macros_section.evaluateToElement()]]);

		view.querySelector('.sub-step-counter').style.display = substep_counter ? '' : 'none';

		Object.entries(this.#data.macros).forEach(([row_index, macro]) => {
			const {config} = this.#template.macros.find(({macro: macro_object}) => macro_object === macro.macro);
			const {field, description} = this.#makeMacroField(macro, config, row_index);

			if (!sections.has(config.section_name)) {
				sections.set(config.section_name,
					this.#view_templates.step_configure_host_macros_collapsible_section.evaluateToElement({
						section_name: config.section_name
					})
				);
			}

			const section = sections.get(config.section_name);
			const macros_list = section.querySelector('.host-macro-list');

			if (field !== null) {
				macros_list.appendChild(field);

				if (description !== null) {
					description.classList.add(ZBX_STYLE_GRID_COLUMN_LAST);
					macros_list.appendChild(description);
				}
			}
		});

		sections.forEach(section => {
			view.appendChild(section)
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);

		for (const path in this.#macro_reset_list) {
			this.#updateFieldMessages(this.#pathToInputName(path), 'warning',
				[<?= json_encode(_('The value does not match the existing value on the selected host.')) ?>]
			);
		}

		Object.entries(this.#data.macros).forEach(([row_index, macro]) => {
			const {type: template_macro_type} = this.#template.macros.find(
				({macro: template_macro}) => template_macro === macro.macro
			);

			const source_macro = this.#host?.macros.find(({macro: host_macro}) => host_macro === macro.macro);

			const $macro_field = $(`[name="macros[${row_index}][value]"]`)
				.closest('.<?= CMacroValue::ZBX_STYLE_MACRO_INPUT_GROUP ?>')
				.macroValue();

			const $secret_input = $('.input-secret', $macro_field).inputSecret();

			if (macro.value !== undefined) {
				$secret_input.inputSecret('activateInput');
			}

			const $undo_button = $('.btn-undo', $macro_field);

			if (source_macro === undefined || Number(template_macro_type) !== <?= ZBX_MACRO_TYPE_SECRET ?>) {
				$undo_button.remove();
			}

			if (Number(template_macro_type) === <?= ZBX_MACRO_TYPE_SECRET ?>) {
				$undo_button.on('click', () => this.#data.macros[row_index].value = undefined);

				if (macro.value === undefined) {
					$undo_button.hide();
				}
			}
		});
	}

	#renderConfigurationFinish() {
		const view = this.#view_templates.step_configuration_finish.evaluateToElement({
			button_label: this.#data.host_new !== null ? <?= json_encode(_('Create')) ?> : <?= json_encode(_('Update')) ?>
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderComplete() {
		const view = this.#view_templates.step_complete.evaluateToElement({
			complete_message: this.#data.host_new !== null
				? sprintf(<?= json_encode(_('Click Finish to navigate to the Latest data section and view the most recent data for your host (%1$s).')) ?>, this.#data.host_new.name)
				: <?= json_encode(_('Click Finish to close the Host wizard.')) ?>
		});

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);
	}

	#renderCancelScreen() {
		const view = this.#view_templates.cancel_screen.evaluateToElement();

		this.#dialogue.querySelector('.step-form-body').replaceWith(view);

		this.#updateProgress();
		this.#updateDialogButton();

		requestAnimationFrame(() => {
			this.#overlay.containFocus();
			this.#dialogue.querySelector('.js-cancel-yes').focus();
		});
	}

	#onBeforeNextStep() {
		const step = this.#getCurrentStep();

		this.#activateStepValidation(step);
		this.#validateStep();

		if (this.#hasErrors()) {
			return Promise.reject();
		}

		switch (step) {
			case this.STEP_WELCOME:
				return this.#disableWelcomeStep();

			case this.STEP_SELECT_TEMPLATE:
				return this.#source_host !== null ? this.#loadWizardConfig() : Promise.resolve();

			case this.STEP_CREATE_HOST:
				return this.#loadWizardConfig();

			case this.STEP_CONFIGURATION_FINISH:
				return this.#saveHost();

			default:
				return Promise.resolve();
		}
	}

	#loadWizardConfig() {
		const {templateid} = this.#getSelectedTemplate();
		const hostid = this.#source_host !== null
			? this.#source_host.hostid
			: this.#data.host !== null
				? this.#data.host.id
				: null;

		// Don't send request if template or host hasn't changed.
		if (this.#template?.templateid === templateid
				&& (this.#host?.hostid === hostid || (this.#host === null && hostid === null))) {
			this.#data.tls_psk_identity = this.#data.tls_required ? this.#generatePSKIdentity() : '';

			return Promise.resolve();
		}

		const url_params = objectToSearchParams({
			action: 'host.wizard.get',
			templateid,
			...(hostid !== null && {hostid})
		});
		const get_url = new URL(`zabbix.php?${url_params}`, location.href);

		return fetch(get_url.href)
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.#removeMessageBoxes();

				this.#template = response.template;
				this.#host = response.host;

				if (this.#host !== null) {
					this.#data.host = {
						...this.#data.host,
						id: this.#host.hostid,
						host: this.#host.host,
						name: this.#host.name
					}
				}

				this.#data.interface_required = [
					response.agent_interface_required && <?= INTERFACE_TYPE_AGENT ?>,
					response.ipmi_interface_required && <?= INTERFACE_TYPE_IPMI ?>,
					response.jmx_interface_required && <?= INTERFACE_TYPE_JMX ?>,
					response.snmp_interface_required && <?= INTERFACE_TYPE_SNMP ?>
				].filter(Boolean);

				this.#data.install_agent_required = response.install_agent_required;
				this.#data.tls_required = this.#data.install_agent_required
					&& (this.#host === null
						|| (Number(this.#host.tls_connect) !== <?= HOST_ENCRYPTION_PSK ?> && !this.#host.tls_in_psk));

				this.#data.tls_warning = false;

				if (this.#host !== null) {
					const no_encryption = Number(this.#host.tls_connect) === <?= HOST_ENCRYPTION_NONE ?>
						&& this.#host.tls_in_none
						&& !this.#host.tls_in_psk
						&& !this.#host.tls_in_cert;

					const psk_encryption = Number(this.#host.tls_connect) === <?= HOST_ENCRYPTION_PSK ?>
						&& !this.#host.tls_in_none
						&& this.#host.tls_in_psk
						&& !this.#host.tls_in_cert;

					this.#data.tls_warning = this.#data.install_agent_required && !no_encryption && !psk_encryption;
				}

				this.#data.tls_psk_identity = this.#data.tls_required ? this.#generatePSKIdentity() : '';
				this.#data.tls_psk = this.#data.tls_required ? this.#generatePSK() : '';

				this.#data.interfaces = [];
				this.#validation_rules[this.STEP_ADD_HOST_INTERFACE] = {};

				this.#data.interface_required.forEach((interface_type, row_index) => {
					const host_interface = this.#host?.interfaces.find(
						host_interface => Number(host_interface.type) === interface_type
					);

					this.#data.interfaces[row_index] = host_interface || this.#data.interface_default[interface_type];

					this.#validation_rules[this.STEP_ADD_HOST_INTERFACE] = {
						...this.#validation_rules[this.STEP_ADD_HOST_INTERFACE],
						[`interfaces.${row_index}.address`]: {
							row_index,
							required: true
						},
						[`interfaces.${row_index}.port`]: {
							row_index,
							type: 'integer',
							required: true,
							min: <?= ZBX_MIN_PORT_NUMBER ?>,
							max: <?= ZBX_MAX_PORT_NUMBER ?>
						},
						...(interface_type === <?= INTERFACE_TYPE_SNMP ?> && {
							[`interfaces.${row_index}.details.community`]: {
								row_index,
								required: (row_index) => {
									return Number(this.#data.interfaces[row_index].details.version) !== <?= SNMP_V3 ?>
								}
							}
						})
					}
				});

				this.#data.ipmi_authtype = this.#host?.ipmi_authtype ?? <?= IPMI_AUTHTYPE_DEFAULT ?>;
				this.#data.ipmi_privilege = this.#host?.ipmi_privilege ?? <?= IPMI_PRIVILEGE_USER ?>;
				this.#data.ipmi_username = this.#host?.ipmi_username || '';
				this.#data.ipmi_password = this.#host?.ipmi_password || '';

				this.#validation_rules[this.STEP_CONFIGURE_HOST] = {}
				this.#macro_reset_list = {};

				this.#data.macros = Object.fromEntries(this.#template.macros.map((template_macro, row_index) => {
					const is_checkbox = Number(template_macro.config.type) === <?= ZBX_WIZARD_FIELD_CHECKBOX ?>;
					const is_list = Number(template_macro.config.type) === <?= ZBX_WIZARD_FIELD_LIST ?>;

					const host_macro = this.#host?.macros.find(({macro}) => macro === template_macro.macro);
					let value = host_macro?.value || template_macro.value || '';

					if (is_checkbox || is_list) {
						const options = Object.values(template_macro.config.options);
						const allowed_values = is_checkbox
							? Object.values(options[0])
							: options.map(option => option.value);

						if (!allowed_values.includes(value)) {
							if (host_macro?.value !== undefined ) {
								this.#macro_reset_list[`macros.${row_index}.value`] = template_macro.macro;
							}

							value = is_checkbox
								? template_macro.config.options[0].unchecked
								: allowed_values[0];
						}
					}
					else if (host_macro && Number(template_macro.type) === <?= ZBX_MACRO_TYPE_SECRET ?>) {
						value = undefined;
					}

					if (Number(host_macro?.type) === <?= ZBX_MACRO_TYPE_SECRET ?>
							&& Number(template_macro.type) !== <?= ZBX_MACRO_TYPE_SECRET ?>) {
						this.#macro_reset_list[`macros.${row_index}.value`] = template_macro.macro;
					}

					this.#validation_rules[this.STEP_CONFIGURE_HOST][`macros.${row_index}.value`] = {
						...(Number(template_macro.config.required) === 1 && {required: true}),
						...(template_macro.config.regex && {regex: new RegExp(template_macro.config.regex)})
					};

					return [row_index, {
						type: Number(template_macro.type),
						macro: template_macro.macro,
						value,
						description: template_macro.description
					}];
				}));
			})
			.catch(exception => {
				this.#ajaxExceptionHandler(exception);

				return Promise.reject();
			});
	}

	#saveHost() {
		const submit_url = new URL('zabbix.php', location.href);

		submit_url.searchParams.set('action', this.#data.host_new !== null
			? 'host.wizard.create'
			: 'host.wizard.update'
		);

		return fetch(submit_url.href, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				...(this.#data.host && {hostid: this.#data.host.id}),
				...(this.#data.host_new && {host: this.#data.host_new.id}),
				...(this.#data.groups.length && {groups: this.#data.groups.map(group => group.id)}),
				...(this.#data.groups_new.length && {groups_new: this.#data.groups_new.map(group => group.id)}),
				templateid: this.#template.templateid,
				...(this.#data.tls_required && {
					tls_psk: this.#data.tls_psk,
					tls_psk_identity: this.#data.tls_psk_identity
				}),
				...(this.#isRequiredAddHostInterface() && {
					interfaces: Object.values(this.#data.interfaces),
					ipmi_authtype: this.#data.ipmi_authtype,
					ipmi_privilege: this.#data.ipmi_privilege,
					ipmi_username: this.#data.ipmi_username,
					ipmi_password: this.#data.ipmi_password
				}),
				macros: Object.values(this.#data.macros),

				[CSRF_TOKEN_NAME]: this.#csrf_token
			})
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				if (this.#data.host_new !== null) {
					this.#data.host_new.name = this.#data.host_new.id
					this.#data.host_new.id = response.hostid;
				}

				this.#ajaxSuccessHandler(response);
			})
			.catch(exception => {
				this.#ajaxExceptionHandler(exception);

				return Promise.reject();
			});
	}

	#ajaxSuccessHandler({success}) {
		if (success !== undefined) {
			this.#addMessageBox(makeMessageBox('good', success.messages, success.title)[0]);
		}
	}

	#ajaxExceptionHandler(exception) {
		let title;
		let messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		this.#addMessageBox(makeMessageBox('bad', messages, title)[0]);
	}

	#addMessageBox(message_box) {
		this.#removeMessageBoxes();

		const step_form = this.#dialogue.querySelector('.step-form');

		step_form.parentNode.insertBefore(message_box, step_form);
	}

	#removeMessageBoxes() {
		this.#dialogue.querySelectorAll('.overlay-dialogue-body .msg-bad').forEach(message_box => message_box.remove());
	}

	#updateStepsQueue() {
		const template_loaded = this.#template !== null;

		this.#steps_queue = [];

		if (Number(this.#data.do_not_show_welcome) !== 1) {
			this.#steps_queue.push(this.STEP_WELCOME);
		}

		this.#steps_queue.push(this.STEP_SELECT_TEMPLATE);

		if (this.#source_host === null) {
			this.#steps_queue.push(this.STEP_CREATE_HOST);
		}

		if (template_loaded && this.#data.install_agent_required) {
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

		if (this.#getCurrentStep() === this.STEP_WELCOME || this.#show_cancel_screen) {
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
				label: <?= json_encode(_('Selected host')) ?>,
				info: this.#source_host?.name,
				visible: this.#source_host !== null,
				steps: [this.STEP_WELCOME]
			},
			{
				label: <?= json_encode(_('Select a template')) ?>,
				info: this.#getSelectedTemplate()?.name,
				visible: true,
				steps: [this.STEP_SELECT_TEMPLATE]
			},
			{
				label: <?= json_encode(_('Create or select a host')) ?>,
				info: this.#data.host_new !== null ? this.#data.host_new.name : this.#data.host?.name,
				visible: this.#source_host === null,
				steps: [this.STEP_CREATE_HOST]
			},
			{
				label: <?= json_encode(_('Install Zabbix agent')) ?>,
				visible: template_loaded && this.#data.install_agent_required,
				steps: [this.STEP_INSTALL_AGENT]
			},
			{
				label: <?= json_encode(_('Add host interface')) ?>,
				visible: template_loaded && this.#isRequiredAddHostInterface(),
				steps: [this.STEP_ADD_HOST_INTERFACE]
			},
			{
				label: <?= json_encode(_('Configure host')) ?>,
				visible: template_loaded,
				steps: [this.STEP_README, this.STEP_CONFIGURE_HOST, this.STEP_CONFIGURATION_FINISH]
			},
			{
				label: <?= json_encode(_('A few more steps')) ?>,
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
				steps.length && Math.max(...steps) < this.#getCurrentStep()
			);
			progress_step.classList.toggle('progress-step-current',
				steps.includes(this.#getCurrentStep())
			);
			progress_step.classList.toggle('progress-step-disabled', !steps.length);
		}

		this.#dialogue.querySelector(`.${ZBX_STYLE_OVERLAY_DIALOGUE_HEADER}`).appendChild(progress);
	}

	#updateForm(path, new_value, old_value) {
		if (this.#form_update_locked) {
			this.#pending_form_update = true;

			return;
		}

		const step = this.#getCurrentStep();

		const scroll_top = this.#dialogue.querySelector('.overlay-dialogue-body').scrollTop;
		const step_init = path === undefined;

		switch (step) {
			case this.STEP_WELCOME:
				break;

			case this.STEP_SELECT_TEMPLATE:
				if (step_init
					|| ['template_search_query', 'data_collection', 'agent_mode', 'show_templates'].includes(path)
					|| path?.startsWith('selected_subclasses')
				) {
					this.#dialogue.querySelectorAll('[name="agent_mode"]').forEach(input => {
						input.disabled = Number(this.#data.data_collection) !== <?= ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED ?>;
					});

					const step_body = document.querySelector('.js-templates');

					step_body.innerHTML = '';

					for (const section of this.#makeCardListSections()) {
						step_body.appendChild(section);
					}
				}

				if ((step_init || path === 'selected_template') && this.#getSelectedTemplate()) {
					this.#updateProgress();
					this.#overlay.has_custom_cancel = true;
				}

				if (path === 'show_info_by_template') {
					if (old_value !== null) {
						this.#updateCardInfo(old_value, false);
					}

					if (new_value !== null) {
						this.#updateCardInfo(new_value, true);
					}
				}
				break;

			case this.STEP_CREATE_HOST:
				if (step_init || path === 'host' || path === 'host_new') {
					this.#updateProgress();
				}

				if (step_init || path === 'groups') {
					jQuery('#groups_', this.#dialogue).multiSelect('setDisabledEntries',
						this.#data.groups.map(group => group.id)
					);
				}

				this.#dialogue.querySelector('.js-groups-description').hidden = this.#data.host === null;

				break;

			case this.STEP_INSTALL_AGENT:
				this.#dialogue.querySelector('.js-agent-encryption-overwrite')
					.style.display = this.#data.tls_required && this.#data.tls_warning ? '' : 'none';

				for (const element of this.#dialogue.querySelectorAll('.js-tls-exists')) {
					element.style.display = !this.#data.tls_required ? '' : 'none';
				}

				for (const element of this.#dialogue.querySelectorAll('.js-tls-input')) {
					element.style.display = this.#data.tls_required ? '' : 'none';
				}

				this.#dialogue.querySelector('.js-windows-distribution-select')
					.style.display = this.#data.monitoring_os === 'windows' ? '' : 'none';

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

				let server_host = '';
				let hostname = this.#getHostName();
				let psk_identity = '';
				let psk = '';

				const step_errors = this.#validation_errors[this.#getCurrentStep()];
				const agent_script_has_error = step_errors['agent_script_server_host'] !== null;
				const tls_psk_identity_has_error = step_errors['tls_psk_identity'] !== null;

				if (this.#data.monitoring_os === 'linux') {
					server_host = this.#data.agent_script_server_host !== '' && !agent_script_has_error
						? `--server-host '${this.#data.agent_script_server_host}'`
						: `--server-host-stdin`;

					hostname = `--hostname '${hostname}'`;

					if (this.#data.tls_required) {
						psk_identity = this.#data.tls_psk_identity !== '' && !tls_psk_identity_has_error
							? `--psk-identity '${this.#data.tls_psk_identity}'`
							: `--psk-identity-stdin`;

						psk = this.#data.tls_psk !== ''
							? `--psk ${this.#data.tls_psk}`
							: `--psk-stdin`;
					}
				}

				if (this.#data.monitoring_os === 'windows') {
					server_host = this.#data.agent_script_server_host !== '' && !tls_psk_identity_has_error
						? `-serverHost '${this.#data.agent_script_server_host.replace(/ /g, `\` `)}'`
						: `-serverHostSTDIN`;

					hostname = `-hostName '${hostname.replace(/ /g, `\` `)}'`;

					if (this.#data.tls_required) {
						psk_identity = this.#data.tls_psk_identity !== '' && !tls_psk_identity_has_error
							? `-pskIdentity '${this.#data.tls_psk_identity.replace(/ /g, `\` `)}'`
							: `-pskIdentitySTDIN`;

						psk = this.#data.tls_psk !== ''
							? `-psk ${this.#data.tls_psk}`
							: `-pskSTDIN`;
					}
				}

				this.#dialogue.querySelector('.js-install-agent-readme').innerHTML = readme_template.evaluate({
					server_host,
					hostname,
					psk_identity,
					psk
				});
				break;

			case this.STEP_ADD_HOST_INTERFACE:
				const interfaces_view = this.#dialogue.querySelectorAll('.js-host-interface');

				for (const [row_index, field] of Object.entries(this.#data.interfaces)) {
					if (Number(field.type) === <?= INTERFACE_TYPE_SNMP ?>) {
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
							case <?= SNMP_V1 ?>:
								visible_fields['js-snmp-community'] = true;
								break;

							case <?= SNMP_V2C ?>:
								visible_fields['js-snmp-community'] = true;
								visible_fields['js-snmp-repetition-count'] = true;
								break;

							case <?= SNMP_V3 ?>:
								visible_fields['js-snmpv3-contextname'] = true;
								visible_fields['js-snmpv3-securityname'] = true;
								visible_fields['js-snmpv3-securitylevel'] = true;
								visible_fields['js-snmp-repetition-count'] = true;

								switch (Number(field.details.securitylevel)) {
									case <?= ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV ?>:
										visible_fields['js-snmpv3-authprotocol'] = true;
										visible_fields['js-snmpv3-authpassphrase'] = true;
										break;

									case <?= ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV ?>:
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

			case this.STEP_CONFIGURE_HOST:
				if (path in this.#macro_reset_list) {
					this.#updateFieldMessages(this.#pathToInputName(path), 'warning', []);

					delete this.#macro_reset_list[path];
				}

				if (step_init && this.#hasErrors()) {
					for (const [path, error] of Object.entries(this.#validation_errors[step] || {})) {
						if (error === null) {
							continue;
						}

						const input = this.#dialogue.querySelector(`[name="${this.#pathToInputName(path)}"]`);

						if (input === null) {
							continue;
						}

						const section = input.closest(`.${ZBX_STYLE_COLLAPSIBLE}`);

						if (section !== null && section.classList.contains(ZBX_STYLE_COLLAPSED)) {
							toggleSection(section.querySelector(`.${ZBX_STYLE_TOGGLE}`));
						}
					}
				}
				break;
		}

		for (const [path, error] of Object.entries(this.#validation_errors[step] || {})) {
			const rule = this.#getValidationRule(path);

			if (!rule?.active || 'base_rule' in rule) {
				continue;
			}

			const alias_error = this.#getAliasError(path);

			this.#updateFieldMessages(this.#pathToInputName(path), 'error',
				error !== null ? [error] : (alias_error !== null ? [alias_error] : [])
			);

			if (error === null && alias_error === null) {
				rule.active = false;
			}
		}

		this.#next_button.toggleAttribute('disabled', this.#hasErrors());

		requestAnimationFrame(() => {
			if (path) {
				this.#dialogue.querySelector('.overlay-dialogue-body').scrollTop = scroll_top;
			}
			else if (step_init) {
				this.#overlay.recoverFocus();
			}

			this.#overlay.containFocus();
		});
	}

	#updateFieldsAsterisk() {
		const rules = this.#validation_rules[this.#getCurrentStep()] || {};

		for (let [path, rule] of Object.entries(rules)) {
			if ('base_path' in rule) {
				continue;
			}

			const name = this.#pathToInputName(path);
			const input = this.#dialogue.querySelector(`[name="${name}"], [data-name="${name}"]`);

			if (input === null) {
				continue;
			}

			const condition = rule.asterisk ?? rule.required;
			const set_asterisk = typeof condition === 'function' ? condition(rule.row_index ?? null) : condition;

			input
				.closest(`.${ZBX_STYLE_FORM_FIELD}`)
				?.querySelector('label')?.classList.toggle(ZBX_STYLE_FIELD_LABEL_ASTERISK, !!set_asterisk);
		}
	}

	#updateDialogButton() {
		const step = this.#getCurrentStep();

		const cancel_button = this.#dialogue.querySelector('.js-cancel');
		cancel_button.hidden = this.#show_cancel_screen || step === this.STEP_COMPLETE;

		this.#back_button.hidden = this.#show_cancel_screen || this.#current_step === 0 || step === this.STEP_COMPLETE;

		this.#next_button.hidden = this.#show_cancel_screen;

		for (const button of this.#dialogue.querySelectorAll('.js-cancel-yes, .js-cancel-no')) {
			button.hidden = !this.#show_cancel_screen;
		}

		switch (step) {
			case this.STEP_CONFIGURATION_FINISH:
				this.#next_button.innerText = this.#data.host_new !== null
					? <?= json_encode(_('Create')) ?>
					: <?= json_encode(_('Update')) ?>;
				break;

			case this.STEP_COMPLETE:
				this.#next_button.innerText = <?= json_encode(_('Finish')) ?>;
				break;

			default:
				this.#next_button.innerText = <?= json_encode(_('Next')) ?>;
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
				if (typeof value !== 'object') {
					field.value = value;
				}
		}
	}

	#updateCardInfo(template, expand) {
		const card = this.#template_cards.get(template);

		if (card === undefined) {
			return;
		}

		if (expand) {
			card.max_height = card.style.maxHeight;
			card.style.maxHeight = '';
			card.querySelector('.template-info').style.display = '';
		}
		else {
			card.style.maxHeight = card.max_height;
			card.querySelector('.template-info').style.display = 'none';
		}

		const button = card.querySelector('.js-template-info-toggle');

		button.classList.toggle(ZBX_ICON_CHEVRON_UP, expand);
		button.classList.toggle(ZBX_ICON_CHEVRON_DOWN, !expand);
		button.querySelector('span').textContent = expand
			? <?= json_encode(_('Show less')) ?>
			: <?= json_encode(_('Show more')) ?>;
	}

	#getSelectedTemplate() {
		const selected = this.#data.selected_template;

		return selected && this.#templates.get(selected.split(':').pop());
	}

	#getHostName() {
		return this.#data.host_new?.id || this.#data.host.host;
	}

	#getVisibleHostName() {
		return this.#data.host_new?.id || this.#data.host.name;
	}

	#isRequiredAddHostInterface() {
		return this.#data.interface_required.some(required_type =>
			!this.#host?.interfaces.some(({type}) => Number(type) === required_type)
		);
	}

	#generatePSKIdentity() {
		const host_field_length = <?= DB::getFieldLength('hosts', 'host') ?>;

		return `${this.#getHostName().substring(0, host_field_length - 4)} PSK`;
	}

	#generatePSK() {
		const array = new Uint8Array(32);

		window.crypto.getRandomValues(array);

		return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
	}

	#makeCardListSections() {
		const template_subclasses = new Map();

		let template_classes = Array.from(this.#templates)
			.filter(([_, template]) => {
				if (template.data_collection !== null
						&& Number(this.#data.data_collection) !== <?= ZBX_TEMPLATE_DATA_COLLECTION_ANY ?>
						&& template.data_collection !== Number(this.#data.data_collection)) {
					return false;
				}

				if (Number(this.#data.data_collection) === <?= ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED ?>
						&& Number(this.#data.agent_mode) !== <?= ZBX_TEMPLATE_AGENT_MODE_ANY ?>
						&& !template.agent_mode.includes(Number(this.#data.agent_mode))) {
					return false;
				}

				if (this.#linked_templates.length) {
					const linked = this.#linked_templates.includes(Number(template.templateid));

					if ((Number(this.#data.show_templates) === <?= ZBX_TEMPLATE_SHOW_LINKED ?> && !linked)
							|| (Number(this.#data.show_templates) === <?= ZBX_TEMPLATE_SHOW_NOT_LINKED ?> && linked)) {
						return false;
					}
				}

				const query = this.#data.template_search_query.toLowerCase();

				if (this.#data.template_search_query !== '') {
					return template.name.toLowerCase().includes(query)
						|| template.tags.some(({tag, value}) => {
							return tag.toLowerCase().includes(query) || value.toLowerCase().includes(query);
						});
				}

				return true;
			})
			.reduce((map, [templateid, {tags}]) => {
				const enrichSubclasses = (class_value, tags) => {
					const subclasses = new Set(template_subclasses.get(class_value) || []);

					tags.forEach(({tag, value}) => tag === 'subclass' && subclasses.add(value));

					template_subclasses.set(class_value, [...subclasses]);
				}

				let found = false;

				for (const {tag, value} of tags) {
					if (tag === 'class') {
						map.set(value, [...(map.get(value) || []), templateid]);
						found = true;

						enrichSubclasses(value, tags);
					}
				}

				if (!found) {
					const value = 'other';

					map.set(value, [...(map.get(value) || []), templateid]);

					enrichSubclasses(value, tags);
				}

				return map;
			}, new Map());

		template_classes = new Map([...template_classes.entries()].sort((a, b) => {
			if (a[0] === 'other') {
				return 1;
			}
			if (b[0] === 'other') {
				return -1;
			}

			return a[0].localeCompare(b[0]);
		}));

		const sections = [];

		this.#template_cards.clear();
		this.#data.show_info_by_template = null;

		if (template_classes.size) {
			for (const [category, templateids] of template_classes) {
				const section = this.#view_templates.templates_section.evaluateToElement({
					title: category.charAt(0).toUpperCase() + category.slice(1)
				});

				if (this.#sections_collapsed.has(category) && !section.classList.contains(ZBX_STYLE_COLLAPSED)) {
					toggleSection(section.querySelector('.toggle'));
				}

				const subclasses_list = section.querySelector('.template-subfilter');
				const subclasses = template_subclasses.get(category);

				if (subclasses?.length > 1 && templateids.length > 1) {
					for (const subclass of subclasses.sort()) {
						const subfilter_button = this.#view_templates.subclass_filter_item.evaluateToElement({
							label: subclass
						});

						subfilter_button.classList.toggle('selected',
							!!this.#data.selected_subclasses[category]?.includes(subclass)
						);

						subfilter_button.addEventListener('click', ({target}) => {
							const selected_subclasses = new Set(this.#data.selected_subclasses[category] || []);

							if (target.classList.contains('selected')) {
								selected_subclasses.delete(subclass);
							}
							else {
								selected_subclasses.add(subclass);
							}

							this.#data.selected_subclasses[category] = Array.from(selected_subclasses);
						});

						subclasses_list.appendChild(subfilter_button);
					}
				}
				else {
					subclasses_list.style.display = 'none';
				}

				const card_list = section.querySelector('.templates-card-list');
				const selected_subclasses = this.#data.selected_subclasses[category] || [];

				let templates_count = 0;

				for (const templateid of templateids) {
					const template = this.#templates.get(templateid);
					const template_key = `${category}:${templateid}`;

					if (selected_subclasses.length && !template.tags.find(
						({tag, value}) => tag === 'subclass' && selected_subclasses.includes(value)
					)) {
						continue;
					}

					const card = this.#makeCard(category, template, template_key === this.#data.selected_template);
					this.#template_cards.set(template_key, card);

					card_list.appendChild(card);

					templates_count++;
				}

				section.querySelector(`.<?= CSection::ZBX_STYLE_HEAD ?> h4`).append(` (${templates_count})`);

				section.addEventListener('expand', () => {
					this.#sections_collapsed.delete(category);
					this.#updateCardsHeight(section);
				});
				section.addEventListener('collapse', () => {
					this.#data.show_info_by_template = null;
					this.#sections_collapsed.add(category);
				});

				sections.push(section);

				setTimeout(() => {
					if (!this.#sections_collapsed.has(category)) {
						this.#updateCardsHeight(section);
					}
				});
			}
		}
		else {
			sections.push(this.#view_templates.templates_section_no_found.evaluateToElement());
		}

		return sections;
	}

	#updateCardsHeight(section) {
		for (const card of section.querySelectorAll('.<?= CRadioCardList::ZBX_STYLE_CLASS_CARD ?>')) {
			const {height} = card.getBoundingClientRect();

			card.style.maxHeight = `${height}px`;
		}
	}

	#makeCard(category, template, checked) {
		const card = this.#view_templates.card.evaluateToElement({
			...template,
			category,
			title: template.name
		});

		const tags_list = card.querySelector(`.${ZBX_STYLE_TAGS_LIST}`);
		const tag = ({tag, value}) => {
			const tag_value = [tag, value].filter(val => val !== '').join(': ');

			return this.#view_templates.tag.evaluateToElement({tag_value, hint_tag_value: escapeHtml(tag_value)});
		}

		if (template.tags.length) {
			const tags_max_height = 44;
			const temp_tag_list = document.createElement('div');

			temp_tag_list.classList.add('temp-tags-list');

			document.body.appendChild(temp_tag_list);

			let all_fits = true;

			for (let i = 0; i < template.tags.length; i++) {
				const tag_element = tag(template.tags[i]);

				temp_tag_list.appendChild(tag_element);

				if (temp_tag_list.scrollHeight > tags_max_height) {
					temp_tag_list.removeChild(tag_element);
					all_fits = false;
					break;
				}
			}

			if (!all_fits) {
				temp_tag_list.appendChild(this.#view_templates.tags_more.evaluateToElement({
					tag_values: template.tags.map(tag_value => tag(tag_value).outerHTML).join('')
				}));

				if (temp_tag_list.scrollHeight > tags_max_height) {
					const tags = temp_tag_list.querySelectorAll(`.${ZBX_STYLE_TAG}`);

					temp_tag_list.removeChild(tags[tags.length - 1]);
				}
			}

			tags_list.innerHTML = temp_tag_list.innerHTML;
			temp_tag_list.remove();
		}
		else {
			card.querySelector('.template-tags').style.display = 'none';
		}

		const info = card.querySelector('.template-info');

		if (template.data_collection !== null) {
			info.appendChild(this.#view_templates.template_info.evaluateToElement({
				title: <?= json_encode(_('Data collection')) ?>,
				content: Number(template.data_collection) === <?= ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED ?>
					? <?= json_encode(_('Agent-based')) ?>
					: <?= json_encode(_('Agentless')) ?>
			}));
		}

		if (template.agent_mode.length) {
			info.appendChild(this.#view_templates.template_info.evaluateToElement({
				title: <?= json_encode(_('Agent mode')) ?>,
				content: [
					template.agent_mode.includes(<?= ZBX_TEMPLATE_AGENT_MODE_ACTIVE ?>) && <?= json_encode(_('Active')) ?>,
					template.agent_mode.includes(<?= ZBX_TEMPLATE_AGENT_MODE_PASSIVE ?>) && <?= json_encode(_('Passive')) ?>
				].filter(Boolean).join(', ')
			}));
		}

		if (template.templategroups.length) {
			info.appendChild(this.#view_templates.template_info.evaluateToElement({
				title: <?= json_encode(_('Template groups')) ?>,
				content: template.templategroups.map(({name}) => name).join(', ')
			}));
		}

		if (template.description !== '') {
			info.appendChild(this.#view_templates.template_info.evaluateToElement({
				title: <?= json_encode(_('Description')) ?>,
				content: template.description
			}));
		}

		info.style.display = 'none';

		const info_toggle = card.querySelector('.js-template-info-toggle');

		info_toggle.template = `${category}:${template.templateid}`;

		card.querySelector('input[type=radio').checked = checked;

		return card;
	}

	#makeMacroField(macro, config, row_index) {
		const field_view = (() => {
			switch (Number(config.type)) {
				case <?= ZBX_WIZARD_FIELD_TEXT ?>:
					return this.#makeMacroFieldText(row_index, config, macro);

				case <?= ZBX_WIZARD_FIELD_LIST ?>:
					return this.#makeMacroFieldList(row_index, config, macro);

				case <?= ZBX_WIZARD_FIELD_CHECKBOX ?>:
					return this.#makeMacroFieldCheckbox(row_index, config);

				default:
					return null;
			}
		})();

		const description = this.#view_templates.macro_field_description.evaluateToElement({
			macro: `<?= _('Macro') ?>: ${macro.macro}`
		});

		if (config.description) {
			description.appendChild(this.#makeMarkdown(config.description));
		}

		return {field: field_view, description};
	}

	#makeMacroFieldText(row_index, {label}, {type}) {
		switch (Number(type)) {
			case <?= ZBX_MACRO_TYPE_SECRET ?>:
				return this.#view_templates.macro_field_secret.evaluateToElement({row_index, label});

			case <?= ZBX_MACRO_TYPE_VAULT ?>:
				return this.#view_templates.macro_field_vault.evaluateToElement({row_index, label});

			default:
				return this.#view_templates.macro_field_text.evaluateToElement({row_index, label});
		}
	}

	#makeMacroFieldList(row_index, {label, options}, {value}) {
		if (options.length > 5) {
			const field_select = this.#view_templates.macro_field_select.evaluateToElement({
				row_index,
				label,
				value
			});
			const select = field_select.querySelector('z-select');

			select.setAttribute('data-options', JSON.stringify(
				options.map(option => ({label: option.text, value: option.value}))
			));

			return field_select;
		}
		else {
			const field_radio = this.#view_templates.macro_field_radio.evaluateToElement({label, value});
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

	#makeMacroFieldCheckbox(row_index, {label, options}) {
		return this.#view_templates.macro_field_checkbox.evaluateToElement({
			row_index,
			label,
			value: options[0].checked,
			unchecked_value: options[0].unchecked
		});
	}

	#makeMarkdown(content) {
		const markdown = this.#view_templates.markdown.evaluateToElement();

		markdown.innerHTML = content;

		markdown.querySelectorAll('a[href]').forEach(link => link.setAttribute('target', '_blank'));

		return markdown;
	}

	#disableWelcomeStep() {
		if (Number(this.#data.do_not_show_welcome) !== 1) {
			return Promise.resolve();
		}

		updateUserProfile('web.host.wizard.show.welcome', 0, []);

		return Promise.reject();
	}

	#onFormDataChange(path, new_value, old_value) {
		if (this.#data_update_locked) {
			return;
		}

		this.#updateField(this.#pathToInputName(path), new_value);
		this.#validateStep();
		this.#updateForm(path, new_value, old_value);
		this.#updateFieldsAsterisk();
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

	#onInputBlur({target}) {
		if (!target.name) {
			return;
		}

		if (['number', 'password', 'text'].includes(target.type)) {
			target.value = target.value.trim();
		}

		const path = this.#inputNameToInputPath(target.name);

		this.#activateFieldValidation(path);

		const error = this.#validateField(path);
		const alias_error = this.#validateFieldAliases(path);

		if (error !== null || alias_error !== null) {
			this.#updateForm(path);
		}
	}

	#activateStepValidation(step) {
		for (const rule of Object.values(this.#validation_rules[step] || {})) {
			if (!('base_rule' in rule)) {
				rule.active = true;
			}
		}
	}

	#activateFieldValidation(path) {
		const rule = this.#getValidationRule(path);

		if (!rule) {
			return;
		}

		rule.active = true;
	}

	#validateStep() {
		const step = this.#getCurrentStep();

		for (const path of Object.keys(this.#validation_rules[this.#getCurrentStep()] || {})) {
			this.#validateField(path);
		}

		switch (step) {
			case this.STEP_SELECT_TEMPLATE:
				this.#validation_errors[step] = !this.#getSelectedTemplate();
				break;
		}
	}

	#validateField(path) {
		const validate = (rule, value) => {
			rule = {type: 'string', active: false, required: false, ...rule};

			const required = (typeof rule.required === 'function')
				? rule.required(rule.row_index ?? null)
				: rule.required;

			if (!!required && (value === null || value === '' || (rule.type === 'array' && !value.length))) {
				return <?= json_encode(_('This field cannot be empty.')) ?>;
			}

			if (rule.type === 'object' && rule.fields !== undefined && value !== null) {
				for (const name in rule.fields) {
					const error = validate(rule.fields[name], value[name]);

					if (error !== null) {
						return error
					}
				}

				return null;
			}

			if (rule.type === 'array' && rule.fields !== undefined) {
				for (const row of value) {
					for (const name in rule.fields) {
						const error = validate(rule.fields[name], row[name]);

						if (error !== null) {
							return error
						}
					}
				}

				return null;
			}

			if (rule.type === 'string' && value !== '') {
				if (rule.minlength > value.length) {
					return <?= json_encode(_('This value is too short.')) ?>;
				}

				if (rule.maxlength < value.length) {
					return <?= json_encode(_('This value is too long.')) ?>;
				}

				if (rule.regex && !rule.regex.test(value)) {
					return <?= json_encode(_('This value does not match pattern.')) ?>;
				}
			}

			if (rule.type === 'integer' && value !== '') {
				if (!/^-?\d+$/.test(value)) {
					return <?= json_encode(_('This value is not a valid integer.')) ?>;
				}

				if (rule.min > value) {
					return sprintf(<?= json_encode(_('This value must be no less than "%1$s".')) ?>, rule.min);
				}

				if (rule.max < value) {
					return sprintf(<?= json_encode(_('This value must be no greater than "%1$s".')) ?>, rule.max);
				}
			}

			return null;
		}

		const rule = this.#getValidationRule(path);

		if (!rule) {
			return null;
		}

		const value = this.#getValueByPath(this.#data, path);
		const error = value !== undefined ? validate(rule, value) : null;

		if (this.#getCurrentStep() in this.#validation_errors) {
			this.#validation_errors[this.#getCurrentStep()][path] = error;
		}

		return error;
	}

	#validateFieldAliases(path) {
		for (const [alias_path, rule] of Object.entries(this.#validation_rules[this.#getCurrentStep()] ?? {})) {
			if (rule.base_path === path) {
				return this.#validateField(alias_path);
			}
		}

		return null;
	}

	#getValidationRule(path) {
		for (const rules of Object.values(this.#validation_rules)) {
			if (rules && path in rules) {
				return rules[path];
			}
		}
	}

	#getAliasError(path) {
		for (const [alias_path, rule] of Object.entries(this.#validation_rules[this.#getCurrentStep()])) {
			const error = this.#validation_errors[this.#getCurrentStep()][alias_path];

			if (rule.base_path === path && error) {
				return error;
			}
		}

		return null;
	}

	#hasErrors() {
		const validation_errors = this.#validation_errors[this.#getCurrentStep()];

		return validation_errors !== undefined
			? (validation_errors === true || Object.entries(validation_errors).some(([path, error]) => {
				return this.#getValidationRule(path)?.active && (error !== null || this.#getAliasError(path) !== null);
			}))
			: false;
	}

	#updateFieldMessages(name, type, messages = []) {
		const field = this.#dialogue.querySelector(`[name="${name}"], [data-name="${name}"]`);

		if (field === null) {
			return;
		}

		const form_field = field.closest(`.${ZBX_STYLE_FORM_FIELD}`);

		if (form_field === null) {
			return;
		}

		form_field.classList.toggle(type === 'error' ? 'field-has-error' : 'field-has-warning', !!messages.length);
		field.classList.toggle(type === 'error' ? 'has-error' : 'has-warning', !!messages.length);

		form_field.querySelectorAll(type === 'error' ? '.error' : '.warning').forEach(element => element.remove());

		messages.forEach(message => {
			const message_element = this.#view_templates[type].evaluateToElement({
				message: `${type === 'error' && messages.length > 1 ? '- ' : ''}${message}`
			});

			if (field.parentElement.classList.contains(ZBX_STYLE_FORM_FIELD)) {
				field.parentNode.insertBefore(message_element, field.nextSibling);
			} else {
				field.parentElement.parentNode.insertBefore(message_element, field.parentElement.nextSibling);
			}
		});
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
					const result = Reflect.set(target, property,
						typeof value === 'string' ? value.trim() : value,
						receiver
					);

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
							: {name: `${full_key}[${index}]`, value: item}
					)
				);
			}

			if (value !== null && typeof value === 'object') {
				return fields.concat(this.#getInputsData(value, full_key));
			}

			return fields.concat({name: full_key, value});
		}, []);
	}

	#getValueByPath(data, path) {
		const keys = path.split('.');
		let current = data;

		for (const key of keys) {
			if (current == null || !(key in current)) {
				return;
			}
			current = current[key];
		}

		return current;
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

	#inputNameToInputPath(name) {
		return this.#parseInputName(name).join('.');
	}

	#pathToInputName(path) {
		const [first, ...rest] = path.split('.');

		return first + rest.map(p => `[${p}]`).join('');
	}
}
