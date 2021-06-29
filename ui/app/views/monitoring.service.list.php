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
$this->addJsFile('class.tagfilteritem.js');

$this->includeJsFile('monitoring.service.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

if (($web_layout_mode == ZBX_LAYOUT_NORMAL)) {
	$filter = (new CFilter())
		->setApplyUrl($data['view_curl'])
		->setResetUrl($data['view_curl'])
		->setProfile('web.service.filter')
		->setActiveTab($data['active_tab']);

	if ($data['service'] !== null) {
		$parents = [];
		foreach ($data['service']['parents'] as $parent) {
			array_push($parents,
				(new CLink($parent['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'service.list')
					->setArgument('path', $data['path'])
					->setArgument('serviceid', $parent['serviceid'])
				))->setAttribute('data-serviceid', $parent['serviceid']),
				CViewHelper::showNum($parent['children']),
				', '
			);
		}
		array_pop($parents);

		if (in_array($data['service']['status'], [TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_NOT_CLASSIFIED])) {
			$service_status = _('OK');
			$service_status_style_class = null;
		}
		else {
			$service_status = getSeverityName($data['service']['status']);
			$service_status_style_class = 'service-status-'.getSeverityStyle($data['service']['status']);
		}

		$filter
			->addTab(
				(new CLink(_('Info'), '#tab_info'))->addClass(ZBX_STYLE_BTN_INFO),
				(new CDiv())
					->setId('tab_info')
					->addClass(ZBX_STYLE_FILTER_CONTAINER)
					->addItem(
						(new CDiv())
							->addClass(ZBX_STYLE_SERVICE_INFO)
							->addClass($service_status_style_class)
							->addItem([
								(new CDiv($data['service']['name']))->addClass(ZBX_STYLE_SERVICE_NAME)
							])
							->addItem([
								(new CDiv(_('Parents')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
								(new CDiv($parents))->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
							])
							->addItem([
								(new CDiv(_('Status')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
								(new CDiv((new CDiv($service_status))->addClass(ZBX_STYLE_SERVICE_STATUS)))
									->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
							])
							->addItem([
								(new CDiv(_('SLA')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
								(new CDiv(sprintf('%.4f', $data['service']['goodsla'])))
									->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
							])
							->addItem([
								(new CDiv(_('Tags')))->addClass(ZBX_STYLE_SERVICE_INFO_LABEL),
								(new CDiv())->addClass(ZBX_STYLE_SERVICE_INFO_VALUE)
							])
					)
			);
	}

	$filter
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Name'), 'filter_select'),
					new CFormField(
						(new CTextBox('filter_select', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
					)
				])
				->addItem([
					new CLabel(_('Status')),
					new CFormField(
						(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
							->addValue(_('Any'), SERVICE_STATUS_ANY)
							->addValue(_('OK'), SERVICE_STATUS_OK)
							->addValue(_('Problem'), SERVICE_STATUS_PROBLEM)
							->setModern(true)
					)
				]),
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Tags')),
					new CFormField(
						CTagFilterFieldHelper::getTagFilterField([
							'evaltype' => $data['filter']['evaltype'],
							'tags' => $data['filter']['tags'] ?: [
								['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]
							]
						])
					)
				])
		])
		->addVar('action', 'service.list')
		->addVar('serviceid', $data['service'] !== null ? $data['service']['serviceid'] : null);
}
else {
	$filter = null;
}

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(_('Name')))->addStyle('width: 25%'),
		(new CColHeader(_('Status')))->addStyle('width: 14%'),
		(new CColHeader(_('Root cause')))->addStyle('width: 24%'),
		(new CColHeader(_('SLA')))->addStyle('width: 14%'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3)
	]);

foreach ($data['services'] as $serviceid => $service) {
	$table->addRow(new CRow([
		$service['children'] > 0
			? [
				(new CLink($service['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'service.list')
					->setArgument('path', $data['path'])
					->setArgument('serviceid', $serviceid)
				))->setAttribute('data-serviceid', $serviceid),
				CViewHelper::showNum($service['children'])
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

$breadcrumbs = [];
if (count($data['breadcrumbs']) > 1) {
	foreach($data['breadcrumbs'] as $key => $path_item) {
		$breadcrumbs[] = (new CSpan())
			->addItem(array_key_exists('curl', $path_item)
				? new CLink($path_item['name'], $path_item['curl'])
				: $path_item['name']
			)
			->addClass(array_key_last($data['breadcrumbs']) === $key ? ZBX_STYLE_SELECTED : null);
	}
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
	->setNavigation(
		$breadcrumbs ? new CList([new CBreadcrumbs($breadcrumbs)]) : null
	)
	->addItem([
		$filter,
		$table,
		$data['paging']
	])
	->show();

(new CScriptTag('
	initializeView(
		'.json_encode($data['service'] !== null ? $data['service']['serviceid'] : null).',
		'.json_encode($data['page']).'
	);
'))
	->setOnDocumentReady()
	->show();
