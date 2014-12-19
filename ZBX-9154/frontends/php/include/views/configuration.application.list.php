<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$applicationWidget = new CWidget();

$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);

// append host summary to widget header
if (empty($this->data['hostid'])) {
	$createButton = new CSubmit('form', _('Create application (select host first)'));
	$createButton->setEnabled(false);
	$createForm->addItem($createButton);
}
else {
	$createForm->addItem(new CSubmit('form', _('Create application')));

	$applicationWidget->addItem(get_header_host_table('applications', $this->data['hostid']));
}

$applicationWidget->addPageHeader(_('CONFIGURATION OF APPLICATIONS'), $createForm);

// create widget header
$filterForm = new CForm('get');
$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB(true)));
$filterForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB(true)));

$applicationWidget->addHeader(_('Applications'), $filterForm);
$applicationWidget->addHeaderRowNumber();

// create form
$applicationForm = new CForm();
$applicationForm->setName('applicationForm');
$applicationForm->addVar('groupid', $this->data['groupid']);
$applicationForm->addVar('hostid', $this->data['hostid']);

// create table
$applicationTable = new CTableInfo(_('No applications found.'));
$applicationTable->setHeader(array(
	new CCheckBox('all_applications', null, "checkAll('".$applicationForm->getName()."', 'all_applications', 'applications');"),
	$this->data['displayNodes'] ? _('Node') : null,
	($this->data['hostid'] > 0) ? null : _('Host'),
	make_sorting_header(_('Application'), 'name'),
	_('Show')
));

foreach ($this->data['applications'] as $application) {
	// inherited app, display the template list
	if ($application['templateids'] && !empty($application['sourceTemplates'])) {
		$name = array();

		CArrayHelper::sort($application['sourceTemplates'], array('name'));

		foreach ($application['sourceTemplates'] as $template) {
			$name[] = new CLink($template['name'], 'applications.php?hostid='.$template['hostid'], 'unknown');
			$name[] = ', ';
		}
		array_pop($name);
		$name[] = NAME_DELIMITER;
		$name[] = $application['name'];
	}
	else {
		$name = new CLink(
			$application['name'],
			'applications.php?'.
				'form=update'.
				'&applicationid='.$application['applicationid'].
				'&hostid='.$application['hostid'].
				'&groupid='.$this->data['groupid']
		);
	}

	$applicationTable->addRow(array(
		new CCheckBox('applications['.$application['applicationid'].']', null, null, $application['applicationid']),
		$this->data['displayNodes'] ? $application['nodename'] : null,
		($this->data['hostid'] > 0) ? null : $application['host'],
		$name,
		array(
			new CLink(
				_('Items'),
				'items.php?'.
					'hostid='.$application['hostid'].
					'&filter_set=1'.
					'&filter_application='.urlencode($application['name'])
			),
			SPACE.'('.count($application['items']).')'
		)
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected applications?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected applications?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected applications?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "applications";');
zbx_add_post_js('chkbxRange.prefix = "'.$this->data['hostid'].'";');
zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$applicationForm->addItem(array($this->data['paging'], $applicationTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$applicationWidget->addItem($applicationForm);

return $applicationWidget;
