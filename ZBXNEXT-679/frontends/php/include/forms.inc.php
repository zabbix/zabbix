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
	$data = array(
		'is_profile' => $isProfile,
		'config' => $config
	);

	if ($userId != 0 && (!hasRequest('form_refresh') || hasRequest('register'))) {
		$users = API::User()->get(array(
			'output' => array('alias', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'theme', 'refresh',
				'rows_per_page', 'type'
			),
			'userids' => $userId
		));
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

		$userGroups = API::UserGroup()->get(array(
			'output' => array('usrgrpid'),
			'userids' => $userId
		));
		$userGroup = zbx_objectValues($userGroups, 'usrgrpid');
		$data['user_groups']	= zbx_toHash($userGroup);

		$data['user_medias'] = array();
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
		$data['user_groups']	= getRequest('user_groups', array());
		$data['change_password']= getRequest('change_password');
		$data['user_medias']	= getRequest('user_medias', array());

		// set messages
		$data['messages'] = getRequest('messages', array());
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
			$data['messages']['triggers.severities'] = array();
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
		$mediaTypeDescriptions = array();
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

		CArrayHelper::sort($data['user_medias'], array('description', 'sendto'));
	}

	// set user rights
	if (!$data['is_profile']) {
		$data['groups'] = API::UserGroup()->get(array(
			'output' => array('usrgrpid', 'name'),
			'usrgrpids' => $data['user_groups']
		));
		order_result($data['groups'], 'name');

		$group_ids = array_values($data['user_groups']);
		if (count($group_ids) == 0) {
			$group_ids = array(-1);
		}
		$db_rights = DBselect('SELECT r.* FROM rights r WHERE '.dbConditionInt('r.groupid', $group_ids));

		// deny beat all, read-write beat read
		$tmp_permitions = array();
		while ($db_right = DBfetch($db_rights)) {
			if (isset($tmp_permitions[$db_right['id']]) && $tmp_permitions[$db_right['id']] != PERM_DENY) {
				$tmp_permitions[$db_right['id']] = ($db_right['permission'] == PERM_DENY)
					? PERM_DENY
					: max($tmp_permitions[$db_right['id']], $db_right['permission']);
			}
			else {
				$tmp_permitions[$db_right['id']] = $db_right['permission'];
			}
		}

		$data['user_rights'] = array();
		foreach ($tmp_permitions as $id => $permition) {
			array_push($data['user_rights'], array('id' => $id, 'permission' => $permition));
		}
	}

	return $data;
}

function getPermissionsFormList($rights = array(), $user_type = USER_TYPE_ZABBIX_USER, $rightsFormList = null) {
	// group
	$lists['group']['label']		= _('Host groups');
	$lists['group']['read_write']	= new CListBox('groups_write', null, 15);
	$lists['group']['read_only']	= new CListBox('groups_read', null, 15);
	$lists['group']['deny']			= new CListBox('groups_deny', null, 15);

	$groups = get_accessible_groups_by_rights($rights, $user_type, PERM_DENY);

	foreach ($groups as $group) {
		switch($group['permission']) {
			case PERM_READ:
				$list_name = 'read_only';
				break;
			case PERM_READ_WRITE:
				$list_name = 'read_write';
				break;
			default:
				$list_name = 'deny';
		}
		$lists['group'][$list_name]->addItem($group['groupid'], $group['name']);
	}
	unset($groups);

	// host
	$lists['host']['label']		= _('Hosts');
	$lists['host']['read_write']= new CListBox('hosts_write', null, 15);
	$lists['host']['read_only']	= new CListBox('hosts_read', null, 15);
	$lists['host']['deny']		= new CListBox('hosts_deny', null, 15);

	$hosts = get_accessible_hosts_by_rights($rights, $user_type, PERM_DENY);

	foreach ($hosts as $host) {
		switch($host['permission']) {
			case PERM_READ:
				$list_name = 'read_only';
				break;
			case PERM_READ_WRITE:
				$list_name = 'read_write';
				break;
			default:
				$list_name = 'deny';
		}
		if (HOST_STATUS_PROXY_ACTIVE == $host['status'] || HOST_STATUS_PROXY_PASSIVE == $host['status']) {
			$host['host_name'] = $host['host'];
		}
		$lists['host'][$list_name]->addItem($host['hostid'], $host['host_name']);
	}
	unset($hosts);

	// display
	if (empty($rightsFormList)) {
		$rightsFormList = new CFormList('rightsFormList');
	}
	$isHeaderDisplayed = false;
	foreach ($lists as $list) {
		$sLabel = '';
		$row = new CRow();
		foreach ($list as $class => $item) {
			if (is_string($item)) {
				$sLabel = $item;
			}
			else {
				$row->addItem(new CCol($item, $class));
			}
		}

		$table = new CTable(_('No accessible resources'), 'right_table calculated');
		if (!$isHeaderDisplayed) {
			$table->setHeader(array(_('Read-write'), _('Read only'), _('Deny')), 'header');
			$isHeaderDisplayed = true;
		}
		$table->addRow($row);
		$rightsFormList->addRow($sLabel, $table);
	}
	return $rightsFormList;
}

