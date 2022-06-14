<?php declare(strict_types = 0);
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

$this->addJsFile('class.calendar.js');
$this->includeJsFile('reports.scheduledreport.edit.js.php', [
	'old_dashboardid' => $data['old_dashboardid'],
	'dashboard_inaccessible' => $data['dashboard_inaccessible']
]);

$widget = (new CWidget())->setTitle(_('Scheduled reports'));

$form = (new CForm())
	->setId('scheduledreport-form')
	->setName('scheduledreport-form')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', ($data['reportid'] == 0) ? 'scheduledreport.create' : 'scheduledreport.update')
			->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

if ($data['reportid'] != 0) {
	$form->addVar('reportid', $data['reportid']);
}

if ($data['old_dashboardid'] != 0) {
	$form->addVar('old_dashboardid', $data['old_dashboardid']);
}

$form_grid = new CPartial('scheduledreport.formgrid.html', [
	'source' => 'reports',
	'form' => $form->getName()
] + $data);

$form->addItem((new CTabView())->addTab('scheduledreport_tab', _('Scheduled report'), $form_grid));

$widget
	->addItem($form)
	->show();
