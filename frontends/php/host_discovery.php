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
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/items.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_DISCOVERY';
$page['file'] = 'host_discovery.php';
$page['scripts'] = array('effects.js', 'class.cviewswitcher.js');
$page['hist_arg'] = array();

include_once('include/page_header.php');
?>
<?php
// needed type to know which field name to use
$itemType = get_request('type', 0);
switch($itemType) {
	case ITEM_TYPE_SSH: case ITEM_TYPE_TELNET: $paramsFieldName = S_EXECUTED_SCRIPT; break;
	case ITEM_TYPE_DB_MONITOR: $paramsFieldName = S_PARAMS; break;
	case ITEM_TYPE_CALCULATED: $paramsFieldName = S_FORMULA; break;
	default: $paramsFieldName = 'params';
}
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'hostid'=>			array(T_ZBX_INT, O_OPT,  null,	DB_ID,			'(!isset({form}) || (isset({form})&&!isset({itemid})))'),
		'itemid'=>			array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))'),

		'description'=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,		'isset({save})'),
		'key'=>				array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})'),
		'delay'=>			array(T_ZBX_INT, O_OPT,  null,  '(('.BETWEEN(1,86400).
				'(!isset({delay_flex}) || !({delay_flex}) || is_array({delay_flex}) && !count({delay_flex}))) ||'.
				'('.BETWEEN(0,86400).'isset({delay_flex})&&is_array({delay_flex})&&count({delay_flex})>0))&&',
				'isset({save})&&(isset({type})&&({type}!='.ITEM_TYPE_TRAPPER.'))'),
		'new_delay_flex'=>		array(T_ZBX_STR, O_OPT,  NOT_EMPTY,  '',	'isset({add_delay_flex})&&(isset({type})&&({type}!=2))'),
		'rem_delay_flex'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),null),
		'delay_flex'=>		array(T_ZBX_STR, O_OPT,  null,  '',null),
		'status'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		'type'=>			array(T_ZBX_INT, O_OPT,  null,
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
					ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_AGGREGATE,ITEM_TYPE_EXTERNAL,ITEM_TYPE_DB_MONITOR,
					ITEM_TYPE_IPMI,ITEM_TYPE_SSH,ITEM_TYPE_TELNET,ITEM_TYPE_CALCULATED)),'isset({save})'),
		'authtype'=>		array(T_ZBX_INT, O_OPT,  NULL,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
											'isset({save})&&isset({type})&&({type}=='.ITEM_TYPE_SSH.')'),
		'username'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&'.IN(
												ITEM_TYPE_SSH.','.
												ITEM_TYPE_TELNET, 'type')),
		'password'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&'.IN(
												ITEM_TYPE_SSH.','.
												ITEM_TYPE_TELNET, 'type')),
		'publickey'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
		'privatekey'=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,		'isset({save})&&isset({type})&&({type})=='.ITEM_TYPE_SSH.'&&({authtype})=='.ITEM_AUTHTYPE_PUBLICKEY),
		'params'=>		array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,	'isset({save})&&isset({type})&&'.IN(
												ITEM_TYPE_SSH.','.
												ITEM_TYPE_DB_MONITOR.','.
												ITEM_TYPE_TELNET.','.
												ITEM_TYPE_CALCULATED,'type'), $paramsFieldName),
//hidden fields for better gui
		'params_script'=>	array(T_ZBX_STR, O_OPT, NULL, NULL, NULL),
		'params_dbmonitor'=>	array(T_ZBX_STR, O_OPT, NULL, NULL, NULL),
		'params_calculted'=>	array(T_ZBX_STR, O_OPT, NULL, NULL, NULL),

		'snmp_community'=>	array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C,'type')),
		'snmp_oid'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,		'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C.','.
													ITEM_TYPE_SNMPV3,'type')),
		'snmp_port'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C.','.
													ITEM_TYPE_SNMPV3,'type')),
		'snmpv3_securitylevel'=>array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_securityname'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_authpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_privpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),

		'ipmi_sensor'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,	'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_IPMI.'))', S_IPMI_SENSOR),

		'new_application'=>	array(T_ZBX_STR, O_OPT, null,	null,	'isset({save})'),
		'applications'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),

		'add_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// Actions
		'go'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
		'group_itemid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
