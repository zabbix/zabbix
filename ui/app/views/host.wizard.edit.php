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
			(new CDiv())->addClass('step-form-body')
		)
		->addItem([
			stepWelcome(),
			stepSelectTemplate($data['old_template_count'])
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
				makeMessageBox('info', [], _s('Some templates (%1$s) are hidden. Custom templates are not supported.',
					$old_template_count)
				),

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
							(new CTextBox('template-search-query'))
								->setAttribute('placeholder', _('Apache, AWS, MySQL, etc.')),
							(new CDiv(_('Type a keyword to search for templates.')))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
						]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
					)
					->addItem(
						(new CDiv([
							(new CFormField([
								new CLabel(_('Data collection')),
								(new CRadioButtonList('data-collection', ZBX_TEMPLATE_DATA_COLLECTION_ANY))
									->addValue(_('All'), ZBX_TEMPLATE_DATA_COLLECTION_ANY)
									->addValue(_('Agent-based'), ZBX_TEMPLATE_DATA_COLLECTION_AGENT_BASED)
									->addValue(_('Agentless'), ZBX_TEMPLATE_DATA_COLLECTION_AGENTLESS)
									->setModern()
							])),
							(new CFormField([
								new CLabel(_('Agent mode')),
								(new CRadioButtonList('agent-mode', ZBX_TEMPLATE_AGENT_MODE_ANY))
									->addValue(_('All'), ZBX_TEMPLATE_AGENT_MODE_ANY)
									->addValue(_('Active'), ZBX_TEMPLATE_AGENT_MODE_ACTIVE)
									->addValue(_('Passive'), ZBX_TEMPLATE_AGENT_MODE_PASSIVE)
									->setModern()
							])),
							new CLabel(_('Show templates')),
							(new CRadioButtonList('agent-mode', ZBX_TEMPLATE_SHOW_ANY))
								->addValue(_('All'), ZBX_TEMPLATE_SHOW_ANY)
								->addValue(_('Linked'), ZBX_TEMPLATE_SHOW_LINKED)
								->addValue(_('Not linked'), ZBX_TEMPLATE_SHOW_NOT_LINKED)
								->setModern()
						]))
							->addClass(ZBX_STYLE_GRID_COLUMNS)
							->addClass(ZBX_STYLE_GRID_COLUMNS_2)
							->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
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

				(new CDiv())->setId('host-wizard-templates')
			]))->addClass('step-form-body')
		),
		new CTemplateTag('host-wizard-templates-section',
			(new CSectionCollapsible())->setToggleLabel(_('#{title}').' (#{count})')
		),
		new CTemplateTag('host-wizard-template-card',
			(new CDiv())
				->addItem(
					(new CLabel([
						'#{title}',
						(new CSpan(
							new CInput('radio', 'template', '#{templateid)')
						))->addClass('radio-card-selector')
					]))->addClass('radio-card-label'),
				)
				->addItem(
					(new CDiv([
						new CLabel(_('Tags')),
						(new CDiv())->addClass('tags-list')
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
				->addClass('radio-card')
		)
	];
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
