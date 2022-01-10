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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 * @var array $data
 */

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);

	return;
}

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('leaflet.js');
$this->addJsFile('leaflet.markercluster.js');
$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('class.geomaps.js');
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.clock.js');
$this->addJsFile('class.widget.geomap.js');
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
$this->addJsFile('class.calendar.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('class.coverride.js');
$this->addJsFile('class.crangecontrol.js');
$this->addJsFile('colorpicker.js');
$this->addJsFile('class.csvggraph.js');
$this->addJsFile('class.cnavtree.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('class.tagfilteritem.js');
$this->addJsFile('class.sortable.js');

$this->includeJsFile('monitoring.dashboard.view.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/Leaflet/leaflet.css');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$main_filter_form = null;

if ($data['dynamic']['has_dynamic_widgets']) {
	$main_filter_form = (new CForm('get'))
		->cleanItems()
		->setAttribute('name', 'dashboard_filter')
		->setAttribute('aria-label', _('Main filter'))
		->addVar('action', 'dashboard.view')
		->addItem([
			(new CLabel(_('Host'), 'dynamic_hostid_ms'))->addStyle('margin-right: 5px;'),
			(new CMultiSelect([
				'name' => 'dynamic_hostid',
				'object_name' => 'hosts',
				'data' => $data['dynamic']['host'] ? [$data['dynamic']['host']] : [],
				'multiple' => false,
				'popup' => [
					'parameters' => [
						'srctbl' => 'hosts',
						'srcfld1' => 'hostid',
						'dstfrm' => 'dashboard_filter',
						'dstfld1' => 'dynamic_hostid',
						'monitored_hosts' => true,
						'with_items' => true
					]
				]
			]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		]);
}

$widget = (new CWidget())
	->setTitle($data['dashboard']['name'])
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CList())
			->setId('dashboard-control')
			->addItem($main_filter_form)
			->addItem((new CTag('nav', true,
				(new CList())
					->addItem(
						(new CButton('dashboard-edit', _('Edit dashboard')))
							->setEnabled($data['dashboard']['can_edit_dashboards'] && $data['dashboard']['editable'])
							->setAttribute('aria-disabled', !$data['dashboard']['editable'] ? 'true' : null)
					)
					->addItem(
						(new CButton('', '&nbsp;'))
							->addClass(ZBX_STYLE_BTN_ACTION)
							->setId('dashboard-actions')
							->setTitle(_('Actions'))
							->setEnabled($data['dashboard']['can_edit_dashboards']
								|| $data['dashboard']['can_view_reports']
							)
							->setAttribute('aria-haspopup', true)
							->setMenuPopup(CMenuPopupHelper::getDashboard($data['dashboard']['dashboardid'],
								$data['dashboard']['editable'], $data['dashboard']['has_related_reports'],
								$data['dashboard']['can_edit_dashboards'], $data['dashboard']['can_view_reports'],
								$data['dashboard']['can_create_reports']
							))
					)
					->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			))->setAttribute('aria-label', _('Content controls')))
			->addItem((new CListItem(
				(new CTag('nav', true, new CList([
					(new CButton('dashboard-config'))->addClass(ZBX_STYLE_BTN_DASHBOARD_CONF),
					(new CList())
						->addClass(ZBX_STYLE_BTN_SPLIT)
						->addItem((new CButton('dashboard-add-widget',
							[(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add')]
						))->addClass(ZBX_STYLE_BTN_ALT))
						->addItem(
							(new CButton('dashboard-add', '&#8203;'))
								->addClass(ZBX_STYLE_BTN_ALT)
								->addClass(ZBX_STYLE_BTN_TOGGLE_CHEVRON)
						),
					(new CButton('dashboard-save', _('Save changes'))),
					(new CLink(_('Cancel'), '#'))->setId('dashboard-cancel'),
					''
				])))
					->setAttribute('aria-label', _('Content controls'))
					->addClass(ZBX_STYLE_DASHBOARD_EDIT)
			))->addStyle('display: none'))
	)
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
		(new CSpan())->addItem(new CLink(_('All dashboards'),
			(new CUrl('zabbix.php'))->setArgument('action', 'dashboard.list')
		)),
		(new CSpan())
			->addItem((new CLink($data['dashboard']['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'dashboard.view')
					->setArgument('dashboardid', $data['dashboard']['dashboardid'])))
					->setId('dashboard-direct-link')
			)
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

$dashboard = (new CDiv())->addClass(ZBX_STYLE_DASHBOARD);

if (count($data['dashboard']['pages']) > 1) {
	$dashboard->addClass(ZBX_STYLE_DASHBOARD_IS_MULTIPAGE);
}

if ($data['dashboard']['dashboardid'] === null) {
	$dashboard->addClass(ZBX_STYLE_DASHBOARD_IS_EDIT_MODE);
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
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'has_time_selector' => $data['has_time_selector'],
		'time_period' => $data['time_period'],
		'dynamic' => $data['dynamic'],
		'web_layout_mode' => $web_layout_mode
	]).');
'))
	->setOnDocumentReady()
	->show();