// form
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'update'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),

		'item_filter' => array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	);

	check_fields($fields);
	validate_sort_and_sortorder('description', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');

// PERMISSIONS
	if(get_request('itemid', false)){
		$options = array(
			'itemids' => $_REQUEST['itemid'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1
		);
		$item = CItem::get($options);
		$item = reset($item);
		if(!$item) access_deny();
		$_REQUEST['hostid'] = $item['hostid'];
	}
	else if(get_request('hostid', 0) > 0){
		$options = array(
			'hostids' => $_REQUEST['hostid'],
			'extendoutput' => 1,
			'templated_hosts' => 1,
			'editable' => 1
		);
		$hosts = CHost::get($options);
		if(empty($hosts)) access_deny();
	}
?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.host_discovery.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

?>
<?php
	if(isset($_REQUEST['del_delay_flex']) && isset($_REQUEST['rem_delay_flex'])){
		$_REQUEST['delay_flex'] = get_request('delay_flex',array());
		foreach($_REQUEST['rem_delay_flex'] as $val){
			unset($_REQUEST['delay_flex'][$val]);
		}
	}
	else if(isset($_REQUEST['add_delay_flex'])&&isset($_REQUEST['new_delay_flex'])){
		$_REQUEST['delay_flex'] = get_request('delay_flex', array());
		array_push($_REQUEST['delay_flex'],$_REQUEST['new_delay_flex']);
	}
	else if(isset($_REQUEST['delete'])&&isset($_REQUEST['itemid'])){
		$result = false;
		if($item = get_item_by_itemid($_REQUEST['itemid'])){
			$result = CItem::delete($_REQUEST['itemid']);
		}

		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);

		unset($_REQUEST['itemid']);
		unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])){
		unset($_REQUEST['itemid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$applications = get_request('applications', array());
		$delay_flex = get_request('delay_flex', array());

		$db_delay_flex = '';
		foreach($delay_flex as $num => $val){
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		}
		$db_delay_flex = trim($db_delay_flex,';');

		if(!zbx_empty($_REQUEST['new_application'])){
			if($new_appid = add_application($_REQUEST['new_application'], $_REQUEST['hostid']))
				$applications[$new_appid] = $new_appid;
		}

		$item = array(
			'description' => get_request('description'),
			'key_' => get_request('key'),
			'hostid' => get_request('hostid'),
			'delay' => get_request('delay'),
			'status' => get_request('status'),
			'type' => get_request('type'),
			'snmp_community' => get_request('snmp_community'),
			'snmp_oid' => get_request('snmp_oid'),
			'snmp_port' => get_request('snmp_port'),
			'snmpv3_securityname' => get_request('snmpv3_securityname'),
			'snmpv3_securitylevel' => get_request('snmpv3_securitylevel'),
			'snmpv3_authpassphrase' => get_request('snmpv3_authpassphrase'),
			'snmpv3_privpassphrase' => get_request('snmpv3_privpassphrase'),
			'delay_flex' => $db_delay_flex,
			'authtype' => get_request('authtype'),
			'username' => get_request('username'),
			'password' => get_request('password'),
			'publickey' => get_request('publickey'),
			'privatekey' => get_request('privatekey'),
			'params' => get_request('params'),
			'ipmi_sensor' => get_request('ipmi_sensor'),
			'applications' => $applications,
			'flags' => ZBX_FLAG_DISCOVERY,
		);

		if(isset($_REQUEST['itemid'])){
			DBstart();

			$db_item = get_item_by_itemid_limited($_REQUEST['itemid']);
			$db_item['applications'] = get_applications_by_itemid($_REQUEST['itemid']);

			$result = smart_update_item($_REQUEST['itemid'], $item);
			$result = DBend($result);

			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
		}
		else{
			DBstart();
			$result = add_item($item);
			$result = DBend($result);
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}

		if($result){
			unset($_REQUEST['itemid']);
			unset($_REQUEST['form']);
		}
	}

// ----- GO -----
	else if((($_REQUEST['go'] == 'activate') || ($_REQUEST['go'] == 'disable')) && isset($_REQUEST['group_itemid'])){
		$group_itemid = $_REQUEST['group_itemid'];

		DBstart();
		$go_result = ($_REQUEST['go'] == 'activate') ? activate_item($group_itemid) : disable_item($group_itemid);
		$go_result = DBend($go_result);
		show_messages($go_result, ($_REQUEST['go'] == 'activate') ? S_ITEMS_ACTIVATED : S_ITEMS_DISABLED, null);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['group_itemid'])){
		global $USER_DETAILS;

		$go_result = true;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_WRITE);

		$group_itemid = $_REQUEST['group_itemid'];

		$sql = 'SELECT h.host, i.itemid, i.description, i.key_, i.templateid, i.type'.
				' FROM items i, hosts h '.
				' WHERE '.DBcondition('i.itemid',$group_itemid).
					' AND h.hostid=i.hostid'.
					' AND '.DBcondition('h.hostid',$available_hosts);
		$db_items = DBselect($sql);
		while($item = DBfetch($db_items)) {
			if($item['templateid'] != ITEM_TYPE_ZABBIX) {
				unset($group_itemid[$item['itemid']]);
				error(S_ITEM.SPACE."'".$item['host'].':'.item_description($item)."'".SPACE.S_CANNOT_DELETE_ITEM.SPACE.'('.S_TEMPLATED_ITEM.')');
				continue;
			}
			else if($item['type'] == ITEM_TYPE_HTTPTEST) {
				unset($group_itemid[$item['itemid']]);
				error(S_ITEM.SPACE."'".$item['host'].':'.item_description($item)."'".SPACE.S_CANNOT_DELETE_ITEM.SPACE.'('.S_WEB_ITEM.')');
				continue;
			}

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$item['itemid'].'] '.S_HOST.' ['.$item['host'].']');
		}

		$go_result &= !empty($group_itemid);
		if($go_result) {
			$go_result = CItem::delete($group_itemid);
		}
		show_messages($go_result, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php
	$items_wdgt = new CWidget();


	if(!isset($_REQUEST['form'])){
		$form = new CForm(null, 'get');
		$form->addVar('hostid', $_REQUEST['hostid']);
		$form->addItem(new CButton('form', S_CREATE_RULE));
	}
	else{
		$form = null;
	}
	$items_wdgt->addPageHeader(S_CONFIGURATION_OF_DISCOVERY_BIG, $form);


	if(isset($_REQUEST['form'])){
		$frmItem = new CFormTable();
		$frmItem->setName('items');
		$frmItem->setTitle(S_RULE);
		$frmItem->setAttribute('style','visibility: hidden;');

		$hostid = get_request('hostid');

		$frmItem->addVar('hostid', $hostid);

		$limited = false;


		$description = get_request('description', '');
		$key = get_request('key', '');
		$delay = get_request('delay', 30);
		$status = get_request('status', 0);
		$type = get_request('type', 0);
		$snmp_community = get_request('snmp_community', 'public');
		$snmp_oid = get_request('snmp_oid', 'interfaces.ifTable.ifEntry.ifInOctets.1');
		$snmp_port = get_request('snmp_port', 161);
		$params = get_request('params', '');
		$new_application = get_request('new_application', '');
		$applications = get_request('applications', array());
		$delay_flex = get_request('delay_flex', array());

		$snmpv3_securityname = get_request('snmpv3_securityname', '');
		$snmpv3_securitylevel = get_request('snmpv3_securitylevel', 0);
		$snmpv3_authpassphrase = get_request('snmpv3_authpassphrase', '');
		$snmpv3_privpassphrase = get_request('snmpv3_privpassphrase', '');
		$ipmi_sensor = get_request('ipmi_sensor', '');
		$authtype = get_request('authtype', 0);
		$username = get_request('username', '');
		$password = get_request('password', '');
		$publickey = get_request('publickey', '');
		$privatekey = get_request('privatekey', '');

		$formula = get_request('formula', '1');
		$logtimefmt = get_request('logtimefmt', '');

		if(isset($_REQUEST['itemid'])){
			$frmItem->addVar('itemid', $_REQUEST['itemid']);

			$options = array(
				'hostids' => $hostid,
				'itemids' => $_REQUEST['itemid'],
				'output' => API_OUTPUT_EXTEND,
				'editable' => 1,
			);
			$item_data = CItem::get($options);
			$item_data = reset($item_data);

			$limited = ($item_data['templateid'] != 0);
		}

		if((isset($_REQUEST['itemid']) && !isset($_REQUEST['form_refresh']))){
			$description = $item_data['description'];
			$key = $item_data['key_'];
			$type = $item_data['type'];
			$snmp_community = $item_data['snmp_community'];
			$snmp_oid = $item_data['snmp_oid'];
			$snmp_port = $item_data['snmp_port'];
			$params = $item_data['params'];

			$snmpv3_securityname = $item_data['snmpv3_securityname'];
			$snmpv3_securitylevel = $item_data['snmpv3_securitylevel'];
			$snmpv3_authpassphrase = $item_data['snmpv3_authpassphrase'];
			$snmpv3_privpassphrase = $item_data['snmpv3_privpassphrase'];

			$ipmi_sensor = $item_data['ipmi_sensor'];

			$authtype = $item_data['authtype'];
			$username = $item_data['username'];
			$password = $item_data['password'];
			$publickey = $item_data['publickey'];
			$privatekey = $item_data['privatekey'];

			$formula = $item_data['formula'];
			$logtimefmt = $item_data['logtimefmt'];

			$new_application = get_request('new_application', '');

			if(!isset($limited) || !isset($_REQUEST['form_refresh'])){
				$delay		= $item_data['delay'];
				$status		= $item_data['status'];
				$db_delay_flex	= $item_data['delay_flex'];

				if(isset($db_delay_flex)){
					$arr_of_dellays = explode(';',$db_delay_flex);
					foreach($arr_of_dellays as $one_db_delay){
						$arr_of_delay = explode('/',$one_db_delay);
						if(!isset($arr_of_delay[0]) || !isset($arr_of_delay[1])) continue;

						array_push($delay_flex, array('delay'=> $arr_of_delay[0], 'period'=> $arr_of_delay[1]));
					}
				}

				$applications = array_unique(zbx_array_merge($applications, get_applications_by_itemid($_REQUEST['itemid'])));
			}
		}

		$authTypeVisibility = array();
		$typeVisibility = array();
		$delay_flex_el = array();

		$types = array_keys(item_type2str());

		$i = 0;
		foreach($delay_flex as $val){
			if(!isset($val['delay']) && !isset($val['period'])) continue;

			array_push($delay_flex_el,
				array(
					new CCheckBox('rem_delay_flex['.$i.']', 'no', null,$i),
					$val['delay'],
					' sec at ',
					$val['period']),
				BR());
			$frmItem->addVar('delay_flex['.$i.'][delay]', $val['delay']);
			$frmItem->addVar('delay_flex['.$i.'][period]', $val['period']);
			foreach($types as $it) {
				if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_ZABBIX_ACTIVE) continue;
				zbx_subarray_push($typeVisibility, $it, 'delay_flex['.$i.'][delay]');
				zbx_subarray_push($typeVisibility, $it, 'delay_flex['.$i.'][period]');
				zbx_subarray_push($typeVisibility, $it, 'rem_delay_flex['.$i.']');
			}
			$i++;
// limit count of intervals.  7 intervals by 30 symbols = 210 characters, db storage field is 256
			if($i >= 7) break;
		}

		array_push($delay_flex_el, count($delay_flex_el)==0 ? S_NO_FLEXIBLE_INTERVALS : new CButton('del_delay_flex',S_DELETE_SELECTED));


// Description
		$frmItem->addRow(S_DESCRIPTION, new CTextBox('description',$description,40, $limited));

// Key
		$frmItem->addRow(S_KEY, new CTextBox('key', $key, 40, $limited));

// Type
		$cmbType = new CComboBox('type', $type);
		$cmbType->addItems(item_type2str());
		$frmItem->addRow(S_TYPE, $cmbType);

// SNMP OID
		$frmItem->addRow(S_SNMP_OID, new CTextBox('snmp_oid',$snmp_oid,40,$limited), null, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmp_oid');

// SNMP community
		$frmItem->addRow(S_SNMP_COMMUNITY, new CTextBox('snmp_community',$snmp_community,16), null, 'row_snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_community');

// SNMPv3 security name
		$frmItem->addRow(S_SNMPV3_SECURITY_NAME, new CTextBox('snmpv3_securityname',$snmpv3_securityname,64), null, 'row_snmpv3_securityname');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');

// SNMPv3 security level
		$cmbSecLevel = new CComboBox('snmpv3_securitylevel', $snmpv3_securitylevel);
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'noAuthPriv');
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'authNoPriv');
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'authPriv');
		$frmItem->addRow(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel, null, 'row_snmpv3_securitylevel');

		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');

