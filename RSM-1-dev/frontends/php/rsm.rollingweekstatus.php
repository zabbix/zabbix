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
require_once dirname(__FILE__).'/include/rollingweekstatus.inc.php';

$page['title'] = _('TLD Rolling week status');
$page['file'] = 'rsm.rollingweekstatus.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if (PAGE_TYPE_HTML == $page['type']) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	// filter
	'filter_set' =>				array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'filter_search' =>			array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_dns' =>				array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_dnssec' =>			array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_rdds' =>			array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_epp' =>				array(T_ZBX_INT, O_OPT,  null,	IN('0,1'),	null),
	'filter_slv' =>				array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_status' =>			array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_gtld_group' =>		array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_cctld_group' =>		array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_othertld_group' =>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_test_group' =>		array(T_ZBX_INT, O_OPT,  null,	null,		null),
	// ajax
	'favobj' =>					array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>					array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>				array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);

check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.rollingweekstatus.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = array();

/*
 * Filter
 */
if (isset($_REQUEST['filter_set'])) {
	$data['filter_search'] = get_request('filter_search');
	$data['filter_dns'] = get_request('filter_dns');
	$data['filter_dnssec'] = get_request('filter_dnssec');
	$data['filter_rdds'] = get_request('filter_rdds');
	$data['filter_epp'] = get_request('filter_epp');
	$data['filter_slv'] = get_request('filter_slv', 0);
	$data['filter_status'] = get_request('filter_status');
	$data['filter_gtld_group'] = get_request('filter_gtld_group');
	$data['filter_cctld_group'] = get_request('filter_cctld_group');
	$data['filter_othertld_group'] = get_request('filter_othertld_group');
	$data['filter_test_group'] = get_request('filter_test_group');

	CProfile::update('web.rsm.rollingweekstatus.filter_search', get_request('filter_search'), PROFILE_TYPE_STR);
	CProfile::update('web.rsm.rollingweekstatus.filter_dns', get_request('filter_dns', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_dnssec', get_request('filter_dnssec', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_rdds', get_request('filter_rdds', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_epp', get_request('filter_epp', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_slv', get_request('filter_slv', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_status', get_request('filter_status', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_gtld_group', get_request('filter_gtld_group', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_cctld_group', get_request('filter_cctld_group', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_othertld_group', get_request('filter_othertld_group', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_test_group', get_request('filter_test_group', 0), PROFILE_TYPE_INT);
}
else {
	$data['filter_search'] = CProfile::get('web.rsm.rollingweekstatus.filter_search');
	$data['filter_dns'] = CProfile::get('web.rsm.rollingweekstatus.filter_dns');
	$data['filter_dnssec'] = CProfile::get('web.rsm.rollingweekstatus.filter_dnssec');
	$data['filter_rdds'] = CProfile::get('web.rsm.rollingweekstatus.filter_rdds');
	$data['filter_epp'] = CProfile::get('web.rsm.rollingweekstatus.filter_epp');
	$data['filter_slv'] = CProfile::get('web.rsm.rollingweekstatus.filter_slv');
	$data['filter_status'] = CProfile::get('web.rsm.rollingweekstatus.filter_status');
	$data['filter_gtld_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_gtld_group');
	$data['filter_cctld_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_cctld_group');
	$data['filter_othertld_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_othertld_group');
	$data['filter_test_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_test_group');
}

$macro = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array(
		'macro' => array(RSM_PAGE_SLV, RSM_ROLLWEEK_SECONDS)
	)
));

foreach ($macro as $macros) {
	if ($macros['macro'] === RSM_PAGE_SLV) {
		$data['slv'] = $macros['value'];
	}
	else {
		$data['rollWeekSeconds'] = $macros['value'];
	}
}

if (!isset($data['slv'])) {
	show_error_message(_s('Macro "%1$s" doesn\'t not exist.', RSM_PAGE_SLV));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}
if (!isset($data['rollWeekSeconds'])) {
	show_error_message(_s('Macro "%1$s" doesn\'t not exist.', RSM_ROLLWEEK_SECONDS));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

// get "TLDs" groupId
$tldGroups = API::HostGroup()->get(array(
	'output' => array('groupid', 'name'),
	'filter' => array(
		'name' => array(RSM_TLDS_GROUP, RSM_CC_TLD_GROUP, RSM_G_TLD_GROUP, RSM_OTHER_TLD_GROUP, RSM_TEST_GROUP)
	)
));

$selectedGroups = array();
$data['allowedGroups'] = array(
	RSM_CC_TLD_GROUP => false,
	RSM_G_TLD_GROUP => false,
	RSM_OTHER_TLD_GROUP => false,
	RSM_TEST_GROUP => false
);

foreach ($tldGroups as $tldGroup) {
	switch ($tldGroup['name']) {
		case RSM_TLDS_GROUP:
			$selectedGroups[$tldGroup['groupid']] = $tldGroup['groupid'];
			break;
		case RSM_CC_TLD_GROUP:
			$data['allowedGroups'][RSM_CC_TLD_GROUP] = true;
			break;
		case RSM_G_TLD_GROUP:
			$data['allowedGroups'][RSM_G_TLD_GROUP] = true;
			break;
		case RSM_OTHER_TLD_GROUP:
			$data['allowedGroups'][RSM_OTHER_TLD_GROUP] = true;
			break;
		case RSM_TEST_GROUP:
			$data['allowedGroups'][RSM_TEST_GROUP] = true;
			break;
	}
}

if (!$selectedGroups) {
	show_error_message(_s('No permissions to referred "%1$s" group or it doesn\'t not exist.', RSM_TLDS_GROUP));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

if ($data['filter_search']) {
	$whereCondition[] = 'h.name LIKE ('.zbx_dbstr('%'.$data['filter_search'].'%').')';
}

$notEmptyResult = true;

// service type filter
if ($data['filter_slv'] !== ''
		&& ($data['filter_dns'] || $data['filter_dnssec'] || $data['filter_rdds']
			|| $data['filter_epp'])) {
	$slvValues = explode(',', $data['slv']);
	if ($data['filter_slv'] == SLA_MONITORING_SLV_FILTER_NON_ZERO || in_array($data['filter_slv'], $slvValues)) {
		$itemCount = 0;

		if ($data['filter_dns']) {
			$items['key'][] = RSM_SLV_DNS_ROLLWEEK;
			$itemCount++;
		}
		if ($data['filter_dnssec']) {
			$items['key'][] = RSM_SLV_DNSSEC_ROLLWEEK;
			$itemCount++;
		}
		if ($data['filter_rdds']) {
			$items['key'][] = RSM_SLV_RDDS_ROLLWEEK;
			$itemCount++;
		}
		if ($data['filter_epp']) {
			$items['key'][] = RSM_SLV_EPP_ROLLWEEK;
			$itemCount++;
		}

		if ($data['filter_slv'] == SLA_MONITORING_SLV_FILTER_NON_ZERO) {
			$filterSlvCondition = '>0';
		}
		else {
			$filterSlvCondition = '>='.$data['filter_slv'];
		}

		$itemsHostids = DBselect(
			'SELECT DISTINCT i.hostid'.
			' FROM items i'.
			' WHERE i.lastvalue'.$filterSlvCondition.
				' AND '.dbConditionString('i.key_', $items['key'])
		);

		$hostIds = array();
		while ($hostId = DBfetch($itemsHostids)) {
			$hostIds[] = $hostId['hostid'];
		}

		if ($hostIds) {
			$whereCondition[] = dbConditionInt('h.hostid', $hostIds);
		}
		else {
			$notEmptyResult = false;
		}
	}
	else {
		show_error_message(_('Not allowed value for "Exceeding or equal to" field'));
	}
}
if ($notEmptyResult) {
	// tld type filter
	if ($data['filter_cctld_group'] || $data['filter_gtld_group'] || $data['filter_othertld_group']
			|| $data['filter_test_group']) {
		$groupNames = array();

		if ($data['filter_cctld_group']) {
			$groupNames[] = RSM_CC_TLD_GROUP;
		}
		if ($data['filter_gtld_group']) {
			$groupNames[] = RSM_G_TLD_GROUP;
		}
		if ($data['filter_othertld_group']) {
			$groupNames[] = RSM_OTHER_TLD_GROUP;
		}
		if ($data['filter_test_group']) {
			$groupNames[] = RSM_TEST_GROUP;
		}

		$getGroups = DBselect(
			'SELECT g.groupid'.
			' FROM groups g'.
			' WHERE '.dbConditionString('g.name', $groupNames)
		);

		if ($getGroups) {
			while ($getGroup = DBfetch($getGroups)) {
				$selectedGroups[$getGroup['groupid']] = $getGroup['groupid'];
			}
		}
	}

	$hostIds = array();

	// get TLDs
	$whereCondition[] = dbConditionInt('hg.groupid', $selectedGroups);
	$whereCondition[] = 'hg.hostid=h.hostid';

	if (CUser::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
		$userid = CWebUser::$data['userid'];
		$userGroups = getUserGroupsByUserId($userid);
		$whereCondition[] = 'EXISTS ('.
			'SELECT NULL'.
			' FROM hosts_groups hgg'.
				' JOIN rights r'.
					' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', $userGroups).
			' WHERE h.hostid=hgg.hostid'.
			' GROUP BY hgg.hostid'.
			' HAVING MIN(r.permission)>='.PERM_READ_ONLY.
		')';
	}

	$where = ' WHERE '.implode(' AND ', $whereCondition);
	$hostCount = (count($selectedGroups) >= 2) ? 2 : 1;

	$dbTlds = DBselect(
		'SELECT h.hostid,h.host,h.name'.
		' FROM hosts h,hosts_groups hg'.
		$where.
		' GROUP BY h.hostid'.
		' HAVING COUNT(h.hostid)>='.$hostCount
	);

	if ($dbTlds) {
		while ($dbTld = DBfetch($dbTlds)) {
			$hostIds[] = $dbTld['hostid'];
			$tlds[$dbTld['hostid']] = array(
				'hostid' => $dbTld['hostid'],
				'host' => $dbTld['host'],
				'name' => $dbTld['name']
			);
		}

		$hostGroups = API::HostGroup()->get(array(
			'output' => array('groupid', 'name'),
			'hostids' => $hostIds
		));

		foreach ($hostGroups as $hostGroup) {
			foreach ($hostGroup['hosts'] as $hostsArray) {
				$tlds[$hostsArray['hostid']]['groups'][] = array(
					'name' => $hostGroup['name']
				);
			}
		}
	}
}

$data['tld'] = array();

if ($hostIds) {
	$sortField = getPageSortField('name');
	$sortOrder = getPageSortOrder();

	// get items
	$items = API::Item()->get(array(
		'hostids' => $hostIds,
		'filter' => array(
			'key_' => array(
				RSM_SLV_DNS_ROLLWEEK, RSM_SLV_DNSSEC_ROLLWEEK, RSM_SLV_RDDS_ROLLWEEK,
				RSM_SLV_EPP_ROLLWEEK, RSM_SLV_DNS_AVAIL, RSM_SLV_DNSSEC_AVAIL,
				RSM_SLV_RDDS_AVAIL, RSM_SLV_EPP_AVAIL
			)
		),
		'output' => array('itemid', 'hostid', 'key_', 'lastvalue'),
		'preservekeys' => true
	));

	if ($items) {
		if ($sortField !== 'name') {
			$sortItems = array();
			foreach ($items as $item) {
				if (($item['key_'] == RSM_SLV_DNS_ROLLWEEK && $sortField == 'dns')
						|| ($item['key_'] == RSM_SLV_DNSSEC_ROLLWEEK && $sortField == 'dnssec')
						|| ($item['key_'] == RSM_SLV_RDDS_ROLLWEEK && $sortField == 'rdds')
						|| ($item['key_'] == RSM_SLV_EPP_ROLLWEEK && $sortField == 'epp')) {
					$sortItems[$item['itemid']]['lastvalue'] = $item['lastvalue'];
					$sortItems[$item['itemid']]['hostid'] = $item['hostid'];
					$itemIds[$item['itemid']] = true;
				}
			}

			// sorting
			CArrayHelper::sort($sortItems, array(array('field' => 'lastvalue', 'order' => $sortOrder)));

			foreach ($sortItems as $itemId => $sortItem) {
				$data['tld'][$sortItem['hostid']][convertSlaServiceName($sortField)]['itemid'] = $itemId;
				$data['tld'][$sortItem['hostid']][convertSlaServiceName($sortField)]['lastvalue'] = sprintf(
					'%.3f',
					$sortItem['lastvalue']
				);
				$data['tld'][$sortItem['hostid']][convertSlaServiceName($sortField)]['trigger'] = false;
			}
		}

		foreach ($items as $item) {
			if ($item['key_'] == RSM_SLV_DNS_ROLLWEEK && $sortField != 'dns') {
				$data['tld'][$item['hostid']][RSM_DNS]['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']][RSM_DNS]['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']][RSM_DNS]['trigger'] = false;
			}
			elseif ($item['key_'] == RSM_SLV_DNSSEC_ROLLWEEK && $sortField != 'dnssec') {
				$data['tld'][$item['hostid']][RSM_DNSSEC]['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']][RSM_DNSSEC]['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']][RSM_DNSSEC]['trigger'] = false;
			}
			elseif ($item['key_'] == RSM_SLV_RDDS_ROLLWEEK && $sortField != 'rdds') {
				$data['tld'][$item['hostid']][RSM_RDDS]['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']][RSM_RDDS]['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']][RSM_RDDS]['trigger'] = false;
			}
			elseif ($item['key_'] == RSM_SLV_EPP_ROLLWEEK && $sortField != 'epp') {
				$data['tld'][$item['hostid']][RSM_EPP]['itemid'] = $item['itemid'];
				$data['tld'][$item['hostid']][RSM_EPP]['lastvalue'] = sprintf(
					'%.3f',
					$item['lastvalue']
				);
				$data['tld'][$item['hostid']][RSM_EPP]['trigger'] = false;
			}
			elseif ($item['key_'] == RSM_SLV_DNS_AVAIL) {
				$data['tld'][$item['hostid']][RSM_DNS]['availItemId'] = $item['itemid'];
				$itemIds[$item['itemid']] = true;
			}
			elseif ($item['key_'] == RSM_SLV_DNSSEC_AVAIL) {
				$data['tld'][$item['hostid']][RSM_DNSSEC]['availItemId'] = $item['itemid'];
				$itemIds[$item['itemid']] = true;
			}
			elseif ($item['key_'] == RSM_SLV_RDDS_AVAIL) {
				$data['tld'][$item['hostid']][RSM_RDDS]['availItemId'] = $item['itemid'];
				$itemIds[$item['itemid']] = true;
			}
			elseif ($item['key_'] == RSM_SLV_EPP_AVAIL) {
				$data['tld'][$item['hostid']][RSM_EPP]['availItemId'] = $item['itemid'];
				$itemIds[$item['itemid']] = true;
			}
		}

		// disabled services check
		$templateName = array();
		foreach ($tlds as $tld) {
			$templateName[$tld['hostid']] = 'Template '.$tld['host'];
			$hostIdByTemplateName['Template '.$tld['host']] = $tld['hostid'];
		}

		$templates = API::Template()->get(array(
			'output' => array('templateid', 'host'),
			'filter' => array(
				'host' => $templateName
			),
			'preservekeys' => true
		));

		$templateIds = array_keys($templates);

		foreach ($templates as $template) {
			$templateName[$template['host']] = $template['templateid'];
		}

		$templateMacros = API::UserMacro()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'hostids' => $templateIds,
			'filter' => array(
				'macro' => array(RSM_TLD_DNSSEC_ENABLED, RSM_TLD_EPP_ENABLED, RSM_TLD_RDDS43_ENABLED,
					RSM_TLD_RDDS80_ENABLED, RSM_TLD_RDAP_ENABLED
				)
			)
		));

		foreach ($templateMacros as $templateMacro) {
			$current_hostid = $hostIdByTemplateName[$templates[$templateMacro['hostid']]['host']];
			if ($templateMacro['macro'] == RSM_TLD_DNSSEC_ENABLED || $templateMacro['macro'] == RSM_TLD_EPP_ENABLED) {
				if ($templateMacro['value'] == 0) {
					if ($templateMacro['macro'] == RSM_TLD_DNSSEC_ENABLED) {
						$service_type = RSM_DNSSEC;
					}
					else {
						$service_type = RSM_EPP;
					}

					// Unset disabled services
					if (isset($data['tld'][$current_hostid][$service_type])) {
						unset($itemIds[$data['tld'][$current_hostid][$service_type]['availItemId']]);
						unset($data['tld'][$current_hostid][$service_type]);
					}
				}
			}
			else {
				if (array_key_exists(RSM_RDDS, $data['tld'][$current_hostid])) {
					$data['tld'][$current_hostid][RSM_RDDS]['subservices'][$templateMacro['macro']] = $templateMacro['value'];
				}
			}
		}

		foreach ($data['tld'] as $key => $tld) {
			if (array_key_exists(RSM_RDDS, $tld)) {
				if (!array_key_exists('subservices', $tld[RSM_RDDS]) || !array_sum($tld[RSM_RDDS]['subservices'])) {
					unset($itemIds[$data['tld'][$key][RSM_RDDS]['availItemId']]);
					unset($data['tld'][$key][RSM_RDDS]);
				}
			}
		}

		foreach ($tlds as $tld) {
			$data['tld'][$tld['hostid']]['hostid'] = $tld['hostid'];
			$data['tld'][$tld['hostid']]['host'] = $tld['host'];
			$data['tld'][$tld['hostid']]['name'] = $tld['name'];
			$data['tld'][$tld['hostid']]['type'] = '';

			foreach ($tld['groups'] as $tldGroup) {
				if ($tldGroup['name'] === RSM_CC_TLD_GROUP) {
					$data['tld'][$tld['hostid']]['type'] = RSM_CC_TLD_GROUP;
				}
				elseif ($tldGroup['name'] === RSM_G_TLD_GROUP) {
					$data['tld'][$tld['hostid']]['type'] = RSM_G_TLD_GROUP;
				}
				elseif ($tldGroup['name'] === RSM_OTHER_TLD_GROUP) {
					$data['tld'][$tld['hostid']]['type'] = RSM_OTHER_TLD_GROUP;
				}
				elseif ($tldGroup['name'] === RSM_TEST_GROUP) {
					$data['tld'][$tld['hostid']]['type'] = RSM_TEST_GROUP;
				}
			}
		}

		// get triggers
		$triggers = API::Trigger()->get(array(
			'output' => array('triggerid', 'value'),
			'itemids' => array_keys($itemIds)
		));

		foreach ($triggers as $trigger) {
			if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
				$trItem = $trigger['itemid'];
				$problem = array();
				switch ($items[$trItem]['key_']) {
					case RSM_SLV_DNS_AVAIL:
						$data['tld'][$items[$trItem]['hostid']][RSM_DNS]['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						if ($data['tld'][$items[$trItem]['hostid']][RSM_DNS]['incident']) {
							$data['tld'][$items[$trItem]['hostid']][RSM_DNS]['trigger'] = true;
						}
						break;
					case RSM_SLV_DNSSEC_AVAIL:
						$data['tld'][$items[$trItem]['hostid']][RSM_DNSSEC]['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						if ($data['tld'][$items[$trItem]['hostid']][RSM_DNSSEC]['incident']) {
							$data['tld'][$items[$trItem]['hostid']][RSM_DNSSEC]['trigger'] = true;
						}
						break;
					case RSM_SLV_RDDS_AVAIL:
						$data['tld'][$items[$trItem]['hostid']][RSM_RDDS]['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						if ($data['tld'][$items[$trItem]['hostid']][RSM_RDDS]['incident']) {
							$data['tld'][$items[$trItem]['hostid']][RSM_RDDS]['trigger'] = true;
						}
						break;
					case RSM_SLV_EPP_AVAIL:
						$data['tld'][$items[$trItem]['hostid']][RSM_EPP]['incident'] = getLastEvent(
							$trigger['triggerid']
						);
						if ($data['tld'][$items[$trItem]['hostid']][RSM_EPP]['incident']) {
							$data['tld'][$items[$trItem]['hostid']][RSM_EPP]['trigger'] = true;
						}
						break;
				}
			}
		}

		// services status filter
		if ($data['filter_status'] == 1) {
			foreach ($data['tld'] as $tld) {
				if ((!isset($tld[RSM_DNS]) || !$tld[RSM_DNS]['trigger'])
						&& (!isset($tld[RSM_DNSSEC]) || !$tld[RSM_DNSSEC]['trigger'])
						&& (!isset($tld[RSM_RDDS]) || !$tld[RSM_RDDS]['trigger'])
						&& (!isset($tld[RSM_EPP]) || !$tld[RSM_EPP]['trigger'])) {
					unset($data['tld'][$tld['hostid']]);
				}
			}
		}
		elseif ($data['filter_status'] == 2) {
			foreach ($data['tld'] as $tld) {
				if (isset($tld[RSM_DNS]) && isset($tld[RSM_DNSSEC]) && isset($tld[RSM_RDDS]) && isset($tld[RSM_EPP])) {
					unset($data['tld'][$tld['hostid']]);
				}
			}
		}

		if ($data['filter_slv'] > 0
				&& ($data['filter_dns'] || $data['filter_dnssec'] || $data['filter_rdds']
					|| $data['filter_epp'])) {
			foreach ($data['tld'] as $tld) {
				if ((!$data['filter_dns'] || (!isset($tld[RSM_DNS]) || $tld[RSM_DNS]['lastvalue'] < $data['filter_slv']))
						&& (!$data['filter_dnssec'] || (!isset($tld[RSM_DNSSEC]) || $tld[RSM_DNSSEC]['lastvalue'] < $data['filter_slv']))
						&& (!$data['filter_rdds'] || (!isset($tld[RSM_RDDS]) || $tld[RSM_RDDS]['lastvalue'] < $data['filter_slv']))
						&& (!$data['filter_epp'] || (!isset($tld[RSM_EPP]) || $tld[RSM_EPP]['lastvalue'] < $data['filter_slv']))) {
					unset($data['tld'][$tld['hostid']]);
				}
			}
		}
	}

	if ($sortField === 'name') {
		order_result($data['tld'], 'name', $sortOrder);
	}
	elseif ($sortField === 'type') {
		order_result($data['tld'], 'type', $sortOrder);
	}
}

$data['paging'] = getPagingLine($data['tld']);

$rsmView = new CView('rsm.rollingweekstatus.list', $data);
$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
