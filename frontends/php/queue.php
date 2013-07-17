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
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Queue');
$page['file'] = 'queue.php';
$page['hist_arg'] = array('config');

define('ZBX_PAGE_DO_REFRESH', 1);

// item count to display in the details queue
define('QUEUE_DETAIL_ITEM_COUNT', 500);

require_once dirname(__FILE__).'/include/page_header.php';
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

	$queueTypes = array(
		0 => CZabbixServer::QUEUE_OVERVIEW,
		1 => CZabbixServer::QUEUE_OVERVIEW_BY_PROXY,
		2 => CZabbixServer::QUEUE_DETAILS
	);
	$config = $queueTypes[$_REQUEST['config']];

	$form = new CForm('get');
	$cmbMode = new CComboBox('config', $_REQUEST['config'], 'submit();');
	$cmbMode->addItem(0, _('Overview'));
	$cmbMode->addItem(1, _('Overview by proxy'));
	$cmbMode->addItem(2, _('Details'));
	$form->addItem($cmbMode);

	$queue_wdgt = new CWidget();
	$queue_wdgt->addPageHeader(_('QUEUE OF ITEMS TO BE UPDATED'), $form);

	$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT);
	$queueData = $zabbixServer->getQueue($config, get_cookie('zbx_sessionid'));

	// check for errors error
	if ($zabbixServer->getError()) {
		error($zabbixServer->getError());
		show_error_message(_('Cannot display item queue.'));

		require_once dirname(__FILE__).'/include/page_footer.php';
	}

	$table = new CTableInfo(_('The queue is empty.'));

	// overview
	if ($config == CZabbixServer::QUEUE_OVERVIEW) {
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
				getSeverityCell(TRIGGER_SEVERITY_DISASTER, $itemTypeData['delay600'], !$itemTypeData['delay600']),
			));
		}
	}
	// overview by proxy
	elseif ($config == CZabbixServer::QUEUE_OVERVIEW_BY_PROXY){
		$proxies = API::proxy()->get(array(
			'output' => array('hostid', 'host'),
			'preservekeys' => true,
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
				getSeverityCell(TRIGGER_SEVERITY_DISASTER, $proxyData['delay600'], !$proxyData['delay600']),
			));
		}
	}
	// details
	elseif ($config == CZabbixServer::QUEUE_DETAILS) {
		$queueData = zbx_toHash($queueData, 'itemid');

		$items = API::Item()->get(array(
			'output' => array('itemid', 'name', 'key_'),
			'selectHosts' => array('name'),
			'itemids' => array_keys($queueData),
			'webitems' => true,
			'preservekeys' => true
		));

		$table->setHeader(array(
			_('Scheduled check'),
			_('Delayed by'),
			is_show_all_nodes() ? _('Node') : null,
			_('Host'),
			_('Name')
		));

		$i = 0;
		foreach ($queueData as $itemData) {
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
				itemName($item)
			));
		}
	}

	$queue_wdgt->addItem($table);
	$queue_wdgt->Show();

	// display the table footer
	if ($config = CZabbixServer::QUEUE_OVERVIEW_BY_PROXY) {
		show_table_header(
			_('Total').": ".$table->GetNumRows().
			((count($queueData) > QUEUE_DETAIL_ITEM_COUNT) ? ' ('._('Truncated').')' : '')
		);
	}


require_once dirname(__FILE__).'/include/page_footer.php';
