<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
$page['hist_arg'] = array('config');

define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

$queueModes = array(
	QUEUE_OVERVIEW,
	QUEUE_OVERVIEW_BY_PROXY,
	QUEUE_DETAILS
);

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'config' => array(T_ZBX_INT, O_OPT, P_SYS, IN($queueModes), null)
);

check_fields($fields);

$config = get_request('config', CProfile::get('web.queue.config', 0));
CProfile::update('web.queue.config', $config, PROFILE_TYPE_INT);

// fetch data
$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
$queueRequests = array(
	QUEUE_OVERVIEW => CZabbixServer::QUEUE_OVERVIEW,
	QUEUE_OVERVIEW_BY_PROXY => CZabbixServer::QUEUE_OVERVIEW_BY_PROXY,
	QUEUE_DETAILS => CZabbixServer::QUEUE_DETAILS
);
$queueData = $zabbixServer->getQueue($queueRequests[$config], get_cookie('zbx_sessionid'));

// check for errors error
if ($zabbixServer->getError()) {
	error($zabbixServer->getError());
	show_error_message(_('Cannot display item queue.'));

	require_once dirname(__FILE__).'/include/page_footer.php';
}

// create filter form
$form = new CForm('get');
$cmbMode = new CComboBox('config', $config, 'submit();');
$cmbMode->addItem(QUEUE_OVERVIEW, _('Overview'));
$cmbMode->addItem(QUEUE_OVERVIEW_BY_PROXY, _('Overview by proxy'));
$cmbMode->addItem(QUEUE_DETAILS, _('Details'));
$form->addItem($cmbMode);

// display table
$queueWidget = new CWidget();
$queueWidget->addPageHeader(_('QUEUE OF ITEMS TO BE UPDATED'), $form);

$table = new CTableInfo(_('The queue is empty.'));

// overview
if ($config == QUEUE_OVERVIEW) {
	$itemTypes = array(
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

	$table->setHeader(array(
		_('Items'),
		_('5 seconds'),
		_('10 seconds'),
		_('30 seconds'),
		_('1 minute'),
		_('5 minutes'),
		_('More than 10 minutes')
	));

	$queueData = zbx_toHash($queueData, 'itemtype');
	foreach($itemTypes as $type) {
		if (isset($queueData[$type])) {
			$itemTypeData = $queueData[$type];
		}
		else {
			$itemTypeData = array(
				'delay5' => 0,
				'delay10' => 0,
				'delay30' => 0,
				'delay60' => 0,
				'delay300' => 0,
				'delay600' => 0
			);
		}

		$table->addRow(array(
			item_type2str($type),
			getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $itemTypeData['delay5'], !$itemTypeData['delay5']),
			getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $itemTypeData['delay10'], !$itemTypeData['delay10']),
			getSeverityCell(TRIGGER_SEVERITY_WARNING, $itemTypeData['delay30'], !$itemTypeData['delay30']),
			getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $itemTypeData['delay60'], !$itemTypeData['delay60']),
			getSeverityCell(TRIGGER_SEVERITY_HIGH, $itemTypeData['delay300'], !$itemTypeData['delay300']),
			getSeverityCell(TRIGGER_SEVERITY_DISASTER, $itemTypeData['delay600'], !$itemTypeData['delay600'])
		));
	}
}

// overview by proxy
elseif ($config == QUEUE_OVERVIEW_BY_PROXY) {
	$proxies = API::proxy()->get(array(
		'output' => array('hostid', 'host'),
		'preservekeys' => true
	));
	order_result($proxies, 'host');

	$proxies[0] = array('host' => _('Server'));

	$table->setHeader(array(
		_('Proxy'),
		_('5 seconds'),
		_('10 seconds'),
		_('30 seconds'),
		_('1 minute'),
		_('5 minutes'),
		_('More than 10 minutes')
	));

	$queueData = zbx_toHash($queueData, 'proxyid');
	foreach ($proxies as $proxyId => $proxy) {
		if (isset($queueData[$proxyId])) {
			$proxyData = $queueData[$proxyId];
		}
		else {
			$proxyData = array(
				'delay5' => 0,
				'delay10' => 0,
				'delay30' => 0,
				'delay60' => 0,
				'delay300' => 0,
				'delay600' => 0
			);
		}

		$table->addRow(array(
			$proxy['host'],
			getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $proxyData['delay5'], !$proxyData['delay5']),
			getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $proxyData['delay10'], !$proxyData['delay10']),
			getSeverityCell(TRIGGER_SEVERITY_WARNING, $proxyData['delay30'], !$proxyData['delay30']),
			getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $proxyData['delay60'], !$proxyData['delay60']),
			getSeverityCell(TRIGGER_SEVERITY_HIGH, $proxyData['delay300'], !$proxyData['delay300']),
			getSeverityCell(TRIGGER_SEVERITY_DISASTER, $proxyData['delay600'], !$proxyData['delay600'])
		));
	}
}

// details
elseif ($config == QUEUE_DETAILS) {
	$queueData = zbx_toHash($queueData, 'itemid');

	$items = API::Item()->get(array(
		'output' => array('itemid', 'hostid', 'name', 'key_'),
		'selectHosts' => array('name'),
		'itemids' => array_keys($queueData),
		'webitems' => true,
		'preservekeys' => true
	));

	$items = CMacrosResolverHelper::resolveItemNames($items);

	$table->setHeader(array(
		_('Scheduled check'),
		_('Delayed by'),
		is_show_all_nodes() ? _('Node') : null,
		_('Host'),
		_('Name')
	));

	$i = 0;
	foreach ($queueData as $itemData) {
		if (!isset($items[$itemData['itemid']])) {
			continue;
		}

		// display only the first 500 items
		$i++;
		if ($i > QUEUE_DETAIL_ITEM_COUNT) {
			break;
		}

		$item = $items[$itemData['itemid']];
		$host = reset($item['hosts']);

		$table->addRow(array(
			zbx_date2str(QUEUE_NODES_DATE_FORMAT, $itemData['nextcheck']),
			zbx_date2age($itemData['nextcheck']),
			get_node_name_by_elid($item['itemid']),
			$host['name'],
			$item['name_expanded']
		));
	}
}

$queueWidget->addItem($table);
$queueWidget->show();

// display the table footer
if ($config == QUEUE_OVERVIEW_BY_PROXY) {
	show_table_header(_('Total').': '.$table->getNumRows());
}
elseif ($config == QUEUE_DETAILS) {
	show_table_header(
		_('Total').': '.$table->getNumRows().
		((count($queueData) > QUEUE_DETAIL_ITEM_COUNT) ? ' ('._('Truncated').')' : '')
	);
}

require_once dirname(__FILE__).'/include/page_footer.php';
