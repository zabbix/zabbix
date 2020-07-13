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
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create dashboard'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'dashboard.view')
					->setArgument('new', '1')
					->getUrl()
			))
		->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))
		->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'dashboard.list')))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			),
			(new CFormList())->addRow(_('Show'),
				(new CRadioButtonList('filter_userid', (int) $data['filter']['userid']))
					->addValue(_('All'), -1)
					->addValue(_('Created by me'), 1)
					->setModern(true)
			)
		])
		->addVar('action', 'dashboard.list')
	);

$form = (new CForm())->setName('dashboardForm');

// create dashboard table
$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.list')
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_dashboards'))
				->onClick("checkAll('".$form->getName()."', 'all_dashboards', 'dashboardids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Owner')
	]);

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.view')
	->setArgument('dashboardid', '');

foreach ($data['dashboards'] as $dashboard) {
	$table->addRow([
		(new CCheckBox('dashboardids['.$dashboard['dashboardid'].']', $dashboard['dashboardid']))
			->setEnabled($dashboard['editable']),
		[
			new CLink($dashboard['name'],
				$url
					->setArgument('dashboardid', $dashboard['dashboardid'])
					->getUrl()
			),
			((int) $dashboard['private'] === PUBLIC_SHARING
				|| count($dashboard['users']) > 0 || count($dashboard['userGroups']) > 0)
					? (new CSpan())->addClass(ZBX_STYLE_DASHBOARD_SHARED)->setAttribute('title', _('Shared'))
					: ''
		],
		$data['owners'][$dashboard['userid']]
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'dashboardids', [
		'dashboard.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected dashboards?')]
	], 'dashboard')
]);

$widget->addItem($form);
$widget->show();
