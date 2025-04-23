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
	'body' => (new CDiv())
		->addItem(
			(new CDiv([
				(new CDiv([_('Select a template'), BR(), 'Apache by HTTP']))
					->addClass('progress-step progress-step-complete'),
				(new CDiv(_('Create or select a host')))
					->addClass('progress-step progress-step-complete'),
				(new CDiv(_('Install Zabbix agent')))
					->addClass('progress-step'),
				(new CDiv(_('Add host interface')))
					->addClass('progress-step'),
				(new CDiv(_('Configure host')))
					->addClass('progress-step progress-step-disabled'),
			]))->addClass('progress')
		)
		->addItem(
			(new CDiv())->addClass('step-form-body')
		)
		->addItem([
			stepWelcome(),
			stepSelectTemplate($data['old_template_count']),
			stepSelectCreateHost(),
			stepSelectInstallAgent()
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
		]
	],
	'script_inline' => $this->readJsFile('host.wizard.edit.js.php')
		.'host_wizard_edit.init('.json_encode([
			'templates' => $data['templates'],
			'linked_templates' => $data['linked_templates'],
			'wizard_hide_welcome' => $data['wizard_hide_welcome']
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
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),

			(new CSection())
				->addItem(
					(new CFormField([
						(new CCheckBox('do-not-show-welcome'))->setLabel(_('Do not show welcome screen'))
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
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
							->addClass(ZBX_STYLE_MARKDOWN)
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
								(new CRadioButtonList('data_collection', ZBX_TEMPLATE_DATA_COLLECTION_ANY))
									->addValue(_('All'), ZBX_TEMPLATE_DATA_COLLECTION_ANY)
									->addValue(_('Agent-based'), ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED)
									->addValue(_('Agentless'), ZBX_TEMPLATE_DATA_COLLECTION_AGENTLESS)
									->setModern()
							]),
							new CFormField([
								new CLabel(_('Agent mode')),
								(new CRadioButtonList('agent_mode', ZBX_TEMPLATE_AGENT_MODE_ANY))
									->addValue(_('All'), ZBX_TEMPLATE_AGENT_MODE_ANY)
									->addValue(_('Active'), ZBX_TEMPLATE_AGENT_MODE_ACTIVE)
									->addValue(_('Passive'), ZBX_TEMPLATE_AGENT_MODE_PASSIVE)
									->setModern()
							]),
							new CFormField([
								new CLabel(_('Show templates')),
								(new CRadioButtonList('show_templates', ZBX_TEMPLATE_SHOW_ANY))
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
							->addClass(ZBX_STYLE_MARKDOWN)
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
							new CInput('radio', 'template', '#{templateid)')
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
		),
		new CTemplateTag('host-wizard-template-tag',
			(new CSpan('#{tag}: #{value}'))->addClass(ZBX_STYLE_TAG)
		),
		new CTemplateTag('host-wizard-template-tag-more',
			new CButtonIcon(ZBX_ICON_MORE)
		)
	];
}

function stepSelectCreateHost(): CTemplateTag {
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
						->addClass(ZBX_STYLE_MARKDOWN)
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
							'selectedLimit' => 1,
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

function stepSelectInstallAgent(): CTemplateTag {
	return new CTemplateTag('host-wizard-step-install-agent',
		(new CDiv([
			(new CSection())
				->addItem(
					(new CDiv([
						new CTag('h1', true, _('Install Zabbix agent')),
						new CTag('p', true, _('The template you selected (Apache by HTTP, this is just an example) requires Zabbix agent to be installed and running on your monitoring target.')),
						new CTag('p', true, _('Skip OS selection if you already have Zabbix agent installed.'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),

			(new CSection())
				->addItem(
					(new CDiv([
						(new CTag('h6', true, [new CSpan('1.'), _('Configure encryption')]))
							->addClass('list-item'),
						new CTag('p', true, _('Communication between Zabbix agent and server/proxy is secured with a unique user-defined pre-shared key identity and a secret pre-shared key linked to it.'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addItem(
					(new CFormField([
						new CLabel(_('Pre-shared key identity')),
						new CTextBox('tls_psk_identity'),
						(new CDiv(
							_('Enter a non-secret pre-shared key identity string. Avoid including sensitive data.')
						))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem(
					(new CFormField([
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
					]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem(
					(new CDiv([
						(new CTag('h6', true, [new CSpan('2.'), _('Select the OS of your monitoring target')]))
							->addClass('list-item'),
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addItem(
					(new CFormField(
						(new CRadioCardList('monitoring_os', 'linux'))
							->addValue(['label' => _('Linux'), 'value' => 'linux'])
							->addValue(['label' => _('Windows'), 'value' => 'windows'])
							->addValue(['label' => _('Other'), 'value' => 'other'])
							->addClass(ZBX_STYLE_GRID_COLUMNS)
					))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
				)
				->addItem(
					(new CFormField([
						new CLabel(_('Select the OS distribution'), 'windows-new'),
						(new CRadioCardList('monitoring_os_distribution'))
							->addValue(['label' => _('Windows 10/Server 2016 or later'), 'value' => 'windows-new'])
							->addValue(['label' => _('Older version'), 'value' => 'windows-old'])
							->addClass(ZBX_STYLE_GRID_COLUMNS)
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass('js-windows-distribution-select')
				)
				->addItem(
					(new CDiv([
						(new CTag('h6', true, [new CSpan('3.'), _('Install Zabbix agent 2 and its plugins on your monitoring target by following the installation instructions below.')]))
							->addClass('list-item'),
						new CTag('p', true, _('Note that during agent installation, you will need to configure both the PSK identity and PSK. Make sure they match the PSK identity and PSK set in step 1.')),
						new CTag('p', true, _('Additionally, make sure to complete the agent installation as described in this step, then return to this screen to continue to the next step.'))
					]))
						->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
						->addClass(ZBX_STYLE_MARKDOWN)
				)
				->addClass(ZBX_STYLE_GRID_COLUMNS)
				->addClass(ZBX_STYLE_GRID_COLUMNS_2),
		]))->addClass('step-form-body')
	);
}







/*
$data['form_name'] = 'host-wizard-form';

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('host')))->removeId())
	->setId($data['form_name'])
	->setName($data['form_name'])
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', $data['form_action'])
		->getUrl()
	)
	->addVar('hostid', $data['hostid'])
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN)); // TODO VM: do we need this?




$output = [
	'header' => _('Host Wizard'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HOST_WIZARD),
	'body' => $form->toString(),
	'script_inline' => getPagePostJs().
		$this->readJsFile('host.wizard.edit.js.php').
		'host_wizard_edit.init('.json_encode([
			'templates' => $data['templates'],
			'linked_templates' => $data['linked_templates'],
			'old_template_count' => $data['old_template_count'],
			'wizard_hide_welcome' => $data['wizard_hide_welcome']
		]).');',
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
*/
