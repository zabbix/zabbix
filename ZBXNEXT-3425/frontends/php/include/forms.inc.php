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


/**
 * Build user edit form data.
 *
 * @param string $userId			user ID
 * @param array	 $config			array of configuration parameters returned in $data['config'] parameter
 *									to later use when configuring user medias
 * @param bool	 $isProfile			true if current user viewing his own profile
 *
 * @return array
 */
function getUserFormData($userId, array $config, $isProfile = false) {
	$data = [
		'is_profile' => $isProfile,
		'config' => $config
	];

	if ($userId != 0 && (!hasRequest('form_refresh') || hasRequest('register'))) {
		$users = API::User()->get([
			'output' => ['alias', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'theme', 'refresh',
				'rows_per_page', 'type'
			],
			'userids' => $userId
		]);
		$user = reset($users);

		$data['alias']			= $user['alias'];
		$data['name']			= $user['name'];
		$data['surname']		= $user['surname'];
		$data['password1']		= null;
		$data['password2']		= null;
		$data['url']			= $user['url'];
		$data['autologin']		= $user['autologin'];
		$data['autologout']		= $user['autologout'];
		$data['lang']			= $user['lang'];
		$data['theme']			= $user['theme'];
		$data['refresh']		= $user['refresh'];
		$data['rows_per_page']	= $user['rows_per_page'];
		$data['user_type']		= $user['type'];
		$data['messages'] 		= getMessageSettings();

		$userGroups = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'userids' => $userId
		]);
		$userGroup = zbx_objectValues($userGroups, 'usrgrpid');
		$data['user_groups']	= zbx_toHash($userGroup);

		$data['user_medias'] = [];
		$dbMedia = DBselect('SELECT m.mediaid,m.mediatypeid,m.period,m.sendto,m.severity,m.active'.
				' FROM media m'.
				' WHERE m.userid='.zbx_dbstr($userId)
		);
		while ($dbMedium = DBfetch($dbMedia)) {
			$data['user_medias'][] = $dbMedium;
		}

		if ($data['autologout'] > 0) {
			$_REQUEST['autologout'] = $data['autologout'];
		}
	}
	else {
		$data['alias']			= getRequest('alias', '');
		$data['name']			= getRequest('name', '');
		$data['surname']		= getRequest('surname', '');
		$data['password1']		= getRequest('password1', '');
		$data['password2']		= getRequest('password2', '');
		$data['url']			= getRequest('url', '');
		$data['autologin']		= getRequest('autologin', 0);
		$data['autologout']		= getRequest('autologout', 900);
		$data['lang']			= getRequest('lang', 'en_gb');
		$data['theme']			= getRequest('theme', THEME_DEFAULT);
		$data['refresh']		= getRequest('refresh', 30);
		$data['rows_per_page']	= getRequest('rows_per_page', 50);
		$data['user_type']		= getRequest('user_type', USER_TYPE_ZABBIX_USER);
		$data['user_groups']	= getRequest('user_groups', []);
		$data['change_password']= getRequest('change_password');
		$data['user_medias']	= getRequest('user_medias', []);

		// set messages
		$data['messages'] = getRequest('messages', []);
		if (!isset($data['messages']['enabled'])) {
			$data['messages']['enabled'] = 0;
		}
		if (!isset($data['messages']['sounds.recovery'])) {
			$data['messages']['sounds.recovery'] = 'alarm_ok.wav';
		}
		if (!isset($data['messages']['triggers.recovery'])) {
			$data['messages']['triggers.recovery'] = 0;
		}
		if (!isset($data['messages']['triggers.severities'])) {
			$data['messages']['triggers.severities'] = [];
		}
		$data['messages'] = array_merge(getMessageSettings(), $data['messages']);
	}

	// authentication type
	if ($data['user_groups']) {
		$data['auth_type'] = getGroupAuthenticationType($data['user_groups'], GROUP_GUI_ACCESS_INTERNAL);
	}
	else {
		$data['auth_type'] = ($userId == 0)
			? $config['authentication_type']
			: getUserAuthenticationType($userId, GROUP_GUI_ACCESS_INTERNAL);
	}

	// set autologout
	if ($data['autologin'] || !isset($data['autologout'])) {
		$data['autologout'] = 0;
	}

	// set media types
	if (!empty($data['user_medias'])) {
		$mediaTypeDescriptions = [];
		$dbMediaTypes = DBselect(
			'SELECT mt.mediatypeid,mt.description FROM media_type mt WHERE '.
				dbConditionInt('mt.mediatypeid', zbx_objectValues($data['user_medias'], 'mediatypeid'))
		);
		while ($dbMediaType = DBfetch($dbMediaTypes)) {
			$mediaTypeDescriptions[$dbMediaType['mediatypeid']] = $dbMediaType['description'];
		}

		foreach ($data['user_medias'] as &$media) {
			$media['description'] = $mediaTypeDescriptions[$media['mediatypeid']];
		}
		unset($media);

		CArrayHelper::sort($data['user_medias'], ['description', 'sendto']);
	}

	// set user rights
	if (!$data['is_profile']) {
		$data['groups'] = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $data['user_groups']
		]);
		order_result($data['groups'], 'name');

		if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
			$data['groups_rights'] = [
				'0' => [
					'permission' => PERM_READ_WRITE,
					'name' => '',
					'grouped' => '1'
				]
			];
		}
		else {
			$data['groups_rights'] = collapseHostGroupRights(getHostGroupsRights($data['user_groups']));
		}
	}

	return $data;
}

function prepareSubfilterOutput($label, $data, $subfilter, $subfilterName) {
	order_result($data, 'name');

	$output = [new CTag('h3', true, $label)];

	foreach ($data as $id => $element) {
		$element['name'] = nbsp(CHtml::encode($element['name']));

		// is activated
		if (str_in_array($id, $subfilter)) {
			$output[] = (new CSpan([
				(new CSpan($element['name']))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->onClick(CHtml::encode(
						'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
						'create_var("zbx_filter", '.CJs::encodeJson($subfilterName.'['.$id.']').', null, true);'
					)),
				SPACE,
				new CSup($element['count'])
			]))->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
		}
		// isn't activated
		else {
			// subfilter has 0 items
			if ($element['count'] == 0) {
				$output[] = (new CSpan($element['name']))->addClass(ZBX_STYLE_GREY);
				$output[] = SPACE;
				$output[] = new CSup($element['count']);
			}
			else {
				// this level has no active subfilters
				$nspan = $subfilter
					? new CSup('+'.$element['count'])
					: new CSup($element['count']);

				$link = (new CSpan($element['name']))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->onClick(CHtml::encode(
						'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
						'create_var("zbx_filter", '.
							CJs::encodeJson($subfilterName.'['.$id.']').', '.
							CJs::encodeJson($id).', '.
							'true'.
						');'
					));

				$output[] = $link;
				$output[] = SPACE;
				$output[] = $nspan;
			}
		}

		$output[] = '&nbsp;&nbsp;&nbsp;';
	}

	array_pop($output);

	return $output;
}

