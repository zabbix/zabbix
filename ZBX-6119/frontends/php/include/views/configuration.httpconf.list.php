<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$httpWidget = new CWidget();

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$httpWidget->addItem(get_header_host_table('web', $this->data['hostid']));
}

// create new scenario button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);
$createForm->addItem(new CSubmit('form', _('Create scenario')));
$httpWidget->addPageHeader(_('CONFIGURATION OF WEB MONITORING'), $createForm);

// header
$filterForm = new CForm('get');
$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
$filterForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB()));

$httpWidget->addHeader(_('Scenarios'), $filterForm);
$httpWidget->addHeaderRowNumber(array(
	'[ ',
	new CLink($this->data['showDisabled'] ? _('Hide disabled scenarios') : _('Show disabled scenarios'),
	'?showdisabled='.($this->data['showDisabled'] ? 0 : 1), null), ' ]'
));

// create form
$httpForm = new CForm();
$httpForm->setName('scenarios');
$httpForm->addVar('hostid', $this->data['hostid']);

$httpTable = new CTableInfo(_('No web scenarios defined.'));
$httpTable->setHeader(array(
	new CCheckBox('all_httptests', null, "checkAll('".$httpForm->getName()."', 'all_httptests', 'group_httptestid');"),
	$_REQUEST['hostid'] == 0 ? make_sorting_header(_('Host'), 'hostname') : null,
	make_sorting_header(_('Name'), 'name'),
	_('Number of steps'),
	_('Update interval'),
	make_sorting_header(_('Status'), 'status'))
);

foreach ($this->data['httpTests'] as $httpTestId => $httpTest) {
	$name = array();
	if (isset($this->data['parentTemplates'][$httpTestId])) {
		$template = $this->data['parentTemplates'][$httpTestId];
		$name[] = new CLink($template['name'], '?groupid=0&hostid='.$template['id'], 'unknown');
		$name[] = ':'.SPACE;
	}
	$name[] = new CLink($httpTest['name'], '?form=update'.'&httptestid='.$httpTest['httptestid'].'&hostid='.$httpTest['hostid']);

	$httpTable->addRow(array(
		new CCheckBox('group_httptestid['.$httpTest['httptestid'].']', null, null, $httpTest['httptestid']),
		$_REQUEST['hostid'] > 0 ? null : $httpTest['hostname'],
		$name,
		$httpTest['stepscnt'],
		$httpTest['delay'],
		new CLink(
			httptest_status2str($httpTest['status']),
			'?group_httptestid[]='.$httpTest['httptestid'].'&go='.($httpTest['status'] ? 'activate' : 'disable'),
			httptest_status2style($httpTest['status'])
		)
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('activate', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected WEB scenarios?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('disable', _('Disable selected'));
$goOption->setAttribute('confirm',_('Disable selected WEB scenarios?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('clean_history', _('Clear history for selected'));
$goOption->setAttribute('confirm', _('Delete history of selected WEB scenarios?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected WEB scenarios?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "group_httptestid";');

// append table to form
$httpForm->addItem(array($this->data['paging'], $httpTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$httpWidget->addItem($httpForm);
return $httpWidget;
