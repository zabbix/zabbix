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
// include JS + templates
?>
<?php
	$_REQUEST['hostid'] = get_request('hostid', 0);
	$frm_title = _('Proxy');

	$name = get_request('host', '');
	$status = get_request('status', HOST_STATUS_PROXY_ACTIVE);
	$hosts = get_request('hosts', array());

	$interfaces = get_request('interfaces',array());

	$frmProxy = new CForm();
	$frmProxy->addVar('form', get_request('form', 1));
	$frmProxy->addVar('form_refresh', get_request('form_refresh', 0) + 1);


	$proxyList = new CFormList('proxylist');

	if ($_REQUEST['hostid'] > 0) {
		$proxies = API::Proxy()->get(array(
			'proxyids' => $_REQUEST['hostid'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid', 'host'),
			'output' => API_OUTPUT_EXTEND
		));
		$proxy = reset($proxies);

		$frm_title = _('Proxy').' ['.$proxy['host'].']';
		$frmProxy->addVar('hostid', $proxy['proxyid']);
	}

	$frmProxy->setName($frm_title);

	if (($_REQUEST['hostid'] > 0) && !isset($_REQUEST['form_refresh'])) {
		$name = $proxy['host'];
		$status = $proxy['status'];

		$interfaces = $proxy['interfaces'];
		$hosts = zbx_objectValues($proxy['hosts'], 'hostid');
	}

	$proxyList->addRow(_('Proxy name'), new CTextBox('host', $name, 30, 'no', 64));

	$statusBox = new CComboBox('status', $status, 'submit()');
	$statusBox->addItem(HOST_STATUS_PROXY_ACTIVE, _('Active'));
	$statusBox->addItem(HOST_STATUS_PROXY_PASSIVE, _('Passive'));

	$proxyList->addRow(_('Proxy mode'), $statusBox);

	if ($status == HOST_STATUS_PROXY_PASSIVE) {
		if (!empty($interfaces)) {
			$interface = reset($interfaces);
		}
		else {
			$interface = array('dns'=>'localhost', 'ip'=>'127.0.0.1', 'useip'=>1, 'port'=>'10051');
		}

		if (isset($interface['interfaceid'])) {
			$frmProxy->addVar('interfaces[0][interfaceid]', $interface['interfaceid']);
			$frmProxy->addVar('interfaces[0][hostid]', $interface['hostid']);
		}

		$cmbConnectBy = new CRadioButtonList('interfaces[0][useip]', $interface['useip']);
		$cmbConnectBy->addValue(_('IP'), 1);
		$cmbConnectBy->addValue(_('DNS'), 0);
		$cmbConnectBy->useJQueryStyle();

		$ifTab = new CTable();
		$ifTab->addClass('formElementTable');
		$ifTab->addRow(array(
			_('IP address'),
			_('DNS name'),
			_('Connect to'),
			_('Port')
		));
		$ifTab->addRow(array(
			new CTextBox('interfaces[0][ip]', $interface['ip'], '24', 'no', 39),
			new CTextBox('interfaces[0][dns]', $interface['dns'], '30', 'no', 64),
			$cmbConnectBy,
			new CTextBox('interfaces[0][port]', $interface['port'], 15, 'no', 64)
		));

		$proxyList->addRow(_('Interface'), new CDiv($ifTab, 'objectgroup inlineblock border_dotted ui-corner-all'));
	}


	$cmbHosts = new CTweenBox($frmProxy, 'hosts', $hosts);

	$sql = 'SELECT hostid, proxy_hostid, name '.
			' FROM hosts '.
			' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
				' AND '.DBin_node('hostid').
			' ORDER BY host';
	$db_hosts=DBselect($sql);
	while ($db_host = DBfetch($db_hosts)) {
		$cmbHosts->addItem(
			$db_host['hostid'],
			$db_host['name'],
			NULL,
			($db_host['proxy_hostid'] == 0 || $_REQUEST['hostid'] > 0 && bccomp($db_host['proxy_hostid'], $_REQUEST['hostid']) == 0)
		);
	}
	$proxyList->addRow(_('Hosts'), $cmbHosts->Get(_('Proxy hosts'), _('Other hosts')));

// Tabed form
	$proxyTabs = new CTabView();
	$proxyTabs->addTab('proxylist', _('Proxy'), $proxyList);

	$frmProxy->addItem($proxyTabs);

// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if ($_REQUEST['hostid'] > 0) {
		$others[] = new CSubmit('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete selected proxy?'), url_param('form').url_param('hostid'));
	}
	$others[] = new CButtonCancel(url_param('groupid'));

	$frmProxy->addItem(makeFormFooter($main, $others));


return $frmProxy;
?>
