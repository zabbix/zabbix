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
$this->addJsFile('class.calendar.js');
$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.clock.js');
$this->addJsFile('class.widget.graph.js');
$this->addJsFile('class.widget.graph-prototype.js');
$this->addJsFile('class.widget.item.js');
$this->addJsFile('class.widget.map.js');
$this->addJsFile('class.widget.navtree.js');
$this->addJsFile('class.widget.paste-placeholder.js');
$this->addJsFile('class.widget.problems.js');
$this->addJsFile('class.widget.problemsbysv.js');
$this->addJsFile('class.widget.svggraph.js');
$this->addJsFile('class.widget.trigerover.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('class.sortable.js');

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
	->setKioskModeControls(
		(count($data['dashboard']['pages']) > 1)
			? (new CList())
				->addClass(ZBX_STYLE_DASHBOARD_KIOSKMODE_CONTROLS)
				->addItem(
					(new CSimpleButton(null))
						->addClass(ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_PREVIOUS_PAGE)
						->setTitle(_('Previous page'))
				)
				->addItem(
					(new CSimpleButton(null))
						->addClass(ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_TOGGLE_SLIDESHOW)
						->setTitle(($data['dashboard']['dashboardid'] !== null && $data['dashboard']['auto_start'] == 1)
							? _s('Stop slideshow')
							: _s('Start slideshow')
						)
						->addClass(
							($data['dashboard']['dashboardid'] !== null && $data['dashboard']['auto_start'] == 1)
								? 'slideshow-state-started'
								: 'slideshow-state-stopped'
						)
				)
				->addItem(
					(new CSimpleButton(null))
						->addClass(ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_NEXT_PAGE)
						->setTitle(_('Next page'))
				)
			: null
	)
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

if ($data['has_time_selector']) {
	$widget->addItem(
		(new CFilter())
			->setProfile($data['time_period']['profileIdx'], $data['time_period']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['time_period']['from'], $data['time_period']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
			)
	);
}

if (count($data['dashboard']['pages']) > 1
		|| (count($data['dashboard']['pages']) == 1 && $data['dashboard']['pages'][0]['widgets'])) {
	$dashboard = (new CDiv())->addClass(ZBX_STYLE_DASHBOARD);

	if (count($data['dashboard']['pages']) > 1) {
		$dashboard->addClass(ZBX_STYLE_DASHBOARD_IS_MULTIPAGE);
	}

	if ($web_layout_mode != ZBX_LAYOUT_KIOSKMODE) {
		$dashboard->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_DASHBOARD_NAVIGATION)
				->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_NAVIGATION_TABS))
				->addItem(
					(new CDiv())
						->addClass(ZBX_STYLE_DASHBOARD_NAVIGATION_CONTROLS)
						->addItem([
							(new CSimpleButton())
								->addClass(ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE)
								->addClass('btn-iterator-page-previous')
								->setEnabled(false),
							(new CSimpleButton())
								->addClass(ZBX_STYLE_DASHBOARD_NEXT_PAGE)
								->addClass('btn-iterator-page-next')
								->setEnabled(false),
							(new CSimpleButton([
								(new CSpan(_s('Start slideshow')))->addClass('slideshow-state-stopped'),
								(new CSpan(_s('Stop slideshow')))->addClass('slideshow-state-started')
							]))
								->addClass(ZBX_STYLE_BTN_ALT)
								->addClass(ZBX_STYLE_DASHBOARD_TOGGLE_SLIDESHOW)
								->addClass(
									($data['dashboard']['dashboardid'] !== null && $data['dashboard']['auto_start'] == 1)
										? 'slideshow-state-started'
										: 'slideshow-state-stopped'
								)
						])
				)
		);
	}

	$dashboard->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID));

	$widget
		->addItem($dashboard)
		->show();

	(new CScriptTag('
		view.init('.json_encode([
			'host' => $data['host'],
			'dashboard' => $data['dashboard'],
			'widget_defaults' => $data['widget_defaults'],
			'time_period' => $data['time_period'],
			'web_layout_mode' => $web_layout_mode
		]).');
	'))
		->setOnDocumentReady()
		->show();
}
else {
	$widget
		->addItem(new CTableInfo())
		->show();
}
