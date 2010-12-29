<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
$useprofile	= get_request('useprofile', 'no');
$host_profile = get_request('host_profile', array());

$profile_fields = array(
	'devicetype' => S_DEVICE_TYPE,
	'name' => S_NAME,
	'os' => S_OS,
	'serialno' => S_SERIALNO,
	'tag' => S_TAG,
	'macaddress' => S_MACADDRESS,
	'hardware' => S_HARDWARE,
	'software' => S_SOFTWARE,
	'contact' => S_CONTACT,
	'location' => S_LOCATION,
	'notes' => S_NOTES
);
foreach($profile_fields as $field => $caption){
	if(!isset($host_profile[$field])) $host_profile[$field] = '';
}

$useprofile_ext = get_request('useprofile_ext','no');
$ext_host_profiles = get_request('ext_host_profiles', array());

$ext_profiles_fields = array(
	'device_alias'=>S_DEVICE_ALIAS,
	'device_type'=>S_DEVICE_TYPE,
	'device_chassis'=>S_DEVICE_CHASSIS,
	'device_os'=>S_DEVICE_OS,
	'device_os_short'=>S_DEVICE_OS_SHORT,
	'device_hw_arch'=>S_DEVICE_HW_ARCH,
	'device_serial'=>S_DEVICE_SERIAL,
	'device_model'=>S_DEVICE_MODEL,
	'device_tag'=>S_DEVICE_TAG,
	'device_vendor'=>S_DEVICE_VENDOR,
	'device_contract'=>S_DEVICE_CONTRACT,
	'device_who'=>S_DEVICE_WHO,
	'device_status'=>S_DEVICE_STATUS,
	'device_app_01'=>S_DEVICE_APP_01,
	'device_app_02'=>S_DEVICE_APP_02,
	'device_app_03'=>S_DEVICE_APP_03,
	'device_app_04'=>S_DEVICE_APP_04,
	'device_app_05'=>S_DEVICE_APP_05,
	'device_url_1'=>S_DEVICE_URL_1,
	'device_url_2'=>S_DEVICE_URL_2,
	'device_url_3'=>S_DEVICE_URL_3,
	'device_networks'=>S_DEVICE_NETWORKS,
	'device_notes'=>S_DEVICE_NOTES,
	'device_hardware'=>S_DEVICE_HARDWARE,
	'device_software'=>S_DEVICE_SOFTWARE,
	'ip_subnet_mask'=>S_IP_SUBNET_MASK,
	'ip_router'=>S_IP_ROUTER,
	'ip_macaddress'=>S_IP_MACADDRESS,
	'oob_ip'=>S_OOB_IP,
	'oob_subnet_mask'=>S_OOB_SUBNET_MASK,
	'oob_router'=>S_OOB_ROUTER,
	'date_hw_buy'=>S_DATE_HW_BUY,
	'date_hw_install'=>S_DATE_HW_INSTALL,
	'date_hw_expiry'=>S_DATE_HW_EXPIRY,
	'date_hw_decomm'=>S_DATE_HW_DECOMM,
	'site_street_1'=>S_SITE_STREET_1,
	'site_street_2'=>S_SITE_STREET_2,
	'site_street_3'=>S_SITE_STREET_3,
	'site_city'=>S_SITE_CITY,
	'site_state'=>S_SITE_STATE,
	'site_country'=>S_SITE_COUNTRY,
	'site_zip'=>S_SITE_ZIP,
	'site_rack'=>S_SITE_RACK,
	'site_notes'=>S_SITE_NOTES,
	'poc_1_name'=>S_POC_1_NAME,
	'poc_1_email'=>S_POC_1_EMAIL,
	'poc_1_phone_1'=>S_POC_1_PHONE_1,
	'poc_1_phone_2'=>S_POC_1_PHONE_2,
	'poc_1_cell'=>S_POC_1_CELL,
	'poc_1_screen'=>S_POC_1_SCREEN,
	'poc_1_notes'=>S_POC_1_NOTES,
	'poc_2_name'=>S_POC_2_NAME,
	'poc_2_email'=>S_POC_2_EMAIL,
	'poc_2_phone_1'=>S_POC_2_PHONE_1,
	'poc_2_phone_2'=>S_POC_2_PHONE_2,
	'poc_2_cell'=>S_POC_2_CELL,
	'poc_2_screen'=>S_POC_2_SCREEN,
	'poc_2_notes'=>S_POC_2_NOTES
);
foreach($ext_profiles_fields as $field => $caption){
	if(!isset($ext_host_profiles[$field])) $ext_host_profiles[$field] = '';
}

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
$all_groups = CHostGroup::get($options);
order_result($all_groups, 'name');
foreach($all_groups as $grp){
	$grp_tb->addItem($grp['groupid'], $grp['name']);
}

$frmHost->addRow(array(
	new CVisibilityBox('visible[groups]', isset($visible['groups']), $grp_tb->getName(), _('Original')),
	S_GROUPS
), $grp_tb->get(_('In groups'), _('Other groups')));

$frmHost->addRow(array(new CVisibilityBox('visible[newgroup]', isset($visible['newgroup']),
	'newgroup', S_ORIGINAL),S_NEW_GROUP
), new CTextBox('newgroup',$newgroup), 'new');

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
$cmbStatus->addItem(HOST_STATUS_MONITORED,	S_MONITORED);
$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);

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

$frmHost->addRow(array(
	new CVisibilityBox('visible[useprofile]', isset($visible['useprofile']), 'useprofile', S_ORIGINAL),S_USE_PROFILE),
	new CCheckBox('useprofile',$useprofile,'submit()')
);

// BEGIN: HOSTS PROFILE EXTENDED Section
$frmHost->addRow(array(
	new CVisibilityBox('visible[useprofile_ext]', isset($visible['useprofile_ext']), 'useprofile_ext', S_ORIGINAL),S_USE_EXTENDED_PROFILE),
	new CCheckBox('useprofile_ext',$useprofile_ext,'submit()')
);
// END:   HOSTS PROFILE EXTENDED Section

if($useprofile==='yes'){
	if($useprofile === 'yes'){
		foreach($profile_fields as $field => $caption){
			$frmHost->addRow(array(
				new CVisibilityBox('visible['.$field.']', isset($visible[$field]), 'host_profile['.$field.']', S_ORIGINAL), $caption),
				new CTextBox('host_profile['.$field.']',$host_profile[$field],80)
			);
		}
	}
	else{
		foreach($profile_fields as $field => $caption){
			$frmHost->addVar('host_profile['.$field.']', $host_profile[$field]);
		}
	}
}

// BEGIN: HOSTS PROFILE EXTENDED Section
if($useprofile_ext=='yes'){
	foreach($ext_profiles_fields as $prof_field => $caption){
		$frmHost->addRow(array(
			new CVisibilityBox('visible['.$prof_field.']', isset($visible[$prof_field]), 'ext_host_profiles['.$prof_field.']', S_ORIGINAL),$caption),
			new CTextBox('ext_host_profiles['.$prof_field.']',$ext_host_profiles[$prof_field],80)
		);
	}
}
else{
	foreach($ext_profiles_fields as $prof_field => $caption){
		$frmHost->addVar('ext_host_profiles['.$prof_field.']',	$ext_host_profiles[$prof_field]);
	}
}
// END:   HOSTS PROFILE EXTENDED Section

$frmHost->addItemToBottomRow(new CSubmit('masssave',S_SAVE));
$frmHost->addItemToBottomRow(SPACE);
$frmHost->addItemToBottomRow(new CButtonCancel(url_param('config').url_param('groupid')));

return $frmHost;

?>
