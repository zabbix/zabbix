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

$visible = get_request('visible', array());

$groups = get_request('groups', array());
$newgroup = get_request('newgroup', '');
$status		= get_request('status',	HOST_STATUS_MONITORED);
$proxy_hostid	= get_request('proxy_hostid','');
$ipmi_authtype	= get_request('ipmi_authtype', -1);
$ipmi_privilege	= get_request('ipmi_privilege', 2);
$ipmi_username	= get_request('ipmi_username', '');
$ipmi_password	= get_request('ipmi_password', '');
$inventory_mode	= get_request('inventory_mode', HOST_INVENTORY_DISABLED);
$host_inventory = get_request('host_inventory', array());


$templates	= get_request('templates',array());
natsort($templates);

$frmHost = new CFormTable(_('Host mass update'));
$frmHost->setName('host.massupdate');
$frmHost->addVar('go', 'massupdate');

$hosts = $_REQUEST['hosts'];
foreach($hosts as $id => $hostid){
	$frmHost->addVar('hosts['.$hostid.']', $hostid);
}

$grp_tb = new CTweenBox($frmHost, 'groups', $groups, 6);
$options = array(
	'output' => API_OUTPUT_EXTEND,
	'editable' => 1,
);
$all_groups = API::HostGroup()->get($options);
order_result($all_groups, 'name');
foreach($all_groups as $grp){
	$grp_tb->addItem($grp['groupid'], $grp['name']);
}

$frmHost->addRow(array(
	new CVisibilityBox('visible[groups]', isset($visible['groups']), $grp_tb->getName(), _('Original')),
	_('Relpace groups')
), $grp_tb->get(_('In groups'), _('Other groups')));

$newgroupTB = new CTextBox('newgroup', $newgroup);
$newgroupTB->setAttribute('maxlength', 64);
$frmHost->addRow(array(new CVisibilityBox('visible[newgroup]', isset($visible['newgroup']),
	'newgroup', _('Original')), _('New group')), $newgroupTB, 'new');

//Proxy
$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);
$cmbProxy->addItem(0, S_NO_PROXY);

$sql = 'SELECT hostid, host '.
		' FROM hosts '.
		' WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.') '.
			' AND '.DBin_node('hostid').
		' ORDER BY host';
$db_proxies = DBselect($sql);
while ($db_proxy = DBfetch($db_proxies))
	$cmbProxy->addItem($db_proxy['hostid'], $db_proxy['host']);

$frmHost->addRow(array(
	new CVisibilityBox('visible[proxy_hostid]', isset($visible['proxy_hostid']), 'proxy_hostid', _('Original')),
	S_MONITORED_BY_PROXY),
	$cmbProxy
);
//----------

$cmbStatus = new CComboBox('status', $status);
$cmbStatus->addItem(HOST_STATUS_MONITORED, _('Monitored'));
$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED, _('Not monitored'));

$frmHost->addRow(array(
	new CVisibilityBox('visible[status]', isset($visible['status']), 'status', _('Original')),
	S_STATUS), $cmbStatus
);

// LINK TEMPLATES {{{
$template_table = new CTable();
$template_table->setAttribute('name', 'template_table');
$template_table->setAttribute('id', 'template_table');

$template_table->setCellPadding(0);
$template_table->setCellSpacing(0);

foreach($templates as $id => $temp_name){
	$frmHost->addVar('templates['.$id.']',$temp_name);
	$template_table->addRow(array(
		new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
		$temp_name,
	));
}

$template_table->addRow(array(
	new CButton('add_template', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->getName().
		"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
		url_param($templates,false,'existed_templates')."',450,450)"),
	new CSubmit('unlink', S_REMOVE)
));

$vbox = new CVisibilityBox('visible[template_table]', isset($visible['template_table']), 'template_table', S_ORIGINAL);
$vbox->setAttribute('id', 'cb_tpladd');
if(isset($visible['template_table_r'])) $vbox->setAttribute('disabled', 'disabled');
$action = $vbox->getAttribute('onclick');
$action .= 'if($("cb_tplrplc").disabled) $("cb_tplrplc").enable(); else $("cb_tplrplc").disable();';
$vbox->setAttribute('onclick', $action);

$frmHost->addRow(array($vbox, S_LINK_ADDITIONAL_TEMPLATES), $template_table, 'T');
// }}} LINK TEMPLATES


// RELINK TEMPLATES {{{
$template_table_r = new CTable();
$template_table_r->setAttribute('name','template_table_r');
$template_table_r->setAttribute('id','template_table_r');

