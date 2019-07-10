<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


if (array_key_exists('error', $data)) {
	show_error_message($data['error']);
}
else {
	$this->addJsFile('flickerfreescreen.js');
	$this->addJsFile('gtlc.js');
	$this->addJsFile('dashboard.grid.js');
	$this->addJsFile('class.calendar.js');
	$this->addJsFile('multiselect.js');
	$this->addJsFile('layout.mode.js');
	$this->addJsFile('class.coverride.js');
	$this->addJsFile('class.cverticalaccordion.js');
	$this->addJsFile('class.crangecontrol.js');
	$this->addJsFile('colorpicker.js');
	$this->addJsFile('class.csvggraph.js');
	$this->addJsFile('csvggraphwidget.js');
	$this->addJsFile('class.cclock.js');
	$this->addJsFile('class.cnavtree.js');
	$this->addJsFile('class.mapWidget.js');
	$this->addJsFile('class.svg.canvas.js');
	$this->addJsFile('class.svg.map.js');

	$this->includeJSfile('app/views/monitoring.dashboard.view.js.php');

	$breadcrumbs = include 'monitoring.dashboard.breadcrumbs.php';

	$main_filter_form = null;

	if ($data['dynamic']['has_dynamic_widgets']) {
		$main_filter_form = (new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addVar('action', 'dashboard.view')
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getGroupsCB()
				])
				->addItem([
					new CLabel(_('Host'), 'hostid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getHostsCB()
				])
		);
	}

	$web_layout_mode = CView::getLayoutMode();

	$widget = (new CWidget())
		->setTitle($data['dashboard']['name'])
		->setWebLayoutMode($web_layout_mode)
		->setControls((new CList())
			->setId('dashbrd-control')
			->addItem($main_filter_form)
			->addItem((new CTag('nav', true, [
				(new CList())
					->addItem((new CButton('dashbrd-edit', _('Edit dashboard')))
						->setEnabled($data['dashboard']['editable'])
						->setAttribute('aria-disabled', !$data['dashboard']['editable'] ? 'true' : null)
					)
					->addItem((new CButton('', '&nbsp;'))
						->addClass(ZBX_STYLE_BTN_ACTION)
						->setId('dashbrd-actions')
						->setTitle(_('Actions'))
						->setAttribute('aria-haspopup', true)
						->setMenuPopup(CMenuPopupHelper::getDashboard($data['dashboard']['dashboardid']))
					)
					->addItem(get_icon('fullscreen'))
			]))->setAttribute('aria-label', _('Content controls'))
		)
		->addItem((new CListItem([
			(new CTag('nav', true, [
				new CList([
					(new CButton('dashbrd-config'))->addClass(ZBX_STYLE_BTN_DASHBRD_CONF),
					(new CButton('dashbrd-add-widget', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add widget')]))
						->addClass(ZBX_STYLE_BTN_ALT),
					(new CButton('dashbrd-save', _('Save changes'))),
					(new CLink(_('Cancel'), '#'))->setId('dashbrd-cancel'),
					''
				])
			]))
				->setAttribute('aria-label', _('Content controls'))
				->addClass(ZBX_STYLE_DASHBRD_EDIT)
			]))
				->addStyle('display: none')
	))
		->setBreadcrumbs((new CList())
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _x('Hierarchy', 'screen reader'))
			->addItem($breadcrumbs)
			->addClass(ZBX_STYLE_OBJECT_GROUP)
			->addClass(ZBX_STYLE_FILTER_BREADCRUMB)
		);

	$timeline = null;
	if ($data['show_timeselector']) {
		$timeline = (new CFilter(new CUrl()))
			->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
			->setActiveTab($data['active_tab'])
			->addTimeSelector($data['timeline']['from'], $data['timeline']['to'],
				$web_layout_mode != ZBX_LAYOUT_KIOSKMODE);
		}

	$widget
		->addItem($timeline)
		->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_CONTAINER))
		->show();

	/*
	 * Javascript
	 */
	// Activate blinking.
	$this->addPostJS('jqBlink.blink();');

	$dashboard_data = [
		// Name is required for new dashboard creation.
		'name'		=> $data['dashboard']['name'],
		'userid'	=> $data['dashboard']['owner']['id'],
		'dynamic'	=> $data['dynamic']
	];

	if (array_key_exists('sharing', $data['dashboard'])) {
		$dashboard_data['sharing'] = $data['dashboard']['sharing'];
	}

	$dashboard_options = [
		'max-rows' => DASHBOARD_MAX_ROWS,
		'max-columns' => DASHBOARD_MAX_COLUMNS,
		'widget-max-rows' => DASHBOARD_WIDGET_MAX_ROWS,
		'editable' => $data['dashboard']['editable'],
		'edit_mode' => $data['dashboard_edit_mode'],
		'kioskmode' => ($web_layout_mode === ZBX_LAYOUT_KIOSKMODE)
	];
	if ($data['dashboard']['dashboardid'] != 0) {
		$dashboard_data['id'] = $data['dashboard']['dashboardid'];
	}
	else {
		$dashboard_options['updated'] = true;
	}

	// must be done before adding widgets, because it causes dashboard to resize.
	if ($data['show_timeselector']) {
		$this->addPostJS(
			'timeControl.useTimeRefresh('.CWebUser::getRefresh().');'.
			'timeControl.addObject("scrollbar", '.CJs::encodeJson($data['timeline']).', '.
				CJs::encodeJson($data['timeControlData']).
			');'.
			'timeControl.processObjects();'
		);
	}

	// Initialize dashboard grid.
	$this->addPostJS(
		'jQuery(".'.ZBX_STYLE_DASHBRD_GRID_CONTAINER.'")'.
			'.dashboardGrid('.CJs::encodeJson($dashboard_options).')'.
			'.dashboardGrid("setDashboardData", '.CJs::encodeJson($dashboard_data).')'.
			'.dashboardGrid("setWidgetDefaults", '.CJs::encodeJson($data['widget_defaults']).')'.
			'.dashboardGrid("addWidgets", '.CJs::encodeJson($data['grid_widgets']).
		');'
	);
}
