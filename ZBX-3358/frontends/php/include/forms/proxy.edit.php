<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// include JS + templates
?>
<?php
	$_REQUEST['hostid'] = get_request('hostid', 0);
	$frm_title = S_PROXY;

	$name = get_request('host', '');
	$status = get_request('status', HOST_STATUS_PROXY_ACTIVE);
	$hosts = get_request('hosts',array());

	$interfaces = get_request('interfaces',array());

	$frmProxy = new CForm('proxies.php');
	$frmProxy->addVar('form', get_request('form', 1));
	$frmProxy->addVar('form_refresh', get_request('form_refresh',0)+1);


	$proxyList = new CFormList('proxylist');

	if($_REQUEST['hostid'] > 0){
		$proxies = CProxy::get(array(
			'proxyids' => $_REQUEST['hostid'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid', 'host'),
			'output' => API_OUTPUT_EXTEND
		));
		$proxy = reset($proxies);

		$frm_title = S_PROXY.' ['.$proxy['host'].']';
		$frmProxy->addVar('hostid', $proxy['proxyid']);
	}

	$frmProxy->setName($frm_title);

	if(($_REQUEST['hostid'] > 0) && !isset($_REQUEST['form_refresh'])){
		$name = $proxy['host'];
		$status = $proxy['status'];

		$interfaces = $proxy['interfaces'];
		$hosts = zbx_objectValues($proxy['hosts'], 'hostid');
	}

	$proxyList->addRow(S_PROXY_NAME, new CTextBox('host', $name, 30));

	$statusBox = new CComboBox('status', $status, 'submit()');
	$statusBox->addItem(HOST_STATUS_PROXY_ACTIVE, S_PROXY_ACTIVE);
	$statusBox->addItem(HOST_STATUS_PROXY_PASSIVE, S_PROXY_PASSIVE);
	
	$proxyList->addRow(S_PROXY_MODE, $statusBox);

	if($status == HOST_STATUS_PROXY_PASSIVE){
		if(!empty($interfaces)) $interface = reset($interfaces);
		else $interface = array('dns'=>'localhost','ip'=>'127.0.0.1','useip'=>1,'port'=>'10051');

		if(isset($interface['interfaceid'])){
			$frmProxy->addVar('interfaces[0][interfaceid]', $interface['interfaceid']);
			$frmProxy->addVar('interfaces[0][hostid]', $interface['hostid']);
		}

		$cmbConnectBy = new CRadioButton('interfaces[0][useip]', $interface['useip']);
		$cmbConnectBy->addValue(S_IP, 1);
		$cmbConnectBy->addValue(S_DNS, 0);
		$cmbConnectBy->useJQueryStyle();

		$ifTab = new CTable();
		$ifTab->addRow(array(
			S_IP_ADDRESS, 
			S_DNS_NAME,
			S_CONNECT_TO,
			S_PORT
		));
		$ifTab->addRow(array(
			new CTextBox('interfaces[0][ip]', $interface['ip'], '24'),
			new CTextBox('interfaces[0][dns]', $interface['dns'], '30'),
			$cmbConnectBy,
			new CTextBox('interfaces[0][port]', $interface['port'], 15)
		));

		$proxyList->addRow(S_INTERFACE, new CDiv($ifTab, 'objectgroup inlineblock border_dotted ui-corner-all'));
	}


	$cmbHosts = new CTweenBox($frmProxy, 'hosts', $hosts);

	$sql = 'SELECT hostid, proxy_hostid, host '.
			' FROM hosts '.
			' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
				' AND '.DBin_node('hostid').
			' ORDER BY host';
	$db_hosts=DBselect($sql);
	while($db_host=DBfetch($db_hosts)){
		$cmbHosts->addItem(
			$db_host['hostid'],
			$db_host['host'],
			NULL,
			($db_host['proxy_hostid'] == 0 || ($_REQUEST['hostid']>0) && ($db_host['proxy_hostid'] == $_REQUEST['hostid']))
		);
	}
	$proxyList->addRow(S_HOSTS,$cmbHosts->Get(S_PROXY.SPACE.S_HOSTS,S_OTHER.SPACE.S_HOSTS));

// Tabed form
	$proxyTabs = new CTabView();
	$proxyTabs->addTab('proxylist', S_PROXY, $proxyList);

	$frmProxy->addItem($proxyTabs);

// Footer
	$main = array(new CSubmit('save', S_SAVE));
	$others = array();
	if($_REQUEST['hostid']>0){
		$others[] = new CSubmit('clone', S_CLONE);
		$others[] = new CButtonDelete(S_DELETE_SELECTED_PROXY_Q, url_param('form').url_param('hostid'));
	}
	$others[] = new CButtonCancel(url_param('groupid'));

	$frmProxy->addItem(makeFormFooter($main, $others));

	
return $frmProxy;
?>