// SNMPv3 auth passphrase
		$frmItem->addRow(S_SNMPV3_AUTH_PASSPHRASE, new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64), null, 'row_snmpv3_authpassphrase');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_authpassphrase');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_authpassphrase');

// SNMPv3 priv passphrase
		$frmItem->addRow(S_SNMPV3_PRIV_PASSPHRASE, new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64), null, 'row_snmpv3_privpassphrase');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_privpassphrase');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_privpassphrase');

// SNMP port
		$frmItem->addRow(S_SNMP_PORT, new CNumericBox('snmp_port',$snmp_port,5), null, 'row_snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmp_port');

// IPMI sensor
		$frmItem->addRow(S_IPMI_SENSOR, new CTextBox('ipmi_sensor', $ipmi_sensor, 64, $limited), null, 'row_ipmi_sensor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'ipmi_sensor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'row_ipmi_sensor');

// Authentication method
		$cmbAuthType = new CComboBox('authtype', $authtype);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD,S_PASSWORD);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY,S_PUBLIC_KEY);

		$frmItem->addRow(S_AUTHENTICATION_METHOD, $cmbAuthType, null, 'row_authtype');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'authtype');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_authtype');

// User name
		$frmItem->addRow(S_USER_NAME, new CTextBox('username',$username,16), null, 'row_username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_username');

