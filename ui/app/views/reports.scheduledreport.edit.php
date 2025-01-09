<?php declare(strict_types = 0);
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
 */

$this->addJsFile('class.calendar.js');
$this->includeJsFile('reports.scheduledreport.edit.js.php', [
	'old_dashboardid' => $data['old_dashboardid'],
	'dashboard_inaccessible' => $data['dashboard_inaccessible']
]);

$html_page = (new CHtmlPage())
	->setTitle(_('Scheduled reports'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORTS_SCHEDULEDREPORT_EDIT));

$form = (new CForm())
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('scheduledreport')))->removeId())
	->setId('scheduledreport-form')
	->setName('scheduledreport-form')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', ($data['reportid'] == 0) ? 'scheduledreport.create' : 'scheduledreport.update')
			->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

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

$html_page
	->addItem($form)
	->show();
