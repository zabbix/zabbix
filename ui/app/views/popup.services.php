<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->cleanItems()
	->setName('services_form');

// Enable form submitting on Enter.
$form->addItem((new CInput('submit', null))->addStyle('display: none;'));

$controls = (new CForm())
	->cleanItems()
	->setName('services_filter_form')
	->addVar('title', $data['title'])
	->addVar('exclude_serviceids', $data['exclude_serviceids'])
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
		new CCol($data['tags'][$service['serviceid']]),
		new CCol($data['problem_tags'][$service['serviceid']])
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
	'script_inline' => $this->readJsFile('popup.services.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
