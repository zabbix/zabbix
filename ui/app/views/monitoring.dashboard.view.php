<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

	$this->includeJsFile('monitoring.dashboard.view.js.php');

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
				(new CLabel(_('Host'), 'hostid'))->addStyle('margin-right: 5px;'),
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
		->setControls((new CList())
			->setId('dashbrd-control')
			->addItem($main_filter_form)
			->addItem((new CTag('nav', true, [
				(new CList())
					->addItem((new CButton('dashbrd-edit', _('Edit dashboard')))
						->setEnabled($data['dashboard']['editable'])
						->setAttribute('aria-disabled', !$data['dashboard']['editable'] ? 'true' : null)
					)
					->addItem((new CButton(null, NBSP()))
						->addClass(ZBX_STYLE_BTN_ACTION)
						->setId('dashbrd-actions')
						->setTitle(_('Actions'))
						->setAttribute('aria-haspopup', true)
						->setMenuPopup(CMenuPopupHelper::getDashboard($data['dashboard']['dashboardid']))
					)
					->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			]))->setAttribute('aria-label', _('Content controls'))
		)
		->addItem((new CListItem([
			(new CTag('nav', true, [
				new CList([
					(new CButton('dashbrd-config'))->addClass(ZBX_STYLE_BTN_DASHBRD_CONF),
					(new CButton('dashbrd-add-widget', [(new CSpan())->addClass(ZBX_STYLE_PLUS_ICON), _('Add widget')]))
						->addClass(ZBX_STYLE_BTN_ALT),
					(new CButton('dashbrd-paste-widget', _('Paste widget')))
						->addClass(ZBX_STYLE_BTN_ALT)
						->setEnabled(false),
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
			->addItem(new CPartial('monitoring.dashboard.breadcrumbs', [
				'dashboard' => $data['dashboard']
			]))
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

	// JavaScript

	// Activate blinking.
	(new CScriptTag('jqBlink.blink();'))->show();

	$dashboard_data = [
		// Name is required for new dashboard creation.
		'name' => $data['dashboard']['name'],
		'userid' => $data['dashboard']['owner']['id'],
		'dynamic_hostid' => $data['dynamic']['host'] ? $data['dynamic']['host']['id'] : null
	];

	if (array_key_exists('sharing', $data['dashboard'])) {
		$dashboard_data['sharing'] = $data['dashboard']['sharing'];
	}

	$dashboard_options = [
		'max-rows' => DASHBOARD_MAX_ROWS,
		'max-columns' => DASHBOARD_MAX_COLUMNS,
		'widget-min-rows' => DASHBOARD_WIDGET_MIN_ROWS,
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

	if ($data['dynamic']['has_dynamic_widgets']) {
		(new CScriptTag(
			// Add event listener to perform dynamic host switch when browser back/previous buttons are pressed.
			'window.addEventListener("popstate", e => {'.
				'var data = (e.state && e.state.host) ? [e.state.host] : [];'.
				'jQuery("#dynamic_hostid").multiSelect("addData", data, false);'.
				'jQuery(".dashbrd-grid-container").dashboardGrid("refreshDynamicWidgets", data ? data[0] : null);'.
			'});'.

			// Dynamic host selector on-change handler.
			'jQuery("#dynamic_hostid").on("change", function() {'.
				'var hosts = jQuery(this).multiSelect("getData"),'.
					'host = hosts.length ? hosts[0] : null,'.
					'url = new Curl("zabbix.php", false);'.

				// Make URL.
				'url.setArgument("action", "dashboard.view");'.
				'url.setArgument("dashboardid", '.$data['dashboard']['dashboardid'].');'.
				($data['show_timeselector']
					? 'url.setArgument("from", "'.$data['timeline']['from'].'");'.
						'url.setArgument("to", "'.$data['timeline']['to'].'");'
					: '').

				'if (host) {'.
					'url.setArgument("hostid", host.id);'.
				'}'.

				// Refresh dynamic widgets.
				'jQuery(".dashbrd-grid-container").dashboardGrid("refreshDynamicWidgets", host);'.

				// Push URL change.
				'history.pushState({host: host}, "", url.getUrl());'.

				// Update user profile.
				'var hostid = host ? host.id : 1;'.
				'updateUserProfile("'.CControllerDashboardView::DYNAMIC_ITEM_HOST_PROFILE_KEY.'", hostid);'.
			'});'
		))->show();
	}

	// Process objects before adding widgets, not to cause dashboard to resize.
	if ($data['show_timeselector']) {
		(new CScriptTag(
			'timeControl.addObject("scrollbar", '.json_encode($data['timeline']).', '.
				json_encode($data['timeControlData']).
			');'.
			'timeControl.processObjects();'
		))->show();
	}

	// Initialize dashboard grid.
	(new CScriptTag(
		'$(".'.ZBX_STYLE_DASHBRD_GRID_CONTAINER.'")'.
			'.dashboardGrid('.json_encode($dashboard_options).')'.
			'.dashboardGrid("setDashboardData", '.json_encode($dashboard_data).')'.
			'.dashboardGrid("setWidgetDefaults", '.json_encode($data['widget_defaults']).')'.
			'.dashboardGrid("addWidgets", '.json_encode($data['grid_widgets']).
		');'
	))->show();
}
