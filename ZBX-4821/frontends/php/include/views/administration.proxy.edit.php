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
?>
<?php
$proxyWidget = new CWidget();
$proxyWidget->addPageHeader(_('CONFIGURATION OF PROXIES'));

// create form
$proxyForm = new CForm();
$proxyForm->setName('proxyForm');
$proxyForm->addVar('form', $this->data['form']);
$proxyForm->addVar('form_refresh', $this->data['form_refresh']);
if (!empty($this->data['proxyid'])) {
	$proxyForm->addVar('proxyid', $this->data['proxyid']);
}

// create form list
$proxyFormList = new CFormList('proxyFormList');
$proxyFormList->addRow(_('Proxy name'), new CTextBox('host', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, 'no', 64));

// append status to form list
$statusBox = new CComboBox('status', $this->data['status'], 'submit()');
$statusBox->addItem(HOST_STATUS_PROXY_ACTIVE, _('Active'));
$statusBox->addItem(HOST_STATUS_PROXY_PASSIVE, _('Passive'));
$proxyFormList->addRow(_('Proxy mode'), $statusBox);

if ($this->data['status'] == HOST_STATUS_PROXY_PASSIVE) {
	if (isset($this->data['interface']['interfaceid'])) {
		$proxyForm->addVar('interfaces[0][interfaceid]', $this->data['interface']['interfaceid']);
		$proxyForm->addVar('interfaces[0][hostid]', $this->data['interface']['hostid']);
	}

	$interfaceTable = new CTable(_('No interfaces defined.'), 'formElementTable');
	$interfaceTable->addRow(array(
		_('IP address'),
		_('DNS name'),
		_('Connect to'),
		_('Port')
	));

	$connectByComboBox = new CRadioButtonList('interfaces[0][useip]', $this->data['interface']['useip']);
	$connectByComboBox->addValue(_('IP'), 1);
	$connectByComboBox->addValue(_('DNS'), 0);
	$connectByComboBox->useJQueryStyle();

	$interfaceTable->addRow(array(
		new CTextBox('interfaces[0][ip]', $this->data['interface']['ip'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 39),
		new CTextBox('interfaces[0][dns]', $this->data['interface']['dns'], ZBX_TEXTBOX_SMALL_SIZE, 'no', 64),
		$connectByComboBox,
		new CTextBox('interfaces[0][port]', $this->data['interface']['port'], 18, 'no', 64)
	));
	$proxyFormList->addRow(_('Interface'), new CDiv($interfaceTable, 'objectgroup inlineblock border_dotted ui-corner-all'));
}

// append hosts to form list
$hostsTweenBox = new CTweenBox($proxyForm, 'hosts', $this->data['hosts']);
foreach ($this->data['dbHosts'] as $host) {
	$hostsTweenBox->addItem(
		$host['hostid'],
		$host['name'],
		null,
		empty($host['proxy_hostid']) || (!empty($this->data['proxyid']) && bccomp($host['proxy_hostid'], $this->data['proxyid']) == 0)
	);
}
$proxyFormList->addRow(_('Hosts'), $hostsTweenBox->get(_('Proxy hosts'), _('Other hosts')));

// append tabs to form
$proxyTab = new CTabView();
$proxyTab->addTab('proxyTab', _('Proxy'), $proxyFormList);
$proxyForm->addItem($proxyTab);

// append buttons to form
if (!empty($this->data['proxyid'])) {
	$proxyForm->addItem(makeFormFooter(
		new CSubmit('save', _('Save')),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete proxy?'), url_param('form').url_param('proxyid')),
			new CButtonCancel()
		)
	));
}
else {
	$proxyForm->addItem(makeFormFooter(
		new CSubmit('save', _('Save')),
		array(new CButtonCancel())
	));
}

// append form to widget
$proxyWidget->addItem($proxyForm);
return $proxyWidget;
?>
