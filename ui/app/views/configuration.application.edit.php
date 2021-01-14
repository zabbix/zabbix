<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$this->includeJsFile('configuration.applications.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Applications'))
	->setNavigation(getHostNavigation('applications', $data['hostid']));

$application_form = (new CForm())
	->setId('application-form')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', ($data['applicationid'] == 0) ? 'application.create' : 'application.update')
		->setArgument('applicationid', $data['applicationid'])
		->setArgument('hostid', $data['hostid'])
		->getUrl()
	)
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('hostid', $data['hostid']);

$application_tab = (new CTabView())
	->addTab('applicationTab', _('Application'),
		(new CFormList())
			->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(),
				(new CTextBox('name', $data['name']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			)
	);

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'application.list')
	->setArgument('page', CPagerHelper::loadPage('application.list', null))
))->setId('cancel');

if ($data['applicationid'] == 0) {
	$application_tab->setFooter(makeFormFooter(new CSubmit('add', _('Add')), [$cancel_button]));
}
else {
	$application_tab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			(new CSimpleButton(_('Clone')))->setId('clone'),
			(new CRedirectButton(_('Delete'), (new CUrl('zabbix.php'))
					->setArgument('action', 'application.delete')
					->setArgument('applicationids', [$data['applicationid']])
					->setArgumentSID(),
				_('Delete application?')
			))->setId('delete'),
			$cancel_button
		]
	));
}

$application_form->addItem($application_tab);
$widget
	->addItem($application_form)
	->show();
