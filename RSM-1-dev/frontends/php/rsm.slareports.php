<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$page['title'] = _('SLA report');
$page['file'] = 'rsm.slareports.php';
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
		CProfile::update('web.rsm.slareports.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = array();
$data['tld'] = array();
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
if (array_key_exists('filter_set', $_REQUEST)) {
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
		'output' => array('hostid', 'host', 'name'),
		'tlds' => true,
		'selectMacros' => API_OUTPUT_EXTEND,
		'filter' => array(
			'name' => $data['filter_search']
		)
	));
	$data['tld'] = reset($tld);

	if ($data['tld']) {
		// Get TLD template
		$templates = API::Template()->get(array(
			'output' => array('templateid'),
			'filter' => array(
				'host' => 'Template '.$data['tld']['host']
			)
		));

		$template = reset($templates);

		$template_macros = API::UserMacro()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'hostids' => $template['templateid'],
			'filter' => array(
				'macro' => array(RSM_TLD_EPP_ENABLED, RSM_TLD_RDDS43_ENABLED, RSM_TLD_RDDS80_ENABLED,
					RSM_TLD_RDAP_ENABLED
				)
			)
		));

		$item_keys = array(RSM_SLV_DNS_DOWNTIME, RSM_SLV_DNS_TCP_RTT_PFAILED, RSM_SLV_DNS_UDP_RTT_PFAILED,
			RSM_SLV_DNS_UDP_UPD_PFAILED
		);

		$macro_keys = array(RSM_SLV_NS_AVAIL, RSM_SLV_DNS_TCP_RTT, RSM_DNS_TCP_RTT_LOW, RSM_SLV_DNS_UDP_RTT,
			RSM_DNS_UDP_RTT_LOW, RSM_SLV_DNS_NS_UPD, RSM_DNS_UPDATE_TIME, RSM_SLV_MACRO_DNS_AVAIL
		);

		$rdds = false;
		$epp = false;

		foreach ($template_macros as $template_macro) {
			if ($template_macro['value'] == 1 && ($template_macro['macro'] == RSM_TLD_RDDS43_ENABLED
					|| $template_macro['macro'] == RSM_TLD_RDDS80_ENABLED
					|| $template_macro['macro'] == RSM_TLD_RDAP_ENABLED)) {
				$rdds = true;
				$item_keys = array_merge($item_keys, array(RSM_SLV_RDDS_DOWNTIME, RSM_SLV_RDDS43_UPD_PFAILED));
				$macro_keys = array_merge($macro_keys, array(RSM_SLV_MACRO_RDDS_AVAIL, RSM_SLV_RDDS_UPD,
					RSM_RDDS_UPDATE_TIME
				));
			}
			elseif ($template_macro['macro'] == RSM_TLD_EPP_ENABLED && $template_macro['value'] == 1) {
				$epp = true;
				$item_keys = array_merge($item_keys, array(RSM_SLV_EPP_DOWNTIME, RSM_SLV_EPP_RTT_LOGIN_PFAILED,
					RSM_SLV_EPP_RTT_UPDATE_PFAILED, RSM_SLV_EPP_RTT_INFO_PFAILED
				));
				$macro_keys = array_merge($macro_keys, array(RSM_SLV_MACRO_EPP_AVAIL, RSM_SLV_EPP_LOGIN,
					RSM_EPP_LOGIN_RTT_LOW, RSM_SLV_EPP_INFO, RSM_EPP_INFO_RTT_LOW, RSM_SLV_EPP_UPDATE,
					RSM_EPP_UPDATE_RTT_LOW
				));
			}
		}

		// Remove key duplicates.
		$item_keys = array_unique($item_keys);
		$macro_keys = array_unique($macro_keys);

		// Get items.
		$items = API::Item()->get(array(
			'output' => array('itemid', 'name', 'key_', 'value_type'),
			'filter' => array(
				'key_' => $item_keys
			)
		));

		$items = zbx_toHash($items, 'key_');

		if (count($item_keys) != count($items)) {
			$missed_items = array();
			foreach ($item_keys as $item_key) {
				if (!array_key_exists($item_key, $items)) {
					$missed_items[] = $item_key;
				}
			}

			show_error_message(_s('Configuration error, cannot find items: "%1$s".', implode(', ', $missed_items)));
			require_once dirname(__FILE__).'/include/page_footer.php';
			exit;
		}

		$macros = API::UserMacro()->get(array(
			'globalmacro' => true,
			'output' => API_OUTPUT_EXTEND,
			'filter' => array(
				'macro' => $macro_keys
			)
		));

		$macros = zbx_toHash($macros, 'macro');

		if (count($macro_keys) != count($macros)) {
			$missed_macro = array();
			foreach ($macro_keys as $macro) {
				if (!array_key_exists($macro, $macros)) {
					$missed_macro[] = $macro;
				}
			}

			show_error_message(_s('Configuration error, cannot find macros: "%1$s".', implode(', ', $missed_macro)));
			require_once dirname(__FILE__).'/include/page_footer.php';
			exit;
		}

		foreach ($data['tld']['macros'] as $macro) {
			$macros[$macro['macro']] = array(
				'macro' => $macro['macro'],
				'value' => $macro['value']
			);
		}

		// Time limits.
		$start_time = mktime(
			0,
			0,
			0,
			$data['filter_month'],
			1,
			$data['filter_year']
		);

		if ($data['filter_month'] == 12) {
			$end_month = 1;
			$end_year = $data['filter_year'] + 1;
		}
		else {
			$end_month = $data['filter_month'] + 1;
			$end_year = $data['filter_year'];
		}

		$end_time = mktime(
			0,
			0,
			0,
			$end_month,
			1,
			$end_year
		);

		// DNS service availability.
		$item_hystory = DBfetch(DBselect(
			'SELECT count(h.itemid) AS cnt'.
			' FROM history_uint h'.
			' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_DNS_DOWNTIME]['itemid']).
				' AND h.value=0'.
				' AND h.clock>='.zbx_dbstr($start_time).
				' AND h.clock<'.zbx_dbstr($end_time)
		));

		$data['services'][] = array(
			'name' => 'DNS service availability',
			'main' => true,
			'details' => '-',
			'from' => '-',
			'to' => '-',
			'slv' => $item_hystory['cnt'].' '._('min'),
			'slr' => _s('%1$s minutes of downtime', round($macros[RSM_SLV_MACRO_DNS_AVAIL]['value'] / 60, 2)),
			'screen' => array(
				new CLink(_('Graph'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_DOWNTIME.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
				),
				SPACE,
				new CLink(_('Screen'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_DOWNTIME.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_SCREEN
				)
			)
		);

		// Get NS items.
		$ns_items = DBselect(
			'SELECT i.itemid,i.key_,i.value_type'.
			' FROM items i'.
			' WHERE i.hostid='.zbx_dbstr($data['tld']['hostid']).
				' AND i.key_ LIKE ('.zbx_dbstr(RSM_SLV_DNS_NS_DOWNTIME.'%').')'
		);

		// NS availability.
		while ($ns_item = DBfetch($ns_items)) {
			$item_hystories = DBselect(
				'SELECT h.clock'.
				' FROM history_uint h'.
				' WHERE h.itemid='.zbx_dbstr($ns_item['itemid']).
					' AND h.value=0'.
					' AND h.clock>='.zbx_dbstr($start_time).
					' AND h.clock<'.zbx_dbstr($end_time)
			);

			$item_values = array();
			while ($item_hystory = DBfetch($item_hystories)) {
				$item_values[] = array('clock' => $item_hystory['clock']);
			}

			if ($item_values) {
				CArrayHelper::sort($item_values, array('clock'));
				$from = date('Y-m-d H:i:s', $item_values[0]['clock']);
				$last_hystory_value = end($item_values);
				$to = date('Y-m-d H:i:s', $last_hystory_value['clock']);
			}
			else {
				$from = '-';
				$to = '-';
			}

			$item_key = new CItemKey($ns_item['key_']);
			$params = $item_key->getParameters();

			$data['services'][] = array(
				'name' => _('DNS name server availability'),
				'main' => false,
				'details' => $params[0],
				'from' => $from,
				'to' => $to,
				'slv' => count($item_values).' '._('min'),
				'slr' => _s('%1$s min of downtime', round($macros[RSM_SLV_NS_AVAIL]['value'] / 60, 2)),
				'screen' => array(
					new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.$ns_item['key_'].'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					),
					SPACE,
					new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.$ns_item['key_'].'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
					),
					SPACE,
					new CLink(_('Screen'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.$ns_item['key_'].'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_SCREEN
					)
				)
			);
		}

		// DNS TCP resolution RTT.
		$item_hystory = DBfetch(DBselect(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_DNS_TCP_RTT_PFAILED]['itemid']).
				' AND h.value=0'.
				' AND h.clock>='.zbx_dbstr($start_time).
				' AND h.clock<'.zbx_dbstr($end_time).
			' ORDER BY h.clock DESC'.
			' LIMIT 1'
		));

		if ($item_hystory['value'] === null) {
			$item_hystory['value'] = '-';
		}

		$slv = $item_hystory['value'].'% '._('queries').' > '.$macros[RSM_DNS_TCP_RTT_LOW]['value']._('ms');
		$data['services'][] = array(
			'name' => 'DNS TCP resolution RTT',
			'main' => false,
			'details' => '-',
			'from' => '-',
			'to' => '-',
			'slv' => $slv,
			'slr' => _s('<=%1$s ms, for at least %2$s%% of the queries', $macros[RSM_DNS_TCP_RTT_LOW]['value'],
				$macros[RSM_SLV_DNS_TCP_RTT]['value']
			),
			'screen' => array(
				new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_TCP_RTT_PFAILED.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
				),
				SPACE,
				new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_TCP_RTT_PFAILED.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
				),
				SPACE,
				new CLink(_('Screen'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_TCP_RTT_PFAILED.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_SCREEN
				)
			)
		);

		// UDP DNS resolution RTT.
		$item_hystory = DBfetch(DBselect(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_DNS_UDP_RTT_PFAILED]['itemid']).
				' AND h.value=0'.
				' AND h.clock>='.zbx_dbstr($start_time).
				' AND h.clock<'.zbx_dbstr($end_time).
			' ORDER BY h.clock DESC'.
			' LIMIT 1'
		));

		if ($item_hystory['value'] === null) {
			$item_hystory['value'] = '-';
		}

		$slv = $item_hystory['value'].'% '._('queries').' > '.$macros[RSM_DNS_UDP_RTT_LOW]['value']._('ms');
		$data['services'][] = array(
			'name' => 'DNS UDP resolution RTT',
			'main' => false,
			'details' => '-',
			'from' => '-',
			'to' => '-',
			'slv' => $slv,
			'slr' => _s('<=%1$s ms, for at least %2$s%% of the queries', $macros[RSM_DNS_UDP_RTT_LOW]['value'],
				$macros[RSM_SLV_DNS_UDP_RTT]['value']
			),
			'screen' => array(
				new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_UDP_RTT_PFAILED.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
				),
				SPACE,
				new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_UDP_RTT_PFAILED.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
				)
			)
		);

		// DNS update time.
		$item_hystory = DBfetch(DBselect(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_DNS_UDP_UPD_PFAILED]['itemid']).
				' AND h.value=0'.
				' AND h.clock>='.zbx_dbstr($start_time).
				' AND h.clock<'.zbx_dbstr($end_time).
			' ORDER BY h.clock DESC'.
			' LIMIT 1'
		));

		if ($item_hystory['value'] === null) {
			$item_hystory['value'] = '-';
		}

		$slv = $item_hystory['value'].'% > '.$macros[RSM_DNS_UPDATE_TIME]['value']._('ms');
		$data['services'][] = array(
			'name' => 'DNS update time',
			'main' => false,
			'details' => '-',
			'from' => '-',
			'to' => '-',
			'slv' => $slv,
			'slr' => _s('<=%1$s ms, for at least %2$s%% probes', $macros[RSM_DNS_UPDATE_TIME]['value'],
				$macros[RSM_SLV_DNS_NS_UPD]['value']
			),
			'screen' => array(
				new CLink(_('Graph'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
					'&item_key='.RSM_SLV_DNS_UDP_UPD_PFAILED.'&filter_year='.$data['filter_year'].
					'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
				)
			)
		);

		// RDDS availability.
		if ($rdds) {
			$item_hystory = DBfetch(DBselect(
				'SELECT count(h.itemid) AS cnt'.
				' FROM history_uint h'.
				' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_RDDS_DOWNTIME]['itemid']).
					' AND h.value=0'.
					' AND h.clock>='.zbx_dbstr($start_time).
					' AND h.clock<'.zbx_dbstr($end_time)
			));

			$data['services'][] = array(
				'name' => 'RDDS service availability',
				'main' => true,
				'details' => '-',
				'from' => '-',
				'to' => '-',
				'slv' => $item_hystory['cnt'].' '._('min'),
				'slr' => _s('%1$s min of downtime', round($macros[RSM_SLV_MACRO_RDDS_AVAIL]['value'] / 60, 2)),
				'screen' => array(
					new CLink(_('Graph'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_RDDS_DOWNTIME.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					)
				)
			);

			// RDDS Query RTT (combined from 43 and 80 below).
			$data['services'][] = array(
				'name' => 'RDDS Query RTT (combined from 43 and 80 below)',
				'main' => false,
				'details' => '-',
				'from' => '-',
				'to' => '-',
				'slv' => '-',
				'slr' => _s('<=%1$s ms, for at least %2$s%% of the queries', 1, 2),
				'screen' => array(
					new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_RDDS_RTT.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					),
					SPACE,
					new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_RDDS_RTT.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
					)
				)
			);

			// If RDDS and EPP is avail
			if ($epp) {
				// RDDS update time.
				$item_hystory = DBfetch(DBselect(
					'SELECT h.value'.
					' FROM history_uint h'.
					' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_RDDS43_UPD_PFAILED]['itemid']).
						' AND h.value=0'.
						' AND h.clock>='.zbx_dbstr($start_time).
						' AND h.clock<'.zbx_dbstr($end_time).
					' ORDER BY h.clock DESC'.
					' LIMIT 1'
				));

				if ($item_hystory['value'] === null) {
					$item_hystory['value'] = '-';
				}

				$slv = $item_hystory['value'].'% > '.$macros[RSM_RDDS_UPDATE_TIME]['value']._('ms');
				$data['services'][] = array(
					'name' => 'RDDS update time',
					'main' => false,
					'details' => '-',
					'from' => '-',
					'to' => '-',
					'slv' => $slv,
					'slr' => _s('<=%1$s ms, for at least %2$s%% probes', $macros[RSM_RDDS_UPDATE_TIME]['value'],
						$macros[RSM_SLV_RDDS_UPD]['value']
					),
					'screen' => array(
						new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
							'&item_key='.RSM_SLV_RDDS43_UPD_PFAILED.'&filter_year='.$data['filter_year'].
							'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
						),
						SPACE,
						new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
							'&item_key='.RSM_SLV_RDDS43_UPD_PFAILED.'&filter_year='.$data['filter_year'].
							'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
						)
					)
				);
			}
		}

		// EPP service availability.
		if ($epp) {
			$item_hystory = DBfetch(DBselect(
				'SELECT count(h.itemid) AS cnt'.
				' FROM history_uint h'.
				' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_EPP_DOWNTIME]['itemid']).
					' AND h.value=0'.
					' AND h.clock>='.zbx_dbstr($start_time).
					' AND h.clock<'.zbx_dbstr($end_time)
			));

			$data['services'][] = array(
				'name' => 'EPP service availability',
				'main' => true,
				'details' => '-',
				'from' => '-',
				'to' => '-',
				'slv' => $item_hystory['cnt'].' '._('min'),
				'slr' => _s('%1$s min of downtime', round($macros[RSM_SLV_MACRO_EPP_AVAIL]['value'] / 60, 2)),
				'screen' => array(
					new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_DOWNTIME.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					),
					SPACE,
					new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_DOWNTIME.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
					)
				)
			);

			// EPP session-command RTT.
			$item_hystory = DBfetch(DBselect(
				'SELECT h.value'.
				' FROM history_uint h'.
				' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_EPP_RTT_LOGIN_PFAILED]['itemid']).
					' AND h.value=0'.
					' AND h.clock>='.zbx_dbstr($start_time).
					' AND h.clock<'.zbx_dbstr($end_time).
				' ORDER BY h.clock DESC'.
				' LIMIT 1'
			));

			if ($item_hystory['value'] === null) {
				$item_hystory['value'] = '-';
			}

			$slv = $item_hystory['value'].'% '._('queries').' > '.$macros[RSM_EPP_INFO_RTT_LOW]['value']._('ms');
			$data['services'][] = array(
				'name' => 'EPP session-command RTT',
				'main' => false,
				'details' => '-',
				'from' => '-',
				'to' => '-',
				'slv' => $slv,
				'slr' => _s('<=%1$s ms, for at least %2$s%% of the commands', $macros[RSM_EPP_INFO_RTT_LOW]['value'],
					$macros[RSM_SLV_EPP_INFO]['value']
				),
				'screen' => array(
					new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_RTT_LOGIN_PFAILED.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					),
					SPACE,
					new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_RTT_LOGIN_PFAILED.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
					)
				)
			);

			// EPP query-command RTT.
			$item_hystory = DBfetch(DBselect(
				'SELECT h.value'.
				' FROM history_uint h'.
				' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_EPP_RTT_INFO_PFAILED]['itemid']).
					' AND h.value=0'.
					' AND h.clock>='.zbx_dbstr($start_time).
					' AND h.clock<'.zbx_dbstr($end_time).
				' ORDER BY h.clock DESC'.
				' LIMIT 1'
			));

			if ($item_hystory['value'] === null) {
				$item_hystory['value'] = '-';
			}

			$slv = $item_hystory['value'].'% '._('queries').' > '.$macros[RSM_EPP_INFO_RTT_LOW]['value']._('ms');
			$data['services'][] = array(
				'name' => 'EPP query-command RTT',
				'main' => false,
				'details' => '-',
				'from' => '-',
				'to' => '-',
				'slv' => $slv,
				'slr' => _s('<=%1$s ms, for at least %2$s%% of the commands', $macros[RSM_EPP_INFO_RTT_LOW]['value'],
					$macros[RSM_SLV_EPP_INFO]['value']
				),
				'screen' => array(
					new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_RTT_INFO_PFAILED.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					),
					SPACE,
					new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_RTT_INFO_PFAILED.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
					)
				)
			);

			// EPP transform-command RTT.
			$item_hystory = DBfetch(DBselect(
				'SELECT h.value'.
				' FROM history_uint h'.
				' WHERE h.itemid='.zbx_dbstr($items[RSM_SLV_EPP_RTT_UPDATE_PFAILED]['itemid']).
					' AND h.value=0'.
					' AND h.clock>='.zbx_dbstr($start_time).
					' AND h.clock<'.zbx_dbstr($end_time).
				' ORDER BY h.clock DESC'.
				' LIMIT 1'
			));

			if ($item_hystory['value'] === null) {
				$item_hystory['value'] = '-';
			}

			$slv = $item_hystory['value'].'% '._('queries').' > '.$macros[RSM_EPP_UPDATE_RTT_LOW]['value']._('ms');
			$data['services'][] = array(
				'name' => 'EPP transform-command RTT',
				'main' => false,
				'details' => '-',
				'from' => '-',
				'to' => '-',
				'slv' => $slv,
				'slr' => _s('<=%1$s ms, for at least %2$s%% of the commands', $macros[RSM_EPP_UPDATE_RTT_LOW]['value'],
					$macros[RSM_SLV_EPP_UPDATE]['value']
				),
				'screen' => array(
					new CLink(_('Graph 1'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_RTT_UPDATE_PFAILED.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_1
					),
					SPACE,
					new CLink(_('Graph 2'), 'rsm.screens.php?filter_set=1&tld='.$data['filter_search'].
						'&item_key='.RSM_SLV_EPP_RTT_UPDATE_PFAILED.'&filter_year='.$data['filter_year'].
						'&filter_month='.$data['filter_month'].'&type='.RSM_SLA_SCREEN_TYPE_GRAPH_2
					)
				)
			);
		}
	}
}

$rsmView = new CView('rsm.slareports.list', $data);
$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
