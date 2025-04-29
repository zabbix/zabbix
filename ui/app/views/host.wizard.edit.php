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

$output = [
	'header' => '',
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_WIZARD),
	'body' => (new CForm())
		->setId('host-wizard-form')
		->setName('host_wizard_form')
		->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
		->addItem(
			(new CDiv())->addClass('step-form-body')
		)
		->addItem([
			stepWelcome(),
			stepSelectTemplate($data['old_template_count']),
			stepCreateHost(),
			stepInstallAgent(),
			stepAddHostInterface(),
			stepReadme(),
			stepConfigureHost(),
			stepConfigurationFinish(),
			stepComplete()
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
			'class' => 'js-back',
		],
		[
			'title' => _('Next'),
			'class' => 'js-next',
		],
		[
			'title' => _('Create'),
			'class' => 'js-create',
		],
		[
			'title' => _('Finish'),
			'class' => 'js-finish',
		]
	],
	'script_inline' => $this->readJsFile('host.wizard.edit.js.php')
		.'host_wizard_edit.init('.json_encode([
			'templates' => $data['templates'],
			'linked_templates' => $data['linked_templates'],
			'wizard_hide_welcome' => $data['wizard_hide_welcome'],
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
						new CTag('p', true, _('The Host Wizard will help you set up your monitoring target (device, application, service, etc.) in Zabbix.')),
						new CTag('p', true, _('You can always access Host Wizard from Data Collection > Hosts.'))
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
						_s('Some templates (%1$s) are hidden. Custom templates are not supported.', $old_template_count)
					)
					: null,

				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, _('Select a template')),
							new CTag('p', true, _('A template is a set of predefined configurations (metrics to be collected, conditions for generating alerts, etc.) designed for your monitoring target.')),
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
								new CLabel(_('Data collection')),
								(new CRadioButtonList('data_collection'))
									->addValue(_('All'), ZBX_TEMPLATE_DATA_COLLECTION_ANY)
									->addValue(_('Agent-based'), ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED)
									->addValue(_('Agentless'), ZBX_TEMPLATE_DATA_COLLECTION_AGENTLESS)
									->setModern()
							]),
							new CFormField([
								new CLabel(_('Agent mode')),
								(new CRadioButtonList('agent_mode'))
									->addValue(_('All'), ZBX_TEMPLATE_AGENT_MODE_ANY)
									->addValue(_('Active'), ZBX_TEMPLATE_AGENT_MODE_ACTIVE)
									->addValue(_('Passive'), ZBX_TEMPLATE_AGENT_MODE_PASSIVE)
									->setModern()
							]),
							new CFormField([
								new CLabel(_('Show templates')),
								(new CRadioButtonList('show_templates'))
									->addValue(_('All'), ZBX_TEMPLATE_SHOW_ANY)
									->addValue(_('Linked'), ZBX_TEMPLATE_SHOW_LINKED)
									->addValue(_('Not linked'), ZBX_TEMPLATE_SHOW_NOT_LINKED)
									->setModern()
							])
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
				->setToggleLabel(_('#{title}').' (#{count})')
				->addItem(
					(new CDiv())
						->addClass(ZBX_STYLE_GRID_COLUMNS)
						->addClass(ZBX_STYLE_GRID_COLUMNS_4)
						->addClass(CRadioCardList::ZBX_STYLE_CLASS)
						->addClass('templates-card-list')
				)
		),
		new CTemplateTag('host-wizard-template-card',
			(new CDiv())
				->addItem(
					(new CLabel([
						'#{title}',
						(new CSpan(
							(new CInput('radio', null, '#{templateid}'))->setAttribute('name', 'template_selected')
						))->addClass(CRadioCardList::ZBX_STYLE_CLASS_SELECTOR)
					]))->addClass(CRadioCardList::ZBX_STYLE_CLASS_LABEL),
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
							->addClass('js-template-info-expand'),
						(new CButtonLink(
							new CSpan(_('Show less'))
						))
							->addClass(ZBX_ICON_CHEVRON_UP)
							->addClass('js-template-info-collapse')
					]))->addClass('template-info-toggles')
				)
				->addClass(CRadioCardList::ZBX_STYLE_CLASS_CARD)
		)
	];
}

