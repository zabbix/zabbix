<?php
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
$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('items.js');
$this->addJsFile('multilineinput.js');

$this->includeJsFile('monitoring.host.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();
$nav_items = new CList();

if ($data['can_create_hosts']) {
	$nav_items->addItem(
		(new CSimpleButton(_('Create host')))
			->onClick('view.createHost()')
	);
}

$nav_items->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]));

$html_page = (new CHtmlPage())
	->setTitle(_('Hosts'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_HOST_VIEW))
	->setControls((new CTag('nav', true, $nav_items))
		->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$filter = (new CTabFilter())
		->setId('monitoring_hosts_filter')
		->setOptions($data['tabfilter_options'])
		->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

	foreach ($data['filter_tabs'] as $tab) {
		$tab['tab_view'] = $data['filter_view'];
		$filter->addTemplatedTab($tab['filter_name'], $tab);
	}

	// Set javascript options for tab filter initialization in monitoring.host.view.js.php file.
	$data['filter_options'] = $filter->options;
	$html_page->addItem($filter);
}
else {
	$data['filter_options'] = null;
}

$html_page
	->addItem(
		(new CForm())
			->setName('host_view')
			->addClass('is-loading')
	)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'filter_options' => $data['filter_options'],
		'refresh_url' => $data['refresh_url'],
		'refresh_interval' => $data['refresh_interval'],
		'applied_filter_groupids' => $data['filter_groupids']
	]).');
'))
	->setOnDocumentReady()
	->show();
