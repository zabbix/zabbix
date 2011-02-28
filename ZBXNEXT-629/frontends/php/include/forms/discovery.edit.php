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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// include JS + templates

?>
<?php
	$data = $data;
	$inputLength = 60;

	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh'])) $divTabs->setSelected(0);

	$formDsc = new CForm();
	$formDsc->setName('discovery.edit');

	$from_rfr = get_request('form_refresh',0);
	$formDsc->addVar('form_refresh', $from_rfr+1);
	$formDsc->addVar('form', get_request('form', 1));

	if(isset($_REQUEST['druleid'])) $formDsc->addVar('druleid', $_REQUEST['druleid']);

	if(isset($data['druleid']))
		$formDsc->setTitle(_s('Discovery rule "%s"',$data['name']));
	else
		$formDsc->setTitle(_('Discovery rule'));

	$discoveryList = new CFormList('actionlist');
	$discoveryList->addRow(_('Name'), new CTextBox('name', $data['name'], $inputLength));


	$cmbProxy = new CComboBox('proxy_hostid', $data['proxy_hostid']);
	$cmbProxy->addItem(0, _('No proxy'));

	$proxies = API::Proxy()->get(array(
		'output' => API_OUTPUT_EXTEND
	));

	order_result($proxies,'host');
	foreach($proxies as $pnum => $proxy){
		$cmbProxy->addItem($proxy['proxyid'], $proxy['host']);
	}

	$discoveryList->addRow(_('Discovery by proxy'), $cmbProxy);


	$discoveryList->addRow(_('IP range'), new CTextBox('iprange', $data['iprange'], 27));
	$discoveryList->addRow(_('Delay (seconds)'), new CNumericBox('delay', $data['delay'], 8));

	$formDsc->addVar('dchecks', $data['dchecks']);
	$formDsc->addVar('dchecks_deleted', $data['dchecks_deleted']);

	$cmbUniquenessCriteria = new CComboBox('uniqueness_criteria', $data['uniqueness_criteria']);
	$cmbUniquenessCriteria->addItem(-1, _('IP address'));

	foreach($data['dchecks'] as $id => $dcheck)
		$data['dchecks'][$id]['name'] = discovery_check2str($dcheck['type'], $dcheck['snmp_community'], $dcheck['key'], $dcheck['ports']);

	order_result($data['dchecks'], 'name');

	$dCheckTab = new CTable();
	foreach($data['dchecks'] as $id => $dcheck){
		$dCheckTab->addRow(array($dcheck['name'], new CButton('delete_ckecks', _('Remove'), null, 'link_menu')));

		if(in_array($dcheck['type'], array(SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2, SVC_SNMPv3)))
			$cmbUniquenessCriteria->addItem($id, $dcheck['name']);
	}

	if(count($data['dchecks'])){
		$dCheckTab->addRow(new CSubmit('new_check', _('New'), null, 'link_menu'), SPACE);

		$discoveryList->addRow(_('Checks'), new CDiv($dCheckTab, 'objectgroup inlineblock border_dotted ui-corner-all'));
	}

// new checks
	$new_check_type	= get_request('new_check_type', SVC_HTTP);
	$new_check_ports = get_request('new_check_ports', '80');
	$new_check_key = get_request('new_check_key', '');
	$new_check_snmp_community = get_request('new_check_snmp_community', '');
	$new_check_snmpv3_securitylevel = get_request('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
	$new_check_snmpv3_securityname = get_request('new_check_snmpv3_securityname', '');
	$new_check_snmpv3_authpassphrase = get_request('new_check_snmpv3_authpassphrase', '');
	$new_check_snmpv3_privpassphrase = get_request('new_check_snmpv3_privpassphrase', '');
	$cmbChkType = new CComboBox('new_check_type', $new_check_type, "if(add_variable(this, 'type_changed', 1)) submit()");
	$cmbChkType->addItems(discovery_check_type2str());

	if(isset($_REQUEST['type_changed']))
		$new_check_ports = svc_default_port($new_check_type);

	$external_param = new CTable();

	if($new_check_type != SVC_ICMPPING){
		$external_param->addRow(array(S_PORTS_SMALL, new CTextBox('new_check_ports', $new_check_ports, 20)));
	}
	switch($new_check_type){
		case SVC_SNMPv1:
		case SVC_SNMPv2:
			$external_param->addRow(array(S_SNMP_COMMUNITY, new CTextBox('new_check_snmp_community', $new_check_snmp_community)));
			$external_param->addRow(array(S_SNMP_OID, new CTextBox('new_check_key', $new_check_key)));

			$formDsc->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
			$formDsc->addVar('new_check_snmpv3_securityname', '');
			$formDsc->addVar('new_check_snmpv3_authpassphrase', '');
			$formDsc->addVar('new_check_snmpv3_privpassphrase', '');
		break;
		case SVC_SNMPv3:
			$formDsc->addVar('new_check_snmp_community', '');

			$external_param->addRow(array(S_SNMP_OID, new CTextBox('new_check_key', $new_check_key)));
			$external_param->addRow(array(S_SNMPV3_SECURITY_NAME, new CTextBox('new_check_snmpv3_securityname', $new_check_snmpv3_securityname)));

			$cmbSecLevel = new CComboBox('new_check_snmpv3_securitylevel', $new_check_snmpv3_securitylevel);
			$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,'noAuthNoPriv');
			$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,'authNoPriv');
			$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,'authPriv');

			$external_param->addRow(array(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel));

