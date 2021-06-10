<?php
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

$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.service.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$filter = ($web_layout_mode == ZBX_LAYOUT_NORMAL)
	? (new CFilter($data['view_curl']))
		->setProfile('web.service.filter')
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow(_('Name'),
					(new CTextBox('filter_select', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				),
			(new CFormList())
				->addRow(_('Tags'), CTagFilterFieldHelper::getTagFilterField([
					'evaltype' => $data['filter']['evaltype'],
					'tags' => $data['filter']['tags']
				]))
		])
	: null;

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(_('Name')))->addStyle('width: 25%'),
		(new CColHeader(_('Status')))->addStyle('width: 14%'),
		(new CColHeader(_('Root cause')))->addStyle('width: 24%'),
		(new CColHeader(_('SLA')))->addStyle('width: 14%'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3)
	]);

foreach ($data['services'] as $serviceid => $service) {
	$dependencies_count = count($service['dependencies']);

	$table->addRow(new CRow([
		$dependencies_count > 0
			? [
				(new CLink($service['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'service.list')
					->setArgument('serviceid', $serviceid)
				))->setAttribute('data-id', $serviceid),
				CViewHelper::showNum($dependencies_count)
			]
			: $service['name'],
		in_array($service['status'], [TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_NOT_CLASSIFIED])
			? (new CCol(_('OK')))->addClass(ZBX_STYLE_GREEN)
			: (new CCol(getSeverityName($service['status'])))->addClass(getSeverityStyle($service['status'])),
		'',
		sprintf('%.4f', $service['goodsla']),
		array_key_exists($serviceid, $data['tags']) ? $data['tags'][$serviceid] : ''
	]));
}

(new CWidget())
	->setTitle(_('Services'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['can_edit']
						? (new CRadioButtonList('list_mode', ZBX_LIST_MODE_VIEW))
							->addValue(_('View'), ZBX_LIST_MODE_VIEW)
							->addValue(_('Edit'), ZBX_LIST_MODE_EDIT)
							->setModern(true)
						: null
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem([
		$filter,
		$table,
		$data['paging']
	])
	->show();

(new CScriptTag('
	initializeView(
		'.json_encode($data['serviceid']).',
		'.json_encode($data['page']).'
	);
'))
	->setOnDocumentReady()
	->show();
