<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
require_once 'include/config.inc.php';
require_once 'include/items.inc.php';

$page['title'] = _('Queue');
$page['file'] = 'queue.php';
$page['hist_arg'] = array('config');

define('ZBX_PAGE_DO_REFRESH', 1);

require_once 'include/page_header.php';
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2"),	NULL)
	);

	check_fields($fields);
?>
<?php
	$_REQUEST['config'] = get_request('config', CProfile::get('web.queue.config', 0));
	CProfile::update('web.queue.config', $_REQUEST['config'], PROFILE_TYPE_INT);

	$form = new CForm('get');

	$cmbMode = new CComboBox('config', $_REQUEST['config'], 'submit();');
	$cmbMode->addItem(0, _('Overview'));
	$cmbMode->addItem(1, _('Overview by proxy'));
	$cmbMode->addItem(2, _('Details'));
	$form->addItem($cmbMode);

	$queue_wdgt = new CWidget();
	$queue_wdgt->addPageHeader(_('QUEUE OF ITEMS TO BE UPDATED'), $form);

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
		ITEM_TYPE_CALCULATED
	);
	$zbx_item_types = array(
		ITEM_TYPE_ZABBIX
	);
	$snmp_item_types = array(
		ITEM_TYPE_SNMPV1,
		ITEM_TYPE_SNMPV2C,
		ITEM_TYPE_SNMPV3
	);
	$ipmi_item_types = array(
		ITEM_TYPE_IPMI
	);
	$jmx_item_types = array(
		ITEM_TYPE_JMX
	);
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
		ITEM_TYPE_JMX,
		ITEM_TYPE_CALCULATED
	);

	$sql = 'SELECT i.itemid,i.lastclock,i.name,i.key_,i.type,h.name as hostname,'.
			'h.hostid,h.proxy_hostid,i.delay,i.delay_flex,i.interfaceid'.
		' FROM items i,hosts h'.
		' WHERE i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND i.value_type NOT IN ('.ITEM_VALUE_TYPE_LOG.')'.
			' AND NOT i.lastclock IS NULL'.
			' AND i.lastclock<'.time().'+i.delay'.
			' AND ('.
				' i.type IN ('.implode(',',$norm_item_types).')'.
				' OR (h.available<>'.HOST_AVAILABLE_FALSE.' AND i.type IN ('.implode(',',$zbx_item_types).'))'.
				' OR (h.snmp_available<>'.HOST_AVAILABLE_FALSE.' AND i.type IN ('.implode(',',$snmp_item_types).'))'.
				' OR (h.ipmi_available<>'.HOST_AVAILABLE_FALSE.' AND i.type IN ('.implode(',',$ipmi_item_types).'))'.
				' OR (h.jmx_available<>'.HOST_AVAILABLE_FALSE.' AND i.type IN ('.implode(',',$jmx_item_types).'))'.
				')'.
			' AND '.DBin_node('i.itemid', get_current_nodeid()).
			' AND i.flags NOT IN ('.ZBX_FLAG_DISCOVERY_CHILD.')'.
		' ORDER BY i.lastclock,h.name,i.name,i.key_';
	$result = DBselect($sql);

	$table = new CTableInfo(_('The queue is empty'));
	$truncated = false;

	if($_REQUEST['config']==0){
		foreach($item_types as $type){
			$sec_10[$type] = 0;
			$sec_30[$type] = 0;
			$sec_60[$type] = 0;
			$sec_300[$type] = 0;
			$sec_600[$type] = 0;
			$sec_rest[$type] = 0;
		}

		while($row = DBfetch($result)){
			$res = calculateItemNextcheck($row['interfaceid'], $row['itemid'], $row['type'], $row['delay'], $row['delay_flex'], $row['lastclock']);
			if(0 != $row['proxy_hostid']){
				$res['nextcheck'] = $row['lastclock'] + $res['delay'];
			}
			$diff = $now - $res['nextcheck'];

			if($diff <= 5)
				continue;
			else if($diff <= 10)
				$sec_10[$row['type']]++;
			else if($diff <= 30)
				$sec_30[$row['type']]++;
			else if($diff <= 60)
				$sec_60[$row['type']]++;
			else if($diff <= 300)
				$sec_300[$row['type']]++;
			else if($diff <= 600)
				$sec_600[$row['type']]++;
			else
				$sec_rest[$row['type']]++;
		}

		$table->setHeader(array(
			_('Items'),
			_('5 seconds'),
			_('10 seconds'),
			_('30 seconds'),
			_('1 minute'),
			_('5 minutes'),
			_('More than 10 minutes')
		));

		foreach($item_types as $type){
			$table->addRow(array(
				item_type2str($type),
				getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $sec_10[$type], !$sec_10[$type]),
				getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $sec_30[$type], !$sec_30[$type]),
				getSeverityCell(TRIGGER_SEVERITY_WARNING, $sec_60[$type], !$sec_60[$type]),
				getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $sec_300[$type], !$sec_300[$type]),
				getSeverityCell(TRIGGER_SEVERITY_HIGH, $sec_600[$type], !$sec_600[$type]),
				getSeverityCell(TRIGGER_SEVERITY_DISASTER, $sec_rest[$type], !$sec_rest[$type]),
			));
		}
	}
	else if ($_REQUEST['config'] == 1){
		$proxies = API::proxy()->get(array(
			'output' => array('hostid', 'host'),
			'preservekeys' => true,
		));
		order_result($proxies, 'host');

		$proxies[0] = array('host' => _('Server'));
		foreach($proxies as $proxyid => $proxy){
			$sec_10[$proxyid] = 0;
			$sec_30[$proxyid] = 0;
			$sec_60[$proxyid] = 0;
			$sec_300[$proxyid] = 0;
			$sec_600[$proxyid] = 0;
			$sec_rest[$proxyid] = 0;
		}

		while($row = DBfetch($result)){
			$res = calculateItemNextcheck($row['interfaceid'], $row['itemid'], $row['type'], $row['delay'], $row['delay_flex'], $row['lastclock']);
			if(0 != $row['proxy_hostid']){
				$res['nextcheck'] = $row['lastclock'] + $res['delay'];
			}
			$diff = $now - $res['nextcheck'];


			if($diff <= 5)
				continue;
			else if($diff <= 10)
				$sec_10[$row['proxy_hostid']]++;
			else if($diff <= 30)
				$sec_30[$row['proxy_hostid']]++;
			else if($diff <= 60)
				$sec_60[$row['proxy_hostid']]++;
			else if($diff <= 300)
				$sec_300[$row['proxy_hostid']]++;
			else if($diff <= 600)
				$sec_600[$row['proxy_hostid']]++;
			else
				$sec_rest[$row['proxy_hostid']]++;
		}

		$table->setHeader(array(
			_('Proxy'),
			_('5 seconds'),
			_('10 seconds'),
			_('30 seconds'),
			_('1 minute'),
			_('5 minutes'),
			_('More than 10 minutes')
		));

		foreach($proxies as $proxyid => $proxy){
			$table->addRow(array(
				$proxy['host'],
				getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $sec_10[$proxyid], !$sec_10[$proxyid]),
				getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $sec_30[$proxyid], !$sec_30[$proxyid]),
				getSeverityCell(TRIGGER_SEVERITY_WARNING, $sec_60[$proxyid], !$sec_60[$proxyid]),
				getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $sec_300[$proxyid], !$sec_300[$proxyid]),
				getSeverityCell(TRIGGER_SEVERITY_HIGH, $sec_600[$proxyid], !$sec_600[$proxyid]),
				getSeverityCell(TRIGGER_SEVERITY_DISASTER, $sec_rest[$proxyid], !$sec_rest[$proxyid]),
			));
		}
	}
	else if($_REQUEST['config'] == 2){
		$arr = array();

		$table->setHeader(array(
			_('Next check'),
			_('Delayed by'),
			is_show_all_nodes() ? _('Node') : null,
			_('Host'),
			_('Name')
		));

		while($row = DBfetch($result)){
			$res = calculateItemNextcheck($row['interfaceid'], $row['itemid'], $row['type'], $row['delay'], $row['delay_flex'], $row['lastclock']);
			if(0 != $row['proxy_hostid']){
				$res['nextcheck'] = $row['lastclock'] + $res['delay'];
			}
			$diff = $now - $res['nextcheck'];

			if($diff <= 5)
				continue;

			$arr[] = array($res['nextcheck'], $row['hostid'], $row['hostname'], itemName($row));
		}

		$rows = 0;
		sort($arr);
		foreach($arr as $r){
			$rows++;
			if($rows > 500){
				$truncated = true;
				break;
			}

			$table->addRow(array(
				zbx_date2str(QUEUE_NODES_DATE_FORMAT, $r[0]),
				zbx_date2age($r[0]),
				get_node_name_by_elid($r[1]),
				$r[2],
				$r[3]
			));
		}
	}

	$queue_wdgt->addItem($table);
	$queue_wdgt->Show();

	if($_REQUEST['config']!=0){
		show_table_header(_('Total').": ".$table->GetNumRows().($truncated ? ' ('._('Truncated').')' : ''));
	}


require_once dirname(__FILE__).'/include/page_footer.php';
?>
