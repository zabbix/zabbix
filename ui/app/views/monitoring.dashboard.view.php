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

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);

	return;
}

$this->addJsFile('d3.js');
$this->addJsFile('class.cnavtree.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('leaflet.js');
$this->addJsFile('leaflet.markercluster.js');
$this->addJsFile('class.geomaps.js');

$this->includeJsFile('monitoring.dashboard.view.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/leaflet.css');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$html_page = (new CHtmlPage())
	->setTitle($data['dashboard']['name'])
	->setWebLayoutMode($web_layout_mode)
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DASHBOARDS_VIEW))
	->setControls(
		(new CList())
			->setId('dashboard-control')
			->addItem(
				(new CListItem(
					(new CForm('get'))
						->setAttribute('name', 'dashboard_filter')
						->setAttribute('aria-label', _('Main filter'))
						->addVar('action', 'dashboard.view')
						->addItem([
							(new CLabel(_('Host'), 'dashboard_hostid_ms'))->addStyle('margin-right: 5px;'),
							(new CMultiSelect([
								'name' => 'dashboard_hostid',
								'object_name' => 'hosts',
								'data' => $data['dashboard_host'] !== null ? [$data['dashboard_host']] : [],
								'multiple' => false,
								'popup' => [
									'parameters' => [
										'srctbl' => 'hosts',
										'srcfld1' => 'hostid',
										'dstfrm' => 'dashboard_filter',
										'dstfld1' => 'dashboard_hostid',
										'monitored_hosts' => true,
										'with_items' => true
									]
								]
							]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						])
				))
					->addClass('js-control-host-override')
					->addStyle('display: none;')
			)
			->addItem(
				(new CListItem(
					(new CTag('nav', true,
						(new CList())
							->addItem(
								(new CButton('dashboard-edit', _('Edit dashboard')))
									->setEnabled($data['dashboard']['can_edit_dashboards'] && $data['dashboard']['editable'])
									->setAttribute('aria-disabled', !$data['dashboard']['editable'] ? 'true' : null)
							)
							->addItem(
								(new CSimpleButton())
									->addClass(ZBX_STYLE_BTN_ACTION)
									->addClass(ZBX_ICON_MENU)
									->setId('dashboard-actions')
									->setTitle(_('Actions'))
									->setEnabled($data['dashboard']['can_edit_dashboards'] || $data['can_view_reports'])
									->setAttribute('aria-haspopup', true)
									->setMenuPopup(CMenuPopupHelper::getDashboard($data['dashboard']['dashboardid'],
										$data['dashboard']['editable'], $data['has_related_reports'],
										$data['dashboard']['can_edit_dashboards'], $data['can_view_reports'],
										$data['can_create_reports']
									))
							)
							->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))

					))->setAttribute('aria-label', _('Content controls'))
				))->addClass('js-control-view-actions')
			)
			->addItem(
				(new CListItem(
					(new CTag('nav', true, new CList([
						(new CButton('dashboard-config'))
							->addClass(ZBX_STYLE_BTN_ICON)
							->addClass(ZBX_ICON_COG_FILLED),
						(new CList())
							->addClass(ZBX_STYLE_BTN_SPLIT)
							->addItem(
								(new CButton('dashboard-add-widget', _('Add')))
									->addClass(ZBX_STYLE_BTN_ALT)
									->addClass(ZBX_ICON_PLUS_SMALL)
							)
							->addItem(
								(new CButton('dashboard-add'))
									->addClass(ZBX_STYLE_BTN_ALT)
									->addClass(ZBX_ICON_CHEVRON_DOWN_SMALL)
							),
						(new CButton('dashboard-save', _('Save changes'))),
						(new CLink(_('Cancel'), '#'))->setId('dashboard-cancel'),
						''
					])))
						->setAttribute('aria-label', _('Content controls'))
						->addClass(ZBX_STYLE_DASHBOARD_EDIT)
				))
					->addClass('js-control-edit-actions')
					->addStyle('display: none')
			)
	)
	->setKioskModeControls(
		count($data['dashboard']['pages']) > 1
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
	])))
	->addItem(
		(new CFilter())
			->setProfile($data['dashboard_time_period']['profileIdx'], $data['dashboard_time_period']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['dashboard_time_period']['from'], $data['dashboard_time_period']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE
			)
			->preventHistoryUpdates()
			->setManualSetup()
			->setHidden()
	);

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

$dashboard->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID));

$html_page
	->addItem($dashboard)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'widget_last_type' => $data['widget_last_type'],
		'configuration_hash' => $data['configuration_hash'],
		'dashboard_host' => $data['dashboard_host'],
		'dashboard_time_period' => $data['dashboard_time_period'],
		'web_layout_mode' => $web_layout_mode,
		'clone' => $data['clone']
	]).');
'))
	->setOnDocumentReady()
	->show();
