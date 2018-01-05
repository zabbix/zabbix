<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Queue');
$page['file'] = 'queue.php';

define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

$queueModes = [
	QUEUE_OVERVIEW,
	QUEUE_OVERVIEW_BY_PROXY,
	QUEUE_DETAILS
];

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'config' => [T_ZBX_INT, O_OPT, P_SYS, IN($queueModes), null]
];

check_fields($fields);

$config = getRequest('config', CProfile::get('web.queue.config', 0));
CProfile::update('web.queue.config', $config, PROFILE_TYPE_INT);

// fetch data
$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
$queueRequests = [
	QUEUE_OVERVIEW => CZabbixServer::QUEUE_OVERVIEW,
	QUEUE_OVERVIEW_BY_PROXY => CZabbixServer::QUEUE_OVERVIEW_BY_PROXY,
	QUEUE_DETAILS => CZabbixServer::QUEUE_DETAILS
];
$queueData = $zabbixServer->getQueue($queueRequests[$config], get_cookie('zbx_sessionid'), QUEUE_DETAIL_ITEM_COUNT);

// check for errors error
if ($zabbixServer->getError()) {
	error($zabbixServer->getError());
	show_error_message(_('Cannot display item queue.'));

	require_once dirname(__FILE__).'/include/page_footer.php';
}

$widget = (new CWidget())
	->setTitle(_('Queue of items to be updated'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem((new CComboBox('config', $config, 'submit();', [
				QUEUE_OVERVIEW => _('Overview'),
				QUEUE_OVERVIEW_BY_PROXY => _('Overview by proxy'),
				QUEUE_DETAILS => _('Details')
			])))
		)
	);

$table = new CTableInfo();

$severityConfig = select_config();

// overview
if ($config == QUEUE_OVERVIEW) {
	$itemTypes = [
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
	];

	$table->setHeader([
		_('Items'),
		_('5 seconds'),
		_('10 seconds'),
		_('30 seconds'),
		_('1 minute'),
		_('5 minutes'),
		_('More than 10 minutes')
	]);

	$queueData = zbx_toHash($queueData, 'itemtype');

	foreach ($itemTypes as $type) {
		if (isset($queueData[$type])) {
			$itemTypeData = $queueData[$type];
		}
		else {
			$itemTypeData = [
				'delay5' => 0,
				'delay10' => 0,
				'delay30' => 0,
				'delay60' => 0,
				'delay300' => 0,
				'delay600' => 0
			];
		}

		$table->addRow([
			item_type2str($type),
			getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $severityConfig, $itemTypeData['delay5'],
				!$itemTypeData['delay5']
			),
			getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $severityConfig, $itemTypeData['delay10'],
				!$itemTypeData['delay10']
			),
			getSeverityCell(TRIGGER_SEVERITY_WARNING, $severityConfig, $itemTypeData['delay30'],
				!$itemTypeData['delay30']
			),
			getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $severityConfig, $itemTypeData['delay60'],
				!$itemTypeData['delay60']
			),
			getSeverityCell(TRIGGER_SEVERITY_HIGH, $severityConfig, $itemTypeData['delay300'],
				!$itemTypeData['delay300']
			),
			getSeverityCell(TRIGGER_SEVERITY_DISASTER, $severityConfig, $itemTypeData['delay600'],
				!$itemTypeData['delay600']
			)
		]);
	}
}