// Public key
		$frmItem->addRow(S_PUBLIC_KEY_FILE, new CTextBox('publickey',$publickey,16), null, 'row_publickey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');

// Private key
		$frmItem->addRow(S_PRIVATE_KEY_FILE, new CTextBox('privatekey',$privatekey,16), null, 'row_privatekey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

// Password
		$frmItem->addRow(S_PASSWORD, new CTextBox('password',$password,16), null, 'row_password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_password');

		$spanEC = new CSpan(S_EXECUTED_SCRIPT);
		$spanEC->setAttribute('id', 'label_executed_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'label_executed_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'label_executed_script');

		$spanP = new CSpan(S_PARAMS);
		$spanP->setAttribute('id', 'label_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'label_params');

		$spanF = new CSpan(S_FORMULA);
		$spanF->setAttribute('id', 'label_formula');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'label_formula');

// Params / DBmonitor / Formula
		$params_script = new CTextArea('params', $params, 60, 4);
		$params_script->setAttribute('id', 'params_script');
		$params_dbmonitor = new CTextArea('params', $params, 60, 4);
		$params_dbmonitor->setAttribute('id', 'params_dbmonitor');
		$params_calculted = new CTextArea('params', $params, 60, 4);
		$params_calculted->setAttribute('id', 'params_calculted');

		$frmItem->addRow(array($spanEC, $spanP, $spanF), array($params_script, $params_dbmonitor, $params_calculted), null, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'params_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'params_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'params_calculted');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'row_params');