function getItemFilterForm(&$items) {
	$filter_groupId				= $_REQUEST['filter_groupid'];
	$filter_hostId				= $_REQUEST['filter_hostid'];
	$filter_application			= $_REQUEST['filter_application'];
	$filter_name				= $_REQUEST['filter_name'];
	$filter_type				= $_REQUEST['filter_type'];
	$filter_key					= $_REQUEST['filter_key'];
	$filter_snmp_community		= $_REQUEST['filter_snmp_community'];
	$filter_snmpv3_securityname	= $_REQUEST['filter_snmpv3_securityname'];
	$filter_snmp_oid			= $_REQUEST['filter_snmp_oid'];
	$filter_port				= $_REQUEST['filter_port'];
	$filter_value_type			= $_REQUEST['filter_value_type'];
	$filter_data_type			= $_REQUEST['filter_data_type'];
	$filter_delay				= $_REQUEST['filter_delay'];
	$filter_history				= $_REQUEST['filter_history'];
	$filter_trends				= $_REQUEST['filter_trends'];
	$filter_status				= $_REQUEST['filter_status'];
	$filter_state				= $_REQUEST['filter_state'];
	$filter_templated_items		= $_REQUEST['filter_templated_items'];
	$filter_with_triggers		= $_REQUEST['filter_with_triggers'];
	$subfilter_hosts			= $_REQUEST['subfilter_hosts'];
	$subfilter_apps				= $_REQUEST['subfilter_apps'];
	$subfilter_types			= $_REQUEST['subfilter_types'];
	$subfilter_value_types		= $_REQUEST['subfilter_value_types'];
	$subfilter_status			= $_REQUEST['subfilter_status'];
	$subfilter_state			= $_REQUEST['subfilter_state'];
	$subfilter_templated_items	= $_REQUEST['subfilter_templated_items'];
	$subfilter_with_triggers	= $_REQUEST['subfilter_with_triggers'];
	$subfilter_history			= $_REQUEST['subfilter_history'];
	$subfilter_trends			= $_REQUEST['subfilter_trends'];
	$subfilter_interval			= $_REQUEST['subfilter_interval'];

	$form = (new CFilter('web.items.filter.state'))
		->addVar('subfilter_hosts', $subfilter_hosts)
		->addVar('subfilter_apps', $subfilter_apps)
		->addVar('subfilter_types', $subfilter_types)
		->addVar('subfilter_value_types', $subfilter_value_types)
		->addVar('subfilter_status', $subfilter_status)
		->addVar('subfilter_state', $subfilter_state)
		->addVar('subfilter_templated_items', $subfilter_templated_items)
		->addVar('subfilter_with_triggers', $subfilter_with_triggers)
		->addVar('subfilter_history', $subfilter_history)
		->addVar('subfilter_trends', $subfilter_trends)
		->addVar('subfilter_interval', $subfilter_interval);

	$filterColumn1 = new CFormList();
	$filterColumn2 = new CFormList();
	$filterColumn3 = new CFormList();
	$filterColumn4 = new CFormList();

	// type select
	$fTypeVisibility = [];
	$cmbType = new CComboBox('filter_type', $filter_type, null, [-1 => _('all')]);
	zbx_subarray_push($fTypeVisibility, -1, 'filter_delay_row');

	$item_types = item_type2str();
	unset($item_types[ITEM_TYPE_HTTPTEST]); // httptest items are only for internal zabbix logic

	$cmbType->addItems($item_types);

	foreach ($item_types as $type => $name) {
		if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP) {
			zbx_subarray_push($fTypeVisibility, $type, 'filter_delay_row');
		}

		switch ($type) {
			case ITEM_TYPE_SNMPV1:
			case ITEM_TYPE_SNMPV2C:
				zbx_subarray_push($fTypeVisibility, $type, 'filter_snmp_community_row');
				zbx_subarray_push($fTypeVisibility, $type, 'filter_snmp_oid_row');
				zbx_subarray_push($fTypeVisibility, $type, 'filter_port_row');
				break;

			case ITEM_TYPE_SNMPV3:
				zbx_subarray_push($fTypeVisibility, $type, 'filter_snmpv3_securityname_row');
				zbx_subarray_push($fTypeVisibility, $type, 'filter_snmp_oid_row');
				zbx_subarray_push($fTypeVisibility, $type, 'filter_port_row');
				break;
		}
	}

	zbx_add_post_js("var filterTypeSwitcher = new CViewSwitcher('filter_type', 'change', ".zbx_jsvalue($fTypeVisibility, true).');');

	// type of information select
	$fVTypeVisibility = [];

	zbx_subarray_push($fVTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'filter_data_type_row');

	zbx_add_post_js("var filterValueTypeSwitcher = new CViewSwitcher('filter_value_type', 'change', ".zbx_jsvalue($fVTypeVisibility, true).');');

	// row 1
	$groupFilter = null;
	if (!empty($filter_groupId)) {
		$getHostInfo = API::HostGroup()->get([
			'groupids' => $filter_groupId,
			'output' => ['name']
		]);
		$getHostInfo = reset($getHostInfo);
		if (!empty($getHostInfo)) {
			$groupFilter[] = [
				'id' => $getHostInfo['groupid'],
				'name' => $getHostInfo['name']
			];
		}
	}

	$filterColumn1->addRow(_('Host group'),
		(new CMultiSelect([
			'name' => 'filter_groupid',
			'selectedLimit' => 1,
			'objectName' => 'hostGroup',
			'objectOptions' => [
				'editable' => true
			],
			'data' => $groupFilter,
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1=filter_groupid'.
					'&srcfld1=groupid&writeonly=1'
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);

	$filterColumn2->addRow(_('Type'), $cmbType);
	$filterColumn3->addRow(_('Type of information'),
		new CComboBox('filter_value_type', $filter_value_type, null, [
			-1 => _('all'),
			ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
			ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
			ITEM_VALUE_TYPE_STR => _('Character'),
			ITEM_VALUE_TYPE_LOG => _('Log'),
			ITEM_VALUE_TYPE_TEXT => _('Text')
		])
	);;
	$filterColumn4->addRow(_('State'),
		new CComboBox('filter_state', $filter_state, null, [
			-1 => _('all'),
			ITEM_STATE_NORMAL => itemState(ITEM_STATE_NORMAL),
			ITEM_STATE_NOTSUPPORTED => itemState(ITEM_STATE_NOTSUPPORTED)
		])
	);

	// row 2
	$hostFilterData = null;
	if (!empty($filter_hostId)) {
		$getHostInfo = API::Host()->get([
			'hostids' => $filter_hostId,
			'templated_hosts' => true,
			'output' => ['name']
		]);
		$getHostInfo = reset($getHostInfo);
		if (!empty($getHostInfo)) {
			$hostFilterData[] = [
				'id' => $getHostInfo['hostid'],
				'name' => $getHostInfo['name']
			];
		}
	}

	$filterColumn1->addRow(_('Host'),
		(new CMultiSelect([
			'name' => 'filter_hostid',
			'selectedLimit' => 1,
			'objectName' => 'hosts',
			'objectOptions' => [
				'editable' => true,
				'templated_hosts' => true
			],
			'data' => $hostFilterData,
			'popup' => [
				'parameters' => 'srctbl=host_templates&dstfrm='.$form->getName().'&dstfld1=filter_hostid'.
					'&srcfld1=hostid&writeonly=1'
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);

	$filterColumn2->addRow(_('Update interval (in sec)'),
		(new CNumericBox('filter_delay', $filter_delay, 5, false, true))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
		'filter_delay_row'
	);
	$filterColumn3->addRow(_('Data type'),
		(new CComboBox('filter_data_type', $filter_data_type))
			->addItem(-1, _('all'))
			->addItems(item_data_type2str()),
		'filter_data_type_row'
	);
	$filterColumn4->addRow(_('Status'),
		new CComboBox('filter_status', $filter_status, null, [
			-1 => _('all'),
			ITEM_STATUS_ACTIVE => item_status2str(ITEM_STATUS_ACTIVE),
			ITEM_STATUS_DISABLED => item_status2str(ITEM_STATUS_DISABLED)
		])
	);

	// row 3
	$filterColumn1->addRow(_('Application'),
		[
			(new CTextBox('filter_application', $filter_application))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton(null, _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick(
					'return PopUp("popup.php?srctbl=applications&srcfld1=name'.
						'&dstfrm='.$form->getName().'&dstfld1=filter_application'.
						'&with_applications=1'.
						'" + (jQuery("input[name=\'filter_hostid\']").length > 0 ? "&hostid="+jQuery("input[name=\'filter_hostid\']").val() : "")'
						.', 0, 0, "application");')
		]
	);
	$filterColumn2->addRow(_('SNMP community'),
		(new CTextBox('filter_snmp_community', $filter_snmp_community))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_community_row'
	);
	$filterColumn2->addRow(_('Security name'),
		(new CTextBox('filter_snmpv3_securityname', $filter_snmpv3_securityname))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmpv3_securityname_row'
	);

	$filterColumn3->addRow(_('History (in days)'),
		(new CNumericBox('filter_history', $filter_history, 8, false, true))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$filterColumn4->addRow(_('Triggers'),
		new CComboBox('filter_with_triggers', $filter_with_triggers, null, [
			-1 => _('all'),
			1 => _('With triggers'),
			0 => _('Without triggers')
		])
	);

	// row 4
	$filterColumn1->addRow(_('Name'),
		(new CTextBox('filter_name', $filter_name))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
	$filterColumn2->addRow(_('SNMP OID'),
		(new CTextBox('filter_snmp_oid', $filter_snmp_oid))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_oid_row'
	);
	$filterColumn3->addRow(_('Trends (in days)'),
		(new CNumericBox('filter_trends', $filter_trends, 8, false, true))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);
	$filterColumn4->addRow(_('Template'),
		new CComboBox('filter_templated_items', $filter_templated_items, null, [
			-1 => _('all'),
			1 => _('Templated items'),
			0 => _('Not Templated items'),
		])
	);

	// row 5
	$filterColumn1->addRow(_('Key'),
		(new CTextBox('filter_key', $filter_key))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
	$filterColumn2->addRow(_('Port'),
		(new CNumericBox('filter_port', $filter_port, 5, false, true))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
		'filter_port_row'
	);

	$form->addColumn($filterColumn1);
	$form->addColumn($filterColumn2);
	$form->addColumn($filterColumn3);
	$form->addColumn($filterColumn4);

	// subfilters
	$table_subfilter = (new CTableInfo())
		->addRow([
			new CTag('h4', true, [
				_('Subfilter'), SPACE, (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
			])
		]);

	// array contains subfilters and number of items in each
	$item_params = [
		'hosts' => [],
		'applications' => [],
		'types' => [],
		'value_types' => [],
		'status' => [],
		'state' => [],
		'templated_items' => [],
		'with_triggers' => [],
		'history' => [],
		'trends' => [],
		'interval' => []
	];

	// generate array with values for subfilters of selected items
	foreach ($items as $item) {
		// hosts
		if (zbx_empty($filter_hostId)) {
			$host = reset($item['hosts']);

			if (!isset($item_params['hosts'][$host['hostid']])) {
				$item_params['hosts'][$host['hostid']] = ['name' => $host['name'], 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_hosts') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$host = reset($item['hosts']);
				$item_params['hosts'][$host['hostid']]['count']++;
			}
		}

		// applications
		if (!empty($item['applications'])) {
			foreach ($item['applications'] as $application) {
				if (!isset($item_params['applications'][$application['name']])) {
					$item_params['applications'][$application['name']] = ['name' => $application['name'], 'count' => 0];
				}
			}
		}
		$show_item = true;
		foreach ($item['subfilters'] as $name => $value) {
			if ($name == 'subfilter_apps') {
				continue;
			}
			$show_item &= $value;
		}
		$sel_app = false;
		if ($show_item) {
			// if any of item applications are selected
			foreach ($item['applications'] as $app) {
				if (str_in_array($app['name'], $subfilter_apps)) {
					$sel_app = true;
					break;
				}
			}
			foreach ($item['applications'] as $app) {
				if (str_in_array($app['name'], $subfilter_apps) || !$sel_app) {
					$item_params['applications'][$app['name']]['count']++;
				}
			}
		}

		// types
		if ($filter_type == -1) {
			if (!isset($item_params['types'][$item['type']])) {
				$item_params['types'][$item['type']] = ['name' => item_type2str($item['type']), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_types') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['types'][$item['type']]['count']++;
			}
		}

		// value types
		if ($filter_value_type == -1) {
			if (!isset($item_params['value_types'][$item['value_type']])) {
				$item_params['value_types'][$item['value_type']] = [
					'name' => itemValueTypeString($item['value_type']),
					'count' => 0
				];
			}

			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_value_types') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['value_types'][$item['value_type']]['count']++;
			}
		}

		// status
		if ($filter_status == -1) {
			if (!isset($item_params['status'][$item['status']])) {
				$item_params['status'][$item['status']] = [
					'name' => item_status2str($item['status']),
					'count' => 0
				];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_status') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['status'][$item['status']]['count']++;
			}
		}

		// state
		if ($filter_state == -1) {
			if (!isset($item_params['state'][$item['state']])) {
				$item_params['state'][$item['state']] = [
					'name' => itemState($item['state']),
					'count' => 0
				];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_state') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['state'][$item['state']]['count']++;
			}
		}

		// template
		if ($filter_templated_items == -1) {
			if ($item['templateid'] == 0 && !isset($item_params['templated_items'][0])) {
				$item_params['templated_items'][0] = ['name' => _('Not Templated items'), 'count' => 0];
			}
			elseif ($item['templateid'] > 0 && !isset($item_params['templated_items'][1])) {
				$item_params['templated_items'][1] = ['name' => _('Templated items'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_templated_items') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				if ($item['templateid'] == 0) {
					$item_params['templated_items'][0]['count']++;
				}
				else {
					$item_params['templated_items'][1]['count']++;
				}
			}
		}

		// with triggers
		if ($filter_with_triggers == -1) {
			if (count($item['triggers']) == 0 && !isset($item_params['with_triggers'][0])) {
				$item_params['with_triggers'][0] = ['name' => _('Without triggers'), 'count' => 0];
			}
			elseif (count($item['triggers']) > 0 && !isset($item_params['with_triggers'][1])) {
				$item_params['with_triggers'][1] = ['name' => _('With triggers'), 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_with_triggers') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				if (count($item['triggers']) == 0) {
					$item_params['with_triggers'][0]['count']++;
				}
				else {
					$item_params['with_triggers'][1]['count']++;
				}
			}
		}

		// trends
		if (zbx_empty($filter_trends)) {
			if (!isset($item_params['trends'][$item['trends']]) && $item['trends'] !== '') {
				$item_params['trends'][$item['trends']] = ['name' => $item['trends'], 'count' => 0];
			}

			$show_item = true;

			foreach ($item['subfilters'] as $name => $value) {
				if ($name === 'subfilter_trends') {
					continue;
				}
				$show_item &= $value;
			}

			if ($show_item && $item['trends'] !== '') {
				$item_params['trends'][$item['trends']]['count']++;
			}
		}

		// history
		if (zbx_empty($filter_history)) {
			if (!isset($item_params['history'][$item['history']])) {
				$item_params['history'][$item['history']] = ['name' => $item['history'], 'count' => 0];
			}
			$show_item = true;
			foreach ($item['subfilters'] as $name => $value) {
				if ($name == 'subfilter_history') {
					continue;
				}
				$show_item &= $value;
			}
			if ($show_item) {
				$item_params['history'][$item['history']]['count']++;
			}
		}

		// interval
		if (zbx_empty($filter_delay) && $filter_type != ITEM_TYPE_TRAPPER) {
			if (!isset($item_params['interval'][$item['delay']]) && $item['delay'] !== '') {
				$item_params['interval'][$item['delay']] = ['name' => $item['delay'], 'count' => 0];
			}

			$show_item = true;

			foreach ($item['subfilters'] as $name => $value) {
				if ($name === 'subfilter_interval') {
					continue;
				}
				$show_item &= $value;
			}

			if ($show_item && $item['delay'] !== '') {
				$item_params['interval'][$item['delay']]['count']++;
			}
		}
	}

	// output
	if (zbx_empty($filter_hostId) && count($item_params['hosts']) > 1) {
		$hosts_output = prepareSubfilterOutput(_('Hosts'), $item_params['hosts'], $subfilter_hosts, 'subfilter_hosts');
		$table_subfilter->addRow($hosts_output);
	}

	if (!empty($item_params['applications']) && count($item_params['applications']) > 1) {
		$application_output = prepareSubfilterOutput(_('Applications'), $item_params['applications'], $subfilter_apps, 'subfilter_apps');
		$table_subfilter->addRow([$application_output]);
	}

	if ($filter_type == -1 && count($item_params['types']) > 1) {
		$type_output = prepareSubfilterOutput(_('Types'), $item_params['types'], $subfilter_types, 'subfilter_types');
		$table_subfilter->addRow([$type_output]);
	}

	if ($filter_value_type == -1 && count($item_params['value_types']) > 1) {
		$value_types_output = prepareSubfilterOutput(_('Type of information'), $item_params['value_types'], $subfilter_value_types, 'subfilter_value_types');
		$table_subfilter->addRow([$value_types_output]);
	}

	if ($filter_status == -1 && count($item_params['status']) > 1) {
		$status_output = prepareSubfilterOutput(_('Status'), $item_params['status'], $subfilter_status, 'subfilter_status');
		$table_subfilter->addRow([$status_output]);
	}

	if ($filter_state == -1 && count($item_params['state']) > 1) {
		$state_output = prepareSubfilterOutput(_('State'), $item_params['state'], $subfilter_state, 'subfilter_state');
		$table_subfilter->addRow([$state_output]);
	}

	if ($filter_templated_items == -1 && count($item_params['templated_items']) > 1) {
		$templated_items_output = prepareSubfilterOutput(_('Template'), $item_params['templated_items'], $subfilter_templated_items, 'subfilter_templated_items');
		$table_subfilter->addRow([$templated_items_output]);
	}

	if ($filter_with_triggers == -1 && count($item_params['with_triggers']) > 1) {
		$with_triggers_output = prepareSubfilterOutput(_('With triggers'), $item_params['with_triggers'], $subfilter_with_triggers, 'subfilter_with_triggers');
		$table_subfilter->addRow([$with_triggers_output]);
	}

	if (zbx_empty($filter_history) && count($item_params['history']) > 1) {
		$history_output = prepareSubfilterOutput(_('History'), $item_params['history'], $subfilter_history, 'subfilter_history');
		$table_subfilter->addRow([$history_output]);
	}

	if (zbx_empty($filter_trends) && (count($item_params['trends']) > 1)) {
		$trends_output = prepareSubfilterOutput(_('Trends'), $item_params['trends'], $subfilter_trends, 'subfilter_trends');
		$table_subfilter->addRow([$trends_output]);
	}

	if (zbx_empty($filter_delay) && $filter_type != ITEM_TYPE_TRAPPER && count($item_params['interval']) > 1) {
		$interval_output = prepareSubfilterOutput(_('Interval'), $item_params['interval'], $subfilter_interval, 'subfilter_interval');
		$table_subfilter->addRow([$interval_output]);
	}

	$form->setFooter($table_subfilter);

	return $form;
}

/**
 * Get data for item edit page.
 *
 * @param array	$item							Item, item prototype, LLD rule or LLD item to take the data from.
 * @param bool $options['is_discovery_rule']
 *
 * @return array
 */
function getItemFormData(array $item = [], array $options = []) {
	$data = [
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'is_discovery_rule' => !empty($options['is_discovery_rule']),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'itemid' => getRequest('itemid'),
		'limited' => false,
		'interfaceid' => getRequest('interfaceid', 0),
		'name' => getRequest('name', ''),
		'description' => getRequest('description', ''),
		'key' => getRequest('key', ''),
		'hostname' => getRequest('hostname'),
		'delay' => getRequest('delay', ZBX_ITEM_DELAY_DEFAULT),
		'history' => getRequest('history', 90),
		'status' => getRequest('status', isset($_REQUEST['form_refresh']) ? 1 : 0),
		'type' => getRequest('type', 0),
		'snmp_community' => getRequest('snmp_community', 'public'),
		'snmp_oid' => getRequest('snmp_oid', 'interfaces.ifTable.ifEntry.ifInOctets.1'),
		'port' => getRequest('port', ''),
		'value_type' => getRequest('value_type', ITEM_VALUE_TYPE_UINT64),
		'data_type' => getRequest('data_type', ITEM_DATA_TYPE_DECIMAL),
		'trapper_hosts' => getRequest('trapper_hosts', ''),
		'units' => getRequest('units', ''),
		'valuemapid' => getRequest('valuemapid', 0),
		'params' => getRequest('params', ''),
		'multiplier' => getRequest('multiplier', 0),
		'delta' => getRequest('delta', 0),
		'trends' => getRequest('trends', DAY_IN_YEAR),
		'new_application' => getRequest('new_application', ''),
		'applications' => getRequest('applications', []),
		'delay_flex' => getRequest('delay_flex', []),
		'snmpv3_contextname' => getRequest('snmpv3_contextname', ''),
		'snmpv3_securityname' => getRequest('snmpv3_securityname', ''),
		'snmpv3_securitylevel' => getRequest('snmpv3_securitylevel', 0),
		'snmpv3_authprotocol' => getRequest('snmpv3_authprotocol', ITEM_AUTHPROTOCOL_MD5),
		'snmpv3_authpassphrase' => getRequest('snmpv3_authpassphrase', ''),
		'snmpv3_privprotocol' => getRequest('snmpv3_privprotocol', ITEM_PRIVPROTOCOL_DES),
		'snmpv3_privpassphrase' => getRequest('snmpv3_privpassphrase', ''),
		'ipmi_sensor' => getRequest('ipmi_sensor', ''),
		'authtype' => getRequest('authtype', 0),
		'username' => getRequest('username', ''),
		'password' => getRequest('password', ''),
		'publickey' => getRequest('publickey', ''),
		'privatekey' => getRequest('privatekey', ''),
		'formula' => getRequest('formula', 1),
		'logtimefmt' => getRequest('logtimefmt', ''),
		'valuemaps' => null,
		'possibleHostInventories' => null,
		'alreadyPopulated' => null,
		'initial_item_type' => null,
		'templates' => []
	];

	// hostid
	if (!empty($data['parent_discoveryid'])) {
		$discoveryRule = API::DiscoveryRule()->get([
			'output' => ['hostid'],
			'itemids' => $data['parent_discoveryid'],
			'editable' => true
		]);
		$discoveryRule = reset($discoveryRule);
		$data['hostid'] = $discoveryRule['hostid'];

		$data['new_application_prototype'] = getRequest('new_application_prototype', '');
		$data['application_prototypes'] = getRequest('application_prototypes', []);
	}
	else {
		$data['hostid'] = getRequest('hostid', 0);
	}

	// types, http items only for internal processes
	$data['types'] = item_type2str();
	unset($data['types'][ITEM_TYPE_HTTPTEST]);
	if (!empty($options['is_discovery_rule'])) {
		unset($data['types'][ITEM_TYPE_AGGREGATE],
			$data['types'][ITEM_TYPE_CALCULATED],
			$data['types'][ITEM_TYPE_SNMPTRAP]
		);
	}

	// item
	if ($item) {
		$data['item'] = $item;
		$data['hostid'] = !empty($data['hostid']) ? $data['hostid'] : $data['item']['hostid'];
		$data['limited'] = ($data['item']['templateid'] != 0);

		// get templates
		$itemid = $item['itemid'];
		do {
			$params = [
				'itemids' => $itemid,
				'output' => ['itemid', 'templateid'],
				'selectHosts' => ['name']
			];
			if ($data['is_discovery_rule']) {
				$item = API::DiscoveryRule()->get($params);
			}
			else {
				$params['selectDiscoveryRule'] = ['itemid'];
				$params['filter'] = ['flags' => null];
				$item = API::Item()->get($params);
			}
			$item = reset($item);

			if (!empty($item)) {
				$host = reset($item['hosts']);
				if (!empty($item['hosts'])) {
					$host['name'] = CHtml::encode($host['name']);
					if (bccomp($data['itemid'], $itemid) == 0) {
					}
					// discovery rule
					elseif ($data['is_discovery_rule']) {
						$data['templates'][] = new CLink($host['name'],
							'host_discovery.php?form=update&itemid='.$item['itemid']
						);
						$data['templates'][] = SPACE.'&rArr;'.SPACE;
					}
					// item prototype
					elseif ($item['discoveryRule']) {
						$data['templates'][] = new CLink($host['name'], 'disc_prototypes.php?form=update'.
							'&itemid='.$item['itemid'].'&parent_discoveryid='.$item['discoveryRule']['itemid']
						);
						$data['templates'][] = SPACE.'&rArr;'.SPACE;
					}
					// plain item
					else {
						$data['templates'][] = new CLink($host['name'],
							'items.php?form=update&itemid='.$item['itemid']
						);
						$data['templates'][] = SPACE.'&rArr;'.SPACE;
					}
				}
				$itemid = $item['templateid'];
			}
			else {
				break;
			}
		} while ($itemid != 0);

		$data['templates'] = array_reverse($data['templates']);
		array_shift($data['templates']);
	}

	// caption
	if (!empty($data['is_discovery_rule'])) {
		$data['caption'] = _('Discovery rule');
	}
	else {
		$data['caption'] = !empty($data['parent_discoveryid']) ? _('Item prototype') : _('Item');
	}

	// hostname
	if (empty($data['is_discovery_rule']) && empty($data['hostname'])) {
		if (!empty($data['hostid'])) {
			$hostInfo = API::Host()->get([
				'hostids' => $data['hostid'],
				'output' => ['name'],
				'templated_hosts' => true
			]);
			$hostInfo = reset($hostInfo);
			$data['hostname'] = $hostInfo['name'];
		}
		else {
			$data['hostname'] = _('not selected');
		}
	}

	// fill data from item
	if (!hasRequest('form_refresh') && ($item || $data['limited'])) {
		$data['name'] = $data['item']['name'];
		$data['description'] = $data['item']['description'];
		$data['key'] = $data['item']['key_'];
		$data['interfaceid'] = $data['item']['interfaceid'];
		$data['type'] = $data['item']['type'];
		$data['snmp_community'] = $data['item']['snmp_community'];
		$data['snmp_oid'] = $data['item']['snmp_oid'];
		$data['port'] = $data['item']['port'];
		$data['value_type'] = $data['item']['value_type'];
		$data['data_type'] = $data['item']['data_type'];
		$data['trapper_hosts'] = $data['item']['trapper_hosts'];
		$data['units'] = $data['item']['units'];
		$data['valuemapid'] = $data['item']['valuemapid'];
		$data['multiplier'] = $data['item']['multiplier'];
		$data['hostid'] = $data['item']['hostid'];
		$data['params'] = $data['item']['params'];
		$data['snmpv3_contextname'] = $data['item']['snmpv3_contextname'];
		$data['snmpv3_securityname'] = $data['item']['snmpv3_securityname'];
		$data['snmpv3_securitylevel'] = $data['item']['snmpv3_securitylevel'];
		$data['snmpv3_authprotocol'] = $data['item']['snmpv3_authprotocol'];
		$data['snmpv3_authpassphrase'] = $data['item']['snmpv3_authpassphrase'];
		$data['snmpv3_privprotocol'] = $data['item']['snmpv3_privprotocol'];
		$data['snmpv3_privpassphrase'] = $data['item']['snmpv3_privpassphrase'];
		$data['ipmi_sensor'] = $data['item']['ipmi_sensor'];
		$data['authtype'] = $data['item']['authtype'];
		$data['username'] = $data['item']['username'];
		$data['password'] = $data['item']['password'];
		$data['publickey'] = $data['item']['publickey'];
		$data['privatekey'] = $data['item']['privatekey'];
		$data['logtimefmt'] = $data['item']['logtimefmt'];
		$data['new_application'] = getRequest('new_application', '');

		if ($data['parent_discoveryid'] != 0) {
			$data['new_application_prototype'] = getRequest('new_application_prototype', '');
		}

		if (!$data['is_discovery_rule']) {
			$data['formula'] = $data['item']['formula'];
		}

		if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
			$data['delay'] = $data['item']['delay'];
			if (($data['type'] == ITEM_TYPE_TRAPPER || $data['type'] == ITEM_TYPE_SNMPTRAP) && $data['delay'] == 0) {
				$data['delay'] = ZBX_ITEM_DELAY_DEFAULT;
			}
			$data['history'] = $data['item']['history'];
			$data['status'] = $data['item']['status'];
			$data['delta'] = $data['item']['delta'];
			$data['trends'] = $data['item']['trends'];

			$parser = new CItemDelayFlexParser($data['item']['delay_flex']);
			if ($parser->isValid()) {
				foreach ($parser->getIntervals() as $interval) {
					if ($interval['type'] == ITEM_DELAY_FLEX_TYPE_FLEXIBLE) {
						$interval_parts = explode('/', $interval['interval']);
						$data['delay_flex'][] = [
							'delay' => $interval_parts[0],
							'period' => $interval_parts[1],
							'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE
						];
					}
					else {
						$data['delay_flex'][] = [
							'schedule' => $interval['interval'],
							'type' => ITEM_DELAY_FLEX_TYPE_SCHEDULING
						];
					}
				}
			}

			$data['applications'] = array_unique(zbx_array_merge($data['applications'], get_applications_by_itemid($data['itemid'])));

			if ($data['parent_discoveryid'] != 0) {
				/*
				 * Get a list of application prototypes assigned to item prototype. Don't select distinct names,
				 * since database can be accidentally created case insensitive.
				 */
				$application_prototypes = DBfetchArray(DBselect(
					'SELECT ap.name'.
					' FROM application_prototype ap,item_application_prototype iap'.
					' WHERE ap.application_prototypeid=iap.application_prototypeid'.
						' AND ap.itemid='.zbx_dbstr($data['parent_discoveryid']).
						' AND iap.itemid='.zbx_dbstr($data['itemid'])
				));

				// Merge form submitted data with data existing in DB to find diff and correctly display ListBox.
				$data['application_prototypes'] = array_unique(
					zbx_array_merge($data['application_prototypes'], zbx_objectValues($application_prototypes, 'name'))
				);
			}
		}
	}

	if (!$data['delay_flex']) {
		$data['delay_flex'][] = ['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEX_TYPE_FLEXIBLE];
	}

	// applications
	if (count($data['applications']) == 0) {
		array_push($data['applications'], 0);
	}
	$data['db_applications'] = DBfetchArray(DBselect(
		'SELECT DISTINCT a.applicationid,a.name'.
		' FROM applications a'.
		' WHERE a.hostid='.zbx_dbstr($data['hostid']).
			($data['parent_discoveryid'] ? ' AND a.flags='.ZBX_FLAG_DISCOVERY_NORMAL : '')
	));
	order_result($data['db_applications'], 'name');

	if ($data['parent_discoveryid'] != 0) {
		// Make the application prototype list no appearing empty, but filling it with "-None-" as first element.
		if (count($data['application_prototypes']) == 0) {
			$data['application_prototypes'][] = 0;
		}

		// Get a list of application prototypes by discovery rule.
		$data['db_application_prototypes'] = DBfetchArray(DBselect(
			'SELECT ap.application_prototypeid,ap.name'.
			' FROM application_prototype ap'.
			' WHERE ap.itemid='.zbx_dbstr($data['parent_discoveryid'])
		));
		order_result($data['db_application_prototypes'], 'name');
	}

	// interfaces
	$data['interfaces'] = API::HostInterface()->get([
		'hostids' => $data['hostid'],
		'output' => API_OUTPUT_EXTEND
	]);

	if ($data['limited'] || ($item && $data['parent_discoveryid'] === null
			&& $data['item']['flags'] == ZBX_FLAG_DISCOVERY_CREATED)) {
		if ($data['valuemapid'] != 0) {
			$valuemaps = API::ValueMap()->get([
				'output' => ['name'],
				'valuemapids' => [$data['valuemapid']]
			]);

			if ($valuemaps) {
				$data['valuemaps'] = $valuemaps[0]['name'];
			}
		}
	}
	else {
		$data['valuemaps'] = API::ValueMap()->get([
			'output' => ['valemapid', 'name']
		]);

		CArrayHelper::sort($data['valuemaps'], ['name']);
	}

	// possible host inventories
	if (empty($data['parent_discoveryid'])) {
		$data['possibleHostInventories'] = getHostInventories();

		// get already populated fields by other items
		$data['alreadyPopulated'] = API::item()->get([
			'output' => ['inventory_link'],
			'filter' => ['hostid' => $data['hostid']],
			'nopermissions' => true
		]);
		$data['alreadyPopulated'] = zbx_toHash($data['alreadyPopulated'], 'inventory_link');
	}

	// unset snmpv3 fields
	if ($data['type'] != ITEM_TYPE_SNMPV3) {
		$data['snmpv3_contextname'] = '';
		$data['snmpv3_securityname'] = '';
		$data['snmpv3_securitylevel'] = ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV;
		$data['snmpv3_authprotocol'] = ITEM_AUTHPROTOCOL_MD5;
		$data['snmpv3_authpassphrase'] = '';
		$data['snmpv3_privprotocol'] = ITEM_PRIVPROTOCOL_DES;
		$data['snmpv3_privpassphrase'] = '';
	}

	// unset ssh auth fields
	if ($data['type'] != ITEM_TYPE_SSH) {
		$data['authtype'] = ITEM_AUTHTYPE_PASSWORD;
		$data['publickey'] = '';
		$data['privatekey'] = '';
	}

	return $data;
}

function getCopyElementsFormData($elementsField, $title = null) {
	$data = [
		'title' => $title,
		'elements_field' => $elementsField,
		'elements' => getRequest($elementsField, []),
		'copy_type' => getRequest('copy_type', COPY_TYPE_TO_HOST_GROUP),
		'copy_groupid' => getRequest('copy_groupid', 0),
		'copy_targetid' => getRequest('copy_targetid', []),
		'hostid' => getRequest('hostid', 0),
		'groups' => [],
		'hosts' => [],
		'templates' => []
	];

	// validate elements
	if (empty($data['elements']) || !is_array($data['elements'])) {
		error(_('Incorrect list of items.'));

		return null;
	}

	if ($data['copy_type'] == COPY_TYPE_TO_HOST_GROUP) {
		// get groups
		$data['groups'] = API::HostGroup()->get([
			'output' => ['groupid', 'name']
		]);
		order_result($data['groups'], 'name');
	}
	else {
		// hosts or templates
		$params = ['output' => ['name', 'groupid']];

		if ($data['copy_type'] == COPY_TYPE_TO_HOST) {
			$params['real_hosts'] = true;
		}
		else {
			$params['templated_hosts'] = true;
		}

		$data['groups'] = API::HostGroup()->get($params);
		order_result($data['groups'], 'name');

		$groupIds = zbx_objectValues($data['groups'], 'groupid');

		if (!in_array($data['copy_groupid'], $groupIds) || $data['copy_groupid'] == 0) {
			$data['copy_groupid'] = reset($groupIds);
		}

		if ($data['copy_type'] == COPY_TYPE_TO_TEMPLATE) {
			$data['templates'] = API::Template()->get([
				'output' => ['name', 'templateid'],
				'groupids' => $data['copy_groupid']
			]);
			order_result($data['templates'], 'name');
		}
		elseif ($data['copy_type'] == COPY_TYPE_TO_HOST) {
			$data['hosts'] = API::Host()->get([
				'output' => ['name', 'hostid'],
				'groupids' => $data['copy_groupid']
			]);
			order_result($data['hosts'], 'name');
		}
	}

	return $data;
}

function getTriggerMassupdateFormData() {
	$data = [
		'visible' => getRequest('visible', []),
		'priority' => getRequest('priority', ''),
		'dependencies' => getRequest('dependencies', []),
		'tags' => getRequest('tags', []),
		'manual_close' => getRequest('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
		'massupdate' => getRequest('massupdate', 1),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'g_triggerid' => getRequest('g_triggerid', []),
		'priority' => getRequest('priority', 0),
		'config' => select_config(),
		'hostid' => getRequest('hostid', 0)
	];

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		]);

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			]);
			$data['dependencies'] = $dependencyTriggers + $dependencyTriggerPrototypes;
		}
		else {
			$data['dependencies'] = $dependencyTriggers;
		}
	}

	foreach ($data['dependencies'] as &$dependency) {
		order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
	}
	unset($dependency);

	order_result($data['dependencies'], 'description', ZBX_SORT_UP);

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}

	return $data;
}

/**
 * Generate data for the trigger configuration form.
 *
 * @param array $data											Trigger data array.
 * @param string $data['form']									Form action.
 * @param string $data['form_refresh']							Form refresh.
 * @param null|string $data['parent_discoveryid']					Parent discovery.
 * @param array $data['dependencies']							Trigger dependencies.
 * @param array $data['db_dependencies']						DB trigger dependencies.
 * @param string $data['triggerid']								Trigger ID.
 * @param string $data['expression']							Trigger expression.
 * @param string $data['recovery_expression']					Trigger recovery expression.
 * @param string $data['expr_temp']								Trigger temporary expression.
 * @param string $data['recovery_expr_temp']					Trigger temporary recovery expression.
 * @param string $data['recovery_mode']							Trigger recovery mode.
 * @param string $data['description']							Trigger description.
 * @param int $data['type']										Trigger problem event generation mode.
 * @param string $data['priority']								Trigger severity.
 * @param int $data['status']									Trigger status.
 * @param string $data['comments']								Trigger description.
 * @param string $data['url']									Trigger URL.
 * @param string $data['expression_constructor']				Trigger expression constructor mode.
 * @param string $data['recovery_expression_constructor']		Trigger recovery expression constructor mode.
 * @param bool $data['limited']									Templated trigger.
 * @param array $data['templates']								Trigger templates.
 * @param string $data['hostid']								Host ID.
 * @param string $data['expression_action']						Trigger expression action.
 * @param string $data['recovery_expression_action']			Trigger recovery expression action.
 *
 * @return array
 */
function getTriggerFormData(array $data) {
	if ($data['triggerid'] !== null) {
		// Get trigger.
		$options = [
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid'],
			'triggerids' => $data['triggerid']
		];

		if (!hasRequest('form_refresh')) {
			$options['selectTags'] = ['tag', 'value'];
		}

		if ($data['parent_discoveryid'] === null) {
			$options['selectDiscoveryRule'] = ['itemid', 'name'];
			$triggers = API::Trigger()->get($options);
		}
		else {
			$triggers = API::TriggerPrototype()->get($options);
		}

		$triggers = CMacrosResolverHelper::resolveTriggerExpressions($triggers,
			['sources' => ['expression', 'recovery_expression']]
		);

		$trigger = reset($triggers);

		if (!hasRequest('form_refresh')) {
			$data['tags'] = $trigger['tags'];
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
		}

		// Get templates.
		$tmp_triggerid = $data['triggerid'];
		do {
			$db_triggers = DBfetch(DBselect(
				'SELECT t.triggerid,t.templateid,id.parent_itemid,h.name,h.hostid'.
				' FROM triggers t'.
					' LEFT JOIN functions f ON t.triggerid=f.triggerid'.
					' LEFT JOIN items i ON f.itemid=i.itemid'.
					' LEFT JOIN hosts h ON i.hostid=h.hostid'.
					' LEFT JOIN item_discovery id ON i.itemid=id.itemid'.
				' WHERE t.triggerid='.zbx_dbstr($tmp_triggerid)
			));
			if (bccomp($data['triggerid'], $tmp_triggerid) != 0) {
				// parent trigger prototype link
				if ($data['parent_discoveryid']) {
					$link = 'trigger_prototypes.php?form=update&triggerid='.$db_triggers['triggerid'].
						'&parent_discoveryid='.$db_triggers['parent_itemid'].'&hostid='.$db_triggers['hostid'];
				}
				// parent trigger link
				else {
					$link = 'triggers.php?form=update&triggerid='.$db_triggers['triggerid'].
						'&hostid='.$db_triggers['hostid'];
				}

				$data['templates'][] = new CLink(CHtml::encode($db_triggers['name']), $link);
				$data['templates'][] = SPACE.'&rArr;'.SPACE;
			}
			$tmp_triggerid = $db_triggers['templateid'];
		} while ($tmp_triggerid != 0);

		$data['templates'] = array_reverse($data['templates']);
		array_shift($data['templates']);

		$data['limited'] = ($trigger['templateid'] != 0);

		// select first host from triggers if gived not match
		$hosts = $trigger['hosts'];
		if (count($hosts) > 0 && !in_array(['hostid' => $data['hostid']], $hosts)) {
			$host = reset($hosts);
			$data['hostid'] = $host['hostid'];
		}
	}

	if ((!empty($data['triggerid']) && !isset($_REQUEST['form_refresh'])) || $data['limited']) {
		$data['expression'] = $trigger['expression'];
		$data['recovery_expression'] = $trigger['recovery_expression'];

		if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
			$data['description'] = $trigger['description'];
			$data['type'] = $trigger['type'];
			$data['recovery_mode'] = $trigger['recovery_mode'];
			$data['correlation_mode'] = $trigger['correlation_mode'];
			$data['correlation_tag'] = $trigger['correlation_tag'];
			$data['manual_close'] = $trigger['manual_close'];
			$data['priority'] = $trigger['priority'];
			$data['status'] = $trigger['status'];
			$data['comments'] = $trigger['comments'];
			$data['url'] = $trigger['url'];

			$db_triggers = DBselect(
				'SELECT t.triggerid,t.description'.
				' FROM triggers t,trigger_depends d'.
				' WHERE t.triggerid=d.triggerid_up'.
					' AND d.triggerid_down='.zbx_dbstr($data['triggerid'])
			);
			while ($db_trigger = DBfetch($db_triggers)) {
				if (uint_in_array($db_trigger['triggerid'], $data['dependencies'])) {
					continue;
				}
				array_push($data['dependencies'], $db_trigger['triggerid']);
			}
		}
	}

	$readonly = false;
	if ($data['triggerid'] !== null) {
		$data['flags'] = $trigger['flags'];

		if ($data['parent_discoveryid'] === null) {
			$data['discoveryRule'] = $trigger['discoveryRule'];
		}

		if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $data['limited']) {
			$readonly = true;
		}
	}

	// Trigger expression constructor.
	if ($data['expression_constructor'] == IM_TREE) {
		$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION);
		if ($analyze !== false) {
			list($data['expression_formula'], $data['expression_tree']) = $analyze;
			if ($data['expression_action'] !== '' && $data['expression_tree'] !== null) {
				$new_expr = remakeExpression($data['expression'], $_REQUEST['expr_target_single'],
					$data['expression_action'], $data['expr_temp']
				);
				if ($new_expr !== false) {
					$data['expression'] = $new_expr;
					$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION);
					if ($analyze !== false) {
						list($data['expression_formula'], $data['expression_tree']) = $analyze;
					}
					else {
						show_messages(false, '', _('Expression syntax error.'));
					}
					$data['expr_temp'] = '';
				}
				else {
					show_messages(false, '', _('Expression syntax error.'));
				}
			}
			$data['expression_field_name'] = 'expr_temp';
			$data['expression_field_value'] = $data['expr_temp'];
			$data['expression_field_readonly'] = true;
		}
		else {
			show_messages(false, '', _('Expression syntax error.'));
			$data['expression_field_name'] = 'expression';
			$data['expression_field_value'] = $data['expression'];
			$data['expression_field_readonly'] = $readonly;
			$data['expression_constructor'] = IM_ESTABLISHED;
		}
	}
	elseif ($data['expression_constructor'] != IM_TREE) {
		$data['expression_field_name'] = 'expression';
		$data['expression_field_value'] = $data['expression'];
		$data['expression_field_readonly'] = $readonly;
	}

	// Trigger recovery expression constructor.
	if ($data['recovery_expression_constructor'] == IM_TREE) {
		$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION);
		if ($analyze !== false) {
			list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;
			if ($data['recovery_expression_action'] !== '' && $data['recovery_expression_tree'] !== null) {
				$new_expr = remakeExpression($data['recovery_expression'], $_REQUEST['recovery_expr_target_single'],
					$data['recovery_expression_action'], $data['recovery_expr_temp']
				);

				if ($new_expr !== false) {
					$data['recovery_expression'] = $new_expr;
					$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION);
					if ($analyze !== false) {
						list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;
					}
					else {
						show_messages(false, '', _('Recovery expression syntax error.'));
					}
					$data['recovery_expr_temp'] = '';
				}
				else {
					show_messages(false, '', _('Recovery expression syntax error.'));
				}
			}
			$data['recovery_expression_field_name'] = 'recovery_expr_temp';
			$data['recovery_expression_field_value'] = $data['recovery_expr_temp'];
			$data['recovery_expression_field_readonly'] = true;
		}
		else {
			show_messages(false, '', _('Recovery expression syntax error.'));
			$data['recovery_expression_field_name'] = 'recovery_expression';
			$data['recovery_expression_field_value'] = $data['recovery_expression'];
			$data['recovery_expression_field_readonly'] = $readonly;
			$data['recovery_expression_constructor'] = IM_ESTABLISHED;
		}
	}
	elseif ($data['recovery_expression_constructor'] != IM_TREE) {
		$data['recovery_expression_field_name'] = 'recovery_expression';
		$data['recovery_expression_field_value'] = $data['recovery_expression'];
		$data['recovery_expression_field_readonly'] = $readonly;
	}

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'flags'],
			'selectHosts' => ['hostid', 'name'],
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		]);

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			]);

			$data['db_dependencies'] = $dependencyTriggers + $dependencyTriggerPrototypes;
		}
		else {
			$data['db_dependencies'] = $dependencyTriggers;
		}
	}

	foreach ($data['db_dependencies'] as &$dependency) {
		order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
	}
	unset($dependency);

	order_result($data['db_dependencies'], 'description');

	if (!$data['tags']) {
		$data['tags'][] = ['tag' => '', 'value' => ''];
	}

	return $data;
}

