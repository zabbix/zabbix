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

$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('class.widgets-data.js');
$this->addJsFile('class.widget-base.js');
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.inaccessible.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.misconfigured.js');
$this->addJsFile('class.widget.paste-placeholder.js');

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);
}

if (array_key_exists('no_data', $data)) {
	(new CHtmlPage())
		->setTitle(_('Dashboards'))
		->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_HOST_DASHBOARD_VIEW))
		->addItem(new CTableInfo())
		->show();

	return;
}

$this->addJsFile('class.csvggraph.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('d3.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('leaflet.js');
$this->addJsFile('leaflet.markercluster.js');
$this->addJsFile('class.geomaps.js');

$this->includeJsFile('monitoring.host.dashboard.view.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/leaflet.css');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$html_page = (new CHtmlPage())
	->setTitle(_('Host dashboards'))
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_HOST_DASHBOARD_VIEW))
	->setControls(
		(new CTag('nav', true))
			->addItem(
				(new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			)
			->setAttribute('aria-label', _('Content controls'))
	)
	->setKioskModeControls(
		(count($data['dashboard']['pages']) > 1)
			? (new CList())
				->addClass(ZBX_STYLE_DASHBOARD_KIOSKMODE_CONTROLS)
				->addItem(
					(new CSimpleButton())
						->addClass(ZBX_ICON_CHEVRON_LEFT)
						->addClass(ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_PREVIOUS_PAGE)
						->setTitle(_('Previous page'))
				)
				->addItem(
					(new CSimpleButton())
						->addClass(ZBX_ICON_PAUSE)
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
					(new CSimpleButton())
						->addClass(ZBX_ICON_CHEVRON_RIGHT)
						->addClass(ZBX_STYLE_BTN_DASHBOARD_KIOSKMODE_NEXT_PAGE)
						->setTitle(_('Next page'))
				)
			: null
	);

$navigation = (new CDiv())
	->addClass(ZBX_STYLE_HOST_DASHBOARD_HEADER_NAVIGATION)
	->addItem(
		(new CList())->addItem(
			new CBreadcrumbs([
				(new CSpan())->addItem(
					new CLink(_('All hosts'), (new CUrl('zabbix.php'))->setArgument('action', 'host.view'))
				),
				(new CSpan())->addItem($data['dashboard_host']['name'])
			])
		)
	);

if ($web_layout_mode != ZBX_LAYOUT_KIOSKMODE) {
	$dashboard_tabs = (new CDiv())
		->addClass(ZBX_STYLE_HOST_DASHBOARD_NAVIGATION)
		->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_HOST_DASHBOARD_NAVIGATION_CONTROLS)
				->addItem((new CButtonIcon(ZBX_ICON_CHEVRON_LEFT, _('Previous dashboard')))
					->addClass(ZBX_STYLE_BTN_HOST_DASHBOARD_PREVIOUS_DASHBOARD)
				)
		)
		->addItem((new CDiv())->addClass(ZBX_STYLE_HOST_DASHBOARD_NAVIGATION_TABS))
		->addItem(
			(new CDiv())
				->addClass(ZBX_STYLE_HOST_DASHBOARD_NAVIGATION_CONTROLS)
				->addItem([
					(new CButtonIcon(ZBX_ICON_CHEVRON_DOWN, _('Dashboard list')))
						->addClass(ZBX_STYLE_BTN_HOST_DASHBOARD_LIST),
					(new CButtonIcon(ZBX_ICON_CHEVRON_RIGHT, _('Next dashboard')))
						->addClass(ZBX_STYLE_BTN_HOST_DASHBOARD_NEXT_DASHBOARD)
				])
		);

	$navigation->addItem($dashboard_tabs);
}

if (array_key_exists(CWidgetsData::DATA_TYPE_TIME_PERIOD, $data['broadcast_requirements'])) {
	$navigation->addItem(
		(new CFilter())
			->setProfile($data['dashboard_time_period']['profileIdx'], $data['dashboard_time_period']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['dashboard_time_period']['from'], $data['dashboard_time_period']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
			)
	);
}

$html_page->addItem($navigation);

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
							(new CButtonIcon(ZBX_ICON_CHEVRON_LEFT, _('Previous page')))
								->addClass(ZBX_STYLE_BTN_DASHBOARD_PREVIOUS_PAGE)
								->setEnabled(false),
							(new CButtonIcon(ZBX_ICON_CHEVRON_RIGHT, _('Next page')))
								->addClass(ZBX_STYLE_BTN_DASHBOARD_NEXT_PAGE)
								->setEnabled(false),
							(new CSimpleButton([
								(new CSpan(_s('Start slideshow')))->addClass('slideshow-state-stopped'),
								(new CSpan(_s('Stop slideshow')))->addClass('slideshow-state-started')
							]))
								->addClass(ZBX_STYLE_BTN_DASHBOARD_TOGGLE_SLIDESHOW)
								->addClass(ZBX_STYLE_BTN_ALT)
								->addClass(
									($data['dashboard']['dashboardid'] !== null && $data['dashboard']['auto_start'] == 1)
										? 'slideshow-state-started'
										: 'slideshow-state-stopped'
								)
						])
				)
		);
	}

	$dashboard->addItem(
		(new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID)
	);

	$html_page
		->addItem($dashboard)
		->show();
}
else {
	$html_page
		->addItem(new CTableInfo())
		->show();
}

(new CScriptTag('
	view.init('.json_encode([
		'host_dashboards' => $data['host_dashboards'],
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'configuration_hash' => $data['configuration_hash'],
		'broadcast_requirements' => $data['broadcast_requirements'],
		'dashboard_host' => $data['dashboard_host'],
		'dashboard_time_period' => $data['dashboard_time_period'],
		'web_layout_mode' => $web_layout_mode
	]).');
'))
	->setOnDocumentReady()
	->show();
