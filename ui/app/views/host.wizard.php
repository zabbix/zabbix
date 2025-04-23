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

//$body = welcomeMessage();
$body = selectTemplate();

$output = [
//	'header' => $title,
//	'doc_url' => CDocHelper::getUrl(CDocHelper::POPUP_SLA_EDIT),
	'body' => implode('', array_map(static fn($element) => $element->toString(), $body)),
	'buttons' => [
		[
			'title' => _('Cancel'),
			'class' => ZBX_STYLE_BTN_LINK.' dialogue-cancel js-cancel',
			'cancel' => true
//			'keepOpen' => true,
//			'isSubmit' => true,
//			'action' => 'sla_edit_popup.submit();'
		],
		[
			'title' => _('Back'),
			'class' => 'js-back',
//			'keepOpen' => true,
//			'isSubmit' => true,
//			'action' => 'sla_edit_popup.submit();'
		],
		[
			'title' => _('Next'),
			'class' => 'js-next',
//			'keepOpen' => true,
//			'isSubmit' => true,
//			'action' => 'sla_edit_popup.submit();'
		]
	],
	'script_inline' => $this->readJsFile('host.wizard.js.php')
		.'host_wizard_popup.init('.json_encode([

		]).');',
	'dialogue_class' => 'modal-popup-host-wizard'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);




function welcomeMessage(): CTemplateTag {
	return [
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
					(new CCheckBox('don-not-show-welcome'))->setLabel(_('Do not show welcome screen'))
				]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
			)
			->addClass(ZBX_STYLE_GRID_COLUMNS)
			->addClass(ZBX_STYLE_GRID_COLUMNS_2)
	];
}



function selectTemplate(): array {









	return [
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
					(new CTextBox())->setAttribute('placeholder', _('Apache, AWS, MySQL, etc.')),
					(new CDiv(_('Type a keyword to search for templates.')))->addClass(ZBX_STYLE_FORM_FIELDS_HINT)
				]))->addClass(ZBX_STYLE_GRID_COLUMN_FIRST)
			)
			->addItem(
				(new CDiv([
					(new CFormField([
						new CLabel(_('Data collection')),
						(new CRadioButtonList('data_collection', 0))
							->addValue(_('All'), 0)
							->addValue(_('Agent-based'), 1)
							->addValue(_('Agentless'), 2)
							->setModern()
					])),
					(new CFormField([
						new CLabel(_('Agent mode')),
						(new CRadioButtonList('agent_mode', 0))
							->addValue(_('All'), 0)
							->addValue(_('Active'), 1)
							->addValue(_('Passive'), 2)
							->setModern()
					]))
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

		(new CSectionCollapsible())
			->setToggleLabel(_('Applications').' (86)')
			->addItem(
				componentSubFilter()
			)
			->addItem(
				componentRadioCardList()
			)
	];
}

function componentSubFilter() {
	$templates = ['Acronis', 'AIX', 'Alcatel', 'Aranet', 'APC Smart-UPS', 'APC UPS', 'Cephagent2', 'Certificateagent2',
		'Certificateagent2', 'Cloudflare', 'Consul http', 'Consul http', 'Cloudflare', 'AIX', 'Certificateagent2', 'Alcatel',
		'Apache', 'Elasticsearch http', 'Apache 5', 'Controlm http', 'Controlm http', 'APC UPS', 'Docker'
	];

	$sub_filter = (new CDiv())
		->addClass('template-subfilter');

	foreach ($templates as $template) {
		$sub_filter->addItem(
			(new CButton(strtolower($template), $template))
				->addClass('template-subfilter-item')
				->addClass($template === 'Apache' ? 'selected' : null)
		);
	}

	return $sub_filter;
}

function componentRadioCardList() {
	$templates = ['Acronis', 'AIX', 'Alcatel', 'Aranet', 'APC Smart-UPS', 'APC UPS', 'Cephagent2', 'Certificateagent2',
		'Certificateagent2', 'Cloudflare', 'Consul httpConsul httpConsul httpConsul http', 'Consul http', 'Cloudflare', 'AIX', 'Certificateagent2', 'Alcatel',
		'Apache', 'Elasticsearch http', 'Apache 5', 'Controlm http', 'Controlm http', 'APC UPS', 'Docker'
	];

	$tags = ['name: description', 'name: tag', 'name: tag', 'name: description'];

	$applications = [];

	foreach ($templates as $template) {
		$applications[] = [
			'label' => $template,
			'name' => strtolower($template),
			'content' => [
				(new CDiv([
					new CLabel(_('Tags')),
					(new CDiv([
						array_map(static function($tag) {
							return (new CSpan($tag))->addClass(ZBX_STYLE_TAG);
						}, $tags),
						new CButtonIcon(ZBX_ICON_MORE)
					]))->addClass('tags-list')
				]))->addClass('template-tags'),
				(new CDiv([
					(new CButtonLink(
						new CSpan(_('Show more'))
					))->addClass(ZBX_ICON_CHEVRON_DOWN),
				]))->addClass('template-description')
			]
		];

		$list->addItem(
			(new CDiv())
				->addItem([
					(new CLabel([
						$template,
						(new CSpan(
							(new CInput('radio', 'applications', strtolower($template)))
								->setAttribute('checked', $template === 'Apache' ? '' : null)
						))->addClass('radio-card-selector')
					]))->addClass('radio-card-label'),

				])
				->addClass('radio-card')
	}

	return (new CRadioCardList('applications', 'Apache'))
		->addClass('templates-card-list')
		->addClass(ZBX_STYLE_GRID_COLUMNS)
		->addClass(ZBX_STYLE_GRID_COLUMNS_4)
		->setValues($applications);
}
