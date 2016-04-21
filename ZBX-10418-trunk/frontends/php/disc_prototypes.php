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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of item prototypes');
$page['file'] = 'disc_prototypes.php';
$page['scripts'] = ['effects.js', 'class.cviewswitcher.js', 'items.js'];

require_once dirname(__FILE__).'/include/page_header.php';

$paramsFieldName = getParamFieldNameByType(getRequest('type', 0));

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'parent_discoveryid' =>			[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'itemid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form}) && ({form} == "update"))'],
	'interfaceid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null, _('Interface')],
	'name' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
		_('Name')
	],
	'description' =>				[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'key' =>						[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})',
		_('Key')
	],
	'delay' =>						[T_ZBX_INT, O_OPT, null,	BETWEEN(0, SEC_PER_DAY),
		'(isset({add}) || isset({update}))'.
			' && (isset({type}) && ({type} != '.ITEM_TYPE_TRAPPER.' && {type} != '.ITEM_TYPE_SNMPTRAP.'))',
		_('Update interval (in sec)')
	],
	'delay_flex' =>					[T_ZBX_STR, O_OPT, null,	null,			null],
	'status' =>						[T_ZBX_INT, O_OPT, null,	IN(ITEM_STATUS_ACTIVE), null],
	'type' =>						[T_ZBX_INT, O_OPT, null,
		IN([-1, ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C,
			ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED,
			ITEM_TYPE_SNMPTRAP]
		),
		'isset({add}) || isset({update})'
	],
	'value_type' =>					[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4'), 'isset({add}) || isset({update})'],
	'data_type' =>					[T_ZBX_INT, O_OPT, null,
		IN(ITEM_DATA_TYPE_DECIMAL.','.ITEM_DATA_TYPE_OCTAL.','.ITEM_DATA_TYPE_HEXADECIMAL.','.ITEM_DATA_TYPE_BOOLEAN),
		'(isset({add}) || isset({update})) && (isset({value_type}) && ({value_type} == '.ITEM_VALUE_TYPE_UINT64.'))'
	],
	'valuemapid' =>					[T_ZBX_INT, O_OPT, null,	DB_ID,
		'(isset({add}) || isset({update})) && isset({value_type})'.
			' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type')
	],
	'authtype' =>					[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHTYPE_PASSWORD.','.ITEM_AUTHTYPE_PUBLICKEY),
		'(isset({add}) || isset({update})) && isset({type}) && ({type} == '.ITEM_TYPE_SSH.')'
	],
	'username' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type'),
		_('User name')
	],
	'password' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_TELNET, 'type')
	],
	'publickey' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && ({type}) == '.ITEM_TYPE_SSH.' && ({authtype}) == '.ITEM_AUTHTYPE_PUBLICKEY
	],
	'privatekey' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && ({type}) == '.ITEM_TYPE_SSH.' && ({authtype}) == '.ITEM_AUTHTYPE_PUBLICKEY
	],
	$paramsFieldName =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && '.IN(ITEM_TYPE_SSH.','.ITEM_TYPE_DB_MONITOR.','.ITEM_TYPE_TELNET.','.ITEM_TYPE_CALCULATED, 'type'),
		getParamFieldLabelByType(getRequest('type', 0))
	],
	'snmp_community' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type}) && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C, 'type'),
		_('SNMP community')
	],
	'snmp_oid' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && isset({type})'.
			' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'),
		_('SNMP OID')
	],
	'port' =>						[T_ZBX_STR, O_OPT, null,	BETWEEN(0, 65535),
		'(isset({add}) || isset({update})) && isset({type})'.
			' && '.IN(ITEM_TYPE_SNMPV1.','.ITEM_TYPE_SNMPV2C.','.ITEM_TYPE_SNMPV3, 'type'),
		_('Port')
	],
	'snmpv3_securitylevel' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.'))'
	],
	'snmpv3_contextname' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.'))'
	],
	'snmpv3_securityname' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.'))'
	],
	'snmpv3_authprotocol' =>		[T_ZBX_INT, O_OPT, null,	IN(ITEM_AUTHPROTOCOL_MD5.','.ITEM_AUTHPROTOCOL_SHA),
		'(isset({add}) || isset({update})) && (isset({type})'.
			' && ({type} == '.ITEM_TYPE_SNMPV3.') && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.
			' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'
	],
	'snmpv3_authpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type})'.
			' && ({type} == '.ITEM_TYPE_SNMPV3.') && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.
			' || {snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV.'))'
	],
	'snmpv3_privprotocol' =>		[T_ZBX_INT, O_OPT, null,	IN(ITEM_PRIVPROTOCOL_DES.','.ITEM_PRIVPROTOCOL_AES),
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.')'.
			' && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'
	],
	'snmpv3_privpassphrase' =>		[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_SNMPV3.')'.
			' && ({snmpv3_securitylevel} == '.ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV.'))'
	],
	'ipmi_sensor' =>				[T_ZBX_STR, O_OPT, P_NO_TRIM,	NOT_EMPTY,
		'(isset({add}) || isset({update})) && (isset({type}) && ({type} == '.ITEM_TYPE_IPMI.'))', _('IPMI sensor')
	],
	'trapper_hosts' =>				[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({type}) && ({type} == 2)'
	],
	'units' =>						[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && isset({value_type})'.
			' && '.IN('0,3', 'value_type').' (isset({data_type}) && ({data_type} != '.ITEM_DATA_TYPE_BOOLEAN.'))'
	],
	'multiplier' =>					[T_ZBX_INT, O_OPT, null,	null,		null],
	'delta' =>						[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),
		'(isset({add}) || isset({update})) && isset({value_type})'.
			' && '.IN('0,3', 'value_type').' (isset({data_type}) && ({data_type} != '.ITEM_DATA_TYPE_BOOLEAN.'))'
	],
	'formula' =>					[T_ZBX_DBL_STR, O_OPT, null,
		'({value_type} == 0 && {} != 0) || ({value_type} == 3 && {} > 0)',
		'(isset({add}) || isset({update})) && isset({multiplier}) && {multiplier} == 1', _('Custom multiplier')
	],
	'logtimefmt' =>					[T_ZBX_STR, O_OPT, null,	null,
		'(isset({add}) || isset({update})) && (isset({value_type}) && ({value_type} == 2))'
	],
	'group_itemid' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_application' =>			[T_ZBX_STR, O_OPT, null,	null,		'isset({add}) || isset({update})'],
	'applications' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,		null],
	'new_application_prototype' =>	[T_ZBX_STR, O_OPT, null,	null,
		'isset({parent_discoveryid}) && (isset({add}) || isset({update}))'
	],
	'application_prototypes' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'history' =>					[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({add}) || isset({update})',
		_('History storage period')
	],
	'trends' =>						[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535),
		'(isset({add}) || isset({update})) && isset({value_type})'.
			' && '.IN(ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64, 'value_type'),
		_('Trend storage period')
	],
	// actions
	'action' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
		IN('"itemprototype.massdelete","itemprototype.massdisable","itemprototype.massenable"'), null
	],
	'add' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'clone' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>						[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' =>				[T_ZBX_INT, O_OPT, null,	null,		null],
	// filter
	'filter_set' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	// sort and sortorder
	'sort' =>						[T_ZBX_STR, O_OPT, P_SYS,
		IN('"delay","history","key_","name","status","trends","type"'), null
	],
	'sortorder' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$_REQUEST['params'] = getRequest($paramsFieldName, '');
unset($_REQUEST[$paramsFieldName]);

// permissions
$discoveryRule = API::DiscoveryRule()->get([
	'output' => ['hostid'],
	'itemids' => getRequest('parent_discoveryid'),
	'editable' => true
]);
$discoveryRule = reset($discoveryRule);
if (!$discoveryRule) {
	access_deny();
}

$itemPrototypeId = getRequest('itemid');
if ($itemPrototypeId && !API::ItemPrototype()->isWritable([$itemPrototypeId])) {
	access_deny();
}

/*
 * Actions
 */
if (hasRequest('delete') && hasRequest('itemid')) {
	DBstart();
	$result = API::Itemprototype()->delete([getRequest('itemid')]);
	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Item prototype deleted'), _('Cannot delete item prototype'));

	unset($_REQUEST['itemid'], $_REQUEST['form']);
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['itemid'])) {
	unset($_REQUEST['itemid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	$applications = getRequest('applications', []);
	$application = reset($applications);
	if ($application == 0) {
		array_shift($applications);
	}

	$result = true;
	DBstart();

	if (!zbx_empty($_REQUEST['new_application'])) {
		$new_appid = API::Application()->create([
			'name' => $_REQUEST['new_application'],
			'hostid' => $discoveryRule['hostid']
		]);
		if ($new_appid) {
			$new_appid = reset($new_appid['applicationids']);
			$applications[$new_appid] = $new_appid;
		}
		else {
			$result = false;
		}
	}

	/*
	 * Intially validate "delay_flex" field one by one to make sure it does not have interval separator ";".
	 * Skip empty fields and convert "delay_flex" array to string glued with ";" which is later validated through API.
	 */
	$delay_flex = '';
	$intervals = [];

	if (getRequest('delay_flex')) {
		foreach (getRequest('delay_flex') as $interval) {
			if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_FLEXIBLE) {
				if ($interval['delay'] === '' && $interval['period'] === '') {
					continue;
				}

				if (strpos($interval['delay'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['delay']));
					break;
				}
				elseif (strpos($interval['period'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['period']));
					break;
				}

				$intervals[] = $interval['delay'].'/'.$interval['period'];
			}
			else {
				if ($interval['schedule'] === '') {
					continue;
				}

				if (strpos($interval['schedule'], ';') !== false) {
					$result = false;
					info(_s('Invalid interval "%1$s".', $interval['schedule']));
					break;
				}

				$intervals[] = $interval['schedule'];
			}
		}

		if ($intervals) {
			$delay_flex = join(';', $intervals);
		}
	}

	if ($result) {
		$application_prototypes = getRequest('application_prototypes', []);
		$application_prototype = reset($application_prototypes);

		if ($application_prototype === '0') {
			array_shift($application_prototypes);
		}

		if ($application_prototypes) {
			foreach ($application_prototypes as &$application_prototype) {
				$application_prototype = ['name' => $application_prototype];
			}
			unset($application_prototype);
		}

		$new_application_prototype = getRequest('new_application_prototype', '');
		if ($new_application_prototype !== '') {
			$application_prototypes[] = ['name' => $new_application_prototype];
		}

		$item = [
			'name'			=> getRequest('name'),
			'description'	=> getRequest('description'),
			'key_'			=> getRequest('key'),
			'hostid'		=> $discoveryRule['hostid'],
			'interfaceid'	=> getRequest('interfaceid'),
			'delay'			=> getRequest('delay'),
			'status'		=> getRequest('status', ITEM_STATUS_DISABLED),
			'type'			=> getRequest('type'),
			'snmp_community' => getRequest('snmp_community'),
			'snmp_oid'		=> getRequest('snmp_oid'),
			'value_type'	=> getRequest('value_type'),
			'trapper_hosts'	=> getRequest('trapper_hosts'),
			'port'			=> getRequest('port'),
			'history'		=> getRequest('history'),
			'trends'		=> getRequest('trends'),
			'units'			=> getRequest('units'),
			'multiplier'	=> getRequest('multiplier', 0),
			'delta'			=> getRequest('delta'),
			'snmpv3_contextname' => getRequest('snmpv3_contextname'),
			'snmpv3_securityname' => getRequest('snmpv3_securityname'),
			'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel'),
			'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol'),
			'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase'),
			'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol'),
			'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase'),
			'formula'		=> getRequest('formula', '1'),
			'logtimefmt'	=> getRequest('logtimefmt'),
			'valuemapid'	=> getRequest('valuemapid'),
			'authtype'		=> getRequest('authtype'),
			'username'		=> getRequest('username'),
			'password'		=> getRequest('password'),
			'publickey'		=> getRequest('publickey'),
			'privatekey'	=> getRequest('privatekey'),
			'params'		=> getRequest('params'),
			'ipmi_sensor'	=> getRequest('ipmi_sensor'),
			'data_type'		=> getRequest('data_type'),
			'ruleid'		=> getRequest('parent_discoveryid'),
			'delay_flex'	=> $delay_flex,
			'applications'	=> $applications,
			'applicationPrototypes' => $application_prototypes
		];

		if (hasRequest('update')) {
			$itemId = getRequest('itemid');

			$dbItem = get_item_by_itemid_limited($itemId);
			$dbItem['applications'] = get_applications_by_itemid($itemId);

			// unset snmpv3 fields
			if ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
				$item['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
				$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}
			elseif ($item['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
				$item['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
			}

			$item = CArrayHelper::unsetEqualValues($item, $dbItem);
			$item['itemid'] = $itemId;

			$result = API::Itemprototype()->update($item);
		}
		else {
			$result = API::Itemprototype()->create($item);
		}
	}

	$result = DBend($result);

	if (hasRequest('add')) {
		show_messages($result, _('Item prototype added'), _('Cannot add item prototype'));
	}
	else {
		show_messages($result, _('Item prototype updated'), _('Cannot update item prototype'));
	}

	if ($result) {
		unset($_REQUEST['itemid'], $_REQUEST['form']);
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['itemprototype.massenable', 'itemprototype.massdisable']) && hasRequest('group_itemid')) {
	$groupItemId = getRequest('group_itemid');
	$enable = (getRequest('action') == 'itemprototype.massenable');

	DBstart();
	$result = $enable ? activate_item($groupItemId) : disable_item($groupItemId);
	$result = DBend($result);

	$updated = count($groupItemId);

	$messageSuccess = $enable
		? _n('Item prototype enabled', 'Item prototypes enabled', $updated)
		: _n('Item prototype disabled', 'Item prototypes disabled', $updated);
	$messageFailed = $enable
		? _n('Cannot enable item prototype', 'Cannot enable item prototypes', $updated)
		: _n('Cannot disable item prototype', 'Cannot disable item prototypes', $updated);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (hasRequest('action') && getRequest('action') == 'itemprototype.massdelete' && hasRequest('group_itemid')) {
	DBstart();

	$result = API::Itemprototype()->delete(getRequest('group_itemid'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows(getRequest('parent_discoveryid'));
	}
	show_messages($result, _('Item prototypes deleted'), _('Cannot delete item prototypes'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$itemPrototype = [];
	if (hasRequest('itemid')) {
		$itemPrototype = API::ItemPrototype()->get([
			'itemids' => getRequest('itemid'),
			'output' => [
				'itemid', 'type', 'snmp_community', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history',
				'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'multiplier', 'delta',
				'snmpv3_securityname', 'snmpv3_securitylevel', 'snmpv3_authpassphrase', 'snmpv3_privpassphrase',
				'formula', 'logtimefmt', 'templateid', 'valuemapid', 'delay_flex', 'params', 'ipmi_sensor',
				'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'snmpv3_authprotocol', 'snmpv3_privprotocol', 'snmpv3_contextname'
			]
		]);
		$itemPrototype = reset($itemPrototype);
	}

	$data = getItemFormData($itemPrototype);
	$data['config'] = select_config();

	// render view
	$itemView = new CView('configuration.item.prototype.edit', $data);
	$itemView->render();
	$itemView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'form' => getRequest('form'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'hostid' => $discoveryRule['hostid'],
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	$data['items'] = API::ItemPrototype()->get([
		'discoveryids' => $data['parent_discoveryid'],
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'selectApplications' => API_OUTPUT_EXTEND,
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);

	foreach ($data['items'] as &$item) {
		if ($item['value_type'] == ITEM_VALUE_TYPE_STR || $item['value_type'] == ITEM_VALUE_TYPE_LOG
				|| $item['value_type'] == ITEM_VALUE_TYPE_TEXT) {
			$item['trends'] = '';
		}

		if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP) {
			$item['delay'] = '';
		}
	}
	unset($item);

	$data['items'] = CMacrosResolverHelper::resolveItemNames($data['items']);

	order_result($data['items'], $sortField, $sortOrder);

	$url = (new CUrl('disc_prototypes.php'))
		->setArgument('parent_discoveryid', $data['parent_discoveryid']);

	$data['paging'] = getPagingLine($data['items'], $sortOrder, $url);

	// render view
	$itemView = new CView('configuration.item.prototype.list', $data);
	$itemView->render();
	$itemView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
