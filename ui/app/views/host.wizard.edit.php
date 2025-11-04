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
 * @var array $data
 */

$form = (new CForm())
	->setId('host-wizard-form')
	->setName('host_wizard_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

$output = [
	'header' => '',
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_WIZARD),
	'body' => $form
		->addItem(
			(new CDiv())->addClass('step-form-body')
		)
		->addItem([
			stepWelcome(),
			stepSelectTemplate($data['old_template_count']),
			stepCreateHost($form),
			stepInstallAgent($data['agent_script_data']),
			stepAddHostInterface(),
			stepReadme(),
			stepConfigureHost(),
			stepConfigurationFinish(),
			stepComplete(),
			cancelScreen()
		])
			->addClass('step-form')
			->toString(),
	'buttons' => [
		[
			'title' => _('Cancel'),
			'class' => ZBX_STYLE_BTN_LINK.' dialogue-cancel js-cancel',
			'cancel' => true
		],
		[
			'title' => _('Back'),
			'class' => 'btn-alt js-back'
		],
		[
			'title' => _('Next'),
			'class' => 'js-next'
		],
		[
			'title' => _('No'),
			'class' => 'btn-alt js-cancel-no'
		],
		[
			'title' => _('Yes'),
			'class' => 'js-cancel-yes',
			'cancel' => true,
			'action' => ''
		]

	],
	'script_inline' => $this->readJsFile('host.wizard.edit.js.php').
		'host_wizard_edit.init('.json_encode([
			'templates' => $data['templates'],
			'linked_templates' => $data['linked_templates'],
			'wizard_show_welcome' => $data['wizard_show_welcome'],
			'source_host' => $data['host'],
			'agent_script_server_host' => $data['agent_script_data']['server_host'],
			'csrf_token' => CCsrfTokenHelper::get('host')
		]).');',
	'dialogue_class' => 'modal-popup-host-wizard'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

function stepWelcome(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-welcome',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Welcome to the Host Wizard')),
						new CTag('p', true,
							_('The Host Wizard will help you set up your monitoring target (device, application, service, etc.) in Zabbix.')
						),
						new CTag('p', true, _('You can always access Host Wizard from Data collection > Hosts.'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),

			(new CSection())
				->addItem(
					(new CFormField([
						(new CCheckBox('do_not_show_welcome'))->setLabel(_('Do not show welcome screen'))
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function stepSelectTemplate($old_template_count): array {
	return [
		new CTemplateTag('host-wizard-step-select-template',
			(new CDiv([
				$old_template_count > 0
					? makeMessageBox(ZBX_STYLE_MSG_INFO, [],
						[
							_s('Some templates (%1$s) are incompatible with the Host Wizard.', $old_template_count),
							' ',
							_('See'),
							' ',
							(new CLink(_('how to update them'), CDocHelper::getUrl(CDocHelper::TEMPLATES_OUT_OF_THE_BOX)))
								->addClass(ZBX_STYLE_LINK_EXTERNAL)
								->setTarget('_blank'),
							'. ',
							_('Custom templates are not supported.')
						],
						false
					)
					: null,

				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, _('Select a template')),
							new CTag('p', true,
								_('A template is a set of predefined configurations (metrics to be collected, conditions for generating alerts, etc.) designed for your monitoring target.')
							)
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							->addClass(ZBX_STYLE_FORMATED_TEXT)
					)
					->addItem(
						(new CFormField([
							(new CTextBox('template_search_query'))
								->setAttribute('placeholder', _('Apache, AWS, MySQL, etc.')),
							(new CDiv(_('Type a keyword to search for templates.')))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
						]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
					)
					->addItem(
						(new CDiv([
							new CFormField([
								new CLabel([
									_('Data collection'),
									helpHint([
										[bold(_('Agent-based')), ' - ', _('Data is collected by Zabbix agent, a lightweight software `component installed on your monitoring target.')],
										BR(),
										[bold(_('Agentless')), ' - ', _('Data is collected by Zabbix server or proxy using standard protocols (e.g., SNMP, ICMP) or remote access methods (e.g., SSH).')]
									])
								]),
								(new CRadioButtonList('data_collection'))
									->addValue(_('All'), ZBX_TEMPLATE_DATA_COLLECTION_ANY)
									->addValue(_('Agent-based'), ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED)
									->addValue(_('Agentless'), ZBX_TEMPLATE_DATA_COLLECTION_AGENTLESS)
									->setModern()
							]),
							new CFormField([
								new CLabel([
									_('Agent mode'),
									helpHint([
										[bold(_('Active')), ' - ', _('Zabbix agent initiates connections to Zabbix server or proxy to send data. Recommended for monitoring targets behind a firewall.')],
										BR(),
										[bold(_('Passive')), ' - ', _('Zabbix server or proxy initiates connections to Zabbix agent to request data. Recommended for networks without a firewall or with open firewall ports.')]
									])
								]),
								(new CRadioButtonList('agent_mode'))
									->addValue(_('All'), ZBX_TEMPLATE_AGENT_MODE_ANY)
									->addValue(_('Active'), ZBX_TEMPLATE_AGENT_MODE_ACTIVE)
									->addValue(_('Passive'), ZBX_TEMPLATE_AGENT_MODE_PASSIVE)
									->setModern()
							]),
							(new CFormField([
								new CLabel(_('Show templates')),
								(new CRadioButtonList('show_templates'))
									->addValue(_('All'), ZBX_TEMPLATE_SHOW_ANY)
									->addValue(_('Linked'), ZBX_TEMPLATE_SHOW_LINKED)
									->addValue(_('Not linked'), ZBX_TEMPLATE_SHOW_NOT_LINKED)
									->setModern()
							]))->addClass('js-show-templates')
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN_FULL)
							->addClass(ZBX_STYLE_FORM_FIELDS_INLINE)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2),

				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h2', true, _('Templates'))
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							->addClass(ZBX_STYLE_FORMATED_TEXT)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2),

				(new CDiv())->addClass('js-templates')
			]))->addClass('step-form-body')
		),
		new CTemplateTag('host-wizard-templates-section',
			(new CSectionCollapsible())
				->setToggleLabel('#{title}')
				->additem(
					(new CDiv())->addClass('template-subfilter')
				)
				->addItem(
					(new CDiv())
						->addClass(ZBX_STYLE_GRID_COLUMNS)
						->addClass(ZBX_STYLE_GRID_COLUMNS_4)
						->addClass(CRadioCardList::ZBX_STYLE_CLASS)
						->addClass('templates-card-list')
				)
		),
		new CTemplateTag('host-wizard-templates-section-no-found',
			(new CSection())
				->addItem(
					(new CDiv(
						(new CDiv(_('No data found')))
							->addClass(ZBX_STYLE_NO_DATA_MESSAGE)
							->addClass(ZBX_ICON_SEARCH_LARGE)
					))->addClass('templates-card-list')
				)
				->addClass('no-found')
		),
		new CTemplateTag('host-wizard-template-card',
			(new CDiv())
				->addItem(
					(new CLabel([
						'#{title}',
						(new CSpan(
							(new CInput('radio', null, '#{category}:#{templateid}'))
								->setAttribute('name', 'selected_template')
						))->addClass(CRadioCardList::ZBX_STYLE_CLASS_SELECTOR)
					]))
						->setTitle('#{title}')
						->addClass(CRadioCardList::ZBX_STYLE_CLASS_LABEL),
				)
				->addItem(
					(new CDiv([
						new CLabel(_('Tags')),
						(new CDiv())->addClass(ZBX_STYLE_TAGS_LIST)
					]))->addClass('template-tags')
				)
				->addItem(
					(new CDiv())->addClass('template-info')
				)
				->addItem(
					(new CDiv([
						(new CButtonLink(
							new CSpan(_('Show more'))
						))
							->addClass(ZBX_ICON_CHEVRON_DOWN)
							->addClass('js-template-info-toggle')
					]))->addClass('template-info-toggle-container')
				)
				->addClass(CRadioCardList::ZBX_STYLE_CLASS_CARD)
		),
		new CTemplateTag('host-wizard-template-tag',
			(new CSpan('#{tag_value}'))
				->addClass(ZBX_STYLE_TAG)
				->setHint('#{hint_tag_value}')
		),
		new CTemplateTag('host-wizard-template-tags-more',
			(new CButtonIcon(ZBX_ICON_MORE))
				->setHint('#{tag_values}', ZBX_STYLE_HINTBOX_WRAP)
		)
	];
}

function stepCreateHost($form): CTemplateTag {
	return new CTemplateTag('host-wizard-step-create-host',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Create or select a host')),
						new CTag('p', true,
							_s('The template you selected (%1$s) must be linked to a host - an entity in Zabbix that represents your monitoring target.', '#{template_name}')
						),
						new CTag('p', true,
							_('Hosts are organized into host groups for easier management and access control.')
						)
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),

			(new CSection())
				->addItem(
					(new CFormField([
						new CLabel(_('Host name')),
						new CMultiSelect([
							'name' => 'host',
							'new_item_name' => 'host_new',
							'object_name' => 'hosts',
							'add_new' => true,
							'maxlength' => DB::getFieldLength('hosts', 'host'),
							'multiple' => false,
							'popup' => [
								'parameters' => [
									'srctbl' => 'hosts',
									'srcfld1' => 'hostid',
									'dstfrm' => $form->getName(),
									'dstfld1' => 'host',
									'editable' => true,
									'normal_only' => true
								]
							],
							'add_post_js' => false
						]),
						(new CDiv(
							_('Start typing or click Select to choose an existing host, or enter a new host name.')
						))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem([
					(new CFormField([
						new CLabel(_('Host groups')),
						new CMultiSelect([
							'name' => 'groups[]',
							'new_item_name' => 'groups_new[]',
							'object_name' => 'hostGroup',
							'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
							'maxlength' => DB::getFieldLength('hstgrp', 'name'),
							'popup' => [
								'parameters' => [
									'srctbl' => 'host_groups',
									'srcfld1' => 'groupid',
									'dstfrm' => $form->getName(),
									'dstfld1' => 'groups_',
									'editable' => true
								]
							],
							'add_post_js' => false
						]),
						(new CDiv(
							_('Start typing or click Select to choose existing host groups, or enter a new host group name.')
						))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST),
					(new CDiv([
						new CTag('p', true,
							_('Specifying new or existing host groups will add the host to these groups without removing it from any current groups.'),
						)
					]))
						->addClass(ZBX_STYLE_FORM_DESCRIPTION)
						->addClass(ZBX_STYLE_MARKDOWN)
						->addClass('field-baseline')
						->addClass('js-groups-description')
				])
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function stepInstallAgent($agent_script_data): array {
	return [
		new CTemplateTag('host-wizard-step-install-agent',
			(new CDiv([
				(makeMessageBox(ZBX_STYLE_MSG_WARNING, [],
					_('This configuration will overwrite all existing encryption settings on the host.'), false
				))->addClass('js-agent-encryption-overwrite'),

				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, _('Install Zabbix agent')),
							new CTag('p', true,
								_s('The template you selected (%1$s) requires Zabbix agent to be installed and running on your monitoring target.', '#{template_name}')
							),
							new CTag('p', true, _('Skip OS selection if you already have Zabbix agent installed.'))
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							->addClass(ZBX_STYLE_FORMATED_TEXT)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2),

				(new CSection())
					->addItem(
						(new CList([
							(new CListItem([
								(new CDiv([
									(new CTag('h6', true, [_('Verify Zabbix server, proxy, or cluster address')]))
										->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER),
									(new CFormField([
										new CTextBox('agent_script_server_host'),
										(new CDiv(
											_('Enter the IP/DNS address and port of your Zabbix server, proxy, or cluster configuration.')
										))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
									]))->addClass('js-agent-script-server-host-input')
								]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST),
								(new CDiv([
									new CTag('h5', true, _('Example:')),
									new CTag('p', true,
										'192.0.2.0:10051, [2001:db8::]:10051, zbx1.local:10051;zbx2.local:10051'
									),
									new CTag('p', true,
										_('Zabbix agent must be able to connect to the specified address or list of addresses.')
									),
									new CTag('h5', true, _('Use:')),
									new CList([
										_('Colon to separate IP/DNS address from port'),
										_('Comma to separate multiple Zabbix servers, proxies, or clusters'),
										_('Semicolon to separate clusters (one or more server addresses)'),
										_('Brackets to specify IPv6 addresses')
									])
								]))
									->addClass(ZBX_STYLE_FORM_DESCRIPTION)
									->addClass(ZBX_STYLE_MARKDOWN)
							]))
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM)
								->addClass(ZBX_STYLE_GRID_COLUMNS)
								->addClass(ZBX_STYLE_GRID_COLUMNS_2),

							(new CListItem(
								(new CDiv([
									(new CTag('h6', true, [_('Configure encryption')]))
										->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER),
									(new CDiv([
										(new CDiv(
											new CTag('p', true,
												_('Communication between Zabbix agent and server/proxy is secured with the pre-shared key (PSK) encryption method.')
											)
										))->addClass(ZBX_STYLE_FORMATED_TEXT),
										(new CDiv(
											new CTag('p', true,
												_('If you do not know your PSK or would like to change it, click the button below. Note that changing the PSK may impact existing configurations.')
											)
										))
											->addClass(ZBX_STYLE_FORMATED_TEXT)
											->addClass('js-tls-exists'),
										(new CDiv(
											(new CSimpleButton(_('Change pre-shared key')))
												->addClass(ZBX_STYLE_BTN_ALT)
												->addClass('js-tls-key-change')
										))->addClass('js-tls-exists')
									]))->addClass(ZBX_STYLE_FORMATED_GROUP),
									(new CFormField([
										new CLabel(_('Pre-shared key identity')),
										(new CTextBox('tls_psk_identity', '', false,
											DB::getFieldLength('hosts', 'tls_psk_identity')
										))->setAriaRequired(),
										(new CDiv(
											_('Enter a unique name that Zabbix components will use to recognize the pre-shared key.')
										))->addClass(ZBX_STYLE_FORM_FIELDS_HINT),
										(new CDiv(
											_('Avoid including sensitive data.')
										))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
									]))->addClass('js-tls-input'),
									(new CFormField([
										new CLabel(_('Pre-shared key')),
										(new CDiv([
											(new CTextArea('tls_psk'))
												->setMaxlength(DB::getFieldLength('hosts', 'tls_psk'))
												->setRows(3)
												->setAriaRequired(),
											(new CSimpleButton(_('Generate new')))
												->addClass(ZBX_STYLE_BTN_GREY)
												->addClass('js-generate-pre-shared-key')
										]))->addClass('pre-shared-key-field'),

										(new CDiv(
											_('Generate a secret pre-shared key hexadecimal string.')
										))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
									]))->addClass('js-tls-input')
								]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							))
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM)
								->addClass(ZBX_STYLE_GRID_COLUMNS)
								->addClass(ZBX_STYLE_GRID_COLUMNS_2),

							(new CListItem(
								(new CDiv([
									(new CTag('h6', true, [_('Select the OS of your monitoring target')]))
										->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER),
									new CFormField(
										(new CRadioCardList('monitoring_os', 'linux'))
											->addValue([
												'label' => _('Linux'),
												'value' => 'linux'
											])
											->addValue([
												'label' => _('Windows'),
												'value' => 'windows'
											])
											->addValue([
												'label' => _('Other'),
												'value' => 'other'
											])
											->addClass(ZBX_STYLE_GRID_COLUMNS)
									),
									(new CFormField([
										new CLabel(_('Select the OS distribution'), 'windows-new'),
										(new CRadioCardList('monitoring_os_distribution'))
											->addValue([
												'label' => _('Windows 10/Server 2016 or later'),
												'value' => 'windows-new'
											])
											->addValue([
												'label' => _('Older version'),
												'value' => 'windows-old'
											])
											->addClass(ZBX_STYLE_GRID_COLUMNS)
									]))->addClass('js-windows-distribution-select')
								]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							))
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM)
								->addClass(ZBX_STYLE_GRID_COLUMNS)
								->addClass(ZBX_STYLE_GRID_COLUMNS_2),

							(new CListItem(
								(new CDiv())
									->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
									->addclass('js-install-agent-readme')
							))
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM)
								->addClass(ZBX_STYLE_GRID_COLUMNS)
								->addClass(ZBX_STYLE_GRID_COLUMNS_2)

						]))->addClass(ZBX_STYLE_ORDERED_LIST)
					)
			]))->addClass('step-form-body')
		),

		new CTemplateTag('host-wizard-step-install-agent-os-linux',
			(new CListItem())
				->addItem(
					(new CTag('h6', true,
						_('Set up Zabbix agent on your monitoring target by executing the following script [bash under root]:')
					))->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('pre', true,
							"$(command -v curl || echo $(command -v wget) -O -) https://cdn.zabbix.com/scripts/install-zabbix.sh | bash -s -- --version {$agent_script_data['version']} #{server_host} #{hostname} #{psk_identity} #{psk}"
						)
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),

		new CTemplateTag('host-wizard-step-install-agent-os-windows-new',
			(new CListItem())
				->addItem(
					(new CTag('h6', true,
						_('Set up Zabbix agent on your monitoring target by executing the following PowerShell script [with administrator permissions]:')
					))->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('pre', true,
							"Invoke-WebRequest -Uri https://cdn.zabbix.com/scripts/install-zabbix.ps1 -OutFile install-zabbix.ps1"
						),
						new CTag('pre', true,
							"powershell -executionpolicy bypass .\install-zabbix.ps1 -zabbixVersion {$agent_script_data['version']} #{server_host} #{hostname} #{psk_identity} #{psk}"
						)
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),

		new CTemplateTag('host-wizard-step-install-agent-os-windows-old',
			(new CListItem())
				->addItem(
					(new CTag('h6', true,
						_('Install Zabbix agent and its plugins on your monitoring target by following the installation instructions below.')
					))->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('p', true,
							_('Note that during agent installation, you will need to configure both the PSK identity and PSK. Make sure they match the PSK identity and PSK set in step 1.')
						),
						new CTag('p', true,
							_('Additionally, make sure to complete the agent installation as described in this step, then return to this screen to continue to the next step.')
						),
						new CTag('p', true,
							(new CLink(_('Open installation instructions'),
								CDocHelper::getUrl(CDocHelper::INSTALLATION_PACKAGES_MSI)
							))->setTarget('_blank'))
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),

		new CTemplateTag('host-wizard-step-install-agent-os-other',
			(new CListItem())
				->addItem(
					(new CTag('h6', true,
						_('Install Zabbix agent and its plugins on your monitoring target by following the installation instructions below.')
					))->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('p', true,
							_('Note that during agent installation, you will need to configure both the PSK identity and PSK. Make sure they match the PSK identity and PSK set in step 1.')
						),
						new CTag('p', true,
							_('Additionally, make sure to complete the agent installation as described in this step, then return to this screen to continue to the next step.')
						),
						new CTag('p', true, [
							(new CLink(_('Open installation instructions'),
								CDocHelper::getUrl(CDocHelper::INSTALLATION_PACKAGES_MAC)
							))->setTarget('_blank'),
							' (', _('macOS'), ')'
						]),
						new CTag('p', true, [
							(new CLink(_('Open installation instructions'),
								CDocHelper::getUrl(CDocHelper::INSTALLATION_PACKAGES_OTHER)
							))->setTarget('_blank'),
							' (', _('Other OS'), ')'
						])
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		)
	];
}

function stepAddHostInterface(): array {
	return [
		new CTemplateTag('host-wizard-step-add-host-interface',
			(new CDiv(
				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, _('Add host interface')),
							new CTag('p', true,
								_s('The template you selected (%1$s) requires the %2$s to be added to the host (%3$s).',
									'#{template_name}', '#{interfaces_long}', '#{host_name}'
								)
							),
							new CTag('p', true,
								_s('Note: %1$s must be configured and enabled on your monitoring target.',
									'#{interfaces_short}'
								)
							)
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN)
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							->addClass(ZBX_STYLE_FORMATED_TEXT)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2)
			))->addClass('step-form-body')
		),

		new CTemplateTag('host-wizard-step-add-host-interface-agent',
			(new CSection())
				->addItem(
					(new CDiv([
						new CFormField([
							(new CLabel(_('Agent address')))->setAsteriskMark(),
							(new CTextBox('interfaces[#{row_index}][address]', '', false,
								DB::getFieldLength('interface', 'dns')
							))->setAriaRequired()
						]),
						new CFormField([
							(new CLabel(_('Agent port')))->setAsteriskMark(),
							(new CTextBox('interfaces[#{row_index}][port]', '', false,
								DB::getFieldLength('interface', 'port')
							))->setAriaRequired()
						]),
						(new CDiv(
							_('Enter the IP/DNS address and port of the Zabbix agent installed on your monitoring target.')
						))
							->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
							->addClass(ZBX_STYLE_GRID_COLUMN_FULL)
					]))
						->addClass(ZBX_STYLE_GRID_COLUMNS)
						->addClass(ZBX_STYLE_FORM_COLUMNS)
						->addClass(ZBX_STYLE_GRID_COLUMNS_2)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface')
		),

		new CTemplateTag('host-wizard-step-add-host-interface-snmp',
			(new CSection())
				->addItem(
					(new CDiv([
						(new CDiv([
							new CFormField([
								(new CLabel(_('SNMP address')))->setAsteriskMark(),
								(new CTextBox('interfaces[#{row_index}][address]', '', false,
									DB::getFieldLength('interface', 'dns')
								))->setAriaRequired()
							]),
							new CFormField([
								(new CLabel(_('SNMP port')))->setAsteriskMark(),
								(new CTextBox('interfaces[#{row_index}][port]', '', false,
									DB::getFieldLength('interface', 'port')
								))->setAriaRequired()
							]),
							(new CDiv(
								_('Enter the IP/DNS address, port, and authentication details of your SNMP-enabled monitoring target.')
							))
								->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
								->addClass(ZBX_STYLE_GRID_COLUMN_FULL)
						]))
							->addClass(ZBX_STYLE_GRID_COLUMNS)
							->addClass(ZBX_STYLE_FORM_COLUMNS)
							->addClass(ZBX_STYLE_GRID_COLUMNS_2),
						(new CDiv([
							new CFormField([
								(new CLabel(_('SNMP version'), 'label_interfaces_#{row_index}_details_version'))
									->setAsteriskMark(),
								(new CSelect('interfaces[#{row_index}][details][version]'))
									->addOptions(CSelect::createOptionsFromArray([
										SNMP_V1 => _('SNMPv1'),
										SNMP_V2C => _('SNMPv2'),
										SNMP_V3 => _('SNMPv3')
									]))
									->setValue(SNMP_V2C)
									->setFocusableElementId('label_interfaces_#{row_index}_details_version')
									->setWidthAuto()
							]),
							(new CFormField([
								(new CLabel(_('SNMP community'), 'interfaces[#{row_index}][details][community]'))
									->setAsteriskMark(),
								(new CTextBox('interfaces[#{row_index}][details][community]', '', false,
									DB::getFieldLength('interface_snmp', 'community')
								))->setAriaRequired()
							]))->addClass('js-snmp-community'),
							(new CFormField([
								(new CLabel([
									_('Max repetition count'),
									makeHelpIcon(_('Max repetition count is applicable to discovery and walk only.'))
								], 'interfaces[#{row_index}][details][max_repetitions]')),
								new CNumericBox('interfaces[#{row_index}][details][max_repetitions]', 0, 10,
									false, false, false
								)
							]))->addClass('js-snmp-repetition-count'),
							(new CFormField([
								new CLabel(_('Context name'), 'interfaces[#{row_index}][details][contextname]'),
								new CTextBox('interfaces[#{row_index}][details][contextname]', '', false,
									DB::getFieldLength('interface_snmp', 'contextname')
								)
							]))->addClass('js-snmpv3-contextname'),
							(new CFormField([
								new CLabel(_('Security name'), 'interfaces[#{row_index}][details][securityname]'),
								new CTextBox('interfaces[#{row_index}][details][securityname]', '', false,
									DB::getFieldLength('interface_snmp', 'securityname')
								)
							]))->addClass('js-snmpv3-securityname'),
							(new CFormField([
								new CLabel(_('Security level'),
									'label_interfaces_#{row_index}_details_securitylevel'
								),
								(new CSelect('interfaces[#{row_index}][details][securitylevel]'))
									->addOptions(CSelect::createOptionsFromArray([
										ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
										ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
										ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
									]))
									->setFocusableElementId('label_interfaces_#{row_index}_details_securitylevel')
									->setWidthAuto()
							]))->addClass('js-snmpv3-securitylevel'),
							(new CFormField([
								new CLabel(_('Authentication protocol'),
									'label_interfaces_#{row_index}_details_authprotocol'
								),
								(new CSelect('interfaces[#{row_index}][details][authprotocol]'))
									->addOptions(CSelect::createOptionsFromArray(getSnmpV3AuthProtocols()))
									->setFocusableElementId('label_interfaces_#{row_index}_details_authprotocol')
									->setWidthAuto()
							]))->addClass('js-snmpv3-authprotocol'),
							(new CFormField([
								new CLabel(_('Authentication passphrase'),
									'interfaces[#{row_index}][details][authpassphrase]'
								),
								(new CTextBox('interfaces[#{row_index}][details][authpassphrase]', '', false,
									DB::getFieldLength('interface_snmp', 'authpassphrase')
								))->disableAutocomplete()
							]))->addClass('js-snmpv3-authpassphrase'),
							(new CFormField([
								new CLabel(_('Privacy protocol'),
									'label_interfaces[#{row_index}][details][privprotocol]'
								),
								(new CSelect('interfaces[#{row_index}][details][privprotocol]'))
									->addOptions(CSelect::createOptionsFromArray(getSnmpV3PrivProtocols()))
									->setFocusableElementId('label_interfaces[#{row_index}][details][privprotocol]')
									->setWidthAuto()
							]))->addClass('js-snmpv3-privprotocol'),
							(new CFormField([
								new CLabel(_('Privacy passphrase'),
									'interfaces[#{row_index}][details][privpassphrase]'
								),
								(new CTextBox('interfaces[#{row_index}][details][privpassphrase]', '', false,
									DB::getFieldLength('interface_snmp', 'privpassphrase')
								))->disableAutocomplete()
							]))->addClass('js-snmpv3-privpassphrase'),
							new CFormField(
								(new CCheckBox('interfaces[#{row_index}][details][bulk]', SNMP_BULK_ENABLED))
									->setLabel(_('Use combined requests'))
							)
						]))->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
					]))
						->addClass(ZBX_STYLE_FORM_COLUMNS)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface')
		),

		new CTemplateTag('host-wizard-step-add-host-interface-ipmi',
			(new CSection())
				->addItem(
					(new CDiv([
						(new CDiv([
							new CFormField([
								(new CLabel(_('IPMI address')))->setAsteriskMark(),
								(new CTextBox('interfaces[#{row_index}][address]', '', false,
									DB::getFieldLength('interface', 'dns')
								))->setAriaRequired()
							]),
							new CFormField([
								(new CLabel(_('IPMI port')))->setAsteriskMark(),
								(new CTextBox('interfaces[#{row_index}][port]', '', false,
									DB::getFieldLength('interface', 'port')
								))->setAriaRequired()
							]),
							(new CDiv(
								_('Enter the IP/DNS address and port of your IPMI-enabled monitoring target.')
							))
								->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
								->addClass(ZBX_STYLE_GRID_COLUMN_FULL)
						]))
							->addClass(ZBX_STYLE_GRID_COLUMNS)
							->addClass(ZBX_STYLE_FORM_COLUMNS)
							->addClass(ZBX_STYLE_GRID_COLUMNS_2),
						(new CDiv([
							new CFormField([
								new CLabel(_('Authentication algorithm'), 'label_ipmi_authtype'),
								(new CSelect('ipmi_authtype'))
									->addOptions(CSelect::createOptionsFromArray(ipmiAuthTypes()))
									->setFocusableElementId('label_ipmi_authtype')
									->setWidthAuto()
							]),
							new CFormField([
								new CLabel(_('Privilege level'), 'label_ipmi_privilege'),
								(new CSelect('ipmi_privilege'))
									->addOptions(CSelect::createOptionsFromArray(ipmiPrivileges()))
									->setFocusableElementId('label_ipmi_privilege')
									->setWidthAuto()
							]),
							new CFormField([
								new CLabel(_('Username'), 'ipmi_username'),
								(new CTextBox('ipmi_username', '', false, DB::getFieldLength('hosts', 'ipmi_username')))
									->disableAutocomplete()
							]),
							new CFormField([
								new CLabel(_('Password'), 'ipmi_password'),
								(new CTextBox('ipmi_password', '', false, DB::getFieldLength('hosts', 'ipmi_password')))
									->disableAutocomplete()
							])
						]))->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
					]))
						->addClass(ZBX_STYLE_FORM_COLUMNS)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem(
					(new CDiv([
						new CTag('h4', true, _('Enable IPMI checks on Zabbix server')),
						new CTag('p', true,
							_('In the Zabbix server configuration file (zabbix_server.conf), set the StartIPMIPollers parameter to a non-zero value.')
						),
						new CTag('p', true, [
							_('For more details, see'),
							' ',
							(new CLink(_('IPMI checks'), CDocHelper::getUrl(CDocHelper::ITEM_TYPES_IPMI_AGENT)))
								->setTarget('_blank')
						])
					]))
						->addClass(ZBX_STYLE_FORM_DESCRIPTION)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface')
		),

		new CTemplateTag('host-wizard-step-add-host-interface-jmx',
			(new CSection())
				->addItem(
					(new CDiv([
						new CFormField([
							(new CLabel(_('JMX address')))->setAsteriskMark(),
							(new CTextBox('interfaces[#{row_index}][address]', '', false,
								DB::getFieldLength('interface', 'dns')
							))->setAriaRequired()
						]),
						new CFormField([
							(new CLabel(_('JMX port')))->setAsteriskMark(),
							(new CTextBox('interfaces[#{row_index}][port]', '', false,
								DB::getFieldLength('interface', 'port')
							))->setAriaRequired()
						]),
						(new CDiv(
							_('Enter the IP/DNS address and port of your JMX-enabled monitoring target.')
						))
							->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
							->addClass(ZBX_STYLE_GRID_COLUMN_FULL)
					]))
						->addClass(ZBX_STYLE_GRID_COLUMNS)
						->addClass(ZBX_STYLE_FORM_COLUMNS)
						->addClass(ZBX_STYLE_GRID_COLUMNS_2)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem(
					(new CDiv([
						new CTag('h4', true, _('Enable remote JMX monitoring')),
						new CTag('p', true, [
							_('1. Install Java Gateway on the same machine running Zabbix server by following the instructions in'),
							' ',
							(new CLink(_('Zabbix documentation'), CDocHelper::getUrl(CDocHelper::ITEM_TYPES_JMX_AGENT)))
								->setTarget('_blank')
						]),
						new CTag('p', true,
							_('2. Configure your Java application to support remote JMX monitoring. For example:')
						),
						new CTag('pre', true,
							"JAVA_OPTS=\"-Dcom.sun.management.jmxremote \\\n-Dcom.sun.management.jmxremote.local.only=false \\\n-Dcom.sun.management.jmxremote.port=<JMX port> \\\n-Dcom.sun.management.jmxremote.rmi.port=<JMX port> \\\n-Dcom.sun.management.jmxremote.authenticate=false \\\n-Dcom.sun.management.jmxremote.ssl=false \\\n-Djava.rmi.server.hostname=<JMX address>\""
						),
						new CTag('p', true, [
							_('For more details, see'), ' ',
							(new CLink(_('JMX monitoring'), CDocHelper::getUrl(CDocHelper::PROCESSES_JAVA_GATEWAY)))
								->setTarget('_blank')
						])
					]))
						->addClass(ZBX_STYLE_FORM_DESCRIPTION)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface')
		)
	];
}

function stepReadme(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-readme',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, [
							_('Configure host'),
							(new CSpan('(1/2)'))->addClass('sub-step-counter')
						]),
						new CTag('p', true,
							_s('The template you selected (%1$s) requires additional configuration.',
								'#{template_name}'
							)
						)
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS),

			(new CSection())
				->addItem(
					(new CDiv())->addClass('js-readme')
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
		]))->addClass('step-form-body')
	);
}

function stepConfigureHost(): array {
	return [
		new CTemplateTag('host-wizard-step-configure-host',
			(new CDiv(
				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, [
								_('Configure host'),
								(new CSpan('(2/2)'))->addClass('sub-step-counter')
							]),
							new CTag('p', true,
								_('To complete the setup, configure the following variables (host macros).')
							)
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							->addClass(ZBX_STYLE_FORMATED_TEXT)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2)

			))->addClass('step-form-body')
		),
		new CTemplateTag('host-wizard-step-configure-host-macros-section',
			(new CSection())
				->addItem(
					(new CDiv())->addClass('host-macro-list')
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		),
		new CTemplateTag('host-wizard-step-configure-host-macros-collapsible-section',
			(new CSectionCollapsible())
				->setToggleLabel(_('#{section_name}'))
				->addItem(
					(new CDiv())->addClass('host-macro-list')
				)
				->setExpanded(false)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		),
		new CTemplateTag('host-wizard-macro-field-checkbox',
			(new CFormField([
				(new CCheckBox('macros[#{row_index}][value]', '#{value}'))
					->setLabel('#{label}')
					->setUncheckedValue('#{unchecked_value}')
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-checkbox')
		),
		new CTemplateTag('host-wizard-macro-field-select',
			(new CFormField([
				new CLabel('#{label}'),
				(new CSelect('macros[#{row_index}][value]'))
					->setValue('#{value}')
					->setWidthAuto()
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-list')
		),
		new CTemplateTag('host-wizard-macro-field-radio',
			(new CFormField([
				new CLabel('#{label}'),
				(new CRadioButtonList('macros[#{row_index}][value]'))->setModern()
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-radio')
		),
		new CTemplateTag('host-wizard-macro-field-text',
			(new CFormField([
				new CLabel('#{label}'),
				(new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{row_index}]', null, false))->addRevertButton()
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-text')
		),
		new CTemplateTag('host-wizard-macro-field-secret',
			(new CFormField([
				new CLabel('#{label}'),
				(new CMacroValue(ZBX_MACRO_TYPE_SECRET, 'macros[#{row_index}]', null, false))->addRevertButton()
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-text')
		),
		new CTemplateTag('host-wizard-macro-field-vault',
			(new CFormField([
				new CLabel('#{label}'),
				(new CMacroValue(ZBX_MACRO_TYPE_VAULT, 'macros[#{row_index}]', null, false))->addRevertButton()
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-text')
		)
	];
}

function stepConfigurationFinish(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-configuration-finish',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Configure host')),
						new CTag('p', true, _s('Click %1$s to complete the setup.', '#{button_label}'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function stepComplete(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-complete',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Configuration complete')),
						new CTag('p', true, '#{complete_message}')
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function cancelScreen(): CTemplateTag {
	return new CTemplateTag('host-wizard-cancel-screen',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Cancel Host Wizard?')),
						new CTag('p', true,
							_('Your progress will be discarded and you will exit the Host Wizard. Are you sure you want to cancel?')
						)
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function helpHint(mixed $help_text): CButtonIcon {
	return (new CButtonIcon(ZBX_ICON_CIRCLE_QUESTION))
		->setSmall()
		->setHint($help_text, ZBX_STYLE_HINTBOX_WRAP);
}
