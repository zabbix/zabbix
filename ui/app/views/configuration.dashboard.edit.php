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
 */

$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('colorpicker.js');
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
$this->addJsFile('class.sortable.js');

$this->includeJsFile('configuration.dashboard.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Dashboards'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_DASHBOARD_EDIT))
	->setControls(
		(new CList())
			->setId('dashboard-control')
			->addItem(
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
					(new CSimpleButton())
						->addClass(ZBX_STYLE_DASHBOARD_PREVIOUS_PAGE)
						->addClass('btn-iterator-page-previous')
						->setEnabled(false),
					(new CSimpleButton())
						->addClass(ZBX_STYLE_DASHBOARD_NEXT_PAGE)
						->addClass('btn-iterator-page-next')
						->setEnabled(false)
				])
		)
);

$dashboard->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBOARD_GRID));

$widget
	->addItem($dashboard)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'dashboard' => $data['dashboard'],
		'widget_defaults' => $data['widget_defaults'],
		'time_period' => $data['time_period'],
		'page' => $data['page']
	]).');
'))
	->setOnDocumentReady()
	->show();