// overview by proxy
elseif ($config == QUEUE_OVERVIEW_BY_PROXY) {
	$proxies = API::proxy()->get([
		'output' => ['hostid', 'host'],
		'preservekeys' => true
	]);
	order_result($proxies, 'host');

	$proxies[0] = ['host' => _('Server')];

	$table->setHeader([
		_('Proxy'),
		_('5 seconds'),
		_('10 seconds'),
		_('30 seconds'),
		_('1 minute'),
		_('5 minutes'),
		_('More than 10 minutes')
	]);

	$queueData = zbx_toHash($queueData, 'proxyid');
	foreach ($proxies as $proxyId => $proxy) {
		if (isset($queueData[$proxyId])) {
			$proxyData = $queueData[$proxyId];
		}
		else {
			$proxyData = [
				'delay5' => 0,
				'delay10' => 0,
				'delay30' => 0,
				'delay60' => 0,
				'delay300' => 0,
				'delay600' => 0
			];
		}

		$table->addRow([
			$proxy['host'],
			getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $severityConfig, $proxyData['delay5'],
				!$proxyData['delay5']
			),
			getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $severityConfig, $proxyData['delay10'],
				!$proxyData['delay10']
			),
			getSeverityCell(TRIGGER_SEVERITY_WARNING, $severityConfig, $proxyData['delay30'], !$proxyData['delay30']),
			getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $severityConfig, $proxyData['delay60'], !$proxyData['delay60']),
			getSeverityCell(TRIGGER_SEVERITY_HIGH, $severityConfig, $proxyData['delay300'], !$proxyData['delay300']),
			getSeverityCell(TRIGGER_SEVERITY_DISASTER, $severityConfig, $proxyData['delay600'], !$proxyData['delay600'])
		]);
	}
}

// details
elseif ($config == QUEUE_DETAILS) {
	$queueData = zbx_toHash($queueData, 'itemid');

	$items = API::Item()->get([
		'output' => ['itemid', 'hostid', 'name', 'key_'],
		'selectHosts' => ['name'],
		'itemids' => array_keys($queueData),
		'webitems' => true,
		'preservekeys' => true
	]);

	$items = CMacrosResolverHelper::resolveItemNames($items);

	// get hosts for queue items
	$hostIds = zbx_objectValues($items, 'hostid');
	$hostIds = array_keys(array_flip($hostIds));

	$hosts = API::Host()->get([
		'output' => ['hostid', 'proxy_hostid'],
		'hostids' => $hostIds,
		'preservekeys' => true
	]);

	// get proxies for those hosts
	$proxyHostIds = [];
	foreach ($hosts as $host) {
		if ($host['proxy_hostid']) {
			$proxyHostIds[$host['proxy_hostid']] = $host['proxy_hostid'];
		}
	}

	if ($proxyHostIds) {
		$proxies = API::Proxy()->get([
			'proxyids' => $proxyHostIds,
			'output' => ['proxyid', 'host'],
			'preservekeys' => true
		]);
	}

	$table->setHeader([
		_('Scheduled check'),
		_('Delayed by'),
		_('Host'),
		_('Name')
	]);

	$i = 0;
	foreach ($queueData as $itemData) {
		if (!isset($items[$itemData['itemid']])) {
			continue;
		}

		// display only the first QUEUE_DETAIL_ITEM_COUNT items (can only occur when using old server versions)
		$i++;
		if ($i > QUEUE_DETAIL_ITEM_COUNT) {
			break;
		}

		$item = $items[$itemData['itemid']];
		$host = reset($item['hosts']);

		$table->addRow([
			zbx_date2str(DATE_TIME_FORMAT_SECONDS, $itemData['nextcheck']),
			zbx_date2age($itemData['nextcheck']),
			(isset($proxies[$hosts[$item['hostid']]['proxy_hostid']]))
				? $proxies[$hosts[$item['hostid']]['proxy_hostid']]['host'].NAME_DELIMITER.$host['name']
				: $host['name'],
			$item['name_expanded']
		]);
	}
}

// display the table footer
if ($config == QUEUE_OVERVIEW_BY_PROXY) {
	$total = _('Total').': '.$table->getNumRows();
}
elseif ($config == QUEUE_DETAILS) {
	$total = _s('Displaying %1$s of %2$s found', $table->getNumRows(), $zabbixServer->getTotalCount());
}
else {
	$total = null;
}

if ($total !== null) {
	$total = (new CDiv())
		->addClass(ZBX_STYLE_TABLE_PAGING)
		->addItem((new CDiv())
			->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
			->addItem((new CDiv())
				->addClass(ZBX_STYLE_TABLE_STATS)
				->addItem($total)
			)
		);
}

$widget
	->addItem($table)
	->addItem($total)
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
