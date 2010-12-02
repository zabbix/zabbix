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

$page['title'] = 'S_CONFIGURATION_OF_ITEMS';
$page['file'] = 'disc_prototypes.php';
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
		'parent_discoveryid' =>	array(T_ZBX_INT, O_MAND,	 P_SYS,	DB_ID,		null),
		'itemid' =>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),

		'groupid'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,			null),

		'add_groupid'=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({register})&&({register}=="go"))'),
		'action'=>			array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,		'(isset({register})&&({register}=="go"))'),

		'copy_type'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0,1'),	'isset({copy})'),
		'copy_mode'=>			array(T_ZBX_INT, O_OPT,	 P_SYS,	IN('0'),	null),

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
		'value_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2,3,4'),	'isset({save})'),
		'data_type'=>		array(T_ZBX_INT, O_OPT,  null,  IN(ITEM_DATA_TYPE_DECIMAL.','.ITEM_DATA_TYPE_OCTAL.','.ITEM_DATA_TYPE_HEXADECIMAL),
					'isset({save})&&(isset({value_type})&&({value_type}=='.ITEM_VALUE_TYPE_UINT64.'))'),
		'valuemapid'=>		array(T_ZBX_INT, O_OPT,	 null,	DB_ID,		'isset({save})&&isset({value_type})&&'.IN(
												ITEM_VALUE_TYPE_FLOAT.','.
												ITEM_VALUE_TYPE_UINT64, 'value_type')),
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
		'port'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	'isset({save})&&isset({type})&&'.IN(
													ITEM_TYPE_SNMPV1.','.
													ITEM_TYPE_SNMPV2C.','.
													ITEM_TYPE_SNMPV3,'type')),

		'snmpv3_securitylevel'=>array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_securityname'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_authpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),
		'snmpv3_privpassphrase'=>array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_SNMPV3.'))'),

		'ipmi_sensor'=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,	'isset({save})&&(isset({type})&&({type}=='.ITEM_TYPE_IPMI.'))', S_IPMI_SENSOR),

		'trapper_hosts'=>	array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})&&isset({type})&&({type}==2)'),
		'units'=>		array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&isset({value_type})&&'.IN('0,3','value_type')),
		'multiplier'=>		array(T_ZBX_INT, O_OPT,  null,  null,		null),
		'delta'=>		array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({save})&&isset({value_type})&&'.IN('0,3','value_type')),

		'formula'=>		array(T_ZBX_DBL, O_OPT,  null,  NOT_ZERO,	'isset({save})&&isset({multiplier})&&({multiplier}==1)&&'.IN('0,3','value_type'), S_CUSTOM_MULTIPLIER),
		'logtimefmt'=>		array(T_ZBX_STR, O_OPT,  null,  null,		'isset({save})&&(isset({value_type})&&({value_type}==2))'),

		'group_itemid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		'copy_targetid'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		'filter_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
		'new_application'=>	array(T_ZBX_STR, O_OPT, null,	null,	'isset({save})'),
		'applications'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),

		'del_history'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_delay_flex'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'register'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'update'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'copy'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'select'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
