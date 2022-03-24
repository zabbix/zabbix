<?php
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
 */

$this->addJsFile('layout.mode.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('class.calendar.js');
$this->addJsFile('class.tabfilter.js');
$this->addJsFile('class.tabfilteritem.js');
$this->addJsFile('class.tagfilteritem.js');

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

$widget = (new CWidget())
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
	$widget->addItem($filter);
}
else {
	$data['filter_options'] = null;
}

$widget->addItem((new CForm())
	->setName('host_view')
	->addClass('is-loading')
);

$widget->show();

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
