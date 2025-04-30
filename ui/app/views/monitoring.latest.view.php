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

$this->addJsFile('layout.mode.js');
$this->addJsFile('class.tabfilter.js');
$this->addJsFile('class.tabfilteritem.js');
$this->addJsFile('class.expandable.subfilter.js');

$this->includeJsFile('monitoring.latest.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

if ($data['uncheck']) {
	uncheckTableRows('latest');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Latest data'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_LATEST_VIEW))
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);


$filter = (new CTabFilter())
	->setId('monitoring_latest_filter')
	->setOptions($data['tabfilter_options'])
	->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

if ($data['mandatory_filter_set'] && $data['items'] || $data['subfilter_set']) {
	$filter->addSubfilter(new CPartial('monitoring.latest.subfilter',
		array_intersect_key($data, array_flip(['subfilters', 'subfilters_expanded'])))
	);
}

foreach ($data['filter_tabs'] as $tab) {
	$tab['tab_view'] = $data['filter_view'];
	$filter->addTemplatedTab($tab['filter_name'], $tab);
}

// Set javascript options for tab filter initialization in monitoring.latest.view.js.php file.
$data['filter_options'] = $filter->options;

$html_page
	->addItem($filter)
	->addItem(
		new CPartial('monitoring.latest.view.html', array_intersect_key($data,
			array_flip(['filter', 'sort_field', 'sort_order', 'view_curl', 'paging', 'hosts', 'items', 'history',
				'config', 'tags', 'maintenances', 'items_rw', 'mandatory_filter_set', 'subfilter_set'
			])
		))
	)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'filter_options' => $data['filter_options'],
		'refresh_url' => $data['refresh_url'],
		'refresh_data' => $data['refresh_data'],
		'refresh_interval' => $data['refresh_interval'],
		'checkbox_object' => 'itemids',
		'filter_set' => $data['mandatory_filter_set'] || $data['subfilter_set'],
		'layout_mode' => $web_layout_mode
	]).');
'))
	->setOnDocumentReady()
	->show();