// filter
		'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,	null),
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
	if(get_request('parent_discoveryid', false)){
		$options = array(
			'itemids' => $_REQUEST['parent_discoveryid'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1
		);
		$discovery_rule = CDiscoveryRule::get($options);
		$discovery_rule = reset($discovery_rule);
		if(!$discovery_rule) access_deny();
		$_REQUEST['hostid'] = $discovery_rule['hostid'];
	}
	else{
		access_deny();
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
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['itemid'])){
		$result = CItemPrototype::delete($_REQUEST['itemid']);
		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);

		unset($_REQUEST['itemid']);
		unset($_REQUEST['form']);
	}
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])){
		unset($_REQUEST['itemid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['save'])){
		$delay_flex = get_request('delay_flex', array());
		$db_delay_flex = '';
		foreach($delay_flex as $num => $val){
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		}
		$db_delay_flex = trim($db_delay_flex,';');

		DBstart();

		$applications = get_request('applications', array());
		$fapp = reset($applications);
		if($fapp == 0) array_shift($applications);

		if(!zbx_empty($_REQUEST['new_application'])){
			$new_appid = CApplication::create(array(
				'name' => $_REQUEST['new_application'],
				'hostid' => $_REQUEST['hostid']
			));
			if($new_appid){
				$new_appid = reset($new_appid['applicationids']);
				$applications[$new_appid] = $new_appid;
			}
		}

		$item = array(
			'description'	=> get_request('description'),
			'key_'			=> get_request('key'),
			'hostid'		=> get_request('hostid'),
			'delay'			=> get_request('delay'),
			'status'		=> get_request('status'),
			'type'			=> get_request('type'),
			'snmp_community'=> get_request('snmp_community'),
			'snmp_oid'		=> get_request('snmp_oid'),
			'value_type'	=> get_request('value_type'),
			'trapper_hosts'	=> get_request('trapper_hosts'),
			'port'		=> get_request('port'),
			'units'			=> get_request('units'),
			'multiplier'	=> get_request('multiplier', 0),
			'delta'			=> get_request('delta'),
			'snmpv3_securityname'	=> get_request('snmpv3_securityname'),
			'snmpv3_securitylevel'	=> get_request('snmpv3_securitylevel'),
			'snmpv3_authpassphrase'	=> get_request('snmpv3_authpassphrase'),
			'snmpv3_privpassphrase'	=> get_request('snmpv3_privpassphrase'),
			'formula'			=> get_request('formula'),
			'logtimefmt'		=> get_request('logtimefmt'),
			'valuemapid'		=> get_request('valuemapid'),
			'delay_flex'		=> $db_delay_flex,
			'authtype'		=> get_request('authtype'),
			'username'		=> get_request('username'),
			'password'		=> get_request('password'),
			'publickey'		=> get_request('publickey'),
			'privatekey'		=> get_request('privatekey'),
			'params'			=> get_request('params'),
			'ipmi_sensor'		=> get_request('ipmi_sensor'),
			'data_type'		=> get_request('data_type'),
			'applications' => $applications,
			'flags' => ZBX_FLAG_DISCOVERY_CHILD,
			'ruleid' => get_request('parent_discoveryid'),
		);

		if(isset($_REQUEST['itemid'])){
			$db_item = get_item_by_itemid_limited($_REQUEST['itemid']);
			$db_item['applications'] = get_applications_by_itemid($_REQUEST['itemid']);

			foreach($item as $field => $value){
				if(isset($db_item[$field]) && ($item[$field] == $db_item[$field]))
					unset($item[$field]);
			}

			$item['itemid'] = $_REQUEST['itemid'];

			$result = CItemPrototype::update($item);

			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
		}
		else{
			$result = CItemPrototype::create($item);
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}

		$result = DBend($result);

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
		$go_result = CItemPrototype::delete($_REQUEST['group_itemid']);
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


	$form = null;
	if(!isset($_REQUEST['form'])){
		$form = new CForm(null, 'get');
		$form->addVar('parent_discoveryid', $_REQUEST['parent_discoveryid']);
		$form->addItem(new CSubmit('form', S_CREATE_PROTOTYPE));
	}
	$items_wdgt->addPageHeader(S_CONFIGURATION_OF_ITEM_PROTOTYPES_BIG, $form);


	if(isset($_REQUEST['form'])){
		$items_wdgt->addItem(insert_item_form());
	}
	else{
// Items Header
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$items_wdgt->addHeader(array(S_ITEM_PROTOTYPES_OF_BIG.SPACE, new CSpan($discovery_rule['description'], 'discoveryName')));
		$items_wdgt->addHeader($numrows, SPACE);

		$items_wdgt->addItem(get_header_host_table($_REQUEST['hostid']));
// ----------------

		$form = new CForm();
		$form->setName('items');
		$form->addVar('parent_discoveryid', $_REQUEST['parent_discoveryid']);

		$sortlink = new Curl();
		$sortlink->setArgument('parent_discoveryid', $_REQUEST['parent_discoveryid']);
		$sortlink = $sortlink->getUrl();
		$table = new CTableInfo();
		$table->setHeader(array(
			new CCheckBox('all_items',null,"checkAll('".$form->GetName()."','all_items','group_itemid');"),
			make_sorting_header(S_DESCRIPTION,'description', $sortlink),
			make_sorting_header(S_KEY,'key_', $sortlink),
			make_sorting_header(S_INTERVAL,'delay', $sortlink),
			make_sorting_header(S_HISTORY,'history', $sortlink),
			make_sorting_header(S_TRENDS,'trends', $sortlink),
			make_sorting_header(S_TYPE,'type', $sortlink),
			make_sorting_header(S_STATUS,'status', $sortlink),
			S_APPLICATIONS,
			S_ERROR
		));


		$sortfield = getPageSortField('description');
		$sortorder = getPageSortOrder();
		$options = array(
			'discoveryids' => $_REQUEST['parent_discoveryid'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'select_applications' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$items = CItemPrototype::get($options);

		order_result($items, $sortfield, $sortorder);
		$paging = getPagingLine($items);

		foreach($items as $inum => $item){
			$description = array();
			if($item['templateid']){
				$template_host = get_realhost_by_itemid($item['templateid']);

				$tpl_disc_ruleid = get_realrule_by_itemid_and_hostid($_REQUEST['parent_discoveryid'], $template_host['hostid']);
				$description[] = new CLink($template_host['host'],'?parent_discoveryid='.$tpl_disc_ruleid, 'unknown');
				$description[] = ':';
			}
			$item['description_expanded'] = item_description($item);

			$disc_link = '&parent_discoveryid='.$_REQUEST['parent_discoveryid'];
			$description[] = new CLink($item['description_expanded'], '?form=update&itemid='.$item['itemid'].$disc_link);

			$status = new CCol(new CLink(item_status2str($item['status']), '?group_itemid='.$item['itemid'].$disc_link.
					'&go='.($item['status']? 'activate':'disable'), item_status2style($item['status'])));


			if(zbx_empty($item['error'])){
				$error = new CDiv(SPACE, 'iconok');
			}
			else{
				$error = new CDiv(SPACE, 'iconerror');
				$error->setHint($item['error'], '', 'on');
			}

			$applications = zbx_objectValues($item['applications'], 'name');
			$applications = implode(', ', $applications);
// ugly dash
			if(empty($applications)) $applications = '-';

			$table->addRow(array(
				new CCheckBox('group_itemid['.$item['itemid'].']',null,null,$item['itemid']),
				$description,
				$item['key_'],
				$item['delay'],
				$item['history'],
				(in_array($item['value_type'], array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT)) ? '' : $item['trends']),
				item_type2str($item['type']),
				$status,
				new CCol($applications, 'wraptext'),
				$error,
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
		$goButton = new CSubmit('goButton',S_GO);
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