$template_table_r->setCellPadding(0);
$template_table_r->setCellSpacing(0);

foreach($templates as $id => $temp_name){
	$frmHost->addVar('templates['.$id.']',$temp_name);
	$template_table_r->addRow(array(
		new CCheckBox('templates_rem['.$id.']', 'no', null, $id),
		$temp_name,
	));
}

$template_table_r->addRow(array(
	new CButton('add_template', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->getName().
		"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
		url_param($templates,false,'existed_templates')."',450,450)"),
	new CSubmit('unlink', S_REMOVE)
));

$vbox = new CVisibilityBox('visible[template_table_r]', isset($visible['template_table_r']), 'template_table_r', S_ORIGINAL);
$vbox->setAttribute('id', 'cb_tplrplc');
if(isset($visible['template_table'])) $vbox->setAttribute('disabled', 'disabled');
$action = $vbox->getAttribute('onclick');
$action .= <<<JS
if($("cb_tpladd").disabled){
$("cb_tpladd").enable();
}
else{
$("cb_tpladd").disable();
}
$("clrcbdiv").toggle();
JS;
$vbox->setAttribute('onclick', $action);

$clear_cb = new CCheckBox('mass_clear_tpls', get_request('mass_clear_tpls', false));
$div = new CDiv(array($clear_cb, S_CLEAR_WHEN_UNLINKING));
$div->setAttribute('id', 'clrcbdiv');
$div->addStyle('margin-left: 20px;');
if(!isset($visible['template_table_r'])) $div->addStyle('display: none;');

$frmHost->addRow(array($vbox, S_RELINK_TEMPLATES, $div), $template_table_r, 'T');
// }}} RELINK TEMPLATES


$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
$frmHost->addRow(array(
	new CVisibilityBox('visible[ipmi_authtype]', isset($visible['ipmi_authtype']), 'ipmi_authtype', S_ORIGINAL), S_IPMI_AUTHTYPE),
	$cmbIPMIAuthtype
);

$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);
$frmHost->addRow(array(
	new CVisibilityBox('visible[ipmi_privilege]', isset($visible['ipmi_privilege']), 'ipmi_privilege', S_ORIGINAL), S_IPMI_PRIVILEGE),
	$cmbIPMIPrivilege
);

$frmHost->addRow(array(
	new CVisibilityBox('visible[ipmi_username]', isset($visible['ipmi_username']), 'ipmi_username', S_ORIGINAL), S_IPMI_USERNAME),
	new CTextBox('ipmi_username', $ipmi_username, 16)
);

$frmHost->addRow(array(
	new CVisibilityBox('visible[ipmi_password]', isset($visible['ipmi_password']), 'ipmi_password', S_ORIGINAL), S_IPMI_PASSWORD),
	new CTextBox('ipmi_password', $ipmi_password, 20)
);

$inventoryModesCC = new CComboBox('inventory_mode', $inventory_mode, 'submit()');
$inventoryModesCC->addItem(HOST_INVENTORY_DISABLED, _('Disabled'));
$inventoryModesCC->addItem(HOST_INVENTORY_MANUAL, _('Manual'));
$inventoryModesCC->addItem(HOST_INVENTORY_AUTOMATIC, _('Automatic'));
$frmHost->addRow(array(
	new CVisibilityBox('visible[inventory_mode]', isset($visible['inventory_mode']), 'inventory_mode', S_ORIGINAL), _('Inventory mode')),
	$inventoryModesCC
);

$inventory_fields = getHostInventories();
$inventory_fields = zbx_toHash($inventory_fields, 'db_field');
if($inventory_mode != HOST_INVENTORY_DISABLED){
	foreach($inventory_fields as $field => $caption){
		$caption = $caption['title'];
		if(!isset($host_inventory[$field])){
			$host_inventory[$field] = '';
		}

		$frmHost->addRow(
			array(
				new CVisibilityBox(
					'visible['.$field.']',
					isset($visible[$field]),
					'host_inventory['.$field.']',
					S_ORIGINAL
				),
				$caption
			),
			new CTextBox('host_inventory['.$field.']', $host_inventory[$field], 80)
		);
	}
}


$frmHost->addItemToBottomRow(new CSubmit('masssave',S_SAVE));
$frmHost->addItemToBottomRow(SPACE);
$frmHost->addItemToBottomRow(new CButtonCancel(url_param('config').url_param('groupid')));

return $frmHost;

?>
