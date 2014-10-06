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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Monthly report');
$page['file'] = 'rsm.monthlyreports.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'export' =>			array(T_ZBX_INT, O_OPT,	P_ACT,	null,		null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'filter_search' =>	array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_year' =>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_month' =>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>			array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);

check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.monthlyreports.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = array();
$data['services'] = array();

$year = date('Y', time());
$month = date('m', time());

if ($month == 1) {
	$year--;
	$month = 12;
}
else {
	$month--;
}

/*
 * Filter
 */
if (isset($_REQUEST['filter_set'])) {
	$data['filter_search'] = get_request('filter_search');
	$data['filter_year'] = get_request('filter_year');
	$data['filter_month'] = get_request('filter_month');

	if ($year < $data['filter_year'] || ($year == $data['filter_year'] && $month < $data['filter_month'])) {
		show_error_message(_('Incorrect report period.'));
	}
}
else {
	$data['filter_search'] = null;
	$data['filter_year'] = $year;
	$data['filter_month'] = $month;
}

if ($data['filter_search']) {
	$tld = API::Host()->get(array(
		'tlds' => true,
		'output' => array('hostid', 'host', 'name'),
		'filter' => array(
			'name' => $data['filter_search']
		)
	));

	$data['tld'] = reset($tld);

	if ($data['tld']) {
		// get application
		$applications = API::Application()->get(array(
			'hostids' => $data['tld']['hostid'],
			'output' => array('applicationid'),
			'filter' => array(
				'name' => MONTHLY_REPORTS_APPLICATION
			)
		));

		if ($applications) {
			$application = reset($applications);

			// time limits
			$startTime = mktime(
				0,
				0,
				0,
				$data['filter_month'],
				1,
				$data['filter_year']
			);

			if ($data['filter_month'] == 12) {
				$endMonth = 1;
				$endYear = $data['filter_year'] + 1;
			}
			else {
				$endMonth = $data['filter_month'] + 1;
				$endYear = $data['filter_year'];
			}

			$endTime = mktime(
				0,
				0,
				0,
				$endMonth,
				1,
				$endYear
			);

			$data['stime'] = date('YmdHis', $startTime);

			// get items
			$items = API::Item()->get(array(
				'applicationids' => $application['applicationid'],
				'output' => array('itemid', 'name', 'key_', 'value_type')
			));

			foreach ($items as $item) {
				$itemKey = new CItemKey($item['key_']);
				switch ($itemKey->getKeyId()) {
					case MONTHLY_REPORTS_DNS_NS_RTT_UDP:
						$newName = 'UDP DNS Resolution RTT';
						$macro = CALCULATED_ITEM_SLV_DNS_NS_RTT_UDP;
						break;
					case MONTHLY_REPORTS_DNS_NS_RTT_TCP:
						$newName = 'TCP DNS Resolution RTT';
						$macro = CALCULATED_ITEM_SLV_DNS_NS_RTT_TCP;
						break;
					case MONTHLY_REPORTS_DNS_NS_UPD:
						$newName = 'DNS update time';
						$macro = CALCULATED_ITEM_SLV_DNS_NS_UPD;
						break;
					case MONTHLY_REPORTS_DNS_NS:
						$newName = 'DNS Name Server availability';
						$macro = CALCULATED_ITEM_SLV_DNS_NS;
						break;
					case MONTHLY_REPORTS_RDDS43_RTT:
						$newName = 'RDDS43 resolution RTT';
						$macro = CALCULATED_ITEM_SLV_RDDS43_RTT;
						break;
					case MONTHLY_REPORTS_RDDS80_RTT:
						$newName = 'RDDS80 resolution RTT';
						$macro = CALCULATED_ITEM_SLV_RDDS80_RTT;
						break;
					case MONTHLY_REPORTS_RDDS_UPD:
						$newName = 'RDDS update time';
						$macro = CALCULATED_ITEM_SLV_RDDS_UPD;
						break;
					case MONTHLY_REPORTS_EPP_INFO:
						$newName = 'EPP INFO resolution RTT';
						$macro = CALCULATED_ITEM_SLV_EPP_INFO;
						break;
					case MONTHLY_REPORTS_EPP_LOGIN:
						$newName = 'EPP LOGIN resolution RTT';
						$macro = CALCULATED_ITEM_SLV_EPP_LOGIN;
						break;
					case MONTHLY_REPORTS_EPP_UPDATE:
						$newName = 'EPP UPDATE resolution RTT';
						$macro = CALCULATED_ITEM_EPP_UPDATE;
						break;
					default:
						$newName = null;
						break;
				}

				if ($newName) {
					$data['services'][$newName]['parameters'][$item['itemid']]['ns'] = implode(': ', $itemKey->getParameters());

					$itemsAndServices[$item['itemid']] = $newName;
					$macroValue[$macro] = $item['itemid'];
					$usedMacro[] = $macro;

					$itemHistory = API::History()->get(array(
						'itemids' => $item['itemid'],
						'time_from' => $startTime,
						'time_till' => $endTime,
						'history' => $item['value_type'],
						'output' => API_OUTPUT_EXTEND
					));

					$itemHistory = reset($itemHistory);

					$serviceName = $itemsAndServices[$item['itemid']];
					if (isset($itemHistory['value'])) {
						$data['services'][$serviceName]['parameters'][$item['itemid']]['slv'] = sprintf(
							'%.3f',
							$itemHistory['value']
						);
					}
				}
			}

			// get calculated items
			$calculatedItems = API::Item()->get(array(
				'output' => array('itemid', 'key_'),
				'filter' => array(
					'key_' => $usedMacro
				),
				'preservekeys' => true
			));

			$calculatedItemIds = array_keys($calculatedItems);

			// get old value
			foreach ($calculatedItemIds as $calculatedItemId) {
				$historyData = DBfetch(DBselect(
					'SELECT h.value, h.itemid'.
					' FROM history_uint h'.
					' WHERE h.itemid='.$calculatedItemId.
						' AND h.clock>='.$startTime.
						' AND h.clock<'.$endTime.
					' ORDER BY h.clock DESC',
					1
				));

				if ($historyData) {
					$itemKey = $calculatedItems[$calculatedItemId]['key_'];
					$mainItemId = $macroValue[$itemKey];
					$serviceName = $itemsAndServices[$mainItemId];
					$data['services'][$serviceName]['acceptable_sla'] = $historyData['value'];
				}
			}
		}
		else {
			show_error_message(_s('Application "%1$s" not exist on TLD', MONTHLY_REPORTS_APPLICATION));
		}
	}
}

$rsmView = new CView('rsm.monthlyreports.list', $data);
$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