// adding id to <tr> elements so they could be then hidden by cviewswitcher.js
			$row = new CRow(array(S_SNMPV3_AUTH_PASSPHRASE, new CTextBox('new_check_snmpv3_authpassphrase', $new_check_snmpv3_authpassphrase)));
			$row->setAttribute('id', 'row_snmpv3_authpassphrase');
			$external_param->addRow($row);

			$row = new CRow(array(S_SNMPV3_PRIV_PASSPHRASE, new CTextBox('new_check_snmpv3_privpassphrase', $new_check_snmpv3_privpassphrase)));
			$row->setAttribute('id', 'row_snmpv3_privpassphrase');
			$external_param->addRow($row);
		break;
		case SVC_AGENT:
			$formDsc->addVar('new_check_snmp_community', '');
			$formDsc->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
			$formDsc->addVar('new_check_snmpv3_securityname', '');
			$formDsc->addVar('new_check_snmpv3_authpassphrase', '');
			$formDsc->addVar('new_check_snmpv3_privpassphrase', '');
			$external_param->addRow(array(S_KEY, new CTextBox('new_check_key', $new_check_key), BR()));
		break;
		case SVC_ICMPPING:
			$formDsc->addVar('new_check_ports', '0');
		default:
			$formDsc->addVar('new_check_snmp_community', '');
			$formDsc->addVar('new_check_key', '');
			$formDsc->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
			$formDsc->addVar('new_check_snmpv3_securityname', '');
			$formDsc->addVar('new_check_snmpv3_authpassphrase', '');
			$formDsc->addVar('new_check_snmpv3_privpassphrase', '');
	}


	if($external_param->getNumRows() == 0) $external_param = null;
	$newCheckDiv = array(
		$cmbChkType,
		$external_param,
		BR(),
		new CSubmit('add_check', _('Add'), null, 'link_menu'),
	);
	$discoveryList->addRow(_('New check'), new CDiv($newCheckDiv,'objectgroup inlineblock border_dotted ui-corner-all'));

	$discoveryList->addRow(_('Device uniqueness criteria'), $cmbUniquenessCriteria);

	$cmbStatus = new CComboBox('status', $data['status']);
	foreach(array(DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED) as $st)
		$cmbStatus->addItem($st, discovery_status2str($st));

	$discoveryList->addRow(_('Status'), $cmbStatus);

// adding javascript, so that auth fields would be hidden if they are not used in specific auth type
	$securityLevelVisibility = array();
	zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');
	zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');
	zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');
	zbx_add_post_js("var securityLevelSwitcher = new CViewSwitcher('new_check_snmpv3_securitylevel', 'change', ".zbx_jsvalue($securityLevelVisibility, true).");");

	$divTabs->addTab('druleTab', _('Discovery rule'), $discoveryList);
	$formDsc->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if(isset($_REQUEST['druleid'])){
		$others[] = new CButton('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete discovery rule?'), url_param('form').url_param('druleid'));
	}
	$others[] = new CButtonCancel();

	$footer = makeFormFooter($main, $others);
	$formDsc->addItem($footer);

	return $formDsc;
?>