function stepCreateHost(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-create-host',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Create or select a host')),
						new CTag('p', true, _('The template you selected (#{template_name}) must be linked to a host – an entity in Zabbix that represents your monitoring target.')),
						new CTag('p', true, _('Hosts are organized into host groups for easier management and access control.'))
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
							'name' => 'hostid',
							'object_name' => 'hosts',
							'add_new' => true,
							'multiple' => false,
							'popup' => [
								'parameters' => [
									'srctbl' => 'hosts',
									'srcfld1' => 'hostid',
									'dstfrm' => '', //$form->getName(),
									'dstfld1' => 'hostid',
									'editable' => true
								]
							],
							'add_post_js' => false
						]),
						(new CDiv(
							_('Start typing or click Select to choose an existing host, or enter a new host name.')
						))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem(
					(new CFormField([
						new CLabel(_('Host groups')),
						new CMultiSelect([
							'name' => 'groups[]',
							'object_name' => 'hostGroup',
							'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
							'popup' => [
								'parameters' => [
									'srctbl' => 'host_groups',
									'srcfld1' => 'groupid',
									'dstfrm' => '', //$form->getName(),
									'dstfld1' => 'groups_',
									'editable' => true
								]
							],
							'add_post_js' => false
						]),
						(new CDiv(
							_('Start typing or click Select to choose existing host groups, or enter a new host group name.')
						))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function stepInstallAgent(): array {
	return [
		new CTemplateTag('host-wizard-step-install-agent',
			(new CDiv([
				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, _('Install Zabbix agent')),
							new CTag('p', true, _('The template you selected (Apache by HTTP, this is just an example) requires Zabbix agent to be installed and running on your monitoring target.')),
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
							(new CListItem())
								->addItem(
									(new CTag('h6', true, [_('Configure encryption')]))
										->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
								)
								->addItem(
									(new CDiv(
										new CTag('p', true, _('Communication between Zabbix agent and server/proxy is secured with a unique user-defined pre-shared key identity and a secret pre-shared key linked to it.'))
									))->addClass(ZBX_STYLE_FORMATED_TEXT)
								)
								->addItem(
									new CFormField([
										new CLabel(_('Pre-shared key identity')),
										new CTextBox('tls_psk_identity'),
										(new CDiv(
											_('Enter a non-secret pre-shared key identity string. Avoid including sensitive data.')
										))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
									])
								)
								->addItem(
									new CFormField([
										new CLabel(_('Pre-shared key')),
										(new CDiv([
											(new CTextArea('tls_psk'))
												->setRows(3),
											(new CSimpleButton(_('Generate new')))
												->addClass(ZBX_STYLE_BTN_GREY)
												->addClass('js-generate-pre-shared-key'),
										]))->addClass('pre-shared-key-field'),

										(new CDiv(
											_('Generate a secret pre-shared key hexadecimal string.')
										))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
									])
								)
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM),

							(new CListItem())
								->addItem(
									(new CTag('h6', true, [_('Select the OS of your monitoring target')]))
										->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
								)
								->addItem(
									new CFormField(
										(new CRadioCardList('monitoring_os', 'linux'))
											->addValue(['label' => _('Linux'), 'value' => 'linux'])
											->addValue(['label' => _('Windows'), 'value' => 'windows'])
											->addValue(['label' => _('Other'), 'value' => 'other'])
											->addClass(ZBX_STYLE_GRID_COLUMNS)
									)
								)
								->addItem(
									(new CFormField([
										new CLabel(_('Select the OS distribution'), 'windows-new'),
										(new CRadioCardList('monitoring_os_distribution'))
											->addValue(['label' => _('Windows 10/Server 2016 or later'), 'value' => 'windows-new'])
											->addValue(['label' => _('Older version'), 'value' => 'windows-old'])
											->addClass(ZBX_STYLE_GRID_COLUMNS)
									]))->addClass('js-windows-distribution-select')
								)
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM),

							(new CListItem())
								->addClass(ZBX_STYLE_ORDERED_LIST_ITEM)
								->addclass('js-install-agent-readme')
						]))
							->addClass(ZBX_STYLE_ORDERED_LIST)
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2)
			]))->addClass('step-form-body')
		),

		new CTemplateTag('host-wizard-step-install-agent-os-linux',
			(new CListItem())
				->addItem(
					(new CTag('h6', true, [_('Set up Zabbix agent on your monitoring target by executing the following script [bash under root]:')]))
						->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('pre', true, "$(command -v curl || echo $(command -v wget) -O -) https://cdn.zabbix.com/scripts/#{version}/install-zabbix.sh | bash -s -- --agent2 --server-host #{serverHost} --hostname #{hostname} --psk-identity string --psk XXXXXXXX"),
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),

		new CTemplateTag('host-wizard-step-install-agent-os-windows-new',
			(new CListItem())
				->addItem(
					(new CTag('h6', true, [_('Set up Zabbix agent on your monitoring target by executing the following PowerShell script [with administrator permissions]:')]))
						->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('pre', true, "Invoke-WebRequest -Uri https://cdn.zabbix.com/scripts/#{version}/install-zabbix.ps1 -OutFile install-zabbix.ps1"),
						new CTag('pre', true, "powershell -executionpolicy bypass .\install-zabbix.ps1 -agent2 -serverHost #{serverHost} -hostname '#{hostname}' -pskIdentity 'qeqweqweqweqwe' -psk AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"),
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),

		new CTemplateTag('host-wizard-step-install-agent-os-windows-old',
			(new CListItem())
				->addItem(
					(new CTag('h6', true, [_('Install Zabbix agent 2 and its plugins on your monitoring target by following the installation instructions below.')]))
						->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('p', true, _('Note that during agent installation, you will need to configure both the PSK identity and PSK. Make sure they match the PSK identity and PSK set in step 1.')),
						new CTag('p', true, _('Additionally, make sure to complete the agent installation as described in this step, then return to this screen to continue to the next step.')),
						new CTag('p', true, new CLink(_('Open installation instructions'), CDocHelper::getUrl(CDocHelper::INSTALLATION_PACKAGES_MSI)))
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),

		new CTemplateTag('host-wizard-step-install-agent-os-other',
			(new CListItem())
				->addItem(
					(new CTag('h6', true, [_('Install Zabbix agent 2 and its plugins on your monitoring target by following the installation instructions below.')]))
						->addClass(ZBX_STYLE_ORDERED_LIST_COUNTER)
				)
				->addItem(
					(new CDiv([
						new CTag('p', true, _('Note that during agent installation, you will need to configure both the PSK identity and PSK. Make sure they match the PSK identity and PSK set in step 1.')),
						new CTag('p', true, _('Additionally, make sure to complete the agent installation as described in this step, then return to this screen to continue to the next step.')),
						new CTag('p', true, [new CLink(_('Open installation instructions'), CDocHelper::getUrl(CDocHelper::INSTALLATION_PACKAGES_MAC)), ' (', _s('Mac OS'), ')']),
						new CTag('p', true, [new CLink(_('Open installation instructions'), CDocHelper::getUrl(CDocHelper::INSTALLATION_PACKAGES_OTHER)), ' (', _s('Other OS'), ')'])
					]))->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
		),
	];
}

function stepAddHostInterface(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-add-host-interface',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Add host interface')),
						new CTag('p', true, _('The template you selected (Apache by HTTP) requires the <Agent interface / Intelligent Platform Management Interface (IPMI) / Java Management Extensions (JMX) interface / Simple Network Management Protocol (SNMP) interface> to be added to the host (PostgreSQL).')),
						new CTag('p', true, _('SNote: <IPMI/JMX/SNMP> must be configured and enabled on your monitoring target.'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),
			(new CSection())
				->addItem(
					(new CDiv([
						new CFormField([
							new CLabel(_('Agent address')),
							new CTextBox('address')
						]),
						new CFormField([
							new CLabel(_('Agent port')),
							new CTextBox('port', '10050')
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
				->addItem(
					(new CDiv([
						new CTag('h4', true, _('Enable IPMI checks on Zabbix server')),
						new CTag('p', true, _('In the Zabbix server configuration file (zabbix_server.conf), set the StartIPMIPollers parameter to a non-zero value.')),
						new CTag('p', true, [
							_('For more details, see'),
							' ',
							new CLink(_('IPMI checks'), '#')
						])
					]))
						->addClass(ZBX_STYLE_FORM_DESCRIPTION)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface-'.INTERFACE_TYPE_AGENT),
			(new CSection())
				->addItem(
					(new CDiv([
						new CFormField([
							new CLabel(_('IPMI address')),
							new CTextBox('address')
						]),
						new CFormField([
							new CLabel(_('IPMI port')),
							new CTextBox('port', '10050')
						]),
						(new CDiv(
							_('Enter the IP/DNS address and port of your IPMI-enabled monitoring target.')
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
						new CFormField([
							new CLabel(_('Authentication algorithm'), 'label_ipmi_authtype'),
							(new CSelect('ipmi_authtype'))
								->addOptions(CSelect::createOptionsFromArray(ipmiAuthTypes()))
								->setWidthAuto()
						]),
						new CFormField([
							new CLabel(_('Privilege level'), 'label_ipmi_privilege'),
							(new CSelect('ipmi_privilege'))
								->addOptions(CSelect::createOptionsFromArray(ipmiPrivileges()))
								->setWidthAuto()
						]),
						new CFormField([
							new CLabel(_('Username'), 'ipmi_username'),
							new CTextBox('ipmi_username')
						]),
						new CFormField([
							new CLabel(_('Password'), 'ipmi_password'),
							new CTextBox('ipmi_password')
						]),
					]))
						->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface-'.INTERFACE_TYPE_IPMI),
			(new CSection())
				->addItem(
					(new CDiv([
						new CFormField([
							new CLabel(_('JMX address')),
							new CTextBox('address')
						]),
						new CFormField([
							new CLabel(_('JMX port')),
							new CTextBox('port', '1089')
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
							new CLink(_('Zabbix documentation'), '#')
						]),
						new CTag('p', true, _('2. Configure your Java application to support remote JMX monitoring. For example:')),
						new CTag('pre', true, "JAVA_OPTS=\"-Dcom.sun.management.jmxremote \\\n-Dcom.sun.management.jmxremote.local.only=false \\\n-Dcom.sun.management.jmxremote.port=<JMX port> \\\n-Dcom.sun.management.jmxremote.rmi.port=<JMX port> \\\n-Dcom.sun.management.jmxremote.authenticate=false \\\n-Dcom.sun.management.jmxremote.ssl=false \\\n-Djava.rmi.server.hostname=<JMX address>\""),
						new CTag('p', true, [_('For more details, see'), ' ', new CLink(_('JMX monitoring'), '#')])
					]))
						->addClass(ZBX_STYLE_FORM_DESCRIPTION)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface-'.INTERFACE_TYPE_JMX),
			(new CSection())
				->addItem(
					(new CDiv([
						new CFormField([
							new CLabel(_('SNMP address')),
							new CTextBox('address')
						]),
						new CFormField([
							new CLabel(_('SNMP port')),
							new CTextBox('port', '444')
						]),
						(new CDiv(
							_('Enter the IP/DNS address, port, and authentication details of your SNMP-enabled monitoring target.')
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
						new CFormField([
							new CLabel(_('SNMP version'), 'label_ipmi_authtype'),
							(new CSelect('ipmi_authtype'))
								->addOptions(CSelect::createOptionsFromArray([
									SNMP_V1 => _('SNMPv1'),
									SNMP_V2C => _('SNMPv2'),
									SNMP_V3 => _('SNMPv3')
								]))
								->setValue(SNMP_V2C)
								->setWidthAuto()
						]),
						new CFormField([
							new CLabel(_('Context name'), 'label_ipmi_privilege'),
							new CTextBox('contextname', '', false, DB::getFieldLength('interface_snmp', 'contextname'))
						]),
						new CFormField([
							new CLabel(_('Security name'), 'ipmi_username'),
							new CTextBox('ipmi_username')
						]),
						new CFormField([
							new CLabel(_('Security level'), 'ipmi_password'),
							(new CSelect('securitylevel'))
								->addOptions(CSelect::createOptionsFromArray([
									ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
									ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
									ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
								]))
								->setValue(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV)
								->setFocusableElementId('label_interfaces_#{iface.interfaceid}_details_securitylevel')
								->setWidthAuto()
						]),
						new CFormField([
							(new CCheckBox('interfaces[#{iface.interfaceid}][details][bulk]', SNMP_BULK_ENABLED))
								->setLabel(_('Use combined requests'))
						])
					]))
						->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
				->addClass('js-host-interface-'.INTERFACE_TYPE_SNMP)
		]))->addClass('step-form-body')
	);
}

function stepReadme(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-readme',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Configure host')),
						new CTag('p', true, _('The template you selected (Apache by HTTP) requires additional configuration.'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),

			(new CSection())
				->addItem(
					(new CDiv())
						->addClass(ZBX_STYLE_MARKDOWN)
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}

function stepConfigureHost(): array {
	return [
		new CTemplateTag('host-wizard-step-configure-host',
			(new CDiv([
				(new CSection())
					->addItem(
						(new CDiv([
							new CTag('h1', true, _('Configure host')),
							new CTag('p', true, _('To complete the setup, configure the following variables (host macros).')),
						]))
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
							->addClass(ZBX_STYLE_FORMATED_TEXT)
					)
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2),
				(new CSection())
					->addClass(ZBX_STYLE_GRID_COLUMNS)
					->addClass(ZBX_STYLE_GRID_COLUMNS_2)
					->addClass('js-host-macro-list'),
			]))->addClass('step-form-body')
		),
		new CTemplateTag('host-wizard-macro-field-checkbox',
			(new CFormField([
				(new CCheckBox('macros[#{index}][value]', '#{value}'))
					->setLabel('#{label}')
					->setUncheckedValue('#{unchecked_value}'),
				(new CDiv('#{macro}'))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-checkbox')
		),
		new CTemplateTag('host-wizard-macro-field-select',
			(new CFormField([
				new CLabel('#{label}'),
				(new CSelect('macros[#{index}][value]'))->setWidthAuto(),
				(new CDiv('#{macro}'))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-list')
		),
		new CTemplateTag('host-wizard-macro-field-radio',
			(new CFormField([
				new CLabel('#{label}'),
				(new CRadioButtonList('macros[#{index}][value]'))->setModern(),
				(new CDiv('#{macro}'))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-radio')
		),
		new CTemplateTag('host-wizard-macro-field-text',
			(new CFormField([
				new CLabel('#{label}'),
				new CMacroValue(ZBX_MACRO_TYPE_TEXT, 'macros[#{index}]', null, false),
				(new CDiv('#{macro}'))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-text')
		),
		new CTemplateTag('host-wizard-macro-field-secret',
			(new CFormField([
				new CLabel('#{label}'),
				new CMacroValue(ZBX_MACRO_TYPE_SECRET, 'macros[#{index}]', null, false),
				(new CDiv('#{macro}'))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
			]))
				->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				->addClass('field-text')
		),
		new CTemplateTag('host-wizard-macro-field-vault',
			(new CFormField([
				new CLabel('#{label}'),
				new CMacroValue(ZBX_MACRO_TYPE_VAULT, 'macros[#{index}]', null, false),
				(new CDiv('#{macro}'))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
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
						new CTag('p', true, _('Click Create to complete the setup.'))
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
						new CTag('p', true, _s('Click Finish to navigate to the Latest data section and view the most recent data for your host (%1$s).', '#{host_name}'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_FORMATED_TEXT)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2)
		]))->addClass('step-form-body')
	);
}
