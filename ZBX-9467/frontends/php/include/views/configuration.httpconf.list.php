<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
?>
<?php

$httpWidget = new CWidget();

// create new scenario button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);
if ($this->data['hostid'] > 0) {
	$createScenarioButton = new CSubmit('form', _('Create scenario'));
}
else {
	$createScenarioButton = new CSubmit('form', _('Create scenario (select host first)'));
	$createScenarioButton->setEnabled(false);
}
$createForm->addItem($createScenarioButton);
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
$httpForm = new CForm('get');
$httpForm->setName('scenarios');
$httpForm->addVar('hostid', $this->data['hostid']);

if (!empty($this->data['showAllApps'])) {
	$expandLink = new CLink(new CImg('images/general/minus.png'), '?close=1'.url_param('groupid').url_param('hostid'));
}
else {
	$expandLink = new CLink(new CImg('images/general/plus.png'), '?open=1'.url_param('groupid').url_param('hostid'));
}

$httpTable = new CTableInfo(_('No web scenarios defined.'));
$httpTable->setHeader(array(
	new CCheckBox('all_httptests', null, "checkAll('".$httpForm->getName()."', 'all_httptests', 'group_httptestid');"),
	is_show_all_nodes() ? make_sorting_header(_('Node'), 'h.hostid') : null,
	$_REQUEST['hostid'] == 0 ? make_sorting_header(_('Host'), 'host') : null,
	make_sorting_header(array($expandLink, SPACE, _('Name')), 'name'),
	_('Number of steps'),
	_('Update interval'),
	make_sorting_header(_('Status'), 'status'))
);

$httpTableRows = array();
foreach ($this->data['db_httptests'] as $httptestid => $httptest_data) {
	$db_app = $this->data['db_apps'][$httptest_data['applicationid']];

	if (!isset($httpTableRows[$db_app['applicationid']])) {
		$httpTableRows[$db_app['applicationid']] = array();
	}
	if (!uint_in_array($db_app['applicationid'], $_REQUEST['applications']) && !isset($this->data['showAllApps'])) {
		continue;
	}

	$httpTableRows[$db_app['applicationid']][] = array(
		new CCheckBox('group_httptestid['.$httptest_data['httptestid'].']', null, null, $httptest_data['httptestid']),
		is_show_all_nodes() ? SPACE : null,
		$_REQUEST['hostid'] > 0 ? null : $db_app['hostname'],
		new CLink($httptest_data['name'], '?form=update'.'&httptestid='.$httptest_data['httptestid'].'&hostid='.$db_app['hostid'].url_param('groupid')),
		$httptest_data['step_count'],
		$httptest_data['delay'],
		new CCol(
			new CLink(
				httptest_status2str($httptest_data['status']),
				'?group_httptestid[]='.$httptest_data['httptestid'].'&go='.($httptest_data['status'] ? 'activate' : 'disable'),
				httptest_status2style($httptest_data['status'])
			)
		)
	);
}

foreach ($httpTableRows as $appid => $app_rows) {
	$db_app = $this->data['db_apps'][$appid];

	if (uint_in_array($db_app['applicationid'], $_REQUEST['applications']) || isset($this->data['showAllApps'])) {
		$link = new CLink(new CImg('images/general/minus.png'), '?close=1&applicationid='.$db_app['applicationid'].url_param('groupid').url_param('hostid').url_param('applications').url_param('select'));
	}
	else {
		$link = new CLink(new CImg('images/general/plus.png'), '?open=1&applicationid='.$db_app['applicationid'].url_param('groupid').url_param('hostid').url_param('applications').url_param('select'));
	}

	$column = new CCol(array(
		$link,
		SPACE,
		bold($db_app['name']),
		SPACE.'('._n('%1$d scenario', '%1$d scenarios', $db_app['scenarios_cnt']).')'
	));
	$column->setColSpan(6);

	$httpTable->addRow(array(get_node_name_by_elid($db_app['applicationid']), $column));

	foreach ($app_rows as $row) {
		$httpTable->addRow($row);
	}
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
