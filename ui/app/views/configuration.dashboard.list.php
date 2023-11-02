<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @var array $data
 */

$this->includeJsFile('configuration.dashboard.list.js.php');

$checkbox_hash = 'dashboard_'.$data['templateid'];

if ($data['uncheck']) {
	uncheckTableRows($checkbox_hash);
}

$form = (new CForm())
	->setName('dashboard_form')
	->addVar('templateid', $data['templateid']);

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_dashboards'))
				->onClick("checkAll('".$form->getName()."', 'all_dashboards', 'dashboardids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'template.dashboard.list')
				->setArgument('templateid', $data['templateid'])
				->getUrl()
		)
	]);

foreach ($data['dashboards'] as $dashboardid => $dashboard) {
	$table->addRow([
		new CCheckBox('dashboardids['.$dashboardid.']', $dashboardid),
		new CLink($dashboard['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'template.dashboard.edit')
				->setArgument('dashboardid', $dashboardid)
				->getUrl()
		)
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'dashboardids', [
		'template.dashboard.delete' => [
			'name' => _('Delete'),
			'confirm_singular' => _('Delete selected dashboard?'),
			'confirm_plural' => _('Delete selected dashboards?'),
			'csrf_token' => CCsrfTokenHelper::get('template')
		]
	], $checkbox_hash)
]);

(new CHtmlPage())
	->setTitle(_('Dashboards'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_DASHBOARDS_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(new CRedirectButton(_('Create dashboard'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'template.dashboard.edit')
						->setArgument('templateid', $data['templateid'])
				))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('dashboards', $data['templateid']))
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'checkbox_hash' => $checkbox_hash
	]).');
'))
	->setOnDocumentReady()
	->show();
