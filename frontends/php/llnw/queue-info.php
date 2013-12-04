<?php
define('ZBX_RPC_REQUEST', 1);
global $ZBX_CONFIGURATION_FILE;
$ZBX_CONFIGURATION_FILE = '../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../include/config.inc.php';
require_once dirname(__FILE__).'/../include/items.inc.php';

// Allow regular zabbix sessions to pass-through to perform auth.
if ( strlen($json['key']) != 32 ) {
	exit;
}
else {
	CWebUser::$data['sessionid'] = $json['key'];
}

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

$data = array();

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

	foreach($item_types as $type){
		$data[item_type2str($type)] = array(
			$sec_10[$type],
			$sec_30[$type],
			$sec_60[$type],
			$sec_300[$type],
			$sec_600[$type],
			$sec_rest[$type]
		);
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

	foreach($proxies as $proxyid => $proxy){
		$data[$proxy['host']] = array(
			$sec_10[$proxyid],
			$sec_30[$proxyid],
			$sec_60[$proxyid],
			$sec_300[$proxyid],
			$sec_600[$proxyid],
			$sec_rest[$proxyid]
		);
	}
}

echo json_encode($data);