function get_timeperiod_form() {
	$tblPeriod = new CTable();

	// init new_timeperiod variable
	$new_timeperiod = getRequest('new_timeperiod', []);
	$new = is_array($new_timeperiod);

	if (is_array($new_timeperiod)) {
		if (isset($new_timeperiod['id'])) {
			$tblPeriod->addItem(new CVar('new_timeperiod[id]', $new_timeperiod['id']));
		}
		if (isset($new_timeperiod['timeperiodid'])) {
			$tblPeriod->addItem(new CVar('new_timeperiod[timeperiodid]', $new_timeperiod['timeperiodid']));
		}
	}
	if (!is_array($new_timeperiod)) {
		$new_timeperiod = [];
		$new_timeperiod['timeperiod_type'] = TIMEPERIOD_TYPE_ONETIME;
	}
	if (!isset($new_timeperiod['every'])) {
		$new_timeperiod['every'] = 1;
	}
	if (!isset($new_timeperiod['day'])) {
		$new_timeperiod['day'] = 1;
	}
	if (!isset($new_timeperiod['hour'])) {
		$new_timeperiod['hour'] = 12;
	}
	if (!isset($new_timeperiod['minute'])) {
		$new_timeperiod['minute'] = 0;
	}
	if (!isset($new_timeperiod['start_date'])) {
		$new_timeperiod['start_date'] = 0;
	}
	if (!isset($new_timeperiod['period_days'])) {
		$new_timeperiod['period_days'] = 0;
	}
	if (!isset($new_timeperiod['period_hours'])) {
		$new_timeperiod['period_hours'] = 1;
	}
	if (!isset($new_timeperiod['period_minutes'])) {
		$new_timeperiod['period_minutes'] = 0;
	}
	if (!isset($new_timeperiod['month_date_type'])) {
		$new_timeperiod['month_date_type'] = !(bool)$new_timeperiod['day'];
	}

	// start time
	if (isset($new_timeperiod['start_time'])) {
		$new_timeperiod['hour'] = floor($new_timeperiod['start_time'] / SEC_PER_HOUR);
		$new_timeperiod['minute'] = floor(($new_timeperiod['start_time'] - ($new_timeperiod['hour'] * SEC_PER_HOUR)) / SEC_PER_MIN);
	}

	// period
	if (isset($new_timeperiod['period'])) {
		$new_timeperiod['period_days'] = floor($new_timeperiod['period'] / SEC_PER_DAY);
		$new_timeperiod['period_hours'] = floor(($new_timeperiod['period'] - ($new_timeperiod['period_days'] * SEC_PER_DAY)) / SEC_PER_HOUR);
		$new_timeperiod['period_minutes'] = floor(($new_timeperiod['period'] - $new_timeperiod['period_days'] * SEC_PER_DAY - $new_timeperiod['period_hours'] * SEC_PER_HOUR) / SEC_PER_MIN);
	}

	// daysofweek
	$dayofweek = '';
	$dayofweek .= !isset($new_timeperiod['dayofweek_mo']) ? '0' : '1';
	$dayofweek .= !isset($new_timeperiod['dayofweek_tu']) ? '0' : '1';
	$dayofweek .= !isset($new_timeperiod['dayofweek_we']) ? '0' : '1';
	$dayofweek .= !isset($new_timeperiod['dayofweek_th']) ? '0' : '1';
	$dayofweek .= !isset($new_timeperiod['dayofweek_fr']) ? '0' : '1';
	$dayofweek .= !isset($new_timeperiod['dayofweek_sa']) ? '0' : '1';
	$dayofweek .= !isset($new_timeperiod['dayofweek_su']) ? '0' : '1';
	if (isset($new_timeperiod['dayofweek'])) {
		$dayofweek = zbx_num2bitstr($new_timeperiod['dayofweek'], true);
	}

	$new_timeperiod['dayofweek_mo'] = $dayofweek[0];
	$new_timeperiod['dayofweek_tu'] = $dayofweek[1];
	$new_timeperiod['dayofweek_we'] = $dayofweek[2];
	$new_timeperiod['dayofweek_th'] = $dayofweek[3];
	$new_timeperiod['dayofweek_fr'] = $dayofweek[4];
	$new_timeperiod['dayofweek_sa'] = $dayofweek[5];
	$new_timeperiod['dayofweek_su'] = $dayofweek[6];

	// months
	$month = '';
	$month .= !isset($new_timeperiod['month_jan']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_feb']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_mar']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_apr']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_may']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_jun']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_jul']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_aug']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_sep']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_oct']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_nov']) ? '0' : '1';
	$month .= !isset($new_timeperiod['month_dec']) ? '0' : '1';
	if (isset($new_timeperiod['month'])) {
		$month = zbx_num2bitstr($new_timeperiod['month'], true);
	}

	$new_timeperiod['month_jan'] = $month[0];
	$new_timeperiod['month_feb'] = $month[1];
	$new_timeperiod['month_mar'] = $month[2];
	$new_timeperiod['month_apr'] = $month[3];
	$new_timeperiod['month_may'] = $month[4];
	$new_timeperiod['month_jun'] = $month[5];
	$new_timeperiod['month_jul'] = $month[6];
	$new_timeperiod['month_aug'] = $month[7];
	$new_timeperiod['month_sep'] = $month[8];
	$new_timeperiod['month_oct'] = $month[9];
	$new_timeperiod['month_nov'] = $month[10];
	$new_timeperiod['month_dec'] = $month[11];

	$bit_dayofweek = strrev($dayofweek);
	$bit_month = strrev($month);

	$cmbType = new CComboBox('new_timeperiod[timeperiod_type]', $new_timeperiod['timeperiod_type'], 'submit()');
	$cmbType->addItem(TIMEPERIOD_TYPE_ONETIME, _('One time only'));
	$cmbType->addItem(TIMEPERIOD_TYPE_DAILY, _('Daily'));
	$cmbType->addItem(TIMEPERIOD_TYPE_WEEKLY, _('Weekly'));
	$cmbType->addItem(TIMEPERIOD_TYPE_MONTHLY, _('Monthly'));

	$tblPeriod->addRow([_('Period type'), $cmbType]);

	if ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) {
		$tblPeriod->addItem(new CVar('new_timeperiod[dayofweek]', bindec($bit_dayofweek)));
		$tblPeriod->addItem(new CVar('new_timeperiod[month]', bindec($bit_month)));
		$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day']));
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));
		$tblPeriod->addItem(new CVar('new_timeperiod[month_date_type]', $new_timeperiod['month_date_type']));
		$tblPeriod->addRow([_('Every day(s)'),
			(new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 3))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		]);
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) {
		$tblPeriod->addItem(new CVar('new_timeperiod[month]', bindec($bit_month)));
		$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day']));
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));
		$tblPeriod->addItem(new CVar('new_timeperiod[month_date_type]', $new_timeperiod['month_date_type']));
		$tblPeriod->addRow([_('Every week(s)'),
			(new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 2))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		]);

		$tabDays = (new CTable())
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_mo]'))->setChecked($dayofweek[0] == 1), _('Monday')],
					'new_timeperiod[dayofweek_mo]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_tu]'))->setChecked($dayofweek[1] == 1), _('Tuesday')],
					'new_timeperiod[dayofweek_tu]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_we]'))->setChecked($dayofweek[2] == 1), _('Wednesday')],
					'new_timeperiod[dayofweek_we]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_th]'))->setChecked($dayofweek[3] == 1), _('Thursday')],
					'new_timeperiod[dayofweek_th]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_fr]'))->setChecked($dayofweek[4] == 1), _('Friday')],
					'new_timeperiod[dayofweek_fr]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_sa]'))->setChecked($dayofweek[5] == 1), _('Saturday')],
					'new_timeperiod[dayofweek_sa]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[dayofweek_su]'))->setChecked($dayofweek[6] == 1), _('Sunday')],
					'new_timeperiod[dayofweek_su]'
				)
			]);
		$tblPeriod->addRow([_('Day of week'), $tabDays]);
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));

		$tabMonths = (new CTable())
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_jan]'))->setChecked($month[0] == 1), _('January')],
					'new_timeperiod[month_jan]'
				),
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_jul]'))->setChecked($month[6] == 1), _('July')],
					'new_timeperiod[month_jul]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_feb]'))->setChecked($month[1] == 1), _('February')],
					'new_timeperiod[month_feb]'
				),
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_aug]'))->setChecked($month[7] == 1), _('August')],
					'new_timeperiod[month_aug]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_mar]'))->setChecked($month[2] == 1), _('March')],
					'new_timeperiod[month_mar]'
				),
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_sep]'))->setChecked($month[8] == 1), _('September')],
					'new_timeperiod[month_sep]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_apr]'))->setChecked($month[3] == 1), _('April')],
					'new_timeperiod[month_apr]'
				),
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_oct]'))->setChecked($month[9] == 1), _('October')],
					'new_timeperiod[month_oct]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_may]'))->setChecked($month[4] == 1), _('May')],
					'new_timeperiod[month_may]'
				),
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_nov]'))->setChecked($month[10] == 1), _('November')],
					'new_timeperiod[month_nov]'
				)
			])
			->addRow([
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_jun]'))->setChecked($month[5] == 1), _('June')],
					'new_timeperiod[month_jun]'
				),
				new CLabel(
					[(new CCheckBox('new_timeperiod[month_dec]'))->setChecked($month[11] == 1), _('December')],
					'new_timeperiod[month_dec]'
				)
			]);
		$tblPeriod->addRow([_('Month'), $tabMonths]);

		$tblPeriod->addRow([_('Date'),
			(new CRadioButtonList('new_timeperiod[month_date_type]', (int) $new_timeperiod['month_date_type']))
				->addValue(_('Day'), 0, null, 'submit()')
				->addValue(_('Day of week'), 1, null, 'submit()')
				->setModern(true)
		]);

		if ($new_timeperiod['month_date_type'] > 0) {
			$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day']));

			$cmbCount = new CComboBox('new_timeperiod[every]', $new_timeperiod['every']);
			$cmbCount->addItem(1, _('First'));
			$cmbCount->addItem(2, _('Second'));
			$cmbCount->addItem(3, _('Third'));
			$cmbCount->addItem(4, _('Fourth'));
			$cmbCount->addItem(5, _('Last'));

			$td = (new CCol($cmbCount))->setColSpan(2);

			$tabDays = (new CTable())
				->addRow($td)
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_mo]'))->setChecked($dayofweek[0] == 1), _('Monday')],
						'new_timeperiod[dayofweek_mo]'
					)
				])
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_tu]'))->setChecked($dayofweek[1] == 1), _('Tuesday')],
						'new_timeperiod[dayofweek_tu]'
					)
				])
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_we]'))->setChecked($dayofweek[2] == 1), _('Wednesday')],
						'new_timeperiod[dayofweek_we]'
					)
				])
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_th]'))->setChecked($dayofweek[3] == 1), _('Thursday')],
						'new_timeperiod[dayofweek_th]'
					)
				])
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_fr]'))->setChecked($dayofweek[4] == 1), _('Friday')],
						'new_timeperiod[dayofweek_fr]'
					)
				])
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_sa]'))->setChecked($dayofweek[5] == 1), _('Saturday')],
						'new_timeperiod[dayofweek_sa]'
					)
				])
				->addRow([
					new CLabel(
						[(new CCheckBox('new_timeperiod[dayofweek_su]'))->setChecked($dayofweek[6] == 1), _('Sunday')],
						'new_timeperiod[dayofweek_su]'
					)
				]);
			$tblPeriod->addRow([_('Day of week'), $tabDays]);
		}
		else {
			$tblPeriod->addItem(new CVar('new_timeperiod[dayofweek]', bindec($bit_dayofweek)));
			$tblPeriod->addRow([_('Day of month'),
				(new CNumericBox('new_timeperiod[day]', $new_timeperiod['day'], 2))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			]);
		}
	}
	else {
		$tblPeriod->addItem(new CVar('new_timeperiod[every]', $new_timeperiod['every'], 'new_timeperiod_every_tmp'));
		$tblPeriod->addItem(new CVar('new_timeperiod[month]', bindec($bit_month), 'new_timeperiod_month_tmp'));
		$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day'], 'new_timeperiod_day_tmp'));
		$tblPeriod->addItem(new CVar('new_timeperiod[hour]', $new_timeperiod['hour'], 'new_timeperiod_hour_tmp'));
		$tblPeriod->addItem(new CVar('new_timeperiod[minute]', $new_timeperiod['minute'], 'new_timeperiod_minute_tmp'));
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));
		$tblPeriod->addItem(new CVar('new_timeperiod[month_date_type]', $new_timeperiod['month_date_type']));
		$tblPeriod->addItem(new CVar('new_timeperiod[dayofweek]', bindec($bit_dayofweek)));

		if (isset($_REQUEST['add_timeperiod'])) {
			$date = [
				'y' => getRequest('new_timeperiod_start_date_year'),
				'm' => getRequest('new_timeperiod_start_date_month'),
				'd' => getRequest('new_timeperiod_start_date_day'),
				'h' => getRequest('new_timeperiod_start_date_hour'),
				'i' => getRequest('new_timeperiod_start_date_minute')
			];
		}
		else {
			$date = zbxDateToTime($new_timeperiod['start_date']
				? $new_timeperiod['start_date'] : date(TIMESTAMP_FORMAT_ZERO_TIME, time()));
		}

		$tblPeriod->addRow([_('Date'), createDateSelector('new_timeperiod_start_date', $date)]);
	}

	if ($new_timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_ONETIME) {
		$tblPeriod->addRow([_('At (hour:minute)'), [
			(new CNumericBox('new_timeperiod[hour]', $new_timeperiod['hour'], 2))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			':',
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CNumericBox('new_timeperiod[minute]', $new_timeperiod['minute'], 2))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		]]);
	}

	$perHours = new CComboBox('new_timeperiod[period_hours]', $new_timeperiod['period_hours'], null, range(0, 23));
	$perMinutes = new CComboBox('new_timeperiod[period_minutes]', $new_timeperiod['period_minutes'], null, range(0, 59));
	$tblPeriod->addRow([
		_('Maintenance period length'),
		[
			(new CNumericBox('new_timeperiod[period_days]', $new_timeperiod['period_days'], 3))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			_('Days').SPACE.SPACE,
			$perHours,
			_('Hours').SPACE.SPACE,
			$perMinutes,
			_('Minutes')
	]]);

	return $tblPeriod;
}
