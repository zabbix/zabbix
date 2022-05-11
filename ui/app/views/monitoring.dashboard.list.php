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

if ($data['uncheck']) {
	uncheckTableRows('dashboard');
}
$this->addJsFile('layout.mode.js');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Dashboards'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CRedirectButton(_('Create dashboard'),
						(new CUrl('zabbix.php'))
							->setArgument('action', 'dashboard.view')
							->setArgument('new', '1')
							->getUrl()
					))->setEnabled($data['allowed_edit'])
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			)
		)->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$widget
		->addItem((new CFilter())
			->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'dashboard.list'))
			->setProfile($data['profileIdx'])
			->setActiveTab($data['active_tab'])
			->addFilterTab(_('Filter'), [
				(new CFormList())->addRow(_('Name'),
					(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				),
				(new CFormList())->addRow(_('Show'),
					(new CRadioButtonList('filter_show', (int) $data['filter']['show']))
						->addValue(_('All'), DASHBOARD_FILTER_SHOW_ALL)
						->addValue(_('Created by me'), DASHBOARD_FILTER_SHOW_MY)
						->setModern(true)
				)
			])
			->addVar('action', 'dashboard.list')
		);
}

$form = (new CForm())->setName('dashboardForm');

// Create dashboard table.
$table = (new CTableInfo())
	->addClass(ZBX_STYLE_DASHBOARD_LIST)
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_dashboards'))
				->onClick("checkAll('".$form->getName()."', 'all_dashboards', 'dashboardids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'dashboard.list')
				->getUrl())
	]);

foreach ($data['dashboards'] as $dashboard) {
	$tags = [];

	if ($dashboard['userid'] == CWebUser::$data['userid']) {
		$tags[] = (new CSpan(_('My')))->addClass(ZBX_STYLE_STATUS_GREEN);

		if ($dashboard['private'] == PUBLIC_SHARING || count($dashboard['users']) > 0
				|| count($dashboard['userGroups']) > 0) {
			$tags[] = ' ';
			$tags[] = (new CSpan(_('Shared')))->addClass(ZBX_STYLE_STATUS_YELLOW);
		}
	}

	$table->addRow([
		(new CCheckBox('dashboardids['.$dashboard['dashboardid'].']', $dashboard['dashboardid']))
			->setEnabled($dashboard['editable']),
		(new CDiv([
			new CLink($dashboard['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'dashboard.view')
					->setArgument('dashboardid', $dashboard['dashboardid'])
					->getUrl()
			),
			$tags ? new CDiv($tags) : null
		]))->addClass(ZBX_STYLE_DASHBOARD_LIST_ITEM)
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'dashboardids', [
		'dashboard.delete' => [
			'name' => _('Delete'),
			'confirm' => _('Delete selected dashboards?'),
			'disabled' => !$data['allowed_edit']
		]
	], 'dashboard')
]);

$widget->addItem($form);
$widget->show();
