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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of host prototypes');
$page['file'] = 'host_prototypes.php';
$page['scripts'] = array('effects.js', 'class.cviewswitcher.js', 'multiselect.js');

//???
$page['hist_arg'] = array('parent_discoveryid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hostid' =>					array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'parent_discoveryid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null),
	'host' =>		        	array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({save})', _('Host name')),
	'name' =>	            	array(T_ZBX_STR, O_OPT, null,		null,		'isset({save})'),
	'status' =>		        	array(T_ZBX_INT, O_OPT, null,		IN(array(HOST_STATUS_NOT_MONITORED, HOST_STATUS_MONITORED)), null),
	'inventory_mode' =>			array(T_ZBX_INT, O_OPT, null, IN(array(HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC)), null),
	'templates' =>		    	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null),
	'add_template' =>			array(T_ZBX_STR, O_OPT, null,		null,	null),
	'add_templates' =>		    array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null),
	'group_links' =>			array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null),
	'group_prototypes' =>		array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null),
	'unlink' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'group_hostid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'go' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'update' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null,	null,		null),
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

// permissions
if (get_request('parent_discoveryid')) {
	$discoveryRule = API::DiscoveryRule()->get(array(
		'itemids' => $_REQUEST['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('flags'),
		'editable' => true
	));
	$discoveryRule = reset($discoveryRule);
	if (!$discoveryRule || $discoveryRule['hosts'][0]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		access_deny();
	}

	if (get_request('hostid')) {
		$hostPrototype = API::HostPrototype()->get(array(
			'hostids' => get_request('hostid'),
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectTemplates' => array('templateid', 'name'),
			'selectParentHost' => array('hostid'),
			'selectInventory' => API_OUTPUT_EXTEND,
			'editable' => true
		));
		$hostPrototype = reset($hostPrototype);
		if (!$hostPrototype) {
			access_deny();
		}
	}
}
else {
	access_deny();
}

/*
 * Actions
 */
// add templates to the list
if (get_request('add_template')) {
	foreach (get_request('add_templates', array()) as $templateId) {
		$_REQUEST['templates'][$templateId] = $templateId;
	}
}
// unlink templates
elseif (get_request('unlink')) {
	foreach (get_request('unlink') as $templateId => $value) {
		unset($_REQUEST['templates'][$templateId]);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {

	DBstart();
	$result = API::HostPrototype()->delete(array(getRequest('hostid')));

	show_messages($result, _('Host prototype deleted'), _('Cannot delete host prototypes'));

	unset($_REQUEST['hostid'], $_REQUEST['form']);
	DBend($result);
	clearCookies($result, $discoveryRule['itemid']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])) {
	unset($_REQUEST['hostid']);
	foreach ($_REQUEST['group_prototypes'] as &$groupPrototype) {
		unset($groupPrototype['group_prototypeid']);
	}
	unset($groupPrototype);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	DBstart();

	$newHostPrototype = array(
		'host' => get_request('host'),
		'name' => get_request('name'),
		'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
		'groupLinks' => array(),
		'groupPrototypes' => array(),
		'templates' => get_request('templates', array()),
		'inventory' => array(
			'inventory_mode' => get_request('inventory_mode')
		)
	);

	// add custom group prototypes
	foreach (get_request('group_prototypes', array()) as $groupPrototype) {
		if (!$groupPrototype['group_prototypeid']) {
			unset($groupPrototype['group_prototypeid']);
		}

		if (!zbx_empty($groupPrototype['name'])) {
			$newHostPrototype['groupPrototypes'][] = $groupPrototype;
		}
	}

	if (get_request('hostid')) {
		$newHostPrototype['hostid'] = get_request('hostid');

		if (!$hostPrototype['templateid']) {
			// add group prototypes based on existing host groups
			$groupPrototypesByGroupId = zbx_toHash($hostPrototype['groupLinks'], 'groupid');
			unset($groupPrototypesByGroupId[0]);
			foreach (get_request('group_links', array()) as $groupId) {
				if (isset($groupPrototypesByGroupId[$groupId])) {
					$newHostPrototype['groupLinks'][] = array(
						'groupid' => $groupPrototypesByGroupId[$groupId]['groupid'],
						'group_prototypeid' => $groupPrototypesByGroupId[$groupId]['group_prototypeid']
					);
				}
				else {
					$newHostPrototype['groupLinks'][] = array(
						'groupid' => $groupId
					);
				}
			}
		}
		else {
			unset($newHostPrototype['groupPrototypes'], $newHostPrototype['groupLinks']);
		}

		$newHostPrototype = CArrayHelper::unsetEqualValues($newHostPrototype, $hostPrototype, array('hostid'));
		$result = API::HostPrototype()->update($newHostPrototype);

		show_messages($result, _('Host prototype updated'), _('Cannot update host prototype'));
	}
	else {
		$newHostPrototype['ruleid'] = get_request('parent_discoveryid');

		// add group prototypes based on existing host groups
		foreach (get_request('group_links', array()) as $groupId) {
			$newHostPrototype['groupLinks'][] = array(
				'groupid' => $groupId
			);
		}

		$result = API::HostPrototype()->create($newHostPrototype);

		show_messages($result, _('Host prototype added'), _('Cannot add host prototype'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
	}

	DBend($result);
	clearCookies($result, $discoveryRule['itemid']);
}
// GO
elseif (str_in_array(getRequest('go'), array('activate', 'disable')) && hasRequest('group_hostid')) {
	$enable = (getRequest('go') == 'activate');
	$status = $enable ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$update = array();

	DBstart();
	foreach ((array) getRequest('group_hostid') as $hostPrototypeId) {
		$update[] = array(
			'hostid' => $hostPrototypeId,
			'status' => $status
		);
	}

	$result = API::HostPrototype()->update($update);
	DBend($result);

	$updated = count($update);

	$messageSuccess = $enable
		? _n('Host prototype enabled', 'Host prototypes enabled', $updated)
		: _n('Host prototype disabled', 'Host prototypes disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable host prototype', 'Cannot enable host prototypes', $updated)
		: _n('Cannot disable host prototype', 'Cannot disable host prototypes', $updated);

	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result, $discoveryRule['itemid']);
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['group_hostid'])) {
	DBstart();
	$go_result = API::HostPrototype()->delete($_REQUEST['group_hostid']);
	show_messages($go_result, _('Host prototypes deleted'), _('Cannot delete host prototypes'));
	DBend($go_result);
	clearCookies($go_result, $discoveryRule['itemid']);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'discovery_rule' => $discoveryRule,
		'host_prototype' => array(
			'hostid' => get_request('hostid'),
			'templateid' => get_request('templateid'),
			'host' => get_request('host'),
			'name' => get_request('name'),
			'status' => getRequest('status', HOST_STATUS_MONITORED),
			'templates' => array(),
			'inventory' => array(
				'inventory_mode' => get_request('inventory_mode', HOST_INVENTORY_DISABLED)
			),
			'groupPrototypes' => get_request('group_prototypes', array())
		),
		'groups' => array()
	);

	// add already linked and new templates
	$data['host_prototype']['templates'] = API::Template()->get(array(
		'output' => array('templateid', 'name'),
		'templateids' => get_request('templates', array())
	));

	// add parent host
	$parentHost = API::Host()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'selectGroups' => array('groupid', 'name'),
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectMacros' => API_OUTPUT_EXTEND,
		'hostids' => $discoveryRule['hostid'],
		'templated_hosts' => true
	));
	$parentHost = reset($parentHost);
	$data['parent_host'] = $parentHost;

	if (get_request('group_links')) {
		$data['groups'] = API::HostGroup()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'groupids' => get_request('group_links'),
			'editable' => true,
			'preservekeys' => true
		));
	}

	if ($parentHost['proxy_hostid']) {
		$proxy = API::Proxy()->get(array(
			'output' => array('host', 'proxyid'),
			'proxyids' => $parentHost['proxy_hostid'],
			'limit' => 1
		));
		$data['proxy'] = reset($proxy);
	}

	// host prototype edit form
	if (get_request('hostid') && !get_request('form_refresh')) {
		$data['host_prototype'] = array_merge($data['host_prototype'], $hostPrototype);

		$data['groups'] = API::HostGroup()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'groupids' => zbx_objectValues($data['host_prototype']['groupLinks'], 'groupid'),
			'editable' => true,
			'preservekeys' => true
		));

		// add parent templates
		if ($hostPrototype['templateid']) {
			$data['parents'] = array();
			$hostPrototypeId = $hostPrototype['templateid'];
			while ($hostPrototypeId) {
				$parentHostPrototype = API::HostPrototype()->get(array(
					'output' => array('itemid', 'templateid'),
					'selectParentHost' => array('hostid', 'name'),
					'selectDiscoveryRule' => array('itemid'),
					'hostids' => $hostPrototypeId
				));
				$parentHostPrototype = reset($parentHostPrototype);
				$hostPrototypeId = null;

				if ($parentHostPrototype) {
					$data['parents'][] = $parentHostPrototype;
					$hostPrototypeId = $parentHostPrototype['templateid'];
				}
			}
		}
	}

	// order linked templates
	CArrayHelper::sort($data['host_prototype']['templates'], array('name'));

	// render view
	$itemView = new CView('configuration.host.prototype.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$data = array(
		'form' => get_request('form', null),
		'parent_discoveryid' => get_request('parent_discoveryid', null),
		'discovery_rule' => $discoveryRule
	);

	// get items
	$sortfield = getPageSortField('name');
	$data['hostPrototypes'] = API::HostPrototype()->get(array(
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'selectTemplates' => array('templateid', 'name'),
		'editable' => true,
		'sortfield' => $sortfield,
		'limit' => $config['search_limit'] + 1
	));

	if ($data['hostPrototypes']) {
		order_result($data['hostPrototypes'], $sortfield, getPageSortOrder());
	}

	$data['paging'] = getPagingLine($data['hostPrototypes']);

	// fetch templates linked to the prototypes
	$templateIds = array();
	foreach ($data['hostPrototypes'] as $hostPrototype) {
		$templateIds = array_merge($templateIds, zbx_objectValues($hostPrototype['templates'], 'templateid'));
	}
	$templateIds = array_unique($templateIds);

	$linkedTemplates = API::Template()->get(array(
		'output' => array('templateid', 'name'),
		'templateids' => $templateIds,
		'selectParentTemplates' => array('hostid', 'name')
	));
	$data['linkedTemplates'] = zbx_toHash($linkedTemplates, 'templateid');

	// fetch source templates and LLD rules
	$hostPrototypeSourceIds = getHostPrototypeSourceParentIds(zbx_objectValues($data['hostPrototypes'], 'hostid'));
	if ($hostPrototypeSourceIds) {
		$hostPrototypeSourceTemplates = DBfetchArrayAssoc(DBSelect(
			'SELECT h.hostid,h2.name,h2.hostid AS parent_hostid'.
			' FROM hosts h,host_discovery hd,items i,hosts h2'.
			' WHERE h.hostid=hd.hostid'.
				' AND hd.parent_itemid=i.itemid'.
				' AND i.hostid=h2.hostid'.
				' AND '.dbConditionInt('h.hostid', $hostPrototypeSourceIds)
		), 'hostid');
		foreach ($data['hostPrototypes'] as &$hostPrototype) {
			if ($hostPrototype['templateid']) {
				$sourceTemplate = $hostPrototypeSourceTemplates[$hostPrototypeSourceIds[$hostPrototype['hostid']]];
				$hostPrototype['sourceTemplate'] = array(
					'hostid' => $sourceTemplate['parent_hostid'],
					'name' => $sourceTemplate['name']
				);
				$sourceDiscoveryRuleId = get_realrule_by_itemid_and_hostid($discoveryRule['itemid'], $sourceTemplate['hostid']);
				$hostPrototype['sourceDiscoveryRuleId'] = $sourceDiscoveryRuleId;
			}
		}
		unset($hostPrototype);
	}

	// render view
	$itemView = new CView('configuration.host.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
