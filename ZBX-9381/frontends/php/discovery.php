<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
require_once dirname(__FILE__).'/include/discovery.inc.php';

$page['title'] = _('Status of discovery');
$page['file'] = 'discovery.php';
$page['hist_arg'] = array('druleid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'druleid' =>	array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'),	null)
);
check_fields($fields);
validate_sort_and_sortorder('ip', ZBX_SORT_UP);

// check discovery for existing if defined druleid
if ($druleid = get_request('druleid')) {
	$dbDRule = API::DRule()->get(array(
			'druleids' => $druleid,
			'countOutput' => true
	));
	if (!$dbDRule) {
		access_deny();
	}
}

/*
 * Display
 */
$data = array(
	'fullscreen' => $_REQUEST['fullscreen'],
	'druleid' => get_request('druleid', 0),
	'sort' => get_request('sort'),
	'sortorder' => get_request('sortorder'),
	'services' => array(),
	'drules' => array()
);

$data['pageFilter'] = new CPageFilter(array(
	'drules' => array('filter' => array('status' => DRULE_STATUS_ACTIVE)),
	'druleid' => get_request('druleid', null)
));

if ($data['pageFilter']->drulesSelected) {

	// discovery rules
	$options = array(
		'filter' => array('status' => DRULE_STATUS_ACTIVE),
		'selectDHosts' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND
	);

	if ($data['pageFilter']->druleid > 0) {
		$options['druleids'] = $data['pageFilter']->druleid; // set selected discovery rule id
	}

	$data['drules'] = API::DRule()->get($options);
	if (!empty($data['drules'])) {
		order_result($data['drules'], 'name');
	}

	// discovery services
	$options = array(
		'selectHosts' => array('hostid', 'name', 'status'),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => getPageSortField('ip'),
		'sortorder' => getPageSortOrder(),
		'limitSelects' => 1
	);
	if (!empty($data['druleid'])) {
		$options['druleids'] = $data['druleid'];
	}
	else {
		$options['druleids'] = zbx_objectValues($data['drules'], 'druleid');
	}
	$dservices = API::DService()->get($options);

	// user macros
	$data['macros'] = API::UserMacro()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'globalmacro' => true
	));
	$data['macros'] = zbx_toHash($data['macros'], 'macro');

	// services
	$data['services'] = array();
	foreach ($dservices as $dservice) {
		$key_ = $dservice['key_'];
		if (!zbx_empty($key_)) {
			if (isset($data['macros'][$key_])) {
				$key_ = $data['macros'][$key_]['value'];
			}
			$key_ = ': '.$key_;
		}
		$serviceName = discovery_check_type2str($dservice['type']).discovery_port2str($dservice['type'], $dservice['port']).$key_;
		$data['services'][$serviceName] = 1;
	}
	ksort($data['services']);

	// discovery services to hash
	$data['dservices'] = zbx_toHash($dservices, 'dserviceid');

	// discovery hosts
	$data['dhosts'] = API::DHost()->get(array(
		'druleids' => zbx_objectValues($data['drules'], 'druleid'),
		'selectDServices' => API_OUTPUT_REFER,
		'output' => API_OUTPUT_REFER
	));
	$data['dhosts'] = zbx_toHash($data['dhosts'], 'dhostid');
}

// render view
$discoveryView = new CView('monitoring.discovery', $data);
$discoveryView->render();
$discoveryView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
?>
