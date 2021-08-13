<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 */

$form = (new CForm())
	->cleanItems()
	->setName('services_form');

$controls = (new CForm())
	->cleanItems()
	->setName('services_filter_form')
	->addVar('title', $data['title'])
	->addVar('exclude_serviceids', $data['exclude_serviceids'])
	->addItem(
		(new CList())
			->addItem(new CLabel(_('Name'), 'services-filter-name'))
			->addItem(
				(new CTextBox('filter_name', $data['filter']['name']))
					->setId('services-filter-name')
					->setAttribute('autofocus', 'autofocus')
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
			->addItem(new CSubmitButton(_('Apply')))
			->addItem(
				(new CSimpleButton(_('Reset')))
					->setAttribute('type', 'reset')
					->addClass(ZBX_STYLE_BTN_ALT)
			)
	);

$services = (new CTableInfo())
	->setHeader([
		(new CColHeader(new CCheckBox('serviceid_all')))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Name'),
		_('Status calculation rule'),
		_('Problem tags')
	]);

foreach ($data['services'] as $service) {
	$services->addRow([
		new CCol([
			(new CCheckBox('serviceid', $service['serviceid']))->removeId(),
			(new CVar('name', $service['name']))->removeId(),
			(new CVar('algorithm', $service['algorithm']))->removeId(),
			(new CVar('problem_tags_html', $data['problem_tags_html'][$service['serviceid']]))->removeId()
		]),
		(new CCol(
			(new CLink($service['name']))->addClass('js-name')
		))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(CServiceHelper::getAlgorithmNames()[$service['algorithm']]))->addClass(ZBX_STYLE_NOWRAP),
		new CCol($data['problem_tags'][$service['serviceid']])
	]);
}

$form
	->addItem($services)
	->addItem(
		(new CScriptTag('
			services_popup.init();
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'controls' => $controls->toString(),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Select'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'services_popup.submit();'
		]
	],
	'script_inline' => $this->readJsFile('popup.services.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
