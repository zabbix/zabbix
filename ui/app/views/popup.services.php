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
	->setName('services_form');

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$controls = (new CForm())
	->setName('services_filter_form')
	->addVar('title', $data['title'])
	->addVar('exclude_serviceids', $data['exclude_serviceids'])
	->addVar('multiple', $data['is_multiple'] ? null : 0)
	->addItem(
		(new CList())
			->addClass(ZBX_STYLE_INLINE_FILTER)
			->addItem(new CLabel(_('Name'), 'services-filter-name'), ZBX_STYLE_INLINE_FILTER_LABEL)
			->addItem(
				(new CTextBox('filter_name', $data['filter']['name']))
					->setId('services-filter-name')
					->setAttribute('autofocus', 'autofocus')
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
			->addItem(new CSubmitButton(_('Filter')))
			->addItem(
				(new CSimpleButton(_('Reset')))
					->setAttribute('type', 'reset')
					->addClass(ZBX_STYLE_BTN_ALT)
			)
	);

$services = (new CTableInfo())
	->setHeader([
		$data['is_multiple'] ? (new CColHeader(new CCheckBox('serviceid_all')))->addClass(ZBX_STYLE_CELL_WIDTH) : null,
		_('Name'),
		_('Tags'),
		_('Problem tags')
	]);

foreach ($data['services'] as $service) {
	$services->addRow([
		$data['is_multiple'] ? (new CCheckBox('serviceid', $service['serviceid']))->removeId() : null,
		(new CCol([
			$data['is_multiple'] ? null : (new CVar('serviceid', $service['serviceid']))->removeId(),
			(new CVar('name', $service['name']))->removeId(),
			(new CVar('problem_tags_html', $data['problem_tags_html'][$service['serviceid']]))->removeId(),
			(new CLink($service['name']))->addClass('js-name')
		]))->addClass(ZBX_STYLE_WORDBREAK),
		new CCol((new CDiv($data['tags'][$service['serviceid']]))->addClass(ZBX_STYLE_TAGS_WRAPPER)),
		new CCol((new CDiv($data['problem_tags'][$service['serviceid']]))->addClass(ZBX_STYLE_TAGS_WRAPPER))
	]);
}

$form
	->addItem($services)
	->addItem(
		(new CScriptTag('
			services_popup.init('.json_encode([
				'is_multiple' => $data['is_multiple']
			]).');
		'))->setOnDocumentReady()
	);

$buttons = [];

if ($data['is_multiple']) {
	$buttons[] = [
		'title' => _('Select'),
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'services_popup.submit();'
	];
}

$output = [
	'header' => $data['title'],
	'controls' => $controls->toString(),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => $this->readJsFile('popup.services.js.php'),
	'dialogue_class' => 'modal-popup-generic'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
