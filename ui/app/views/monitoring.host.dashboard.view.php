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

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);
}

if (array_key_exists('no_data', $data)) {
	(new CWidget())
		->setTitle(_('Dashboards'))
		->addItem(new CTableInfo())
		->show();

	return;
}

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('dashboard.grid.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('class.cclock.js');

$this->includeJsFile('monitoring.host.dashboard.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle($data['dashboard']['name'])
	->setWebLayoutMode($web_layout_mode)
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(
				(new CForm('get'))
					->cleanItems()
					->addVar('action', 'host.dashboard.view')
					->addVar('hostid', $data['host']['hostid'])
					->addItem((new CLabel(_('Dashboard'), 'label-dashboard'))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN))
					->addItem(
						(new CSelect('dashboardid'))
							->setId('dashboardid')
							->setFocusableElementId('label-dashboard')
							->setValue($data['dashboard']['dashboardid'])
							->addOptions(CSelect::createOptionsFromArray($data['host_dashboards']))
							->addClass(ZBX_STYLE_HEADER_Z_SELECT)
					)
			)
			->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
	))->setAttribute('aria-label', _('Content controls')))
	->setNavigation((new CList())->addItem(new CBreadcrumbs([
		(new CSpan())->addItem(new CLink(_('All hosts'), (new CUrl('zabbix.php'))->setArgument('action', 'host.view'))),
		(new CSpan())->addItem($data['host']['name']),
		(new CSpan())
			->addItem(new CLink($data['dashboard']['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'host.dashboard.view')
					->setArgument('hostid', $data['host']['hostid'])
			))
			->addClass(ZBX_STYLE_SELECTED)
	])));

if ($data['time_selector'] !== null) {
	$widget->addItem(
		(new CFilter(new CUrl()))
			->setProfile($data['time_selector']['profileIdx'], $data['time_selector']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['time_selector']['from'], $data['time_selector']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
			)
	);
}

if ($data['dashboard']['widgets']) {
	$widget
		->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_CONTAINER))
		->show();

	(new CScriptTag(
		'initializeHostDashboard('.
			json_encode($data['host']).','.
			json_encode($data['dashboard']).','.
			json_encode($data['widget_defaults']).','.
			json_encode($web_layout_mode).
		');'
	))
		->setOnDocumentReady()
		->show();
}
else {
	$widget
		->addItem(new CTableInfo())
		->show();
}
