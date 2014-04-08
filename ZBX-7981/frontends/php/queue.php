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
	require_once 'include/config.inc.php';
	require_once 'include/items.inc.php';

	$page['title'] = 'S_QUEUE';
	$page['file'] = 'queue.php';
	$page['hist_arg'] = array('config');

	define('ZBX_PAGE_DO_REFRESH', 1);

include_once 'include/page_header.php';

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"config"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2"),	NULL)
	);

	check_fields($fields);
?>
<?php
	$_REQUEST['config'] = get_request('config', CProfile::get('web.queue.config', 0));
	CProfile::update('web.queue.config',$_REQUEST['config'], PROFILE_TYPE_INT);

	$form = new CForm();
	$form->setMethod('get');

	$cmbMode = new CComboBox("config", $_REQUEST["config"], "submit();");
	$cmbMode->addItem(0, S_OVERVIEW);
	$cmbMode->addItem(1, S_OVERVIEW_BY_PROXY);
	$cmbMode->addItem(2, S_DETAILS);
	$form->addItem($cmbMode);

	$queue_wdgt = new CWidget();
	$queue_wdgt->addPageHeader(S_QUEUE_OF_ITEMS_TO_BE_UPDATED_BIG, $form);

	$now = time();
	$norm_item_types = array(
			ITEM_TYPE_ZABBIX_ACTIVE,
			ITEM_TYPE_SSH,
			ITEM_TYPE_TELNET,
			ITEM_TYPE_SIMPLE,
			ITEM_TYPE_INTERNAL,
			ITEM_TYPE_DB_MONITOR,
			ITEM_TYPE_AGGREGATE,
			ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_CALCULATED);
	$zbx_item_types = array(
			ITEM_TYPE_ZABBIX);
	$snmp_item_types = array(
			ITEM_TYPE_SNMPV1,
			ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_SNMPV3);
	$ipmi_item_types = array(
			ITEM_TYPE_IPMI);

	$item_types = array(
			ITEM_TYPE_ZABBIX,
			ITEM_TYPE_ZABBIX_ACTIVE,
			ITEM_TYPE_SIMPLE,
			ITEM_TYPE_SNMPV1,
			ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_SNMPV3,
			ITEM_TYPE_INTERNAL,
			ITEM_TYPE_AGGREGATE,
			ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR,
			ITEM_TYPE_IPMI,
			ITEM_TYPE_SSH,
			ITEM_TYPE_TELNET,
			ITEM_TYPE_CALCULATED);

	$sql = 'SELECT i.itemid,i.lastclock,i.description,i.key_,i.type,h.host,h.hostid,h.proxy_hostid,i.delay,i.delay_flex'.
		' FROM items i,hosts h'.
		' WHERE i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND i.value_type not in ('.ITEM_VALUE_TYPE_LOG.')'.
			' AND i.key_ NOT IN ('.zbx_dbstr('status').','.zbx_dbstr('zabbix[log]').')'.
			' AND NOT i.lastclock IS NULL'.
			' AND ('.
				' i.type in ('.implode(',',$norm_item_types).')'.
				' OR (h.available<>'.HOST_AVAILABLE_FALSE.' AND i.type in ('.implode(',',$zbx_item_types).'))'.
				' OR (h.snmp_available<>'.HOST_AVAILABLE_FALSE.' AND i.type in ('.implode(',',$snmp_item_types).'))'.
				' OR (h.ipmi_available<>'.HOST_AVAILABLE_FALSE.' AND i.type in ('.implode(',',$ipmi_item_types).'))'.
				')'.
			' AND '.DBin_node('i.itemid', get_current_nodeid()).
		' ORDER BY i.lastclock,h.host,i.description,i.key_';
	$result = DBselect($sql);

	$table = new CTableInfo(S_THE_QUEUE_IS_EMPTY);
	$truncated = 0;

	if($_REQUEST["config"]==0){

		foreach($item_types as $type){
			$sec_10[$type]=0;
			$sec_30[$type]=0;
			$sec_60[$type]=0;
			$sec_300[$type]=0;
			$sec_600[$type]=0;
			$sec_rest[$type]=0;
		}

		while($row=DBfetch($result)){
			$res = calculate_item_nextcheck($row['itemid'], $row['type'], $row['delay'], $row['delay_flex'], $row['lastclock']);
			if (0 != $row['proxy_hostid'])
				$res['nextcheck'] = $row['lastclock'] + $res['delay'];

			$diff = $now - $res['nextcheck'];
			if ($diff <= 5)
				continue;

			if ($diff <= 10)	$sec_10[$row['type']]++;
			else if ($diff <= 30)	$sec_30[$row['type']]++;
			else if ($diff <= 60)	$sec_60[$row['type']]++;
			else if ($diff <= 300)	$sec_300[$row['type']]++;
			else if ($diff <= 600)	$sec_600[$row['type']]++;
			else	$sec_rest[$row['type']]++;

		}

		$table->setHeader(array(S_ITEMS,S_5_SECONDS,S_10_SECONDS,S_30_SECONDS,S_1_MINUTE,S_5_MINUTES,S_MORE_THAN_10_MINUTES));
		foreach($item_types as $type){
			$elements=array(
				item_type2str($type),
				new CCol($sec_10[$type],($sec_10[$type])?"unknown_trigger":"normal"),
				new CCol($sec_30[$type],($sec_30[$type])?"information":"normal"),
				new CCol($sec_60[$type],($sec_60[$type])?"warning":"normal"),
				new CCol($sec_300[$type],($sec_300[$type])?"average":"normal"),
				new CCol($sec_600[$type],($sec_600[$type])?"high":"normal"),
				new CCol($sec_rest[$type],($sec_rest[$type])?"disaster":"normal")
			);

			$table->addRow($elements);
		}
	}
	else if ($_REQUEST["config"] == 1){
		$db_proxies = DBselect('SELECT hostid FROM hosts WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')');

		while (null != ($db_proxy = DBfetch($db_proxies))){
			$sec_10[$db_proxy['hostid']]	= 0;
			$sec_30[$db_proxy['hostid']]	= 0;
			$sec_60[$db_proxy['hostid']]	= 0;
			$sec_300[$db_proxy['hostid']]	= 0;
			$sec_600[$db_proxy['hostid']]	= 0;
			$sec_rest[$db_proxy['hostid']]	= 0;
		}

		$sec_10[0]	= 0;
		$sec_30[0]	= 0;
		$sec_60[0]	= 0;
		$sec_300[0]	= 0;
		$sec_600[0]	= 0;
		$sec_rest[0]	= 0;

		while ($row = DBfetch($result)){
			$res = calculate_item_nextcheck($row['itemid'], $row['type'], $row['delay'], $row['delay_flex'], $row['lastclock']);
			if (0 != $row['proxy_hostid'])
				$res['nextcheck'] = $row['lastclock'] + $res['delay'];

			$diff = $now - $res['nextcheck'];
			if ($diff <= 5)
				continue;

			if ($diff <= 10)	$sec_10[$row['proxy_hostid']]++;
			else if ($diff <= 30)	$sec_30[$row['proxy_hostid']]++;
			else if ($diff <= 60)	$sec_60[$row['proxy_hostid']]++;
			else if ($diff <= 300)	$sec_300[$row['proxy_hostid']]++;
			else if ($diff <= 600)	$sec_600[$row['proxy_hostid']]++;
			else	$sec_rest[$row['proxy_hostid']]++;

		}

		$table->setHeader(array(S_PROXY,S_5_SECONDS,S_10_SECONDS,S_30_SECONDS,S_1_MINUTE,S_5_MINUTES,S_MORE_THAN_10_MINUTES));

		$db_proxies = DBselect('SELECT hostid,host FROM hosts WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.') ORDER BY host');

		while (null != ($db_proxy = DBfetch($db_proxies))){
			$elements = array(
				$db_proxy['host'],
				new CCol($sec_10[$db_proxy['hostid']], $sec_10[$db_proxy['hostid']] ? "unknown_trigger" : "normal"),
				new CCol($sec_30[$db_proxy['hostid']], $sec_30[$db_proxy['hostid']] ? "information" : "normal"),
				new CCol($sec_60[$db_proxy['hostid']], $sec_60[$db_proxy['hostid']] ? "warning" : "normal"),
				new CCol($sec_300[$db_proxy['hostid']], $sec_300[$db_proxy['hostid']] ? "average" : "normal"),
				new CCol($sec_600[$db_proxy['hostid']], $sec_600[$db_proxy['hostid']] ? "high" : "normal"),
				new CCol($sec_rest[$db_proxy['hostid']], $sec_rest[$db_proxy['hostid']] ? "disaster" : "normal")
			);
			$table->addRow($elements);
		}
		$elements = array(
			new CCol(S_SERVER, 'bold'),
			new CCol($sec_10[0], $sec_10[0] ? 'unknown_trigger' : 'normal'),
			new CCol($sec_30[0], $sec_30[0] ? 'information' : 'normal'),
			new CCol($sec_60[0], $sec_60[0] ? 'warning' : 'normal'),
			new CCol($sec_300[0], $sec_300[0] ? 'average' : 'normal'),
			new CCol($sec_600[0], $sec_600[0] ? 'high' : 'normal'),
			new CCol($sec_rest[0], $sec_rest[0] ? 'disaster' : 'normal')
		);
		$table->addRow($elements);
	}
	else if ($_REQUEST["config"] == 2){
		$arr = array();

		$table->setHeader(array(
				S_NEXT_CHECK,
				S_DELAYED_BY,
				is_show_all_nodes() ? S_NODE : null,
				S_HOST,
				S_DESCRIPTION
				));
		while($row=DBfetch($result)){
			$res = calculate_item_nextcheck($row['itemid'], $row['type'], $row['delay'], $row['delay_flex'], $row['lastclock']);
			if (0 != $row['proxy_hostid'])
				$res['nextcheck'] = $row['lastclock'] + $res['delay'];

			$diff = $now - $res['nextcheck'];
			if ($diff <= 5)
				continue;

			array_push($arr, array($res['nextcheck'], $row['hostid'], $row['host'], item_description($row)));
		}

		$rows = 0;
		sort($arr);
		foreach($arr as $r){
			$rows++;
			if ($rows > 500){
				$truncated = 1;
				break;
			}

			$table->addRow(array(
				zbx_date2str(S_QUEUE_NODES_DATE_FORMAT,
					$r[0]),
				zbx_date2age($r[0]),
				get_node_name_by_elid($r[1]),
				$r[2],
				$r[3]
				));
		}
	}

	$queue_wdgt->addItem($table);
	$queue_wdgt->Show();

	if($_REQUEST["config"]!=0){
		show_table_header(S_TOTAL.": ".$table->GetNumRows().($truncated ? ' ('.S_TRUNCATED.')' : ''));
	}


include_once "include/page_footer.php";
?>