function prepareSubfilterOutput($label, $data, $subfilter, $subfilterName) {
	order_result($data, 'name');

	$output = array(new CTag('h3', 'yes', $label));

	foreach ($data as $id => $element) {
		$element['name'] = nbsp(CHtml::encode($element['name']));

		// is activated
		if (str_in_array($id, $subfilter)) {
			$link = new CLink($element['name'], null, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREEN);
			$link->onClick(CHtml::encode(
				'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
				'create_var("zbx_filter", '.CJs::encodeJson($subfilterName.'['.$id.']').', null, true);'
			));
			$output[] = $link;
			$output[] = new CSup(SPACE.$element['count']);
		}

		// isn't activated
		else {
			// subfilter has 0 items
			if ($element['count'] == 0) {
				$output[] = new CLink($element['name'], null,  ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREY);
				$output[] = new CSup(SPACE.$element['count']);
			}
			else {
				// this level has no active subfilters
				$nspan = $subfilter
					? new CSup(SPACE.'+'.$element['count'])
					: new CSup(SPACE.$element['count']);

				$link = new CLink($element['name'], null, ZBX_STYLE_LINK_ACTION);
				$link->onClick(CHtml::encode(
					'javascript: create_var("zbx_filter", "subfilter_set", "1", false);'.
					'create_var("zbx_filter", '.
						CJs::encodeJson($subfilterName.'['.$id.']').', '.
						CJs::encodeJson($id).', '.
						'true'.
					');'
				));

				$output[] = $link;
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

	$form = new CFilter('web.items.filter.state');
	$form->addVar('subfilter_hosts', $subfilter_hosts);
	$form->addVar('subfilter_apps', $subfilter_apps);
	$form->addVar('subfilter_types', $subfilter_types);
	$form->addVar('subfilter_value_types', $subfilter_value_types);
	$form->addVar('subfilter_status', $subfilter_status);
	$form->addVar('subfilter_state', $subfilter_state);
	$form->addVar('subfilter_templated_items', $subfilter_templated_items);
	$form->addVar('subfilter_with_triggers', $subfilter_with_triggers);
	$form->addVar('subfilter_history', $subfilter_history);
	$form->addVar('subfilter_trends', $subfilter_trends);
	$form->addVar('subfilter_interval', $subfilter_interval);

	$filterColumn1 = new CFormList();
	$filterColumn2 = new CFormList();
	$filterColumn3 = new CFormList();
	$filterColumn4 = new CFormList();

	// type select
	$fTypeVisibility = array();
	$cmbType = new CComboBox('filter_type', $filter_type);
	$cmbType->setAttribute('id', 'filter_type');
	$cmbType->addItem(-1, _('all'));
	foreach (array('filter_delay_label', 'filter_delay') as $vItem) {
		zbx_subarray_push($fTypeVisibility, -1, $vItem);
	}

	$itemTypes = item_type2str();
	unset($itemTypes[ITEM_TYPE_HTTPTEST]); // httptest items are only for internal zabbix logic

	$cmbType->addItems($itemTypes);

	foreach ($itemTypes as $typeNum => $typeLabel) {
		if ($typeNum != ITEM_TYPE_TRAPPER) {
			zbx_subarray_push($fTypeVisibility, $typeNum, 'filter_delay_label');
			zbx_subarray_push($fTypeVisibility, $typeNum, 'filter_delay');
		}

		switch ($typeNum) {
			case ITEM_TYPE_SNMPV1:
			case ITEM_TYPE_SNMPV2C:
				$snmp_types = array(
					'filter_snmp_community_label', 'filter_snmp_community',
					'filter_snmp_oid_label', 'filter_snmp_oid',
					'filter_port_label', 'filter_port'
				);
				foreach ($snmp_types as $vItem) {
					zbx_subarray_push($fTypeVisibility, $typeNum, $vItem);
				}
				break;
			case ITEM_TYPE_SNMPV3:
				foreach (array('filter_snmpv3_securityname_label', 'filter_snmpv3_securityname', 'filter_snmp_oid_label',
					'filter_snmp_oid', 'filter_port_label', 'filter_port') as $vItem) {
					zbx_subarray_push($fTypeVisibility, $typeNum, $vItem);
				}
				break;
		}
	}

	zbx_add_post_js("var filterTypeSwitcher = new CViewSwitcher('filter_type', 'change', ".zbx_jsvalue($fTypeVisibility, true).');');

	// type of information select
	$fVTypeVisibility = array();

	$cmbValType = new CComboBox('filter_value_type', $filter_value_type);
	$cmbValType->addItem(-1, _('all'));
	$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64, _('Numeric (unsigned)'));
	$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT, _('Numeric (float)'));
	$cmbValType->addItem(ITEM_VALUE_TYPE_STR, _('Character'));
	$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, _('Log'));
	$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT, _('Text'));

	foreach (array('filter_data_type_label','filter_data_type') as $vItem) {
		zbx_subarray_push($fVTypeVisibility, ITEM_VALUE_TYPE_UINT64, $vItem);
	}

	zbx_add_post_js("var filterValueTypeSwitcher = new CViewSwitcher('filter_value_type', 'change', ".zbx_jsvalue($fVTypeVisibility, true).');');

	// status select
	$cmbStatus = new CComboBox('filter_status', $filter_status);
	$cmbStatus->addItem(-1, _('all'));
	foreach (array(ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED) as $status) {
		$cmbStatus->addItem($status, item_status2str($status));
	}

	// state select
	$cmbState = new CComboBox('filter_state', $filter_state);
	$cmbState->addItem(-1, _('all'));
	foreach (array(ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED) as $state) {
		$cmbState->addItem($state, itemState($state));
	}

	// update interval
	$updateIntervalLabel = new CSpan(_('Update interval (in sec)'));
	$updateIntervalLabel->setAttribute('id', 'filter_delay_label');

	$updateIntervalInput = new CNumericBox('filter_delay', $filter_delay, 5, false, true);

	// data type
	$dataTypeLabel = new CSpan(bold(_('Data type').NAME_DELIMITER));
	$dataTypeLabel->setAttribute('id', 'filter_data_type_label');

	$dataTypeInput = new CComboBox('filter_data_type', $filter_data_type);
	$dataTypeInput->addItem(-1, _('all'));
	$dataTypeInput->addItems(item_data_type2str());

	// SNMP community
	$snmpCommunityLabel = new CSpan(array(bold(_('SNMP community')), SPACE._('like').NAME_DELIMITER));
	$snmpCommunityLabel->setAttribute('id', 'filter_snmp_community_label');

	$snmpCommunityField = new CTextBox('filter_snmp_community', $filter_snmp_community, ZBX_TEXTBOX_FILTER_SIZE);

	// SNMPv3 security name
	$snmpSecurityLabel = new CSpan(array(bold(_('Security name')), SPACE._('like').NAME_DELIMITER));
	$snmpSecurityLabel->setAttribute('id', 'filter_snmpv3_securityname_label');

	$snmpSecurityField = new CTextBox('filter_snmpv3_securityname', $filter_snmpv3_securityname, ZBX_TEXTBOX_FILTER_SIZE);

	// SNMP OID
	$snmpOidLabel = new CSpan(array(bold(_('SNMP OID')), SPACE._('like').NAME_DELIMITER));
	$snmpOidLabel->setAttribute('id', 'filter_snmp_oid_label');

	$snmpOidField = new CTextBox('filter_snmp_oid', $filter_snmp_oid, ZBX_TEXTBOX_FILTER_SIZE);

	// port
	$portLabel = new CSpan(array(bold(_('Port')), SPACE._('like').NAME_DELIMITER));
	$portLabel->setAttribute('id', 'filter_port_label');

	$portField = new CNumericBox('filter_port', $filter_port, 5, false, true);

	// row 1
	$groupFilter = null;
	if (!empty($filter_groupId)) {
		$getHostInfo = API::HostGroup()->get(array(
			'groupids' => $filter_groupId,
			'output' => array('name')
		));
		$getHostInfo = reset($getHostInfo);
		if (!empty($getHostInfo)) {
			$groupFilter[] = array(
				'id' => $getHostInfo['groupid'],
				'name' => $getHostInfo['name']
			);
		}
	}

	$filterColumn1->addRow(_('Host group'),
		new CMultiSelect(array(
			'name' => 'filter_groupid',
			'selectedLimit' => 1,
			'objectName' => 'hostGroup',
			'objectOptions' => array(
				'editable' => true
			),
			'data' => $groupFilter,
			'popup' => array(
				'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1=filter_groupid'.
					'&srcfld1=groupid&writeonly=1'
			)
		))
	);

	$filterColumn2->addRow(_('Type'), $cmbType);
	$filterColumn3->addRow(_('Type of information'), $cmbValType);
	$filterColumn4->addRow(_('State'), $cmbState);

	// row 2
	$hostFilterData = null;
	if (!empty($filter_hostId)) {
		$getHostInfo = API::Host()->get(array(
			'hostids' => $filter_hostId,
			'templated_hosts' => true,
			'output' => array('name')
		));
		$getHostInfo = reset($getHostInfo);
		if (!empty($getHostInfo)) {
			$hostFilterData[] = array(
				'id' => $getHostInfo['hostid'],
				'name' => $getHostInfo['name']
			);
		}
	}

	$filterColumn1->addRow(_('Host'),
		new CMultiSelect(array(
			'name' => 'filter_hostid',
			'selectedLimit' => 1,
			'objectName' => 'hosts',
			'objectOptions' => array(
				'editable' => true,
				'templated_hosts' => true
			),
			'data' => $hostFilterData,
			'popup' => array(
				'parameters' => 'srctbl=host_templates&dstfrm='.$form->getName().'&dstfld1=filter_hostid'.
					'&srcfld1=hostid&writeonly=1'
			)
		))
	);

	$filterColumn2->addRow($updateIntervalLabel, $updateIntervalInput);
	$filterColumn3->addRow($dataTypeLabel, $dataTypeInput);
	$filterColumn4->addRow(_('Status'), $cmbStatus);

	// row 3
	$filterColumn1->addRow(_('Application'),
		array(
			new CTextBox('filter_application', $filter_application, ZBX_TEXTBOX_FILTER_SIZE),
			new CButton('btn_app', _('Select'),
				'return PopUp("popup.php?srctbl=applications&srcfld1=name'.
					'&dstfrm='.$form->getName().'&dstfld1=filter_application'.
					'&with_applications=1'.
					'" + (jQuery("input[name=\'filter_hostid\']").length > 0 ? "&hostid="+jQuery("input[name=\'filter_hostid\']").val() : "")'
					.', 0, 0, "application");',
				'button-form'
			)
		)
	);
	$filterColumn2->addRow(array($snmpCommunityLabel, $snmpSecurityLabel), array($snmpCommunityField, $snmpSecurityField));
	$filterColumn3->addRow(_('History (in days)'), new CNumericBox('filter_history', $filter_history, 8, false, true));
	$filterColumn4->addRow(_('Triggers'),
		new CComboBox('filter_with_triggers', $filter_with_triggers, null, array(
			-1 => _('all'),
			1 => _('With triggers'),
			0 => _('Without triggers')
		))
	);

	// row 4
	$filterColumn1->addRow(_('Name like'), new CTextBox('filter_name', $filter_name, ZBX_TEXTBOX_FILTER_SIZE));
	$filterColumn2->addRow($snmpOidLabel, $snmpOidField);
	$filterColumn3->addRow(_('Trends (in days)'), new CNumericBox('filter_trends', $filter_trends, 8, false, true));
	$filterColumn4->addRow(_('Template'),
		new CComboBox('filter_templated_items', $filter_templated_items, null, array(
			-1 => _('all'),
			1 => _('Templated items'),
			0 => _('Not Templated items'),
		))
	);

	// row 5
	$filterColumn1->addRow(_('Key like'), new CTextBox('filter_key', $filter_key, ZBX_TEXTBOX_FILTER_SIZE));
	$filterColumn2->addRow($portLabel, $portField);

	$form->addColumn($filterColumn1);
	$form->addColumn($filterColumn2);
	$form->addColumn($filterColumn3);
	$form->addColumn($filterColumn4);

	// subfilters
	$table_subfilter = new CTableInfo();
	$table_subfilter->addRow(
		array(
			new CTag('h4', 'yes',
				array(
					_('Subfilter').SPACE,
					new CSpan(_('affects only filtered data'))
				)
			))
	);

	// array contains subfilters and number of items in each
	$item_params = array(
		'hosts' => array(),
		'applications' => array(),
		'types' => array(),
		'value_types' => array(),
		'status' => array(),
		'state' => array(),
		'templated_items' => array(),
		'with_triggers' => array(),
		'history' => array(),
		'trends' => array(),
		'interval' => array()
	);

	// generate array with values for subfilters of selected items
	foreach ($items as $item) {
		// hosts
		if (zbx_empty($filter_hostId)) {
			$host = reset($item['hosts']);

			if (!isset($item_params['hosts'][$host['hostid']])) {
				$item_params['hosts'][$host['hostid']] = array('name' => $host['name'], 'count' => 0);
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
					$item_params['applications'][$application['name']] = array('name' => $application['name'], 'count' => 0);
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
				$item_params['types'][$item['type']] = array('name' => item_type2str($item['type']), 'count' => 0);
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
				$item_params['value_types'][$item['value_type']] = array(
					'name' => itemValueTypeString($item['value_type']),
					'count' => 0
				);
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
				$item_params['status'][$item['status']] = array(
					'name' => item_status2str($item['status']),
					'count' => 0
				);
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
				$item_params['state'][$item['state']] = array(
					'name' => itemState($item['state']),
					'count' => 0
				);
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
				$item_params['templated_items'][0] = array('name' => _('Not Templated items'), 'count' => 0);
			}
			elseif ($item['templateid'] > 0 && !isset($item_params['templated_items'][1])) {
				$item_params['templated_items'][1] = array('name' => _('Templated items'), 'count' => 0);
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
				$item_params['with_triggers'][0] = array('name' => _('Without triggers'), 'count' => 0);
			}
			elseif (count($item['triggers']) > 0 && !isset($item_params['with_triggers'][1])) {
				$item_params['with_triggers'][1] = array('name' => _('With triggers'), 'count' => 0);
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
				$item_params['trends'][$item['trends']] = array('name' => $item['trends'], 'count' => 0);
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
				$item_params['history'][$item['history']] = array('name' => $item['history'], 'count' => 0);
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
				$item_params['interval'][$item['delay']] = array('name' => $item['delay'], 'count' => 0);
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
		$table_subfilter->addRow(array($application_output));
	}

	if ($filter_type == -1 && count($item_params['types']) > 1) {
		$type_output = prepareSubfilterOutput(_('Types'), $item_params['types'], $subfilter_types, 'subfilter_types');
		$table_subfilter->addRow(array($type_output));
	}

	if ($filter_value_type == -1 && count($item_params['value_types']) > 1) {
		$value_types_output = prepareSubfilterOutput(_('Type of information'), $item_params['value_types'], $subfilter_value_types, 'subfilter_value_types');
		$table_subfilter->addRow(array($value_types_output));
	}

	if ($filter_status == -1 && count($item_params['status']) > 1) {
		$status_output = prepareSubfilterOutput(_('Status'), $item_params['status'], $subfilter_status, 'subfilter_status');
		$table_subfilter->addRow(array($status_output));
	}

	if ($filter_state == -1 && count($item_params['state']) > 1) {
		$state_output = prepareSubfilterOutput(_('State'), $item_params['state'], $subfilter_state, 'subfilter_state');
		$table_subfilter->addRow(array($state_output));
	}

	if ($filter_templated_items == -1 && count($item_params['templated_items']) > 1) {
		$templated_items_output = prepareSubfilterOutput(_('Template'), $item_params['templated_items'], $subfilter_templated_items, 'subfilter_templated_items');
		$table_subfilter->addRow(array($templated_items_output));
	}

	if ($filter_with_triggers == -1 && count($item_params['with_triggers']) > 1) {
		$with_triggers_output = prepareSubfilterOutput(_('With triggers'), $item_params['with_triggers'], $subfilter_with_triggers, 'subfilter_with_triggers');
		$table_subfilter->addRow(array($with_triggers_output));
	}

	if (zbx_empty($filter_history) && count($item_params['history']) > 1) {
		$history_output = prepareSubfilterOutput(_('History'), $item_params['history'], $subfilter_history, 'subfilter_history');
		$table_subfilter->addRow(array($history_output));
	}

	if (zbx_empty($filter_trends) && (count($item_params['trends']) > 1)) {
		$trends_output = prepareSubfilterOutput(_('Trends'), $item_params['trends'], $subfilter_trends, 'subfilter_trends');
		$table_subfilter->addRow(array($trends_output));
	}

	if (zbx_empty($filter_delay) && $filter_type != ITEM_TYPE_TRAPPER && count($item_params['interval']) > 1) {
		$interval_output = prepareSubfilterOutput(_('Interval'), $item_params['interval'], $subfilter_interval, 'subfilter_interval');
		$table_subfilter->addRow(array($interval_output));
	}

	$form->setFooter($table_subfilter);

	return $form;
}

/**
 * Get data for item edit page.
 *
 * @param array	$item							item, item prototype or LLD rule to take the data from
 * @param bool $options['is_discovery_rule']
 *
 * @return array
 */
function getItemFormData(array $item = array(), array $options = array()) {
	$data = array(
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'is_discovery_rule' => !empty($options['is_discovery_rule']),
		'parent_discoveryid' => getRequest('parent_discoveryid', !empty($options['is_discovery_rule']) ? getRequest('itemid') : null),
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
		'applications' => getRequest('applications', array()),
		'delay_flex' => getRequest('delay_flex', array()),
		'new_delay_flex' => getRequest('new_delay_flex', array('delay' => 50, 'period' => ZBX_DEFAULT_INTERVAL)),
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
		'add_groupid' => getRequest('add_groupid', getRequest('groupid', 0)),
		'valuemaps' => null,
		'possibleHostInventories' => null,
		'alreadyPopulated' => null,
		'initial_item_type' => null,
		'templates' => array()
	);

	// hostid
	if (!empty($data['parent_discoveryid'])) {
		$discoveryRule = API::DiscoveryRule()->get(array(
			'itemids' => $data['parent_discoveryid'],
			'output' => API_OUTPUT_EXTEND,
			'editable' => true
		));
		$discoveryRule = reset($discoveryRule);
		$data['hostid'] = $discoveryRule['hostid'];
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
			$params = array(
				'itemids' => $itemid,
				'output' => array('itemid', 'templateid'),
				'selectHosts' => array('name')
			);
			if ($data['is_discovery_rule']) {
				$item = API::DiscoveryRule()->get($params);
			}
			else {
				$params['selectDiscoveryRule'] = array('itemid');
				$params['filter'] = array('flags' => null);
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
						$data['templates'][] = new CLink($host['name'], 'host_discovery.php?form=update&itemid='.$item['itemid'], 'highlight underline weight_normal');
						$data['templates'][] = SPACE.'&rArr;'.SPACE;
					}
					// item prototype
					elseif ($item['discoveryRule']) {
						$data['templates'][] = new CLink($host['name'], 'disc_prototypes.php?form=update&itemid='.$item['itemid'].'&parent_discoveryid='.$item['discoveryRule']['itemid'], 'highlight underline weight_normal');
						$data['templates'][] = SPACE.'&rArr;'.SPACE;
					}
					// plain item
					else {
						$data['templates'][] = new CLink($host['name'], 'items.php?form=update&itemid='.$item['itemid'], 'highlight underline weight_normal');
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
			$hostInfo = API::Host()->get(array(
				'hostids' => $data['hostid'],
				'output' => array('name'),
				'templated_hosts' => true
			));
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

			$db_delay_flex = $data['item']['delay_flex'];
			if (isset($db_delay_flex)) {
				$arr_of_dellays = explode(';', $db_delay_flex);
				foreach ($arr_of_dellays as $one_db_delay) {
					$arr_of_delay = explode('/', $one_db_delay);
					if (!isset($arr_of_delay[0]) || !isset($arr_of_delay[1])) {
						continue;
					}
					array_push($data['delay_flex'], array('delay' => $arr_of_delay[0], 'period' => $arr_of_delay[1]));
				}
			}
			$data['applications'] = array_unique(zbx_array_merge($data['applications'], get_applications_by_itemid($data['itemid'])));
		}
	}

	// applications
	if (count($data['applications']) == 0) {
		array_push($data['applications'], 0);
	}
	$data['db_applications'] = DBfetchArray(DBselect(
		'SELECT DISTINCT a.applicationid,a.name'.
		' FROM applications a'.
		' WHERE a.hostid='.zbx_dbstr($data['hostid'])
	));
	order_result($data['db_applications'], 'name');

	// interfaces
	$data['interfaces'] = API::HostInterface()->get(array(
		'hostids' => $data['hostid'],
		'output' => API_OUTPUT_EXTEND
	));

	// valuemapid
	if ($data['limited']) {
		if (!empty($data['valuemapid'])) {
			if ($map_data = DBfetch(DBselect('SELECT v.name FROM valuemaps v WHERE v.valuemapid='.zbx_dbstr($data['valuemapid'])))) {
				$data['valuemaps'] = $map_data['name'];
			}
		}
	}
	else {
		$data['valuemaps'] = DBfetchArray(DBselect('SELECT v.* FROM valuemaps v'));

		order_result($data['valuemaps'], 'name');
	}

	// possible host inventories
	if (empty($data['parent_discoveryid'])) {
		$data['possibleHostInventories'] = getHostInventories();

		// get already populated fields by other items
		$data['alreadyPopulated'] = API::item()->get(array(
			'output' => array('inventory_link'),
			'filter' => array('hostid' => $data['hostid']),
			'nopermissions' => true
		));
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
	$data = array(
		'title' => $title,
		'elements_field' => $elementsField,
		'elements' => getRequest($elementsField, array()),
		'copy_type' => getRequest('copy_type', COPY_TYPE_TO_HOST_GROUP),
		'copy_groupid' => getRequest('copy_groupid', 0),
		'copy_targetid' => getRequest('copy_targetid', array()),
		'hostid' => getRequest('hostid', 0),
		'groups' => array(),
		'hosts' => array(),
		'templates' => array()
	);

	// validate elements
	if (empty($data['elements']) || !is_array($data['elements'])) {
		error(_('Incorrect list of items.'));

		return null;
	}

	if ($data['copy_type'] == COPY_TYPE_TO_HOST_GROUP) {
		// get groups
		$data['groups'] = API::HostGroup()->get(array(
			'output' => array('groupid', 'name')
		));
		order_result($data['groups'], 'name');
	}
	else {
		// hosts or templates
		$params = array('output' => array('name', 'groupid'));

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
			$data['templates'] = API::Template()->get(array(
				'output' => array('name', 'templateid'),
				'groupids' => $data['copy_groupid']
			));
			order_result($data['templates'], 'name');
		}
		elseif ($data['copy_type'] == COPY_TYPE_TO_HOST) {
			$data['hosts'] = API::Host()->get(array(
				'output' => array('name', 'hostid'),
				'groupids' => $data['copy_groupid']
			));
			order_result($data['hosts'], 'name');
		}
	}

	return $data;
}

function getTriggerMassupdateFormData() {
	$data = array(
		'visible' => getRequest('visible', array()),
		'priority' => getRequest('priority', ''),
		'dependencies' => getRequest('dependencies', array()),
		'massupdate' => getRequest('massupdate', 1),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'g_triggerid' => getRequest('g_triggerid', array()),
		'priority' => getRequest('priority', 0),
		'config' => select_config(),
		'hostid' => getRequest('hostid', 0)
	);

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get(array(
			'output' => array('triggerid', 'description', 'flags'),
			'selectHosts' => array('hostid', 'name'),
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		));

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get(array(
				'output' => array('triggerid', 'description', 'flags'),
				'selectHosts' => array('hostid', 'name'),
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			));
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

	return $data;
}

/**
 * Generate data for the trigger configuration form.
 *
 * @param string $exprAction	expression constructor action, see remakeExpression() for a list of supported values
 *
 * @return array
 */
function getTriggerFormData($exprAction) {
	$data = array(
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh'),
		'parent_discoveryid' => getRequest('parent_discoveryid'),
		'dependencies' => getRequest('dependencies', array()),
		'db_dependencies' => array(),
		'triggerid' => getRequest('triggerid'),
		'expression' => getRequest('expression', ''),
		'expr_temp' => getRequest('expr_temp', ''),
		'description' => getRequest('description', ''),
		'type' => getRequest('type', 0),
		'priority' => getRequest('priority', 0),
		'status' => getRequest('status', 0),
		'comments' => getRequest('comments', ''),
		'url' => getRequest('url', ''),
		'input_method' => getRequest('input_method', IM_ESTABLISHED),
		'limited' => false,
		'templates' => array(),
		'hostid' => getRequest('hostid', 0)
	);

	if (!empty($data['triggerid'])) {
		// get trigger
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => array('hostid'),
			'triggerids' => $data['triggerid']
		);
		$trigger = ($data['parent_discoveryid']) ? API::TriggerPrototype()->get($options) : API::Trigger()->get($options);
		$data['trigger'] = reset($trigger);

		// get templates
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
					$link = 'trigger_prototypes.php?form=update&triggerid='.$db_triggers['triggerid'].'&parent_discoveryid='.$db_triggers['parent_itemid'].'&hostid='.$db_triggers['hostid'];
				}
				// parent trigger link
				else {
					$link = 'triggers.php?form=update&triggerid='.$db_triggers['triggerid'].'&hostid='.$db_triggers['hostid'];
				}

				$data['templates'][] = new CLink(
					CHtml::encode($db_triggers['name']),
					$link,
					'highlight underline weight_normal'
				);
				$data['templates'][] = SPACE.'&rArr;'.SPACE;
			}
			$tmp_triggerid = $db_triggers['templateid'];
		} while ($tmp_triggerid != 0);
		$data['templates'] = array_reverse($data['templates']);
		array_shift($data['templates']);

		$data['limited'] = ($data['trigger']['templateid'] != 0);

		// select first host from triggers if gived not match
		$hosts = $data['trigger']['hosts'];
		if (count($hosts) > 0 && !in_array(array('hostid' => $data['hostid']), $hosts)) {
			$host = reset($hosts);
			$data['hostid'] = $host['hostid'];
		}
	}

	if ((!empty($data['triggerid']) && !isset($_REQUEST['form_refresh'])) || $data['limited']) {
		$data['expression'] = explode_exp($data['trigger']['expression']);

		if (!$data['limited'] || !isset($_REQUEST['form_refresh'])) {
			$data['description'] = $data['trigger']['description'];
			$data['type'] = $data['trigger']['type'];
			$data['priority'] = $data['trigger']['priority'];
			$data['status'] = $data['trigger']['status'];
			$data['comments'] = $data['trigger']['comments'];
			$data['url'] = $data['trigger']['url'];

			$db_triggers = DBselect(
				'SELECT t.triggerid,t.description'.
				' FROM triggers t,trigger_depends d'.
				' WHERE t.triggerid=d.triggerid_up'.
					' AND d.triggerid_down='.zbx_dbstr($data['triggerid'])
			);
			while ($trigger = DBfetch($db_triggers)) {
				if (uint_in_array($trigger['triggerid'], $data['dependencies'])) {
					continue;
				}
				array_push($data['dependencies'], $trigger['triggerid']);
			}
		}
	}

	if ($data['input_method'] == IM_TREE) {
		$analyze = analyzeExpression($data['expression']);
		if ($analyze !== false) {
			list($data['outline'], $data['eHTMLTree']) = $analyze;
			if ($exprAction !== null && $data['eHTMLTree'] != null) {
				$new_expr = remakeExpression($data['expression'], $_REQUEST['expr_target_single'],
					$exprAction, $data['expr_temp']
				);
				if ($new_expr !== false) {
					$data['expression'] = $new_expr;
					$analyze = analyzeExpression($data['expression']);
					if ($analyze !== false) {
						list($data['outline'], $data['eHTMLTree']) = $analyze;
					}
					else {
						show_messages(false, '', _('Expression Syntax Error.'));
					}
					$data['expr_temp'] = '';
				}
				else {
					show_messages(false, '', _('Expression Syntax Error.'));
				}
			}
			$data['expression_field_name'] = 'expr_temp';
			$data['expression_field_value'] = $data['expr_temp'];
			$data['expression_field_readonly'] = true;
		}
		else {
			show_messages(false, '', _('Expression Syntax Error.'));
			$data['input_method'] = IM_ESTABLISHED;
		}
	}
	if ($data['input_method'] != IM_TREE) {
		$data['expression_field_name'] = 'expression';
		$data['expression_field_value'] = $data['expression'];
		$data['expression_field_readonly'] = $data['limited'];
	}

	if ($data['dependencies']) {
		$dependencyTriggers = API::Trigger()->get(array(
			'output' => array('triggerid', 'description', 'flags'),
			'selectHosts' => array('hostid', 'name'),
			'triggerids' => $data['dependencies'],
			'preservekeys' => true
		));

		if ($data['parent_discoveryid']) {
			$dependencyTriggerPrototypes = API::TriggerPrototype()->get(array(
				'output' => array('triggerid', 'description', 'flags'),
				'selectHosts' => array('hostid', 'name'),
				'triggerids' => $data['dependencies'],
				'preservekeys' => true
			));

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

	return $data;
}

function get_timeperiod_form() {
	$tblPeriod = new CTable(null, 'formElementTable');

	// init new_timeperiod variable
	$new_timeperiod = getRequest('new_timeperiod', array());
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
		$new_timeperiod = array();
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

	$tblPeriod->addRow(array(_('Period type'), $cmbType));

	if ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) {
		$tblPeriod->addItem(new CVar('new_timeperiod[dayofweek]', bindec($bit_dayofweek)));
		$tblPeriod->addItem(new CVar('new_timeperiod[month]', bindec($bit_month)));
		$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day']));
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));
		$tblPeriod->addItem(new CVar('new_timeperiod[month_date_type]', $new_timeperiod['month_date_type']));
		$tblPeriod->addRow(array(_('Every day(s)'), new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 3)));
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) {
		$tblPeriod->addItem(new CVar('new_timeperiod[month]', bindec($bit_month)));
		$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day']));
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));
		$tblPeriod->addItem(new CVar('new_timeperiod[month_date_type]', $new_timeperiod['month_date_type']));
		$tblPeriod->addRow(array(_('Every week(s)'), new CNumericBox('new_timeperiod[every]', $new_timeperiod['every'], 2)));

		$tabDays = new CTable();
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_mo]', $dayofweek[0], null, 1), _('Monday')));
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_tu]', $dayofweek[1], null, 1), _('Tuesday')));
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_we]', $dayofweek[2], null, 1), _('Wednesday')));
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_th]', $dayofweek[3], null, 1), _('Thursday')));
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_fr]', $dayofweek[4], null, 1), _('Friday')));
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_sa]', $dayofweek[5], null, 1), _('Saturday')));
		$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_su]', $dayofweek[6], null, 1), _('Sunday')));
		$tblPeriod->addRow(array(_('Day of week'), $tabDays));
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
		$tblPeriod->addItem(new CVar('new_timeperiod[start_date]', $new_timeperiod['start_date']));

		$tabMonths = new CTable();
		$tabMonths->addRow(array(
			new CCheckBox('new_timeperiod[month_jan]', $month[0], null, 1), _('January'),
			SPACE, SPACE,
			new CCheckBox('new_timeperiod[month_jul]', $month[6], null, 1), _('July')
		));
		$tabMonths->addRow(array(
			new CCheckBox('new_timeperiod[month_feb]', $month[1], null, 1), _('February'),
			SPACE, SPACE,
			new CCheckBox('new_timeperiod[month_aug]', $month[7], null, 1), _('August')
		));
		$tabMonths->addRow(array(
			new CCheckBox('new_timeperiod[month_mar]', $month[2], null, 1), _('March'),
			SPACE, SPACE,
			new CCheckBox('new_timeperiod[month_sep]', $month[8], null, 1), _('September')
		));
		$tabMonths->addRow(array(
			new CCheckBox('new_timeperiod[month_apr]', $month[3], null, 1), _('April'),
			SPACE, SPACE,
			new CCheckBox('new_timeperiod[month_oct]', $month[9], null, 1), _('October')
		));
		$tabMonths->addRow(array(
			new CCheckBox('new_timeperiod[month_may]', $month[4], null, 1), _('May'),
			SPACE, SPACE,
			new CCheckBox('new_timeperiod[month_nov]', $month[10], null, 1), _('November')
		));
		$tabMonths->addRow(array(
			new CCheckBox('new_timeperiod[month_jun]', $month[5], null, 1), _('June'),
			SPACE, SPACE,
			new CCheckBox('new_timeperiod[month_dec]', $month[11], null, 1), _('December')
		));
		$tblPeriod->addRow(array(_('Month'), $tabMonths));

		$tblPeriod->addRow(array(_('Date'), array(
			new CRadioButton('new_timeperiod[month_date_type]', '0', null, null, !$new_timeperiod['month_date_type'], 'submit()'),
			_('Day'),
			SPACE,
			new CRadioButton('new_timeperiod[month_date_type]', '1', null, null, $new_timeperiod['month_date_type'], 'submit()'),
			_('Day of week')))
		);

		if ($new_timeperiod['month_date_type'] > 0) {
			$tblPeriod->addItem(new CVar('new_timeperiod[day]', $new_timeperiod['day']));

			$cmbCount = new CComboBox('new_timeperiod[every]', $new_timeperiod['every']);
			$cmbCount->addItem(1, _('First'));
			$cmbCount->addItem(2, _('Second'));
			$cmbCount->addItem(3, _('Third'));
			$cmbCount->addItem(4, _('Fourth'));
			$cmbCount->addItem(5, _('Last'));

			$td = new CCol($cmbCount);
			$td->setColSpan(2);

			$tabDays = new CTable();
			$tabDays->addRow($td);
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_mo]', $dayofweek[0], null, 1), _('Monday')));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_tu]', $dayofweek[1], null, 1), _('Tuesday')));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_we]', $dayofweek[2], null, 1), _('Wednesday')));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_th]', $dayofweek[3], null, 1), _('Thursday')));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_fr]', $dayofweek[4], null, 1), _('Friday')));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_sa]', $dayofweek[5], null, 1), _('Saturday')));
			$tabDays->addRow(array(new CCheckBox('new_timeperiod[dayofweek_su]', $dayofweek[6], null, 1), _('Sunday')));
			$tblPeriod->addRow(array(_('Day of week'), $tabDays));
		}
		else {
			$tblPeriod->addItem(new CVar('new_timeperiod[dayofweek]', bindec($bit_dayofweek)));
			$tblPeriod->addRow(array(_('Day of month'), new CNumericBox('new_timeperiod[day]', $new_timeperiod['day'], 2)));
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
			$date = array(
				'y' => getRequest('new_timeperiod_start_date_year'),
				'm' => getRequest('new_timeperiod_start_date_month'),
				'd' => getRequest('new_timeperiod_start_date_day'),
				'h' => getRequest('new_timeperiod_start_date_hour'),
				'i' => getRequest('new_timeperiod_start_date_minute')
			);
		}
		else {
			$date = zbxDateToTime($new_timeperiod['start_date']
				? $new_timeperiod['start_date'] : date(TIMESTAMP_FORMAT_ZERO_TIME, time()));
		}

		$tblPeriod->addRow(array(_('Date'), createDateSelector('new_timeperiod_start_date', $date)));
	}

	if ($new_timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_ONETIME) {
		$tblPeriod->addRow(array(_('At (hour:minute)'), array(
			new CNumericBox('new_timeperiod[hour]', $new_timeperiod['hour'], 2),
			':',
			new CNumericBox('new_timeperiod[minute]', $new_timeperiod['minute'], 2)))
		);
	}

	$perHours = new CComboBox('new_timeperiod[period_hours]', $new_timeperiod['period_hours'], null, range(0, 23));
	$perMinutes = new CComboBox('new_timeperiod[period_minutes]', $new_timeperiod['period_minutes'], null, range(0, 59));
	$tblPeriod->addRow(array(
		_('Maintenance period length'),
		array(
			new CNumericBox('new_timeperiod[period_days]', $new_timeperiod['period_days'], 3),
			_('Days').SPACE.SPACE,
			$perHours,
			_('Hours').SPACE.SPACE,
			$perMinutes,
			_('Minutes')
	)));

	return $tblPeriod;
}