// Update interval (in sec)
		$frmItem->addRow(S_UPDATE_INTERVAL_IN_SEC, new CNumericBox('delay',$delay,5), null, 'row_delay');
		foreach($types as $it) {
			if($it == ITEM_TYPE_TRAPPER) continue;
			zbx_subarray_push($typeVisibility, $it, 'delay');
			zbx_subarray_push($typeVisibility, $it, 'row_delay');
		}

// Filter
		$frmItem->addRow(S_FILTER, new CTextArea('item_filter','',40, 3), null);

// Flexible intervals (sec)
		$frmItem->addRow(S_FLEXIBLE_INTERVALS, $delay_flex_el, null, 'row_flex_intervals');

// New flexible interval
		$frmItem->addRow(S_NEW_FLEXIBLE_INTERVAL, array(
			S_DELAY, SPACE,	new CNumericBox('new_delay_flex[delay]', '50', 5),
			S_PERIOD, SPACE, new CTextBox('new_delay_flex[period]', '1-7,00:00-23:59', 27),
			BR(),
			new CButton('add_delay_flex', S_ADD)
		), 'new', 'row_new_delay_flex');

		foreach($types as $it) {
			if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_ZABBIX_ACTIVE) continue;
			zbx_subarray_push($typeVisibility, $it, 'row_flex_intervals');
			zbx_subarray_push($typeVisibility, $it, 'row_new_delay_flex');
			zbx_subarray_push($typeVisibility, $it, 'new_delay_flex[delay]');
			zbx_subarray_push($typeVisibility, $it, 'new_delay_flex[period]');
			zbx_subarray_push($typeVisibility, $it, 'add_delay_flex');
		}

// Status
		$cmbStatus = new CComboBox('status', $status);
		$cmbStatus->addItems(item_status2str());
		$frmItem->addRow(S_STATUS, $cmbStatus);

