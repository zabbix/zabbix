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

$checkbox_hash = 'dashboard'.crc32(json_encode([]));
if ($data['uncheck']) {
	uncheckTableRows($checkbox_hash);
}

$form = (new CForm())->setName('application_form');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_dashboards'))
				->onClick("checkAll('".$form->getName()."', 'all_dashboards', 'dashboardids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Dashboards'), 'name', $data['sort'], $data['sortorder'], (new CUrl('zabbix.php'))
				->setArgument('action', 'template.dashboard.list')
				->setArgument('templateid', $data['templateid'])
				->getUrl()
		)
	]);

foreach ($data['dashboards'] as $dashboard) {
	$name = new CLink($dashboard['name'], (new CUrl('zabbix.php'))
			->setArgument('action', 'template.dashboard.edit')
			->setArgument('dashboardid', $dashboard['dashboardid'])
			->getUrl()
	);

	$checkBox = new CCheckBox('dashboardids['.$dashboard['dashboardid'].']', $dashboard['dashboardid']);

	$table->addRow([$checkBox, $name]);
}

// Append table to form.
$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'dashboardids',
		['template.dashboard.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected dashboards?')]],
		$checkbox_hash
	)
]);

// Make widget.
(new CWidget())
	->setTitle(_('Dashboards'))
	->setControls(
		(new CTag('nav', true, new CRedirectButton(_('Create dashboard'), (new CUrl('zabbix.php'))
				->setArgument('action', 'template.dashboard.edit')
				->setArgument('templateid', $data['templateid'])
				->getUrl()
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(get_header_host_table('dashboards', $data['templateid']))
	->addItem($form)
	->show();
