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

$this->addJsFile('class.cnavtree.js');
$this->addJsFile('class.coverride.js');
$this->addJsFile('class.crangecontrol.js');
$this->addJsFile('class.csvggraph.js');
$this->addJsFile('class.dashboard.js');
$this->addJsFile('class.dashboard.page.js');
$this->addJsFile('class.dashboard.widget.placeholder.js');
$this->addJsFile('class.svg.canvas.js');
$this->addJsFile('class.svg.map.js');
$this->addJsFile('class.widgets-data.js');
$this->addJsFile('class.widget-base.js');
$this->addJsFile('class.widget.js');
$this->addJsFile('class.widget.inaccessible.js');
$this->addJsFile('class.widget.iterator.js');
$this->addJsFile('class.widget.misconfigured.js');
$this->addJsFile('class.widget.paste-placeholder.js');
$this->addJsFile('class.widget-field.checkbox-list.js');
$this->addJsFile('class.widget-field.multiselect.js');
$this->addJsFile('class.widget-field.time-period.js');
$this->addJsFile('class.widget-select.popup.js');
$this->addJsFile('colorpicker.js');
$this->addJsFile('d3.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('leaflet.js');
$this->addJsFile('leaflet.markercluster.js');
$this->addJsFile('class.geomaps.js');

$this->includeJsFile('configuration.dashboard.edit.js.php');

$this->addCssFile('assets/styles/vendors/Leaflet/leaflet.css');

$html_page = (new CHtmlPage())
	->setTitle(_('Dashboards'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_DASHBOARDS_EDIT))
	->setControls(
		(new CList())
			->setId('dashboard-control')
			->addItem(
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
			)
	)
	->setNavigation(getHostNavigation('dashboards', $data['dashboard']['templateid']));

$dashboard = (new CDiv())
	->addClass(ZBX_STYLE_DASHBOARD)
	->addClass(ZBX_STYLE_DASHBOARD_IS_EDIT_MODE);

if (count($data['dashboard']['pages']) > 1) {
	$dashboard->addClass(ZBX_STYLE_DASHBOARD_IS_MULTIPAGE);
}

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
						->setEnabled(false)
				])
		)
);

$dashboard->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID));

$html_page
	->addItem($dashboard)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'widget_last_type' => $data['widget_last_type'],
		'dashboard_time_period' => $data['dashboard_time_period'],
		'page' => $data['page']
	]).');
'))
	->setOnDocumentReady()
	->show();
