<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 */

if (array_key_exists('error', $data)) {
	show_error_message($data['error']);

	return;
}

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
$this->addJsFile('class.tab-indicators.js');

$this->includeJsFile('dashboard/class.dashboard.js.php');
$this->includeJsFile('dashboard/class.dashboard-share.js.php');
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
			->setId('dashbrd-control')
			->addItem($main_filter_form)
			->addItem((new CTag('nav', true, [
				(new CList())
					->addItem(
						(new CButton('dashbrd-edit', _('Edit dashboard')))
							->setEnabled($data['allowed_edit'] && $data['dashboard']['editable'])
							->setAttribute('aria-disabled', !$data['dashboard']['editable'] ? 'true' : null)
					)
					->addItem(
						(new CButton('', '&nbsp;'))
							->addClass(ZBX_STYLE_BTN_ACTION)
							->setId('dashbrd-actions')
							->setTitle(_('Actions'))
							->setEnabled($data['allowed_edit'])
							->setAttribute('aria-haspopup', true)
							->setMenuPopup(CMenuPopupHelper::getDashboard($data['dashboard']['dashboardid'],
								$data['dashboard']['editable']
							))
					)
					->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			]))->setAttribute('aria-label', _('Content controls')))
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
			]))->addStyle('display: none'))
	)->setBreadcrumbs(
		(new CList())
			->setAttribute('role', 'navigation')
			->setAttribute('aria-label', _x('Hierarchy', 'screen reader'))
			->addItem(new CPartial('monitoring.dashboard.breadcrumbs', [
				'dashboard' => $data['dashboard']
			]))
				->addClass(ZBX_STYLE_OBJECT_GROUP)
				->addClass(ZBX_STYLE_FILTER_BREADCRUMB)
	);

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

$widget
	->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_CONTAINER))
	->show();

(new CScriptTag(
	'initializeDashboard('.
		json_encode($data['dashboard']).','.
		json_encode($data['widget_defaults']).','.
		json_encode($data['time_selector']).','.
		json_encode($data['dynamic']).','.
		json_encode($web_layout_mode).
	');'
))
	->setOnDocumentReady()
	->show();