// New application
		$frmItem->addRow(S_NEW_APPLICATION, new CTextBox('new_application', $new_application,40), 'new');


		$all_apps = CApplication::get(array(
			'hostids' => $hostid,
			'ediatble' => 1,
			'output' => API_OUTPUT_EXTEND,
		));
		if(!empty($all_apps)){
			$cmbApps = new CListBox('applications[]', $applications, 10);
			foreach($all_apps as $app){
				$cmbApps->addItem($app['applicationid'], $app['name']);
			}
			$frmItem->addRow(S_APPLICATIONS, $cmbApps);
		}


		$frmRow = array(new CButton('save',S_SAVE));
		if(isset($_REQUEST['itemid'])){
			$frmRow[] = new CButton('clone',S_CLONE);
			$frmRow[] = new CButtonDelete(S_DELETE_SELECTED_ITEM_Q,	url_param('form').url_param('groupid').url_param('itemid'));
		}
		$frmRow[] = new CButtonCancel(url_param('groupid').url_param('hostid'));
		$frmItem->addItemToBottomRow($frmRow);

		zbx_add_post_js("var authTypeSwitcher = new CViewSwitcher('authtype', 'change', ".zbx_jsvalue($authTypeVisibility, true).");");
		zbx_add_post_js("var typeSwitcher = new CViewSwitcher('type', 'change', ".zbx_jsvalue($typeVisibility, true).(isset($_REQUEST['itemid'])? ', true': '').');');
		zbx_add_post_js("var mnFrmTbl = document.getElementById('".$frmItem->getName()."'); if(mnFrmTbl) mnFrmTbl.style.visibility = 'visible';");

		$items_wdgt->addItem($frmItem);
	}
	else{
// Items Header
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$items_wdgt->addHeader(S_ITEMS_BIG, SPACE);
		$items_wdgt->addHeader($numrows, SPACE);

		$items_wdgt->addItem(get_header_host_table($_REQUEST['hostid']));
// ----------------

		$form = new CForm();
		$form->addVar('hostid', $_REQUEST['hostid']);
		$form->setName('items');

		$table  = new CTableInfo();
		$table->setHeader(array(
			new CCheckBox('all_items',null,"checkAll('".$form->GetName()."','all_items','group_itemid');"),
			make_sorting_header(S_DESCRIPTION,'description'),
			'Subrules',
			make_sorting_header(S_KEY,'key_'),
			make_sorting_header(S_INTERVAL,'delay'),
			make_sorting_header(S_TYPE,'type'),
			make_sorting_header(S_STATUS,'status'),
			S_APPLICATIONS,
			S_ERROR
		));


		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();
		$options = array(
			'hostids' => $_REQUEST['hostid'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY),
			'select_applications' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$items = CItem::get($options);

		order_result($items, $sortfield, $sortorder);
		$paging = getPagingLine($items);

		foreach($items as $inum => $item){
			$description = array();
			if($item['templateid']){
				$template_host = get_realhost_by_itemid($item['templateid']);
				$description[] = new CLink($template_host['host'],'?hostid='.$template_host['hostid'], 'unknown');
				$description[] = ':';
			}
			$item['description_expanded'] = item_description($item);
			$description[] = new CLink($item['description_expanded'], '?form=update&itemid='.$item['itemid']);

			$status = new CCol(new CLink(item_status2str($item['status']), '?group_itemid='.$item['itemid'].'&go='.
				($item['status']? 'activate':'disable'), item_status2style($item['status'])));


			if(zbx_empty($item['error'])){
				$error = new CDiv(SPACE, 'iconok');
			}
			else{
				$error = new CDiv(SPACE, 'iconerror');
				$error->setHint($item['error'], '', 'on');
			}

			if(empty($item['applications'])){
				$applications = '-';
			}
			else{
				$applications = array();
				foreach($item['applications'] as $anum => $app){
					$applications[] = $app['name'];
				}
				$applications = implode(', ', $applications);
			}

			$subrules = array(new CLink('subrule', 'host_subrule.php?&parent_itemid='.$item['itemid']),
				' ('.'1'.')');

			$table->addRow(array(
				new CCheckBox('group_itemid['.$item['itemid'].']',null,null,$item['itemid']),
				$description,
				$subrules,
				$item['key_'],
				$item['delay'],
				item_type2str($item['type']),
				$status,
				new CCol($applications, 'wraptext'),
				$error
			));
		}

// GO{
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_ITEMS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "group_itemid";');

		$footer = get_table_header(array($goBox, $goButton));
// }GO

		$form->addItem(array($paging, $table, $paging, $footer));
		$items_wdgt->addItem($form);
	}

	$items_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>
