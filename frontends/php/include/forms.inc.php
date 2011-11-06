<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	function insert_slideshow_form(){
		$form = new CFormTable(S_SLIDESHOW, null, 'post');
		$form->setHelp('config_advanced.php');

		if(isset($_REQUEST['slideshowid'])){
			$form->addVar('slideshowid', $_REQUEST['slideshowid']);
		}

		$name		= get_request('name', '');
		$delay		= get_request('delay', 5);
		$steps		= get_request('steps', array());

		$new_step	= get_request('new_step', null);

		if((isset($_REQUEST['slideshowid']) && !isset($_REQUEST['form_refresh']))){
			$slideshow_data = DBfetch(DBselect('SELECT * FROM slideshows WHERE slideshowid='.$_REQUEST['slideshowid']));

			$name		= $slideshow_data['name'];
			$delay		= $slideshow_data['delay'];
			$steps		= array();
			$db_steps = DBselect('SELECT * FROM slides WHERE slideshowid='.$_REQUEST['slideshowid'].' order by step');

			while($step_data = DBfetch($db_steps)){
				$steps[$step_data['step']] = array(
						'screenid' => $step_data['screenid'],
						'delay' => $step_data['delay']
					);
			}
		}

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));

		$delayBox = new CComboBox('delay', $delay);
		$delayBox->addItem(10,'10');
		$delayBox->addItem(30,'30');
		$delayBox->addItem(60,'60');
		$delayBox->addItem(120,'120');
		$delayBox->addItem(600,'600');
		$delayBox->addItem(900,'900');

		$form->addRow(_('Update interval (in sec)'), $delayBox);

		$tblSteps = new CTableInfo(S_NO_SLIDES_DEFINED);
		$tblSteps->setHeader(array(S_SCREEN, S_DELAY, S_SORT));
		if(count($steps) > 0){
			ksort($steps);
			$first = min(array_keys($steps));
			$last = max(array_keys($steps));
		}

		foreach($steps as $sid => $s){
			if( !isset($s['screenid']) ) $s['screenid'] = 0;

			if(isset($s['delay']) && $s['delay'] > 0 )
				$s['delay'] = bold($s['delay']);
			else
				$s['delay'] = $delay;

			$up = null;
			if($sid != $first){
				$up = new CSpan(S_UP,'link');
				$up->onClick("return create_var('".$form->getName()."','move_up',".$sid.", true);");
			}

			$down = null;
			if($sid != $last){
				$down = new CSpan(S_DOWN,'link');
				$down->onClick("return create_var('".$form->getName()."','move_down',".$sid.", true);");
			}

			$screen_data = get_screen_by_screenid($s['screenid']);
			$name = new CSpan($screen_data['name'],'link');
			$name->onClick("return create_var('".$form->getName()."','edit_step',".$sid.", true);");

			$tblSteps->addRow(array(
				array(new CCheckBox('sel_step[]',null,null,$sid), $name),
				$s['delay'],
				array($up, isset($up) && isset($down) ? SPACE : null, $down)
				));
		}
		$form->addVar('steps', $steps);

		$form->addRow(S_SLIDES, array(
			$tblSteps,
			!isset($new_step) ? new CSubmit('add_step_bttn',S_ADD,
				"return create_var('".$form->getName()."','add_step',1, true);") : null,
			(count($steps) > 0) ? new CSubmit('del_sel_step',S_DELETE_SELECTED) : null
			));

		if(isset($new_step)){
			if( !isset($new_step['screenid']) )	$new_step['screenid'] = 0;
			if( !isset($new_step['delay']) )	$new_step['delay'] = 0;

			if( isset($new_step['sid']) )
				$form->addVar('new_step[sid]',$new_step['sid']);

			$form->addVar('new_step[screenid]',$new_step['screenid']);

			$screen_data = get_screen_by_screenid($new_step['screenid']);

			$form->addRow(S_NEW_SLIDE, array(
					S_DELAY,
					new CNumericBox('new_step[delay]', $new_step['delay'], 5), BR(),
					new CTextBox('screen_name', $screen_data['name'], 40, 'yes'),
					new CButton('select_screen',S_SELECT,
						'return PopUp("popup.php?dstfrm='.$form->getName().'&srctbl=screens'.
						'&dstfld1=screen_name&srcfld1=name'.
						'&dstfld2=new_step_screenid&srcfld2=screenid");'),
					BR(),
					new CSubmit('add_step', isset($new_step['sid']) ? S_SAVE : S_ADD),
					new CSubmit('cancel_step', S_CANCEL)

				),
				isset($new_step['sid']) ? 'edit' : 'new');
		}

		$form->addItemToBottomRow(new CSubmit("save",S_SAVE));
		if(isset($_REQUEST['slideshowid'])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CSubmit('clone',S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_SLIDESHOW_Q,
				url_param('form').url_param('slideshowid').url_param('config')));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		return $form;
	}

	function getUserFormData($userid, $isProfile = false) {
		$config = select_config();
		$data = array('is_profile' => $isProfile);

		// get title
		if (isset($userid)) {
			$options = array(
				'userids' => $userid,
				'output' => API_OUTPUT_EXTEND
			);
			if ($data['is_profile']) {
				$options['nodeids'] = id2nodeid($userid);
			}
			$users = API::User()->get($options);
			$user = reset($users);
			$data['title'] = _('User').' "'.$user['alias'].'"';
		}
		else {
			$data['title'] = _('User');
		}

		$data['auth_type'] = isset($userid) ? get_user_system_auth($userid) : $config['authentication_type'];

		if (isset($userid) && (!isset($_REQUEST['form_refresh']) || isset($_REQUEST['register']))) {
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

			$userGroups = API::UserGroup()->get(array('userids' => $userid, 'output' => API_OUTPUT_SHORTEN));
			$userGroup = zbx_objectValues($userGroups, 'usrgrpid');
			$data['user_groups']	= zbx_toHash($userGroup);

			$user_medias = array();
			$db_medias = DBselect('SELECT m.* FROM media m WHERE m.userid='.$userid);
			while ($db_media = DBfetch($db_medias)) {
				$user_medias[] = array(
					'mediaid' => $db_media['mediaid'],
					'mediatypeid' => $db_media['mediatypeid'],
					'period' => $db_media['period'],
					'sendto' => $db_media['sendto'],
					'severity' => $db_media['severity'],
					'active' => $db_media['active']
				);
			}
			$data['user_medias'] = $user_medias;

			if ($data['autologout'] > 0) {
				$_REQUEST['autologout'] = $data['autologout'];
			}
		}
		else {
			$data['alias']			= get_request('alias', '');
			$data['name']			= get_request('name', '');
			$data['surname']		= get_request('surname', '');
			$data['password1']		= get_request('password1', '');
			$data['password2']		= get_request('password2', '');
			$data['url']			= get_request('url', '');
			$data['autologin']		= get_request('autologin', 0);
			$data['autologout']		= get_request('autologout', 90);
			$data['lang']			= get_request('lang', 'en_gb');
			$data['theme']			= get_request('theme', 'default.css');
			$data['refresh']		= get_request('refresh', 30);
			$data['rows_per_page']	= get_request('rows_per_page', 50);
			$data['user_type']		= get_request('user_type', USER_TYPE_ZABBIX_USER);;
			$data['user_groups']	= get_request('user_groups', array());
			$data['change_password']= get_request('change_password', null);
			$data['user_medias']	= get_request('user_medias', array());

			// set messages
			$data['messages'] 		= get_request('messages', array());
			if (!isset($data['messages']['enabled'])) {
				$data['messages']['enabled'] = 0;
			}
			if (!isset($data['messages']['sounds.recovery'])) {
				$data['messages']['sounds.recovery'] = 0;
			}
			if (!isset($data['messages']['triggers.recovery'])) {
				$data['messages']['triggers.recovery'] = 0;
			}
			if (!isset($data['messages']['triggers.severities'])) {
				$data['messages']['triggers.severities'] = array();
			}
			$data['messages'] = array_merge(getMessageSettings(), $data['messages']);
		}

		// set autologout
		if ($data['autologin'] || !isset($_REQUEST['autologout'])) {
			$data['autologout'] = 0;
		}
		elseif (isset($_REQUEST['autologout']) && $data['autologout'] < 90) {
			$data['autologout'] = 90;
		}

		// set media types
		$data['media_types'] = array();
		$media_type_ids = array();
		foreach ($data['user_medias'] as $media) {
			$media_type_ids[$media['mediatypeid']] = 1;
		}
		if (count($media_type_ids) > 0) {
			$db_media_types = DBselect('SELECT mt.mediatypeid,mt.description FROM media_type mt WHERE mt.mediatypeid IN ('.implode(',', array_keys($media_type_ids)).')');
			while ($db_media_type = DBfetch($db_media_types)) {
				$data['media_types'][$db_media_type['mediatypeid']] = $db_media_type['description'];
			}
		}

		// set user rights
		if (!$data['is_profile']) {
			$data['groups'] = API::UserGroup()->get(array('usrgrpids' => $data['user_groups'], 'output' => API_OUTPUT_EXTEND));
			order_result($data['groups'], 'name');

			$group_ids = array_values($data['user_groups']);
			if (count($group_ids) == 0) {
				$group_ids = array(-1);
			}
			$db_rights = DBselect('SELECT r.* FROM rights r WHERE '.DBcondition('r.groupid', $group_ids));

			$tmp_permitions = array();
			while ($db_right = DBfetch($db_rights)) {
				if (isset($tmp_permitions[$db_right['id']])) {
					$tmp_permitions[$db_right['id']] = min($tmp_permitions[$db_right['id']], $db_right['permission']);
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
		// nodes
		if (ZBX_DISTRIBUTED) {
			$lists['node']['label']		= _('Nodes');
			$lists['node']['read_write']= new CListBox('nodes_write', null, 10);
			$lists['node']['read_only']	= new CListBox('nodes_read', null, 10);
			$lists['node']['deny']		= new CListBox('nodes_deny', null, 10);

			$lists['node']['read_write']->setAttribute('style', 'background: #EBEFF2;');
			$lists['node']['read_only']->setAttribute('style', 'background: #EBEFF2;');
			$lists['node']['deny']->setAttribute('style', 'background: #EBEFF2;');

			$nodes = get_accessible_nodes_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY);
			foreach ($nodes as $node) {
				switch($node['permission']) {
					case PERM_READ_ONLY:
						$list_name = 'read_only';
						break;
					case PERM_READ_WRITE:
						$list_name = 'read_write';
						break;
					default:
						$list_name = 'deny';
				}
				$lists['node'][$list_name]->addItem($node['nodeid'], $node['name']);
			}
			unset($nodes);
		}

		// group
		$lists['group']['label']		= _('Host groups');
		$lists['group']['read_write']	= new CListBox('groups_write', null, 15);
		$lists['group']['read_only']	= new CListBox('groups_read', null, 15);
		$lists['group']['deny']			= new CListBox('groups_deny', null, 15);

		$lists['group']['read_write']->setAttribute('style', 'background: #EBEFF2;');
		$lists['group']['read_only']->setAttribute('style', 'background: #EBEFF2;');
		$lists['group']['deny']->setAttribute('style', 'background: #EBEFF2;');

		$groups = get_accessible_groups_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY, get_current_nodeid(true));

		foreach ($groups as $group) {
			switch($group['permission']) {
				case PERM_READ_ONLY:
					$list_name = 'read_only';
					break;
				case PERM_READ_WRITE:
					$list_name = 'read_write';
					break;
				default:
					$list_name = 'deny';
			}
			$lists['group'][$list_name]->addItem($group['groupid'], (empty($group['node_name']) ? '' : $group['node_name'].':' ).$group['name']);
		}
		unset($groups);

		// host
		$lists['host']['label']		= _('Hosts');
		$lists['host']['read_write']= new CListBox('hosts_write', null, 15);
		$lists['host']['read_only']	= new CListBox('hosts_read', null, 15);
		$lists['host']['deny']		= new CListBox('hosts_deny', null, 15);

		$lists['host']['read_write']->setAttribute('style', 'background: #EBEFF2;');
		$lists['host']['read_only']->setAttribute('style', 'background: #EBEFF2;');
		$lists['host']['deny']->setAttribute('style', 'background: #EBEFF2;');

		$hosts = get_accessible_hosts_by_rights($rights, $user_type, PERM_DENY, PERM_RES_DATA_ARRAY, get_current_nodeid(true));

		foreach ($hosts as $host) {
			switch($host['permission']) {
				case PERM_READ_ONLY:
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
			$lists['host'][$list_name]->addItem($host['hostid'], (empty($host['node_name']) ? '' : $host['node_name'].':' ).$host['host_name']);
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

			$table = new CTable(_('No accessible resources'), 'right_table');
			if (!$isHeaderDisplayed) {
				$table->setHeader(array(_('Read-write'), _('Read only'), _('Deny')), 'header');
				$isHeaderDisplayed = true;
			}
			$table->addRow($row);
			$rightsFormList->addRow($sLabel, $table);
		}
		return $rightsFormList;
	}

/* ITEMS FILTER functions { --->>> */
	function prepare_subfilter_output($data, $subfilter, $subfilter_name){

		$output = array();
		order_result($data, 'name');
		foreach($data as $id => $elem){

// subfilter is activated
			if(str_in_array($id, $subfilter)){
				$span = new CSpan($elem['name'].' ('.$elem['count'].')', 'subfilter_enabled');
				$script = "javascript: create_var('zbx_filter', '".$subfilter_name.'['.$id."]', null, true);";
				$span->onClick($script);
				$output[] = $span;
			}
// subfilter isn't activated
			else{
				$script = "javascript: create_var('zbx_filter', '".$subfilter_name.'['.$id."]', '$id', true);";

// subfilter has 0 items
				if($elem['count'] == 0){
					$span = new CSpan($elem['name'].' ('.$elem['count'].')', 'subfilter_inactive');
					$span->onClick($script);
					$output[] = $span;
				}
				else{
					// this level has no active subfilters
					if(empty($subfilter)){
						$nspan = new CSpan(' ('.$elem['count'].')', 'subfilter_active');
					}
					else{
						$nspan = new CSpan(' (+'.$elem['count'].')', 'subfilter_active');
					}
					$span = new CSpan($elem['name'], 'subfilter_disabled');
					$span->onClick($script);

					$output[] = $span;
					$output[] = $nspan;
				}
			}
			$output[] = ' , ';
		}
		array_pop($output);

		return $output;
	}

	function get_item_filter_form(&$items){

		$filter_group			= $_REQUEST['filter_group'];
		$filter_hostname		= $_REQUEST['filter_hostname'];
		$filter_application		= $_REQUEST['filter_application'];
		$filter_name		= $_REQUEST['filter_name'];
		$filter_type			= $_REQUEST['filter_type'];
		$filter_key			= $_REQUEST['filter_key'];
		$filter_snmp_community		= $_REQUEST['filter_snmp_community'];
		$filter_snmpv3_securityname	= $_REQUEST['filter_snmpv3_securityname'];
		$filter_snmp_oid		= $_REQUEST['filter_snmp_oid'];
		$filter_port			= $_REQUEST['filter_port'];
		$filter_value_type		= $_REQUEST['filter_value_type'];
		$filter_data_type		= $_REQUEST['filter_data_type'];
		$filter_delay			= $_REQUEST['filter_delay'];
		$filter_history			= $_REQUEST['filter_history'];
		$filter_trends			= $_REQUEST['filter_trends'];
		$filter_status			= $_REQUEST['filter_status'];
		$filter_templated_items		= $_REQUEST['filter_templated_items'];
		$filter_with_triggers		= $_REQUEST['filter_with_triggers'];
// subfilter
		$subfilter_hosts		= $_REQUEST['subfilter_hosts'];
		$subfilter_apps			= $_REQUEST['subfilter_apps'];
		$subfilter_types		= $_REQUEST['subfilter_types'];
		$subfilter_value_types		= $_REQUEST['subfilter_value_types'];
		$subfilter_status		= $_REQUEST['subfilter_status'];
		$subfilter_templated_items	= $_REQUEST['subfilter_templated_items'];
		$subfilter_with_triggers	= $_REQUEST['subfilter_with_triggers'];
		$subfilter_history		= $_REQUEST['subfilter_history'];
		$subfilter_trends		= $_REQUEST['subfilter_trends'];
		$subfilter_interval		= $_REQUEST['subfilter_interval'];

		$form = new CForm();
		$form->setAttribute('name','zbx_filter');
		$form->setAttribute('id','zbx_filter');
		$form->setMethod('get');
		$form->addVar('filter_hostid',get_request('filter_hostid',get_request('hostid')));

		$form->addVar('subfilter_hosts',		$subfilter_hosts);
		$form->addVar('subfilter_apps',			$subfilter_apps);
		$form->addVar('subfilter_types',		$subfilter_types);
		$form->addVar('subfilter_value_types',		$subfilter_value_types);
		$form->addVar('subfilter_status',		$subfilter_status);
		$form->addVar('subfilter_templated_items',	$subfilter_templated_items);
		$form->addVar('subfilter_with_triggers',	$subfilter_with_triggers);
		$form->addVar('subfilter_history',		$subfilter_history);
		$form->addVar('subfilter_trends',		$subfilter_trends);
		$form->addVar('subfilter_interval',		$subfilter_interval);

// FORM FOR FILTER DISPLAY {
		$table = new CTable('', 'itemfilter');
		$table->setCellPadding(0);
		$table->setCellSpacing(0);

// 1st col
		$col_table1 = new CTable(null, 'filter');
		$col_table1->addRow(array(bold(S_HOST_GROUP.': '),
				array(new CTextBox('filter_group', $filter_group, 20),
					new CButton('btn_group', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->getName().
						'&dstfld1=filter_group&srctbl=host_group&srcfld1=name",450,450);', 'G'))
		));
		$col_table1->addRow(array(bold(S_HOST.': '),
				array(new CTextBox('filter_hostname', $filter_hostname, 20),
					new CButton('btn_host', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->getName().
						'&dstfld1=filter_hostname&srctbl=hosts_and_templates&srcfld1=name",450,450);', 'H'))
		));
		$col_table1->addRow(array(bold(S_APPLICATION.': '),
				array(new CTextBox('filter_application', $filter_application, 20),
					new CButton('btn_app', S_SELECT, 'return PopUp("popup.php?dstfrm='.$form->getName().
						'&dstfld1=filter_application&srctbl=applications&srcfld1=name",400,300,"application");', 'A'))
		));
		$col_table1->addRow(array(array(bold(_('Name')),SPACE.S_LIKE_SMALL.': '),
			new CTextBox("filter_name", $filter_name, 30)));

		$col_table1->addRow(array(array(bold(S_KEY),SPACE.S_LIKE_SMALL.': '),
			new CTextBox("filter_key", $filter_key, 30)));

// 2nd col
		$col_table2 = new CTable(null, 'filter');
		$fTypeVisibility = array();

//first row
		$cmbType = new CComboBox("filter_type", $filter_type); //"javascript: create_var('zbx_filter', 'filter_set', '1', true); ");
		$cmbType->setAttribute('id', 'filter_type');
		$cmbType->addItem(-1, S_ALL_SMALL);
		foreach(array('filter_delay_label','filter_delay') as $vItem){
			zbx_subarray_push($fTypeVisibility, -1, $vItem);
		}

		$itemTypes = item_type2str();
// httptest items are only for internal zabbix logic
		unset($itemTypes[ITEM_TYPE_HTTPTEST]);

		$cmbType->addItems($itemTypes);

		foreach($itemTypes as $typeNum => $typeLabel){
			if($typeNum != ITEM_TYPE_TRAPPER){
				zbx_subarray_push($fTypeVisibility, $typeNum, 'filter_delay_label');
				zbx_subarray_push($fTypeVisibility, $typeNum, 'filter_delay');
			}

			switch($typeNum){
				case ITEM_TYPE_SNMPV1:
				case ITEM_TYPE_SNMPV2C:
					$snmp_types = array(
						'filter_snmp_community_label', 'filter_snmp_community',
						'filter_snmp_oid_label', 'filter_snmp_oid',
						'filter_port_label', 'filter_port'
					);
					foreach($snmp_types as $vItem){
						zbx_subarray_push($fTypeVisibility, $typeNum, $vItem);
					}
					break;
				case ITEM_TYPE_SNMPV3:
					foreach(array(
						'filter_snmpv3_securityname_label', 'filter_snmpv3_securityname',
						'filter_snmp_oid_label', 'filter_snmp_oid',
						'filter_port_label', 'filter_port'
					) as $vItem)
						zbx_subarray_push($fTypeVisibility, $typeNum, $vItem);
					break;
			}
		}

		zbx_add_post_js("var filterTypeSwitcher = new CViewSwitcher('filter_type', 'change', ".zbx_jsvalue($fTypeVisibility, true).");");
		$col21 = new CCol(bold(S_TYPE.': '));
		$col21->setAttribute('style', 'width: 170px');

		$col_table2->addRow(array($col21, $cmbType));
	//second row
		$label221 = new CSpan(bold(_('Update interval (in sec)').': '));
		$label221->setAttribute('id', 'filter_delay_label');

		$field221 = new CNumericBox('filter_delay', $filter_delay, 5, null, true);
		$field221->setEnabled('no');

		$col_table2->addRow(array(array($label221, SPACE), array($field221, SPACE)));
	//third row
		$label231 = new CSpan(array(bold(S_SNMP_COMMUNITY), SPACE.S_LIKE_SMALL.': '));
		$label231->setAttribute('id', 'filter_snmp_community_label');

		$field231 = new CTextBox('filter_snmp_community', $filter_snmp_community, 40);
		$field231->setEnabled('no');

		$label232 = new CSpan(array(bold(S_SNMPV3_SECURITY_NAME), SPACE.S_LIKE_SMALL.': '));
		$label232->setAttribute('id', 'filter_snmpv3_securityname_label');

		$field232 = new CTextBox('filter_snmpv3_securityname', $filter_snmpv3_securityname, 40);
		$field232->setEnabled('no');

		$col_table2->addRow(array(array($label231, $label232, SPACE), array($field231, $field232, SPACE)));
	//fourth row
		$label241 = new CSpan(array(bold(S_SNMP_OID), SPACE.S_LIKE_SMALL.': '));
		$label241->setAttribute('id', 'filter_snmp_oid_label');

		$field241 = new CTextBox('filter_snmp_oid', $filter_snmp_oid, 40);
		$field241->setEnabled('no');

		$col_table2->addRow(array(array($label241, SPACE), array($field241, SPACE)));
	//fifth row
		$label251 = new CSpan(array(bold(S_PORT), SPACE.S_LIKE_SMALL.': '));
		$label251->setAttribute('id', 'filter_port_label');

		$field251 = new CNumericBox('filter_port', $filter_port, 5 ,null, true);
		$field251->setEnabled('no');

		$col_table2->addRow(array(array($label251, SPACE), array($field251, SPACE)));
// 3rd col
		$col_table3 = new CTable(null, 'filter');
		$fVTypeVisibility = array();

		$cmbValType = new CComboBox('filter_value_type', $filter_value_type); //, "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
		$cmbValType->addItem(-1, S_ALL_SMALL);
		$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64, S_NUMERIC_UNSIGNED);
		$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT, S_NUMERIC_FLOAT);
		$cmbValType->addItem(ITEM_VALUE_TYPE_STR, S_CHARACTER);
		$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, S_LOG);
		$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT, S_TEXT);

		foreach(array('filter_data_type_label','filter_data_type') as $vItem)
			zbx_subarray_push($fVTypeVisibility, ITEM_VALUE_TYPE_UINT64, $vItem);

		$col_table3->addRow(array(bold(S_TYPE_OF_INFORMATION.': '), $cmbValType));

		zbx_add_post_js("var filterValueTypeSwitcher = new CViewSwitcher('filter_value_type', 'change', ".zbx_jsvalue($fVTypeVisibility, true).");");
//second row
		$label321 = new CSpan(bold(S_DATA_TYPE.': '));
		$label321->setAttribute('id', 'filter_data_type_label');

		$field321 = new CComboBox('filter_data_type', $filter_data_type);//, 'submit()');
		$field321->addItem(-1, S_ALL_SMALL);
		$field321->addItems(item_data_type2str());
		$field321->setEnabled('no');

		$col_table3->addRow(array(array($label321, SPACE), array($field321, SPACE)));

		$col_table3->addRow(array(bold(S_KEEP_HISTORY_IN_DAYS.': '), new CNumericBox('filter_history',$filter_history,8,null,true)));

		$col_table3->addRow(array(bold(S_KEEP_TRENDS_IN_DAYS.': '), new CNumericBox('filter_trends',$filter_trends,8,null,true)));
// 4th col
		$col_table4 = new CTable(null, 'filter');

		$cmbStatus = new CComboBox('filter_status',$filter_status);
		$cmbStatus->addItem(-1,S_ALL_SMALL);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));

		$cmbBelongs = new CComboBox('filter_templated_items', $filter_templated_items);
		$cmbBelongs->addItem(-1, S_ALL_SMALL);
		$cmbBelongs->addItem(1, S_TEMPLATED_ITEMS);
		$cmbBelongs->addItem(0, S_NOT_TEMPLATED_ITEMS);

		$cmbWithTriggers = new CComboBox('filter_with_triggers', $filter_with_triggers);
		$cmbWithTriggers->addItem(-1, S_ALL_SMALL);
		$cmbWithTriggers->addItem(1, S_WITH_TRIGGERS);
		$cmbWithTriggers->addItem(0, S_WITHOUT_TRIGGERS);

		$col_table4->addRow(array(bold(S_STATUS.': '), $cmbStatus));
		$col_table4->addRow(array(bold(S_TRIGGERS.': '), $cmbWithTriggers));
		$col_table4->addRow(array(bold(S_TEMPLATE.': '), $cmbBelongs));

//adding all cols tables to main table
		$col1 = new CCol($col_table1, 'top');
		$col1->setAttribute('style', 'width: 280px');
		$col2 = new CCol($col_table2, 'top');
		$col2->setAttribute('style', 'width: 410px');
		$col3 = new CCol($col_table3, 'top');
		$col3->setAttribute('style', 'width: 160px');
		$col4 = new CCol($col_table4, 'top');

		$table->addRow(array($col1, $col2, $col3, $col4));

		$reset = new CSpan( S_RESET,'link_menu');
		$reset->onClick("javascript: clearAllForm('zbx_filter');");

		$filter = new CButton('filter',S_FILTER,"javascript: create_var('zbx_filter', 'filter_set', '1', true);");
		$filter->useJQueryStyle();

		$div_buttons = new CDiv(array($filter, SPACE, SPACE, SPACE, $reset));
		$div_buttons->setAttribute('style', 'padding: 4px 0;');
		$footer = new CCol($div_buttons, 'center');
		$footer->setColSpan(4);

		$table->addRow($footer);
		$form->addItem($table);

// } FORM FOR FILTER DISPLAY

// SUBFILTERS {
		$h = new CDiv(S_SUBFILTER.SPACE.'['.S_AFFECTS_ONLY_FILTERED_DATA_SMALL.']', 'thin_header');
		$form->addItem($h);

		$table_subfilter = new CTable(null, 'filter');

// array contains subfilters and number of items in each
		$item_params = array(
			'hosts' => array(),
			'applications' => array(),
			'types' => array(),
			'value_types' => array(),
			'status' => array(),
			'templated_items' => array(),
			'with_triggers' => array(),
			'history' => array(),
			'trends' => array(),
			'interval' => array()
		);

// generate array with values for subfilters of selected items
		foreach($items as $num => $item){
			if(zbx_empty($filter_hostname)){
// hosts
				$host = reset($item['hosts']);

				if(!isset($item_params['hosts'][$host['hostid']]))
					$item_params['hosts'][$host['hostid']] = array('name' => $host['name'], 'count' => 0);

				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_hosts') continue;
					$show_item &= $value;
				}
				if($show_item){
					$host = reset($item['hosts']);
					$item_params['hosts'][$host['hostid']]['count']++;
				}
			}

// applications
			foreach($item['applications'] as $appid => $app){
				if(!isset($item_params['applications'][$app['name']])){
					$item_params['applications'][$app['name']] = array('name' => $app['name'], 'count' => 0);
				}
			}
			$show_item = true;
			foreach($item['subfilters'] as $name => $value){
				if($name == 'subfilter_apps') continue;
				$show_item &= $value;
			}
			$sel_app = false;
			if($show_item){
// if any of item applications are selected
				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $subfilter_apps)){
						$sel_app = true;
						break;
					}
				}

				foreach($item['applications'] as $app){
					if(str_in_array($app['name'], $subfilter_apps) || !$sel_app){
						$item_params['applications'][$app['name']]['count']++;
					}
				}
			}

// types
			if($filter_type == -1){
				if(!isset($item_params['types'][$item['type']])){
					$item_params['types'][$item['type']] = array('name' => item_type2str($item['type']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_types') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['types'][$item['type']]['count']++;
				}
			}

// value types
			if($filter_value_type == -1){
				if(!isset($item_params['value_types'][$item['value_type']])){
					$item_params['value_types'][$item['value_type']] = array('name' => item_value_type2str($item['value_type']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_value_types') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['value_types'][$item['value_type']]['count']++;
				}
			}

// status
			if($filter_status == -1){
				if(!isset($item_params['status'][$item['status']])){
					$item_params['status'][$item['status']] = array('name' => item_status2str($item['status']), 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_status') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['status'][$item['status']]['count']++;
				}
			}

// template
			if($filter_templated_items == -1){
				if(($item['templateid'] == 0) && !isset($item_params['templated_items'][0])){
					$item_params['templated_items'][0] = array('name' => S_NOT_TEMPLATED_ITEMS, 'count' => 0);
				}
				else if(($item['templateid'] > 0) && !isset($item_params['templated_items'][1])){
					$item_params['templated_items'][1] = array('name' => S_TEMPLATED_ITEMS, 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_templated_items') continue;
					$show_item &= $value;
				}
				if($show_item){
					if($item['templateid'] == 0){
						$item_params['templated_items'][0]['count']++;
					}
					else{
						$item_params['templated_items'][1]['count']++;
					}
				}
			}

// with triggers
			if($filter_with_triggers == -1){
				if((count($item['triggers']) == 0) && !isset($item_params['with_triggers'][0])){
					$item_params['with_triggers'][0] = array('name' => S_WITHOUT_TRIGGERS, 'count' => 0);
				}
				else if((count($item['triggers']) > 0) && !isset($item_params['with_triggers'][1])){
					$item_params['with_triggers'][1] = array('name' => S_WITH_TRIGGERS, 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_with_triggers') continue;
					$show_item &= $value;
				}
				if($show_item){
					if(count($item['triggers']) == 0){
						$item_params['with_triggers'][0]['count']++;
					}
					else{
						$item_params['with_triggers'][1]['count']++;
					}
				}
			}

// trends
			if(zbx_empty($filter_trends)){
				if(!isset($item_params['trends'][$item['trends']])){
					$item_params['trends'][$item['trends']] = array('name' => $item['trends'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_trends') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['trends'][$item['trends']]['count']++;
				}
			}

// history
			if(zbx_empty($filter_history)){
				if(!isset($item_params['history'][$item['history']])){
					$item_params['history'][$item['history']] = array('name' => $item['history'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_history') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['history'][$item['history']]['count']++;
				}
			}

// interval
			if(zbx_empty($filter_delay) && ($filter_type != ITEM_TYPE_TRAPPER)){
				if(!isset($item_params['interval'][$item['delay']])){
					$item_params['interval'][$item['delay']] = array('name' => $item['delay'], 'count' => 0);
				}
				$show_item = true;
				foreach($item['subfilters'] as $name => $value){
					if($name == 'subfilter_interval') continue;
					$show_item &= $value;
				}
				if($show_item){
					$item_params['interval'][$item['delay']]['count']++;
				}
			}
		}

// output
		if(zbx_empty($filter_hostname) && (count($item_params['hosts']) > 1)){
			$hosts_output = prepare_subfilter_output($item_params['hosts'], $subfilter_hosts, 'subfilter_hosts');
			$table_subfilter->addRow(array(S_HOSTS, $hosts_output));
		}

		if(!empty($item_params['applications']) && (count($item_params['applications']) > 1)){
			$application_output = prepare_subfilter_output($item_params['applications'], $subfilter_apps, 'subfilter_apps');
			$table_subfilter->addRow(array(S_APPLICATIONS, $application_output));
		}

		if(($filter_type == -1) && (count($item_params['types']) > 1)){
			$type_output = prepare_subfilter_output($item_params['types'], $subfilter_types, 'subfilter_types');
			$table_subfilter->addRow(array(S_TYPES, $type_output));
		}

		if(($filter_value_type == -1) && (count($item_params['value_types']) > 1)){
			$value_types_output = prepare_subfilter_output($item_params['value_types'], $subfilter_value_types, 'subfilter_value_types');
			$table_subfilter->addRow(array(S_TYPE_OF_INFORMATION, $value_types_output));
		}

		if(($filter_status == -1) && (count($item_params['status']) > 1)){
			$status_output = prepare_subfilter_output($item_params['status'], $subfilter_status, 'subfilter_status');
			$table_subfilter->addRow(array(S_STATUS, $status_output));
		}

		if(($filter_templated_items == -1) && (count($item_params['templated_items']) > 1)){
			$templated_items_output = prepare_subfilter_output($item_params['templated_items'], $subfilter_templated_items, 'subfilter_templated_items');
			$table_subfilter->addRow(array(S_TEMPLATE, $templated_items_output));
		}

		if(($filter_with_triggers == -1) && (count($item_params['with_triggers']) > 1)){
			$with_triggers_output = prepare_subfilter_output($item_params['with_triggers'], $subfilter_with_triggers, 'subfilter_with_triggers');
			$table_subfilter->addRow(array(S_WITH_TRIGGERS, $with_triggers_output));
		}

		if(zbx_empty($filter_history) && (count($item_params['history']) > 1)){
			$history_output = prepare_subfilter_output($item_params['history'], $subfilter_history, 'subfilter_history');
			$table_subfilter->addRow(array(S_HISTORY, $history_output));
		}

		if(zbx_empty($filter_trends) && (count($item_params['trends']) > 1)){
			$trends_output = prepare_subfilter_output($item_params['trends'], $subfilter_trends, 'subfilter_trends');
			$table_subfilter->addRow(array(S_TRENDS, $trends_output));
		}

		if(zbx_empty($filter_delay) && ($filter_type != ITEM_TYPE_TRAPPER) && (count($item_params['interval']) > 1)){
			$interval_output = prepare_subfilter_output($item_params['interval'], $subfilter_interval, 'subfilter_interval');
			$table_subfilter->addRow(array(S_INTERVAL, $interval_output));
		}
//} SUBFILTERS

		$form->addItem($table_subfilter);

	return $form;
	}

// Insert form for Item information
	function insert_item_form(){
		$frmItem = new CFormTable(S_ITEM);
		$frmItem->setAttribute('style','visibility: hidden;');
		$frmItem->setHelp('web.items.item.php');

		$parent_discoveryid = get_request('parent_discoveryid');
		if($parent_discoveryid){
			$frmItem->addVar('parent_discoveryid', $parent_discoveryid);

			$options = array(
				'itemids' => $parent_discoveryid,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true,
			);
			$discoveryRule = API::DiscoveryRule()->get($options);
			$discoveryRule = reset($discoveryRule);
			$hostid = $discoveryRule['hostid'];
		}
		else
			$hostid = get_request('form_hostid', 0);

		$interfaceid = get_request('interfaceid', 0);
		$name = get_request('name', '');
		$description = get_request('description', '');
		$key = get_request('key', '');
		$hostname = get_request('hostname', null);
		$delay = get_request('delay', ZBX_ITEM_DELAY_DEFAULT);
		$history = get_request('history', 90);
		$status = get_request('status', 0);
		$type = get_request('type', 0);
		$snmp_community = get_request('snmp_community', 'public');
		$snmp_oid = get_request('snmp_oid', 'interfaces.ifTable.ifEntry.ifInOctets.1');
		$port = get_request('port', '');
		$value_type = get_request('value_type', ITEM_VALUE_TYPE_UINT64);
		$data_type = get_request('data_type', ITEM_DATA_TYPE_DECIMAL);
		$trapper_hosts = get_request('trapper_hosts', '');
		$units = get_request('units', '');
		$valuemapid = get_request('valuemapid', 0);
		$params = get_request('params', '');
		$multiplier = get_request('multiplier', 0);
		$delta = get_request('delta', 0);
		$trends = get_request('trends', 365);
		$new_application = get_request('new_application', '');
		$applications = get_request('applications', array());
		$delay_flex = get_request('delay_flex', array());

		$snmpv3_securityname = get_request('snmpv3_securityname', '');
		$snmpv3_securitylevel = get_request('snmpv3_securitylevel', 0);
		$snmpv3_authpassphrase = get_request('snmpv3_authpassphrase', '');
		$snmpv3_privpassphrase = get_request('snmpv3_privpassphrase', '');
		$ipmi_sensor = get_request('ipmi_sensor', '');
		$authtype = get_request('authtype', 0);
		$username = get_request('username', '');
		$password = get_request('password', '');
		$publickey = get_request('publickey', '');
		$privatekey = get_request('privatekey', '');

		$formula = get_request('formula', '1');
		$logtimefmt = get_request('logtimefmt', '');

		$inventory_link = get_request('inventory_link', '0');

		$add_groupid = get_request('add_groupid', get_request('groupid', 0));

		$limited = false;

		$types = item_type2str();
		// http items only for internal processes
		unset($types[ITEM_TYPE_HTTPTEST]);

		if(isset($_REQUEST['itemid'])){
			$frmItem->addVar('itemid', $_REQUEST['itemid']);

			$options = array(
				'itemids' => $_REQUEST['itemid'],
				'output' => API_OUTPUT_EXTEND,
			);
			$item_data = API::Item()->get($options);
			$item_data = reset($item_data);

			$hostid	= ($hostid > 0) ? $hostid : $item_data['hostid'];
			$limited = ($item_data['templateid'] != 0);
		}

		if(is_null($hostname)){
			if($hostid > 0){
				$options = array(
					'hostids' => $hostid,
					'output' => array('name'),
					'templated_hosts' => 1
				);
				$host_info = API::Host()->get($options);
				$host_info = reset($host_info);
				$hostname = $host_info['name'];
			}
			else
				$hostname = S_NOT_SELECTED_SMALL;
		}

		if((isset($_REQUEST['itemid']) && !isset($_REQUEST['form_refresh'])) || $limited){
			$name		= $item_data['name'];
			$description		= $item_data['description'];
			$key			= $item_data['key_'];
			$interfaceid	= $item_data['interfaceid'];
//			$host			= $item_data['host'];
			$type			= $item_data['type'];
			$snmp_community		= $item_data['snmp_community'];
			$snmp_oid		= $item_data['snmp_oid'];
			$port		= $item_data['port'];
			$value_type		= $item_data['value_type'];
			$data_type		= $item_data['data_type'];
			$trapper_hosts		= $item_data['trapper_hosts'];
			$units			= $item_data['units'];
			$valuemapid		= $item_data['valuemapid'];
			$multiplier		= $item_data['multiplier'];
			$hostid			= $item_data['hostid'];
			$params			= $item_data['params'];

			$snmpv3_securityname	= $item_data['snmpv3_securityname'];
			$snmpv3_securitylevel	= $item_data['snmpv3_securitylevel'];
			$snmpv3_authpassphrase	= $item_data['snmpv3_authpassphrase'];
			$snmpv3_privpassphrase	= $item_data['snmpv3_privpassphrase'];

			$ipmi_sensor		= $item_data['ipmi_sensor'];

			$authtype		= $item_data['authtype'];
			$username		= $item_data['username'];
			$password		= $item_data['password'];
			$publickey		= $item_data['publickey'];
			$privatekey		= $item_data['privatekey'];

			$formula		= $item_data['formula'];
			$logtimefmt		= $item_data['logtimefmt'];

			$inventory_link   = $item_data['inventory_link'];

			$new_application	= get_request('new_application',	'');

			if(!$limited || !isset($_REQUEST['form_refresh'])){
				$delay		= $item_data['delay'];
				if (($type == ITEM_TYPE_TRAPPER || $type == ITEM_TYPE_SNMPTRAP) && $delay == 0) {
					$delay = ZBX_ITEM_DELAY_DEFAULT;
				}

				$history	= $item_data['history'];
				$status		= $item_data['status'];
				$delta		= $item_data['delta'];
				$trends		= $item_data['trends'];
				$db_delay_flex	= $item_data['delay_flex'];

				if(isset($db_delay_flex)){
					$arr_of_dellays = explode(';',$db_delay_flex);
					foreach($arr_of_dellays as $one_db_delay){
						$arr_of_delay = explode('/',$one_db_delay);
						if(!isset($arr_of_delay[0]) || !isset($arr_of_delay[1])) continue;

						array_push($delay_flex, array('delay'=> $arr_of_delay[0], 'period'=> $arr_of_delay[1]));
					}
				}

				$applications = array_unique(zbx_array_merge($applications, get_applications_by_itemid($_REQUEST['itemid'])));
			}
		}

		$securityLevelVisibility = array();
		$valueTypeVisibility = array();
		$authTypeVisibility = array();
		$typeVisibility = array();
		$delay_flex_el = array();


		$i = 0;
		foreach($delay_flex as $val){
			if(!isset($val['delay']) && !isset($val['period'])) continue;

			array_push($delay_flex_el,
				array(
					new CCheckBox('rem_delay_flex['.$i.']', 'no', null,$i),
					$val['delay'],
					' sec at ',
					$val['period']),
				BR());
			$frmItem->addVar('delay_flex['.$i.'][delay]', $val['delay']);
			$frmItem->addVar('delay_flex['.$i.'][period]', $val['period']);
			foreach($types as $it => $caption) {
				if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_ZABBIX_ACTIVE || $it == ITEM_TYPE_SNMPTRAP) continue;
				zbx_subarray_push($typeVisibility, $it, 'delay_flex['.$i.'][delay]');
				zbx_subarray_push($typeVisibility, $it, 'delay_flex['.$i.'][period]');
				zbx_subarray_push($typeVisibility, $it, 'rem_delay_flex['.$i.']');
			}
			$i++;
			if($i >= 7) break;	/* limit count of intervals
						 * 7 intervals by 30 symbols = 210 characters
						 * db storage field is 256
						 */
		}

		array_push($delay_flex_el, count($delay_flex_el)==0 ? S_NO_FLEXIBLE_INTERVALS : new CSubmit('del_delay_flex',S_DELETE_SELECTED));

		if(count($applications)==0) array_push($applications, 0);

		if(isset($_REQUEST['itemid'])){
			$caption = array();
			$itemid = $_REQUEST['itemid'];
			do{
				$sql = 'SELECT i.itemid, i.templateid, h.name'.
						' FROM items i, hosts h'.
						' WHERE i.itemid='.$itemid.
							' AND h.hostid=i.hostid';
				$itemFromDb = DBfetch(DBselect($sql));
				if($itemFromDb){
					if(bccomp($_REQUEST['itemid'], $itemid) == 0){
						$caption[] = SPACE;
						$caption[] = $itemFromDb['name'];
					}
					else{
						$caption[] = ' : ';
						$caption[] = new CLink($itemFromDb['name'], 'items.php?form=update&itemid='.$itemFromDb['itemid'], 'highlight underline');
					}

					$itemid = $itemFromDb['templateid'];
				}
				else break;
			}while($itemid != 0);

			$caption[] = ($parent_discoveryid) ? S_ITEM_PROTOTYPE.' "' : S_ITEM.' "';
			$caption = array_reverse($caption);
			$caption[] = ': ';
			$caption[] = $item_data['name'];
			$caption[] = '"';
			$frmItem->setTitle($caption);
		}
		else
			$frmItem->setTitle(_s('Item %1$s : %2$s', $hostname, $name));

		if(!$parent_discoveryid){
			$frmItem->addVar('form_hostid', $hostid);
			$frmItem->addRow(S_HOST, array(
				new CTextBox('hostname', $hostname, 32, true),
				new CButton('btn_host', S_SELECT,
					"return PopUp('popup.php?dstfrm=".$frmItem->getName().
					"&dstfld1=hostname&dstfld2=form_hostid&srctbl=hosts_and_templates&srcfld1=name&srcfld2=hostid&noempty=1',450,450);",
					'H')
			));

			$interfaces = API::HostInterface()->get(array(
				'hostids' => $hostid,
				'output' => API_OUTPUT_EXTEND
			));
			if(!empty($interfaces)){
				$sbIntereaces = new CComboBox('interfaceid', $interfaceid);
				foreach($interfaces as $ifnum => $interface){
					$caption = $interface['useip'] ? $interface['ip'] : $interface['dns'];
					$caption.= ' : '.$interface['port'];

					$sbIntereaces->addItem($interface['interfaceid'], $caption);
				}
				$frmItem->addRow(S_HOST_INTERFACE, $sbIntereaces, null, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_ZABBIX, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_ZABBIX, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SIMPLE, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SIMPLE, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_EXTERNAL, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_EXTERNAL, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'interfaceid');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPTRAP, 'interface_row');
				zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPTRAP, 'interfaceid');
			}
		}

		$frmItem->addRow(_('Name'), new CTextBox('name', $name, 40, $limited));

		if($limited){
			$frmItem->addRow(S_TYPE,  new CTextBox('typename', item_type2str($type), 40, 'yes'));
			$frmItem->addVar('type', $type);
		}
		else{
			$cmbType = new CComboBox('type',$type);
			$cmbType->addItems($types);
			$frmItem->addRow(S_TYPE, $cmbType);
		}

		$row = new CRow(array(new CCol(S_SNMP_OID,'form_row_l'), new CCol(new CTextBox('snmp_oid',$snmp_oid,40,$limited), 'form_row_r')));
		$row->setAttribute('id', 'row_snmp_oid');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_oid');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmp_oid');

		$row = new CRow(array(new CCol(S_SNMP_COMMUNITY,'form_row_l'), new CCol(new CTextBox('snmp_community',$snmp_community,16), 'form_row_r')));
		$row->setAttribute('id', 'row_snmp_community');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_snmp_community');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_snmp_community');

		$row = new CRow(array(new CCol(S_SNMPV3_SECURITY_NAME,'form_row_l'), new CCol(new CTextBox('snmpv3_securityname',$snmpv3_securityname,64), 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_securityname');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securityname');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securityname');

		$cmbSecLevel = new CComboBox('snmpv3_securitylevel', $snmpv3_securitylevel);
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,'noAuthNoPriv');
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,'authNoPriv');
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,'authPriv');

		$row = new CRow(array(new CCol(S_SNMPV3_SECURITY_LEVEL,'form_row_l'), new CCol($cmbSecLevel, 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_securitylevel');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'snmpv3_securitylevel');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_snmpv3_securitylevel');

		$row = new CRow(array(new CCol(S_SNMPV3_AUTH_PASSPHRASE,'form_row_l'), new CCol(new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64), 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_authpassphrase');
		$frmItem->addRow($row);
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'snmpv3_authpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');

		$row = new CRow(array(new CCol(S_SNMPV3_PRIV_PASSPHRASE,'form_row_l'), new CCol(new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64), 'form_row_r')));
		$row->setAttribute('id', 'row_snmpv3_privpassphrase');
		$frmItem->addRow($row);
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_privpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'snmpv3_authpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');

		$row = new CRow(array(new CCol(S_PORT,'form_row_l'), new CCol(new CTextBox('port',$port,15), 'form_row_r')));
		$row->setAttribute('id', 'row_port');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV1, 'row_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV2C, 'row_port');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SNMPV3, 'row_port');


		$row = new CRow(array(new CCol(S_IPMI_SENSOR,'form_row_l'), new CCol(new CTextBox('ipmi_sensor', $ipmi_sensor, 64, $limited),'form_row_r')));
		$row->setAttribute('id', 'row_ipmi_sensor');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'ipmi_sensor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_IPMI, 'row_ipmi_sensor');

		if($limited)
			$btnSelect = null;
		else
			$btnSelect = new CButton('btn1', _('Select'),
				"return PopUp('popup.php?dstfrm=".$frmItem->getName()."&dstfld1=key&srctbl=help_items&srcfld1=key_&itemtype='+jQuery('#type option:selected').val());",
				'T');

		$frmItem->addRow(S_KEY, array(new CTextBox('key',$key,40,$limited), $btnSelect));
		foreach($types as $it => $ilabel) {
			switch($it){
				case ITEM_TYPE_DB_MONITOR:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key','defaultValue'=> 'db.odbc.select[<unique short description>]'));
					zbx_subarray_push($typeVisibility, $it, array('id'=>'params_dbmonitor','defaultValue'=> "DSN=<database source name>\nuser=<user name>\npassword=<password>\nsql=<query>"));
					break;
				case ITEM_TYPE_SSH:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key','defaultValue'=> 'ssh.run[<unique short description>,<ip>,<port>,<encoding>]'));
					break;
				case ITEM_TYPE_TELNET:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key', 'defaultValue'=> 'telnet.run[<unique short description>,<ip>,<port>,<encoding>]'));
					break;
				case ITEM_TYPE_JMX:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key', 'defaultValue'=> 'jmx[<object name>,<attribute name>]'));
					break;
				default:
					zbx_subarray_push($typeVisibility, $it, array('id'=>'key', 'defaultValue'=> ''));
			}
		}

		$cmbAuthType = new CComboBox('authtype', $authtype);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY,S_PUBLIC_KEY);

		$row = new CRow(array(new CCol(S_AUTHENTICATION_METHOD,'form_row_l'), new CCol($cmbAuthType,'form_row_r')));
		$row->setAttribute('id', 'row_authtype');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'authtype');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_authtype');

		$row = new CRow(array(new CCol(S_USER_NAME,'form_row_l'), new CCol(new CTextBox('username',$username,16),'form_row_r')));
		$row->setAttribute('id', 'row_username');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'username');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'row_username');


		$row = new CRow(array(new CCol(S_PUBLIC_KEY_FILE,'form_row_l'), new CCol(new CTextBox('publickey',$publickey,16),'form_row_r')));
		$row->setAttribute('id', 'row_publickey');
		$frmItem->addRow($row);
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'publickey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_publickey');

		$row = new CRow(array(new CCol(S_PRIVATE_KEY_FILE,'form_row_l'), new CCol(new CTextBox('privatekey',$privatekey,16),'form_row_r')));
		$row->setAttribute('id', 'row_privatekey');
		$frmItem->addRow($row);
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'privatekey');
		zbx_subarray_push($authTypeVisibility, ITEM_AUTHTYPE_PUBLICKEY, 'row_privatekey');

		$row = new CRow(array(new CCol(_('Password'), 'form_row_l'), new CCol(new CTextBox('password', $password, 16), 'form_row_r')));
		$row->setAttribute('id', 'row_password');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'password');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_JMX, 'row_password');

		$spanEC = new CSpan(S_EXECUTED_SCRIPT);
		$spanEC->setAttribute('id', 'label_executed_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'label_executed_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'label_executed_script');

		$spanP = new CSpan(S_PARAMS);
		$spanP->setAttribute('id', 'label_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'label_params');

		$spanF = new CSpan(S_FORMULA);
		$spanF->setAttribute('id', 'label_formula');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'label_formula');


		$params_script = new CTextArea('params', $params, 60, 4);
		$params_script->setAttribute('id', 'params_script');
		$params_dbmonitor = new CTextArea('params', $params, 60, 4);
		$params_dbmonitor->setAttribute('id', 'params_dbmonitor');
		$params_calculted = new CTextArea('params', $params, 60, 4);
		$params_calculted->setAttribute('id', 'params_calculted');

		$row = new CRow(array(
			new CCol(array($spanEC, $spanP, $spanF),'form_row_l'),
			new CCol(array($params_script, $params_dbmonitor, $params_calculted),'form_row_r')
		));
		$row->setAttribute('id', 'row_params');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'params_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_SSH, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'params_script');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TELNET, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'params_dbmonitor');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_DB_MONITOR, 'row_params');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'params_calculted');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_CALCULATED, 'row_params');


		if($limited){
			$frmItem->addVar('value_type', $value_type);
			$cmbValType = new CTextBox('value_type_name', item_value_type2str($value_type), 40, 'yes');
		}
		else {
			$cmbValType = new CComboBox('value_type',$value_type);
			$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UNSIGNED);
			$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
			$cmbValType->addItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
			$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
			$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
		}

		$frmItem->addRow(S_TYPE_OF_INFORMATION,$cmbValType);

		if($limited){
			$frmItem->addVar('data_type', $data_type);
			$cmbDataType = new CTextBox('data_type_name', item_data_type2str($data_type), 20, 'yes');
		}
		else{
			$cmbDataType = new CComboBox('data_type', $data_type);
			$cmbDataType->addItems(item_data_type2str());
		}

		$row = new CRow(array(new CCol(S_DATA_TYPE,'form_row_l'), new CCol($cmbDataType,'form_row_r')));
		$row->setAttribute('id', 'row_data_type');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'data_type');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_data_type');

		$row = new CRow(array(new CCol(S_UNITS,'form_row_l'), new CCol(new CTextBox('units',$units,40, $limited),'form_row_r')));
		$row->setAttribute('id', 'row_units');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'units');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_units');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'units');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_units');

		$mltpbox = array();
		if($limited){
			$frmItem->addVar('multiplier', $multiplier);

			$mcb = new CCheckBox('multiplier', $multiplier == 1 ? 'yes':'no');
			$mcb->setAttribute('disabled', 'disabled');
			$mltpbox[] = $mcb;
			if($multiplier){
				$mltpbox[] = SPACE;
				$ctb = new CTextBox('formula', $formula, 10, 1);
				$ctb->setAttribute('style', 'text-align: right;');
				$mltpbox[] = $ctb;
			}
		}
		else{
			$mltpbox[] = new CCheckBox('multiplier',$multiplier == 1 ? 'yes':'no', 'var editbx = document.getElementById(\'formula\'); if(editbx) editbx.disabled = !this.checked;', 1);
			$mltpbox[] = SPACE;
			$ctb = new CTextBox('formula', $formula, 10);
			$ctb->setAttribute('style', 'text-align: right;');
			$mltpbox[] = $ctb;
		}


		$row = new CRow(array(new CCol(S_USE_CUSTOM_MULTIPLIER,'form_row_l'), new CCol($mltpbox,'form_row_r')));
		$row->setAttribute('id', 'row_multiplier');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'multiplier');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_multiplier');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'multiplier');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_multiplier');


		$row = new CRow(array(new CCol(_('Update interval (in sec)'), 'form_row_l'), new CCol(new CNumericBox('delay', $delay, 5), 'form_row_r')));
		$row->setAttribute('id', 'row_delay');
		$frmItem->addRow($row);
		foreach($types as $it => $ilabel) {
			if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_SNMPTRAP) continue;
			zbx_subarray_push($typeVisibility, $it, 'delay');
			zbx_subarray_push($typeVisibility, $it, 'row_delay');
		}

		$row = new CRow(array(new CCol(S_FLEXIBLE_INTERVALS,'form_row_l'), new CCol($delay_flex_el,'form_row_r')));
		$row->setAttribute('id', 'row_flex_intervals');
		$frmItem->addRow($row);

		$row = new CRow(array(new CCol(S_NEW_FLEXIBLE_INTERVAL,'form_row_l'), new CCol(
			array(
				S_DELAY, SPACE,
				new CNumericBox('new_delay_flex[delay]','50',5),
				S_PERIOD, SPACE,
				new CTextBox('new_delay_flex[period]',ZBX_DEFAULT_INTERVAL,27), BR(),
				new CSubmit('add_delay_flex',S_ADD)
			),'form_row_r')), 'new');
		$row->setAttribute('id', 'row_new_delay_flex');
		$frmItem->addRow($row);

		foreach($types as $it => $ilabel){
			if($it == ITEM_TYPE_TRAPPER || $it == ITEM_TYPE_ZABBIX_ACTIVE || $it == ITEM_TYPE_SNMPTRAP) continue;
			zbx_subarray_push($typeVisibility, $it, 'row_flex_intervals');
			zbx_subarray_push($typeVisibility, $it, 'row_new_delay_flex');
			zbx_subarray_push($typeVisibility, $it, 'new_delay_flex[delay]');
			zbx_subarray_push($typeVisibility, $it, 'new_delay_flex[period]');
			zbx_subarray_push($typeVisibility, $it, 'add_delay_flex');
		}

		$frmItem->addRow(S_KEEP_HISTORY_IN_DAYS, array(
			new CNumericBox('history',$history,8),
			(!isset($_REQUEST['itemid']) || $parent_discoveryid) ? null :
				new CButtonQMessage('del_history',S_CLEAR_HISTORY,S_HISTORY_CLEARING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q)
			));

		$row = new CRow(array(new CCol(S_KEEP_TRENDS_IN_DAYS,'form_row_l'), new CCol(new CNumericBox('trends',$trends,8),'form_row_r')));
		$row->setAttribute('id', 'row_trends');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'trends');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_trends');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'trends');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_trends');

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st, item_status2str($st));
		$frmItem->addRow(S_STATUS, $cmbStatus);

		$row = new CRow(array(new CCol(S_LOG_TIME_FORMAT,'form_row_l'), new CCol(new CTextBox('logtimefmt',$logtimefmt,16,$limited),'form_row_r')));
		$row->setAttribute('id', 'row_logtimefmt');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_LOG, 'logtimefmt');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_LOG, 'row_logtimefmt');

		$cmbDelta= new CComboBox('delta',$delta);
		$cmbDelta->addItem(0,S_AS_IS);
		$cmbDelta->addItem(1,S_DELTA_SPEED_PER_SECOND);
		$cmbDelta->addItem(2,S_DELTA_SIMPLE_CHANGE);

		$row = new CRow(array(new CCol(S_STORE_VALUE,'form_row_l'), new CCol($cmbDelta,'form_row_r')));
		$row->setAttribute('id', 'row_delta');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'delta');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_delta');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'delta');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_delta');

		if($limited){
			$frmItem->addVar('valuemapid', $valuemapid);
			$map_name = S_AS_IS;
			if($map_data = DBfetch(DBselect('SELECT name FROM valuemaps WHERE valuemapid='.$valuemapid))){
				$map_name = $map_data['name'];
			}
			$cmbMap = new CTextBox('valuemap_name', $map_name, 20, 'yes');
		}
		else {
			$cmbMap = new CComboBox('valuemapid',$valuemapid);
			$cmbMap->addItem(0,S_AS_IS);
			$db_valuemaps = DBselect('SELECT * FROM valuemaps WHERE '.DBin_node('valuemapid'));
			while($db_valuemap = DBfetch($db_valuemaps))
				$cmbMap->addItem(
					$db_valuemap['valuemapid'],
					get_node_name_by_elid($db_valuemap['valuemapid'], null, ': ').$db_valuemap['name']
					);
		}

		$link = new CLink(S_SHOW_VALUE_MAPPINGS,'config.php?config=6');
		$link->setAttribute('target','_blank');

		$row = new CRow(array(new CCol(S_SHOW_VALUE), new CCol(array($cmbMap, SPACE, $link))));
		$row->setAttribute('id', 'row_valuemap');
		$frmItem->addRow($row);
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'valuemapid');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_valuemap');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'valuemap_name');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'valuemapid');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_valuemap');
		zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'valuemap_name');

		$row = new CRow(array(new CCol(S_ALLOWED_HOSTS,'form_row_l'), new CCol(new CTextBox('trapper_hosts',$trapper_hosts,40),'form_row_r')));
		$row->setAttribute('id', 'row_trapper_hosts');
		$frmItem->addRow($row);
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TRAPPER, 'trapper_hosts');
		zbx_subarray_push($typeVisibility, ITEM_TYPE_TRAPPER, 'row_trapper_hosts');


		$new_app = new CTextBox('new_application',$new_application,40);
		$frmItem->addRow(S_NEW_APPLICATION,$new_app,'new');

		$cmbApps = new CListBox('applications[]',$applications,6);
		$cmbApps->addItem(0,'-'.S_NONE.'-');

		$sql = 'SELECT DISTINCT applicationid,name '.
				' FROM applications '.
				' WHERE hostid='.$hostid.
				' ORDER BY name';
		$db_applications = DBselect($sql);
		while($db_app = DBfetch($db_applications)){
			$cmbApps->addItem($db_app['applicationid'],$db_app['name']);
		}
		$frmItem->addRow(S_APPLICATIONS,$cmbApps);

		// control to choose host_inventory field, that will be populated by this item (if any)
		if(!$parent_discoveryid){
			$itemCloned = isset($_REQUEST['clone']);
			$hostInventoryFieldDropDown = new CComboBox('inventory_link');
			$possibleHostInventories = getHostInventories();

			// which fields are already being populated by other items
			$options = array(
				'output' => array('inventory_link'),
				'filter' => array('hostid' => $hostid),
				'nopermissions' => true
			);
			$alreadyPopulated = API::item()->get($options);
			$alreadyPopulated = zbx_toHash($alreadyPopulated, 'inventory_link');
			// default option - do not populate
			$hostInventoryFieldDropDown->addItem(0, '-'._('None').'-', $inventory_link == '0' ? 'yes' : null); // 'yes' means 'selected'
			// a list of available host inventory fields
			foreach($possibleHostInventories as $fieldNo => $fieldInfo){
				if(isset($alreadyPopulated[$fieldNo])){
					$enabled = isset($item_data['inventory_link'])
							? $item_data['inventory_link'] == $fieldNo
							: $inventory_link == $fieldNo && !$itemCloned;
				}
				else{
					$enabled = true;
				}
				$hostInventoryFieldDropDown->addItem(
					$fieldNo,
					$fieldInfo['title'],
					($inventory_link == $fieldNo && $enabled  ? 'yes' : null), // selected?
					$enabled ? 'yes' : 'no'
				);
			}

			$row =  new CRow(array(_('Item will populate host inventory field'), $hostInventoryFieldDropDown));
			$row->setAttribute('id', 'row_inventory_link');
			$frmItem->addRow($row);
			// inventory link field should not be visible for all item value types except 'log'
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_STR, 'inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_STR, 'row_inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_TEXT, 'inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_TEXT, 'row_inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_FLOAT, 'row_inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'inventory_link');
			zbx_subarray_push($valueTypeVisibility, ITEM_VALUE_TYPE_UINT64, 'row_inventory_link');
		}

		$tarea = new CTextArea('description', $description);
		$tarea->addStyle('margin-top: 5px;');
		$frmItem->addRow(_('Description'), $tarea);

		$frmRow = array(new CSubmit('save',S_SAVE));
		if(isset($_REQUEST['itemid'])){
			array_push($frmRow,
				SPACE,
				new CSubmit('clone',S_CLONE));

			if(!$limited){
				array_push($frmRow,
					SPACE,
					new CButtonDelete(S_DELETE_SELECTED_ITEM_Q,
						url_param('form').url_param('groupid').url_param('itemid').url_param('parent_discoveryid'))
				);
			}
		}
		array_push($frmRow,
			SPACE,
			new CButtonCancel(url_param('groupid').url_param('parent_discoveryid'))
		);

		if($parent_discoveryid){
			$frmItem->addItemToBottomRow($frmRow,'form_row_last');
		}
		else{
			$frmItem->addSpanRow($frmRow,'form_row_last');
		}


		if(!$parent_discoveryid){
// GROUP OPERATIONS
			$cmbGroups = new CComboBox('add_groupid',$add_groupid);
			$groups = API::HostGroup()->get(array(
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
			));
			order_result($groups, 'name');
			foreach($groups as $group){
				$cmbGroups->addItem($group['groupid'], get_node_name_by_elid($group['groupid'], null, ': ').$group['name']);
			}
			$frmItem->addRow(S_GROUP,$cmbGroups);

			$cmbAction = new CComboBox('action');
			$cmbAction->addItem('add to group', _('Add to host group'));
			if(isset($_REQUEST['itemid'])){
				$cmbAction->addItem('update in group', _('Update in host group'));
				$cmbAction->addItem('delete from group', _('Delete from host group'));
			}
			$frmItem->addItemToBottomRow(array($cmbAction, SPACE, new CSubmit('register',S_DO)));
		}

		zbx_add_post_js("var valueTypeSwitcher = new CViewSwitcher('value_type', 'change', ".zbx_jsvalue($valueTypeVisibility, true).");");
		zbx_add_post_js("var authTypeSwitcher = new CViewSwitcher('authtype', 'change', ".zbx_jsvalue($authTypeVisibility, true).");");
		zbx_add_post_js("var typeSwitcher = new CViewSwitcher('type', 'change', ".zbx_jsvalue($typeVisibility, true).(isset($_REQUEST['itemid'])? ', true': '').');');
		zbx_add_post_js("var securityLevelSwitcher = new CViewSwitcher('snmpv3_securitylevel', 'change', ".zbx_jsvalue($securityLevelVisibility, true).");");
		zbx_add_post_js("var multpStat = document.getElementById('multiplier'); if(multpStat && multpStat.onclick) multpStat.onclick();");
		zbx_add_post_js("var mnFrmTbl = document.getElementById('web.items.item.php'); if(mnFrmTbl) mnFrmTbl.style.visibility = 'visible';");

		return $frmItem;
	}

	function insert_mass_update_item_form(){
		$itemids = get_request('group_itemid',array());

		$frmItem = new CFormTable(S_ITEM,null,'post');
		$frmItem->setHelp('web.items.item.php');
		$frmItem->setTitle(S_MASS_UPDATE);

		$frmItem->addVar('massupdate',1);

		$frmItem->addVar('group_itemid', $itemids);

		$description = get_request('description', '');
		$delay = get_request('delay', ZBX_ITEM_DELAY_DEFAULT);
		$history	= get_request('history'		,90);
		$status		= get_request('status'		,0);
		$type		= get_request('type'		,0);
		$snmp_community	= get_request('snmp_community'	,'public');
		$port	= get_request('port', '');
		$value_type	= get_request('value_type'	,ITEM_VALUE_TYPE_UINT64);
		$data_type	= get_request('data_type'	,ITEM_DATA_TYPE_DECIMAL);
		$trapper_hosts	= get_request('trapper_hosts'	,'');
		$units		= get_request('units'		,'');
		$authtype = get_request('authtype', '');
		$username = get_request('username', '');
		$password = get_request('password', '');
		$publickey = get_request('publickey', '');
		$privatekey = get_request('privatekey', '');
		$valuemapid	= get_request('valuemapid'	,0);
		$delta		= get_request('delta'		,0);
		$trends		= get_request('trends'		,365);
		$applications	= get_request('applications'	,array());
		$delay_flex	= get_request('delay_flex'	,array());

		$snmpv3_securityname	= get_request('snmpv3_securityname'	,'');
		$snmpv3_securitylevel	= get_request('snmpv3_securitylevel'	,0);
		$snmpv3_authpassphrase	= get_request('snmpv3_authpassphrase'	,'');
		$snmpv3_privpassphrase	= get_request('snmpv3_privpassphrase'	,'');

		$formula	= get_request('formula'		,'1');
		$logtimefmt	= get_request('logtimefmt'	,'');

		$delay_flex_el = array();

		$i = 0;
		foreach($delay_flex as $val){
			if(!isset($val['delay']) && !isset($val['period'])) continue;

			array_push($delay_flex_el,
				array(
					new CCheckBox('rem_delay_flex[]', 'no', null,$i),
						$val['delay'],
						' sec at ',
						$val['period']
				),
				BR());
			$frmItem->addVar("delay_flex[".$i."][delay]", $val['delay']);
			$frmItem->addVar("delay_flex[".$i."][period]", $val['period']);
			$i++;
			if($i >= 7) break;
// limit count of  intervals 7 intervals by 30 symbols = 210 characters
// db storage field is 256
		}

		if(count($delay_flex_el)==0)
			array_push($delay_flex_el, S_NO_FLEXIBLE_INTERVALS);
		else
			array_push($delay_flex_el, new CSubmit('del_delay_flex',S_DELETE_SELECTED));

		if(count($applications)==0)  array_push($applications,0);

		$dbHosts = API::Host()->get(array(
			'itemids' => $itemids,
			'selectInterfaces' => API_OUTPUT_EXTEND
		));

		if(count($dbHosts) == 1){
			$dbHost = reset($dbHosts);

			$sbIntereaces = new CComboBox('interfaceid');
			foreach($dbHost['interfaces'] as $ifnum => $interface){
				$caption = $interface['useip'] ? $interface['ip'] : $interface['dns'];
				$caption.= ' : '.$interface['port'];

				$sbIntereaces->addItem($interface['interfaceid'], $caption);
			}
			$frmItem->addRow(array( new CVisibilityBox('interface_visible', get_request('interface_visible'), 'interfaceid', S_ORIGINAL),
				S_HOST_INTERFACE), $sbIntereaces);
		}

		$itemTypes = item_type2str();
		// http items only for internal processes
		unset($itemTypes[ITEM_TYPE_HTTPTEST]);

		$cmbType = new CComboBox('type',$type);
		$cmbType->addItems($itemTypes);

		$frmItem->addRow(array( new CVisibilityBox('type_visible', get_request('type_visible'), 'type', S_ORIGINAL),
			S_TYPE), $cmbType);

		$frmItem->addRow(array( new CVisibilityBox('community_visible', get_request('community_visible'), 'snmp_community', S_ORIGINAL),
			S_SNMP_COMMUNITY), new CTextBox('snmp_community',$snmp_community,16));

		$frmItem->addRow(array( new CVisibilityBox('securityname_visible', get_request('securityname_visible'), 'snmpv3_securityname',
			S_ORIGINAL), S_SNMPV3_SECURITY_NAME), new CTextBox('snmpv3_securityname',$snmpv3_securityname,64));

		$cmbSecLevel = new CComboBox('snmpv3_securitylevel',$snmpv3_securitylevel);
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,"noAuthNoPriv");
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,"authNoPriv");
		$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,"authPriv");
		$frmItem->addRow(array( new CVisibilityBox('securitylevel_visible',  get_request('securitylevel_visible'), 'snmpv3_securitylevel',
			S_ORIGINAL), S_SNMPV3_SECURITY_LEVEL), $cmbSecLevel);
		$frmItem->addRow(array( new CVisibilityBox('authpassphrase_visible', get_request('authpassphrase_visible'),
			'snmpv3_authpassphrase', S_ORIGINAL), S_SNMPV3_AUTH_PASSPHRASE),
			new CTextBox('snmpv3_authpassphrase',$snmpv3_authpassphrase,64));

		$frmItem->addRow(array( new CVisibilityBox('privpassphras_visible', get_request('privpassphras_visible'), 'snmpv3_privpassphrase',
			S_ORIGINAL), S_SNMPV3_PRIV_PASSPHRASE), new CTextBox('snmpv3_privpassphrase',$snmpv3_privpassphrase,64));

		$frmItem->addRow(array( new CVisibilityBox('port_visible', get_request('port_visible'), 'port', S_ORIGINAL), S_PORT),
			new CTextBox('port',$port,15));

		$cmbValType = new CComboBox('value_type',$value_type);
		$cmbValType->addItem(ITEM_VALUE_TYPE_UINT64,	S_NUMERIC_UNSIGNED);
		$cmbValType->addItem(ITEM_VALUE_TYPE_FLOAT,	S_NUMERIC_FLOAT);
		$cmbValType->addItem(ITEM_VALUE_TYPE_STR, 	S_CHARACTER);
		$cmbValType->addItem(ITEM_VALUE_TYPE_LOG, 	S_LOG);
		$cmbValType->addItem(ITEM_VALUE_TYPE_TEXT,	S_TEXT);
		$frmItem->addRow(array( new CVisibilityBox('value_type_visible', get_request('value_type_visible'), 'value_type', S_ORIGINAL),
			S_TYPE_OF_INFORMATION), $cmbValType);

		$cmbDataType = new CComboBox('data_type',$data_type);
		$cmbDataType->addItems(item_data_type2str());
		$frmItem->addRow(array( new CVisibilityBox('data_type_visible', get_request('data_type_visible'), 'data_type', S_ORIGINAL),
			S_DATA_TYPE), $cmbDataType);

		$frmItem->addRow(array( new CVisibilityBox('units_visible', get_request('units_visible'), 'units', S_ORIGINAL), S_UNITS),
			new CTextBox('units',$units,40));


		$cmbAuthType = new CComboBox('authtype', $authtype);
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
		$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY, S_PUBLIC_KEY);
		$frmItem->addRow(
			array(new CVisibilityBox('authtype_visible', get_request('authtype_visible'), 'authtype', S_ORIGINAL), S_AUTHENTICATION_METHOD),
			$cmbAuthType
		);
		$frmItem->addRow(
			array(new CVisibilityBox('username_visible', get_request('username_visible'), 'username', S_ORIGINAL), S_USER_NAME),
			new CTextBox('username', $username, 40)
		);
		$frmItem->addRow(
			array(new CVisibilityBox('publickey_visible', get_request('publickey_visible'), 'publickey', S_ORIGINAL), S_PUBLIC_KEY_FILE),
			new CTextBox('publickey', $publickey, 40)
		);
		$frmItem->addRow(
			array(new CVisibilityBox('privatekey_visible', get_request('privatekey_visible'), 'privatekey', S_ORIGINAL), S_PRIVATE_KEY_FILE),
			new CTextBox('privatekey', $privatekey, 40)
		);
		$frmItem->addRow(
			array(new CVisibilityBox('password_visible', get_request('password_visible'), 'password', S_ORIGINAL), _('Password')),
			new CTextBox('password', $password, 40)
		);

		$frmItem->addRow(array( new CVisibilityBox('formula_visible', get_request('formula_visible'), 'formula', S_ORIGINAL),
			S_CUSTOM_MULTIPLIER.' (0 - '.S_DISABLED.')'), new CTextBox('formula',$formula,40));

		$frmItem->addRow(array( new CVisibilityBox('delay_visible', get_request('delay_visible'), 'delay', S_ORIGINAL),
			_('Update interval (in sec)')), new CNumericBox('delay', $delay, 5));

		$delay_flex_el = new CSpan($delay_flex_el);
		$delay_flex_el->setAttribute('id', 'delay_flex_list');

		$frmItem->addRow(array(
						new CVisibilityBox('delay_flex_visible',
								get_request('delay_flex_visible'),
								array('delay_flex_list', 'new_delay_flex_el'),
								S_ORIGINAL),
						S_FLEXIBLE_INTERVALS), $delay_flex_el);

		$new_delay_flex_el = new CSpan(array(
										S_DELAY, SPACE,
										new CNumericBox("new_delay_flex[delay]","50",5),
										S_PERIOD, SPACE,
										new CTextBox("new_delay_flex[period]",ZBX_DEFAULT_INTERVAL,27), BR(),
										new CSubmit("add_delay_flex",S_ADD)
									));
		$new_delay_flex_el->setAttribute('id', 'new_delay_flex_el');

		$frmItem->addRow(S_NEW_FLEXIBLE_INTERVAL, $new_delay_flex_el, 'new');

		$frmItem->addRow(array( new CVisibilityBox('history_visible', get_request('history_visible'), 'history', S_ORIGINAL),
			S_KEEP_HISTORY_IN_DAYS), new CNumericBox('history',$history,8));
		$frmItem->addRow(array( new CVisibilityBox('trends_visible', get_request('trends_visible'), 'trends', S_ORIGINAL),
			S_KEEP_TRENDS_IN_DAYS), new CNumericBox('trends',$trends,8));

		$cmbStatus = new CComboBox('status',$status);
		foreach(array(ITEM_STATUS_ACTIVE,ITEM_STATUS_DISABLED,ITEM_STATUS_NOTSUPPORTED) as $st)
			$cmbStatus->addItem($st,item_status2str($st));
		$frmItem->addRow(array( new CVisibilityBox('status_visible', get_request('status_visible'), 'status', S_ORIGINAL), S_STATUS),
			$cmbStatus);

		$frmItem->addRow(array( new CVisibilityBox('logtimefmt_visible', get_request('logtimefmt_visible'), 'logtimefmt', S_ORIGINAL),
			S_LOG_TIME_FORMAT), new CTextBox("logtimefmt",$logtimefmt,16));

		$cmbDelta= new CComboBox('delta',$delta);
		$cmbDelta->addItem(0,S_AS_IS);
		$cmbDelta->addItem(1,S_DELTA_SPEED_PER_SECOND);
		$cmbDelta->addItem(2,S_DELTA_SIMPLE_CHANGE);
		$frmItem->addRow(array( new CVisibilityBox('delta_visible', get_request('delta_visible'), 'delta', S_ORIGINAL),
			S_STORE_VALUE),$cmbDelta);

		$cmbMap = new CComboBox('valuemapid',$valuemapid);
		$cmbMap->addItem(0,S_AS_IS);
		$db_valuemaps = DBselect('SELECT * FROM valuemaps WHERE '.DBin_node('valuemapid'));
		while($db_valuemap = DBfetch($db_valuemaps))
			$cmbMap->addItem(
					$db_valuemap["valuemapid"],
					get_node_name_by_elid($db_valuemap["valuemapid"], null, ': ').$db_valuemap["name"]
					);

		$link = new CLink(S_SHOW_VALUE_MAPPINGS,'config.php?config=6');
		$link->setAttribute('target','_blank');

		$frmItem->addRow(array( new CVisibilityBox('valuemapid_visible', get_request('valuemapid_visible'), 'valuemapid', S_ORIGINAL),
			S_SHOW_VALUE), array($cmbMap, SPACE, $link));

		$frmItem->addRow(array( new CVisibilityBox('trapper_hosts_visible', get_request('trapper_hosts_visible'), 'trapper_hosts',
			S_ORIGINAL), S_ALLOWED_HOSTS), new CTextBox('trapper_hosts',$trapper_hosts,40));

		$cmbApps = new CListBox('applications[]',$applications,6);
		$cmbApps->addItem(0,'-'.S_NONE.'-');

		if(isset($_REQUEST['hostid'])){
			$sql = 'SELECT applicationid,name '.
				' FROM applications '.
				' WHERE hostid='.$_REQUEST['hostid'].
				' ORDER BY name';
			$db_applications = DBselect($sql);
			while($db_app = DBfetch($db_applications)){
				$cmbApps->addItem($db_app["applicationid"],$db_app["name"]);
			}
		}
		$frmItem->addRow(array( new CVisibilityBox('applications_visible', get_request('applications_visible'), 'applications_',
			S_ORIGINAL), S_APPLICATIONS),$cmbApps);

		$tarea = new CTextArea('description', $description);
		$tarea->addStyle('margin-top: 5px;');
		$frmItem->addRow(array( new CVisibilityBox('description_visible', get_request('description_visible'), 'description', S_ORIGINAL),
			_('Description')), $tarea);

		$frmItem->addItemToBottomRow(array(new CSubmit("update",S_UPDATE),
			SPACE, new CButtonCancel(url_param('groupid').url_param("hostid").url_param("config"))));

	return $frmItem;
	}

	function insert_copy_elements_to_forms($elements_array_name){

		$copy_type = get_request('copy_type', 0);
		$filter_groupid = get_request('filter_groupid', 0);
		$group_itemid = get_request($elements_array_name, array());
		$copy_targetid = get_request('copy_targetid', array());

		if(!is_array($group_itemid) || (is_array($group_itemid) && count($group_itemid) < 1)){
			error(S_INCORRECT_LIST_OF_ITEMS);
			return;
		}

		$frmCopy = new CFormTable(count($group_itemid).' '.S_X_ELEMENTS_COPY_TO_DOT_DOT_DOT,null,'post',null,'go');
		$frmCopy->setHelp('web.items.copyto.php');
		$frmCopy->addVar($elements_array_name, $group_itemid);
		$frmCopy->addVar('hostid', get_request('hostid', 0));

		$cmbCopyType = new CComboBox('copy_type',$copy_type,'submit()');
		$cmbCopyType->addItem(0,S_HOSTS);
		$cmbCopyType->addItem(1,S_HOST_GROUPS);
		$frmCopy->addRow(S_TARGET_TYPE, $cmbCopyType);

		$target_list = array();

		$groups = API::HostGroup()->get(array(
			'output'=>API_OUTPUT_EXTEND,
			'sortorder'=>'name'
		));
		order_result($groups, 'name');

		if(0 == $copy_type){
			$cmbGroup = new CComboBox('filter_groupid',$filter_groupid,'submit()');

			foreach($groups as $gnum => $group){
				if(empty($filter_groupid)) $filter_groupid = $group['groupid'];
				$cmbGroup->addItem($group['groupid'],$group['name']);
			}

			$frmCopy->addRow('Group', $cmbGroup);

			$options = array(
				'output'=>API_OUTPUT_EXTEND,
				'groupids' => $filter_groupid,
				'templated_hosts' => 1
			);
			$hosts = API::Host()->get($options);
			order_result($hosts, 'name');

			foreach($hosts as $num => $host){
				$hostid = $host['hostid'];

				array_push($target_list,array(
					new CCheckBox('copy_targetid['.$hostid.']',
						uint_in_array($hostid, $copy_targetid),
						null,
						$hostid),
					SPACE,
					$host['name'],
					BR()
				));
			}
		}
		else{
			foreach($groups as $groupid => $group){
				array_push($target_list,array(
					new CCheckBox('copy_targetid['.$group['groupid'].']',
						uint_in_array($group['groupid'], $copy_targetid),
						null,
						$group['groupid']),
					SPACE,
					$group['name'],
					BR()
					));
			}
		}

		$frmCopy->addRow(S_TARGET, $target_list);

		$frmCopy->addItemToBottomRow(new CSubmit("copy",S_COPY));
		$frmCopy->addItemToBottomRow(array(SPACE,
			new CButtonCancel(url_param('groupid').url_param("hostid").url_param("config"))));

	return $frmCopy;
	}

// TRIGGERS
	function insert_mass_update_trigger_form(){//$elements_array_name){
		$visible = get_request('visible',array());
		$priority = get_request('priority',	'');
		$dependencies = get_request('dependencies',array());

		asort($dependencies);

		$frmMTrig = new CFormTable(S_TRIGGERS_MASSUPDATE);
		$frmMTrig->addVar('massupdate',get_request('massupdate',1));
		$frmMTrig->addVar('go',get_request('go','massupdate'));
		$frmMTrig->setAttribute('id', 'massupdate');
		$frmMTrig->setName('trig_form');

		$parent_discoveryid = get_request('parent_discoveryid');
		if($parent_discoveryid){
			$frmMTrig->addVar('parent_discoveryid', $parent_discoveryid);
		}

		$triggers = $_REQUEST['g_triggerid'];
		foreach($triggers as $id => $triggerid){
			$frmMTrig->addVar('g_triggerid['.$triggerid.']',$triggerid);
		}

		$cmbPrior = new CComboBox("priority",$priority);
		$cmbPrior->addItems(getSeverityCaption());

		$frmMTrig->addRow(array(
			new CVisibilityBox('visible[priority]', isset($visible['priority']), 'priority', S_ORIGINAL), S_SEVERITY),
			$cmbPrior
		);

		if(!$parent_discoveryid){
/* dependencies */
			$dep_el = array();
			foreach($dependencies as $val){
				array_push($dep_el,
					array(
						new CCheckBox("rem_dependence[]", 'no', null, strval($val)),
						expand_trigger_description($val)
					),
					BR());
				$frmMTrig->addVar("dependencies[]",strval($val));
			}

			if(count($dep_el)==0)
				$dep_el[] = S_NO_DEPENDENCES_DEFINED;
			else
				$dep_el[] = new CSubmit('del_dependence',S_DELETE_SELECTED);

	//		$frmMTrig->addRow(S_THE_TRIGGER_DEPENDS_ON,$dep_el);
	/* end dependencies */
	/* new dependency */
			//$frmMTrig->addVar('new_dependence','0');

			$btnSelect = new CButton('btn1', S_ADD,
					"return PopUp('popup.php?dstfrm=massupdate&dstact=add_dependence&reference=deptrigger".
					"&dstfld1=new_dependence[]&srctbl=triggers&objname=triggers&srcfld1=triggerid&multiselect=1".
					"',1000,700);",
					'T');

			array_push($dep_el, array(br(),$btnSelect));

			$dep_div = new CDiv($dep_el);
			$dep_div->setAttribute('id','dependency_box');

			$frmMTrig->addRow(array(new CVisibilityBox('visible[dependencies]', isset($visible['dependencies']), 'dependency_box', S_ORIGINAL),S_TRIGGER_DEPENDENCIES),
								$dep_div
							);
		}
// end new dependency

		$frmMTrig->addItemToBottomRow(new CSubmit('mass_save',S_SAVE));
		$frmMTrig->addItemToBottomRow(SPACE);
		$frmMTrig->addItemToBottomRow(new CButtonCancel(url_param('groupid').url_param('parent_discoveryid')));

		$script = "function addPopupValues(list){
						if(!isset('object', list)) return false;

						if(list.object == 'deptrigger'){
							for(var i=0; i < list.values.length; i++){
								var trigger = list.values[i];

								create_var('".$frmMTrig->getName()."', 'new_dependence['+i+']', list.values[i].triggerid, false);
							}

							create_var('".$frmMTrig->getName()."','add_dependence', 1, true);
						}
					}";
		insert_js($script);

	return $frmMTrig;
	}

// Insert form for Trigger
	function insert_trigger_form(){
		$frmTrig = new CFormTable(S_TRIGGER);
		$frmTrig->setHelp('config_triggers.php');
		$parent_discoveryid = get_request('parent_discoveryid');
		$frmTrig->addVar('parent_discoveryid', $parent_discoveryid);

		$dep_el = array();
		$dependencies = get_request('dependencies', array());

		$limited = null;

		if(isset($_REQUEST['triggerid'])){
			$frmTrig->addVar('triggerid', $_REQUEST['triggerid']);

			$trigger = get_trigger_by_triggerid($_REQUEST['triggerid']);

			$caption = array();
			$trigid = $_REQUEST['triggerid'];
			do{
				$sql = 'SELECT t.triggerid, t.templateid, h.name'.
						' FROM triggers t, functions f, items i, hosts h'.
						' WHERE t.triggerid='.$trigid.
							' AND h.hostid=i.hostid'.
							' AND i.itemid=f.itemid'.
							' AND f.triggerid=t.triggerid';
				$trig = DBfetch(DBselect($sql));

				if(bccomp($_REQUEST['triggerid'],$trigid) != 0){
					$caption[] = ' : ';
					$caption[] = new CLink($trig['name'], 'triggers.php?form=update&triggerid='.$trig['triggerid'], 'highlight underline');
				}

				$trigid = $trig['templateid'];
			}while($trigid != 0);

			$caption[] = S_TRIGGER.' "';
			$caption = array_reverse($caption);
			$caption[] = htmlspecialchars($trigger['description']);
			$caption[] = '"';
			$frmTrig->setTitle($caption);

			$limited = $trigger['templateid'] ? 'yes' : null;
		}

		$expression		= get_request('expression',	'');
		$description	= get_request('description',	'');
		$type 			= get_request('type',		0);
		$priority		= get_request('priority',	0);
		$status			= get_request('status',		0);
		$comments		= get_request('comments',	'');
		$url			= get_request('url',		'');

		$expr_temp		= get_request('expr_temp',	'');
		$input_method	= get_request('input_method',	IM_ESTABLISHED);

		if((isset($_REQUEST['triggerid']) && !isset($_REQUEST['form_refresh']))  || isset($limited)){
			$description	= $trigger['description'];

			$expression	= explode_exp($trigger['expression']);

			if(!isset($limited) || !isset($_REQUEST['form_refresh'])){
				$type = $trigger['type'];
				$priority	= $trigger['priority'];
				$status		= $trigger['status'];
				$comments	= $trigger['comments'];
				$url		= $trigger['url'];

				$trigs=DBselect('SELECT t.triggerid,t.description,t.expression '.
							' FROM triggers t,trigger_depends d '.
							' WHERE t.triggerid=d.triggerid_up '.
								' AND d.triggerid_down='.$_REQUEST['triggerid']);

				while($trig=DBfetch($trigs)){
					if(uint_in_array($trig['triggerid'],$dependencies))	continue;
					array_push($dependencies,$trig['triggerid']);
				}
			}
		}

		$frmTrig->addRow(S_NAME, new CTextBox('description',$description,90, $limited));

		if($input_method == IM_TREE){
			$alz = analyze_expression($expression);

			if($alz !== false){
				list($outline, $eHTMLTree) = $alz;
				if(isset($_REQUEST['expr_action']) && $eHTMLTree != null){

					$new_expr = remake_expression($expression, $_REQUEST['expr_target_single'], $_REQUEST['expr_action'], $expr_temp);
					if($new_expr !== false){
						$expression = $new_expr;
						$alz = analyze_expression($expression);

						if($alz !== false) list($outline, $eHTMLTree) = $alz;
						else show_messages(false, '', S_EXPRESSION_SYNTAX_ERROR);

						$expr_temp = '';
					}
					else{
						show_messages(false, '', S_EXPRESSION_SYNTAX_ERROR);
					}
				}

				$frmTrig->addVar('expression', $expression);
				$exprfname = 'expr_temp';
				$exprtxt = new CTextBox($exprfname, $expr_temp, 65, 'yes');
				$macrobtn = new CSubmit('insert_macro', S_INSERT_MACRO, 'return call_ins_macro_menu(event);');
				//disabling button, if this trigger is templated
				if($limited=='yes'){
					$macrobtn->setAttribute('disabled', 'disabled');
				}

				$exprparam = "this.form.elements['$exprfname'].value";
			}
			else{
				show_messages(false, '', S_EXPRESSION_SYNTAX_ERROR);
				$input_method = IM_ESTABLISHED;
			}
		}

		if($input_method != IM_TREE){
			$exprfname = 'expression';
			$exprtxt = new CTextBox($exprfname,$expression,75,$limited);
			$exprparam = "getSelectedText(this.form.elements['$exprfname'])";
		}


		$add_expr_button = new CButton('insert',$input_method == IM_TREE ? S_EDIT : S_ADD,
								 "return PopUp('popup_trexpr.php?dstfrm=".$frmTrig->getName().
								 "&dstfld1=${exprfname}&srctbl=expression".url_param('parent_discoveryid').
								 "&srcfld1=expression&expression=' + escape($exprparam),1000,700);");
		//disabling button, if this trigger is templated
		if($limited=='yes'){
			$add_expr_button->setAttribute('disabled', 'disabled');
		}


		$row = array($exprtxt, $add_expr_button);

		if(isset($macrobtn)) array_push($row, $macrobtn);
		if($input_method == IM_TREE){
			array_push($row, BR());
			if(empty($outline)){

				$tmpbtn = new CSubmit('add_expression', S_ADD, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);
			}
			else{
				$tmpbtn = new CSubmit('and_expression', S_AND_BIG, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);

				$tmpbtn = new CSubmit('or_expression', S_OR_BIG, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);

				$tmpbtn = new CSubmit('replace_expression', S_REPLACE, "");
				if($limited=='yes'){
					$tmpbtn->setAttribute('disabled', 'disabled');
				}
				array_push($row, $tmpbtn);
			}
		}
		$frmTrig->addVar('input_method', $input_method);
		$frmTrig->addVar('toggle_input_method', '');
		$exprtitle = array(S_EXPRESSION);

		if($input_method != IM_FORCED){
			$btn_im = new CSpan(S_TOGGLE_INPUT_METHOD,'link');
			$btn_im->setAttribute('onclick','javascript: '.
								"document.getElementById('toggle_input_method').value=1;".
								"document.getElementById('input_method').value=".(($input_method==IM_TREE)?IM_ESTABLISHED:IM_TREE).';'.
								"document.forms['".$frmTrig->getName()."'].submit();");

			$exprtitle[] = array(SPACE, '(', $btn_im, ')');
		}

		$frmTrig->addRow($exprtitle, $row);

		if($input_method == IM_TREE){
			$exp_table = new CTable(null, 'tableinfo');
			$exp_table->setAttribute('id','exp_list');
			$exp_table->setOddRowClass('even_row');
			$exp_table->setEvenRowClass('even_row');

			$exp_table->setHeader(array(($limited == 'yes' ? null : S_TARGET), S_EXPRESSION, S_EXPRESSION_PART_ERROR, ($limited == 'yes' ? null : S_DELETE)));

			$allowedTesting = true;
			if($eHTMLTree != null){
				foreach($eHTMLTree as $i => $e){

					if($limited != 'yes'){
						$del_url = new CSpan(S_DELETE,'link');

						$del_url->setAttribute('onclick', 'javascript: if(confirm("'.S_DELETE_EXPRESSION_Q.'")) {'.
										' delete_expression(\''.$e['id'] .'\');'.
										' document.forms["config_triggers.php"].submit(); '.
									'}');
						$tgt_chk = new CCheckbox('expr_target_single', ($i==0) ? 'yes':'no', 'check_target(this);', $e['id']);
					}
					else{
						$tgt_chk = null;
					}

					if(!isset($e['expression']['levelErrors'])) {
						$errorImg = new CImg('images/general/ok_icon.png', 'expression_no_errors');
						$errorImg->setHint(S_EXPRESSION_PART_NO_ERROR, '', '', false);
					}else{
						$allowedTesting = false;
						$errorImg = new CImg('images/general/error_icon.png', 'expression_errors');

						$errorTexts = Array();
						if(is_array($e['expression']['levelErrors'])) {
							foreach($e['expression']['levelErrors'] as $expVal => $errTxt) {
								if(count($errorTexts) > 0) array_push($errorTexts, BR());
								array_push($errorTexts, $expVal, ':', $errTxt);
							}
						}

						$errorImg->setHint($errorTexts, '', 'left', false);
					}

					//if it is a templated trigger
					if($limited == 'yes'){
						//make all links inside inactive
						for($i = 0; $i < count($e['list']); $i++){
							if(gettype($e['list'][$i]) == 'object' && get_class($e['list'][$i]) == 'CSpan' && $e['list'][$i]->getAttribute('class') == 'link'){
								$e['list'][$i]->removeAttribute('class');
								$e['list'][$i]->setAttribute('onclick', '');
							}
						}
					}

					$errorCell = new CCol($errorImg, 'center');
					$row = new CRow(array($tgt_chk, $e['list'], $errorCell, (isset($del_url) ? $del_url : null)));
					$exp_table->addRow($row);
				}
			}
			else{
				$allowedTesting = false;
				$outline = '';
			}

			$frmTrig->addVar('remove_expression', '');

			$btn_test = new CButton('test_expression', S_TEST,
									"openWinCentered(".
									"'tr_testexpr.php?expression=' + encodeURIComponent(this.form.elements['expression'].value)".
									",'ExpressionTest'".
									",850,400".
									",'titlebar=no, resizable=yes, scrollbars=yes');".
									"return false;");
			if(!isset($allowedTesting) || !$allowedTesting) $btn_test->setAttribute('disabled', 'disabled');
			if (empty($outline)) $btn_test->setAttribute('disabled', 'yes');
			//SDI($outline);
			$wrapOutline = new CSpan(array($outline));
			$wrapOutline->addStyle('white-space: pre;');
			$frmTrig->addRow(SPACE, array($wrapOutline,
										  BR(),BR(),
										  $exp_table,
										  $btn_test));
		}

		if(!$parent_discoveryid){
// dependencies
			foreach($dependencies as $val){
				array_push($dep_el,
					array(
						new CCheckBox('rem_dependence['.$val.']', 'no', null, strval($val)),
						expand_trigger_description($val)
					),
					BR());
				$frmTrig->addVar('dependencies[]',strval($val));
			}

			if(count($dep_el)==0)
				array_push($dep_el,  S_NO_DEPENDENCES_DEFINED);
			else
				array_push($dep_el, new CSubmit('del_dependence',S_DELETE_SELECTED));
			$frmTrig->addRow(S_THE_TRIGGER_DEPENDS_ON,$dep_el);
		/* end dependencies */

		/* new dependency */
	//		$frmTrig->addVar('new_dependence','0');

	//		$txtCondVal = new CTextBox('trigger','',75,'yes');

			$btnSelect = new CButton('btn1',S_ADD,
					"return PopUp('popup.php?srctbl=triggers".
								'&srcfld1=triggerid'.
								'&reference=deptrigger'.
								'&multiselect=1'.
							"',1000,700);",'T');

			$frmTrig->addRow(S_NEW_DEPENDENCY, $btnSelect, 'new');
	// end new dependency
		}

		$type_select = new CComboBox('type', $type);
		$type_select->additem(TRIGGER_MULT_EVENT_DISABLED, _('Normal'));
		$type_select->additem(TRIGGER_MULT_EVENT_ENABLED, _('Normal + Multiple PROBLEM events'));

		$frmTrig->addRow(S_EVENT_GENERATION, $type_select);

		$cmbPrior = new CComboBox('priority', $priority);
		$cmbPrior->addItems(getSeverityCaption());

		$frmTrig->addRow(S_SEVERITY,$cmbPrior);

		$frmTrig->addRow(S_COMMENTS,new CTextArea("comments", $comments,90,7));
		$frmTrig->addRow(S_URL,new CTextBox("url", $url, 90));
		$frmTrig->addRow(S_DISABLED,new CCheckBox("status", $status));

		$buttons = array();
		$buttons[] = new CSubmit("save", S_SAVE);
		if(isset($_REQUEST["triggerid"])){
			$buttons[] = new CSubmit("clone", S_CLONE);
			if(!$limited){
				$buttons[] = new CButtonDelete(S_DELETE_TRIGGER_Q,
					url_param("form").url_param('groupid').url_param("hostid").
					url_param("triggerid").url_param("parent_discoveryid"));
			}
		}
		$buttons[] = new CButtonCancel(url_param('groupid').url_param("hostid").url_param("parent_discoveryid"));
		$frmTrig->addItemToBottomRow($buttons);

		$script = "function addPopupValues(list){
						if(!isset('object', list)) return false;
						if(list.object == 'deptrigger'){
							for(var i=0; i < list.values.length; i++){
								create_var('".$frmTrig->getName()."', 'new_dependence['+i+']', list.values[i].triggerid, false);
							}

							create_var('".$frmTrig->getName()."','add_dependence', 1, true);
						}
					}";
		insert_js($script);

	return $frmTrig;
	}

	function insert_graph_form(){
		$frmGraph = new CFormTable(S_GRAPH);
		$frmGraph->setName('frm_graph');

		$parent_discoveryid = get_request('parent_discoveryid');
		if($parent_discoveryid) $frmGraph->addVar('parent_discoveryid', $parent_discoveryid);


		if(isset($_REQUEST['graphid'])){
			$frmGraph->addVar('graphid', $_REQUEST['graphid']);

			$options = array(
				'graphids' => $_REQUEST['graphid'],
				'filter' => array('flags' => null),
				'output' => API_OUTPUT_EXTEND,
			);
			$graphs = API::Graph()->get($options);
			$graph = reset($graphs);

			$frmGraph->setTitle(S_GRAPH.' "'.$graph['name'].'"');
		}

		if(isset($_REQUEST['graphid']) && !isset($_REQUEST['form_refresh'])){
			$name = $graph['name'];
			$width = $graph['width'];
			$height = $graph['height'];
			$ymin_type = $graph['ymin_type'];
			$ymax_type = $graph['ymax_type'];
			$yaxismin = $graph['yaxismin'];
			$yaxismax = $graph['yaxismax'];
			$ymin_itemid = $graph['ymin_itemid'];
			$ymax_itemid = $graph['ymax_itemid'];
			$showworkperiod = $graph['show_work_period'];
			$showtriggers = $graph['show_triggers'];
			$graphtype = $graph['graphtype'];
			$legend = $graph['show_legend'];
			$graph3d = $graph['show_3d'];
			$percent_left = $graph['percent_left'];
			$percent_right = $graph['percent_right'];

			$options = array(
				'graphids' => $_REQUEST['graphid'],
				'sortfield' => 'sortorder',
				'output' => API_OUTPUT_EXTEND,
			);
			$items = API::GraphItem()->get($options);
		}
		else{
			$name = get_request('name', '');
			$graphtype = get_request('graphtype', GRAPH_TYPE_NORMAL);

			if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
				$width = get_request('width', 400);
				$height = get_request('height', 300);
			}
			else{
				$width = get_request('width', 900);
				$height = get_request('height', 200);
			}

			$ymin_type = get_request('ymin_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$ymax_type = get_request('ymax_type', GRAPH_YAXIS_TYPE_CALCULATED);
			$yaxismin = get_request('yaxismin', 0.00);
			$yaxismax = get_request('yaxismax', 100.00);
			$ymin_itemid = get_request('ymin_itemid', 0);
			$ymax_itemid	= get_request('ymax_itemid', 0);
			$showworkperiod = get_request('showworkperiod', 0);
			$showtriggers	= get_request('showtriggers', 0);
			$legend = get_request('legend', 0);
			$graph3d	= get_request('graph3d', 0);
			$visible = get_request('visible');
			$percent_left  = 0;
			$percent_right = 0;

			if(isset($visible['percent_left'])) $percent_left = get_request('percent_left', 0);
			if(isset($visible['percent_right'])) $percent_right = get_request('percent_right', 0);

			$items = get_request('items', array());
		}


		if(!isset($_REQUEST['graphid']) && !isset($_REQUEST['form_refresh'])){
			$legend = $_REQUEST['legend'] = 1;
		}



/* reinit $_REQUEST */
		$_REQUEST['items'] = $items;
		$_REQUEST['name'] = $name;
		$_REQUEST['width'] = $width;
		$_REQUEST['height'] = $height;

		$_REQUEST['ymin_type'] = $ymin_type;
		$_REQUEST['ymax_type'] = $ymax_type;

		$_REQUEST['yaxismin'] = $yaxismin;
		$_REQUEST['yaxismax'] = $yaxismax;

		$_REQUEST['ymin_itemid'] = $ymin_itemid;
		$_REQUEST['ymax_itemid'] = $ymax_itemid;

		$_REQUEST['showworkperiod'] = $showworkperiod;
		$_REQUEST['showtriggers'] = $showtriggers;
		$_REQUEST['graphtype'] = $graphtype;
		$_REQUEST['legend'] = $legend;
		$_REQUEST['graph3d'] = $graph3d;
		$_REQUEST['percent_left'] = $percent_left;
		$_REQUEST['percent_right'] = $percent_right;
/********************/

		if($graphtype != GRAPH_TYPE_NORMAL){
			foreach($items as $gid => $gitem){
				if($gitem['type'] == GRAPH_ITEM_AGGREGATED)
					unset($items[$gid]);
			}
		}

		$items = array_values($items);
		$icount = count($items);
		for($i=0; $i < $icount-1;){
// check if we deletd an item
			$next = $i+1;
			while(!isset($items[$next]) && ($next < ($icount-1))) $next++;

			if(isset($items[$next]) && ($items[$i]['sortorder'] == $items[$next]['sortorder']))
				for($j=$next; $j < $icount; $j++)
					if($items[$j-1]['sortorder'] >= $items[$j]['sortorder']) $items[$j]['sortorder']++;

			$i = $next;
		}

		asort_by_key($items, 'sortorder');

		$items = array_values($items);

		$group_gid = get_request('group_gid', array());

		$frmGraph->addVar('ymin_itemid', $ymin_itemid);
		$frmGraph->addVar('ymax_itemid', $ymax_itemid);

		$frmGraph->addRow(S_NAME, new CTextBox('name', $name, 32));
		$frmGraph->addRow(S_WIDTH, new CNumericBox('width', $width, 5));
		$frmGraph->addRow(S_HEIGHT, new CNumericBox('height', $height, 5));

		$cmbGType = new CComboBox('graphtype', $graphtype, 'graphs.submit(this)');
		$cmbGType->addItems(graphType());
		$frmGraph->addRow(S_GRAPH_TYPE, $cmbGType);


// items beforehead, to get only_hostid for miny maxy items
		$only_hostid = null;
		$monitored_hosts = null;

		if(count($items)){
			$frmGraph->addVar('items', $items);

			$keys = array_keys($items);
			$first = reset($keys);
			$last = end($keys);

			$items_table = new CTableInfo();
			foreach($items as $gid => $gitem){
				//if($graphtype == GRAPH_TYPE_STACKED && $gitem['type'] == GRAPH_ITEM_AGGREGATED) continue;
				$host = get_host_by_itemid($gitem['itemid']);
				$item = get_item_by_itemid($gitem['itemid']);

				if($host['status'] == HOST_STATUS_TEMPLATE)
					$only_hostid = $host['hostid'];
				else
					$monitored_hosts = 1;

				if($gitem['type'] == GRAPH_ITEM_AGGREGATED)
					$color = '-';
				else
					$color = new CColorCell(null,$gitem['color']);


				if($gid == $first){
					$do_up = null;
				}
				else{
					$do_up = new CSpan(S_UP,'link');
					$do_up->onClick("return create_var('".$frmGraph->getName()."','move_up',".$gid.", true);");
				}

				if($gid == $last){
					$do_down = null;
				}
				else{
					$do_down = new CSpan(S_DOWN,'link');
					$do_down->onClick("return create_var('".$frmGraph->getName()."','move_down',".$gid.", true);");
				}

				$description = new CSpan($host['name'].': '.itemName($item),'link');
				$description->onClick(
					'return PopUp("popup_gitem.php?list_name=items&dstfrm='.$frmGraph->getName().
					url_param($only_hostid, false, 'only_hostid').
					url_param($monitored_hosts, false, 'monitored_hosts').
					url_param($graphtype, false, 'graphtype').
					url_param($gitem, false).
					url_param($gid,false,'gid').
					url_param(get_request('graphid',0),false,'graphid').
					'",550,400,"graph_item_form");'
				);

				if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
					$items_table->addRow(array(
							new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
							$description,
							graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
							graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
							$color,
							array( $do_up, ((!is_null($do_up) && !is_null($do_down)) ? SPACE."|".SPACE : ''), $do_down )
						));
				}
				else{
					$items_table->addRow(array(
							new CCheckBox('group_gid['.$gid.']',isset($group_gid[$gid])),
//							$gitem['sortorder'],
							$description,
							graph_item_calc_fnc2str($gitem["calc_fnc"],$gitem["type"]),
							graph_item_type2str($gitem['type'],$gitem["periods_cnt"]),
							($gitem['yaxisside']==GRAPH_YAXIS_SIDE_LEFT)?S_LEFT:S_RIGHT,
							graph_item_drawtype2str($gitem["drawtype"],$gitem["type"]),
							$color,
							array( $do_up, ((!is_null($do_up) && !is_null($do_down)) ? SPACE."|".SPACE : ''), $do_down )
						));
				}
			}
			$dedlete_button = new CSubmit('delete_item', S_DELETE_SELECTED);
		}
		else{
			$items_table = $dedlete_button = null;
		}

		$frmGraph->addRow(S_SHOW_LEGEND, new CCheckBox('legend',$legend, null, 1));

		if(($graphtype == GRAPH_TYPE_NORMAL) || ($graphtype == GRAPH_TYPE_STACKED)){
			$frmGraph->addRow(S_SHOW_WORKING_TIME,new CCheckBox('showworkperiod',$showworkperiod,null,1));
			$frmGraph->addRow(S_SHOW_TRIGGERS,new CCheckBox('showtriggers',$showtriggers,null,1));


			if($graphtype == GRAPH_TYPE_NORMAL){
				$percent_left = sprintf('%2.2f', $percent_left);
				$percent_right = sprintf('%2.2f', $percent_right);

				$pr_left_input = new CTextBox('percent_left', $percent_left, '5');
				$pr_left_chkbx = new CCheckBox('visible[percent_left]',1,"javascript: ShowHide('percent_left');",1);
				if($percent_left == 0){
					$pr_left_input->setAttribute('style','display: none;');
					$pr_left_chkbx->setChecked(0);
				}

				$pr_right_input = new CTextBox('percent_right',$percent_right,'5');
				$pr_right_chkbx = new CCheckBox('visible[percent_right]',1,"javascript: ShowHide('percent_right');",1);
				if($percent_right == 0){
					$pr_right_input->setAttribute('style','display: none;');
					$pr_right_chkbx->setChecked(0);
				}

				$frmGraph->addRow(S_PERCENTILE_LINE.' ('.S_LEFT.')',array($pr_left_chkbx, $pr_left_input));
				$frmGraph->addRow(S_PERCENTILE_LINE.' ('.S_RIGHT.')',array($pr_right_chkbx, $pr_right_input));
			}

			$yaxis_min = array();

			$cmbYType = new CComboBox('ymin_type',$ymin_type,'javascript: submit();');
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE,S_ITEM);

			$yaxis_min[] = $cmbYType;

			if($ymin_type == GRAPH_YAXIS_TYPE_FIXED){
				$yaxis_min[] = new CTextBox("yaxismin",$yaxismin,9);
			}
			else if($ymin_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$frmGraph->addVar('yaxismin',$yaxismin);

				$ymin_name = '';
				if($ymin_itemid > 0){
					$min_host = get_host_by_itemid($ymin_itemid);
					$min_item = get_item_by_itemid($ymin_itemid);
					$ymin_name = $min_host['host'].':'.itemName($min_item);
				}

				if(count($items)){
					$yaxis_min[] = new CTextBox("ymin_name",$ymin_name,80,'yes');
					$yaxis_min[] = new CButton('yaxis_min',S_SELECT,'javascript: '.
						"return PopUp('popup.php?dstfrm=".$frmGraph->getName().
						url_param($only_hostid, false, 'only_hostid').
						url_param($monitored_hosts, false, 'monitored_hosts').
							"&dstfld1=ymin_itemid".
							"&dstfld2=ymin_name".
							"&srctbl=items".
							"&srcfld1=itemid".
							"&srcfld2=name',0,0,'zbx_popup_item');");
				}
				else{
					$yaxis_min[] = S_ADD_GRAPH_ITEMS;
				}
			}
			else{
				$frmGraph->addVar('yaxismin', $yaxismin);
			}

			$frmGraph->addRow(S_YAXIS_MIN_VALUE, $yaxis_min);

			$yaxis_max = array();

			$cmbYType = new CComboBox("ymax_type",$ymax_type,"submit()");
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_CALCULATED,S_CALCULATED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_FIXED,S_FIXED);
			$cmbYType->addItem(GRAPH_YAXIS_TYPE_ITEM_VALUE,S_ITEM);

			$yaxis_max[] = $cmbYType;

			if($ymax_type == GRAPH_YAXIS_TYPE_FIXED){
				$yaxis_max[] = new CTextBox('yaxismax',$yaxismax,9);
			}
			else if($ymax_type == GRAPH_YAXIS_TYPE_ITEM_VALUE){
				$frmGraph->addVar('yaxismax',$yaxismax);

				$ymax_name = '';
				if($ymax_itemid > 0){
					$max_host = get_host_by_itemid($ymax_itemid);
					$max_item = get_item_by_itemid($ymax_itemid);
					$ymax_name = $max_host['host'].':'.itemName($max_item);
				}

				if(count($items)){
					$yaxis_max[] = new CTextBox("ymax_name",$ymax_name,80,'yes');
					$yaxis_max[] = new CButton('yaxis_max',S_SELECT,'javascript: '.
							"return PopUp('popup.php?dstfrm=".$frmGraph->getName().
							url_param($only_hostid, false, 'only_hostid').
							url_param($monitored_hosts, false, 'monitored_hosts').
							"&dstfld1=ymax_itemid".
							"&dstfld2=ymax_name".
							"&srctbl=items".
							"&srcfld1=itemid".
							"&srcfld2=name',0,0,'zbx_popup_item');"
					);
				}
				else{
					$yaxis_max[] = S_ADD_GRAPH_ITEMS;
				}
			}
			else{
				$frmGraph->addVar('yaxismax', $yaxismax);
			}

			$frmGraph->addRow(S_YAXIS_MAX_VALUE, $yaxis_max);
		}
		else{
			$frmGraph->addRow(S_3D_VIEW,new CCheckBox('graph3d',$graph3d,null,1));
		}

		$addProtoBtn = null;
		if($parent_discoveryid){
			$addProtoBtn = new CButton('add_protoitem', S_ADD_PROTOTYPE,
				"return PopUp('popup_gitem.php?dstfrm=".$frmGraph->getName().
				url_param($graphtype, false, 'graphtype').
				url_param('parent_discoveryid').
				"',700,400,'graph_item_form');");
		}

		$normal_only = $parent_discoveryid ? '&normal_only=1' : '';
		$frmGraph->addRow(S_ITEMS, array(
			$items_table,
			new CButton('add_item',S_ADD,
				"return PopUp('popup_gitem.php?dstfrm=".$frmGraph->getName().
				url_param($only_hostid, false, 'only_hostid').
				url_param($monitored_hosts, false, 'monitored_hosts').
				url_param($graphtype, false, 'graphtype').
				$normal_only.
				"',700,400,'graph_item_form');"),
			$addProtoBtn,
			$dedlete_button
		));

		$footer = array(
			new CSubmit('preview', S_PREVIEW),
			new CSubmit('save', S_SAVE),
		);
		if(isset($_REQUEST['graphid'])){
			$footer[] = new CSubmit('clone', S_CLONE);
			$footer[] = new CButtonDelete(S_DELETE_GRAPH_Q,url_param('graphid').url_param('parent_discoveryid'));
		}
		$footer[] = new CButtonCancel(url_param('parent_discoveryid'));
		$frmGraph->addItemToBottomRow($footer);

		$frmGraph->show();
	}

	function get_timeperiod_form() {
		$tblPeriod = new CTableInfo();

		// init new_timeperiod variable
		$new_timeperiod = get_request('new_timeperiod', array());
		$new = is_array($new_timeperiod);

		if (is_array($new_timeperiod) && isset($new_timeperiod['id'])) {
			$tblPeriod->addItem(new CVar('new_timeperiod[id]', $new_timeperiod['id']));
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
			$new_timeperiod['hour'] = floor($new_timeperiod['start_time'] / 3600);
			$new_timeperiod['minute'] = floor(($new_timeperiod['start_time'] - ($new_timeperiod['hour'] * 3600)) / 60);
		}

		// period
		if (isset($new_timeperiod['period'])) {
			$new_timeperiod['period_days'] = floor($new_timeperiod['period'] / 86400);
			$new_timeperiod['period_hours'] = floor(($new_timeperiod['period'] - ($new_timeperiod['period_days'] * 86400)) / 3600);
			$new_timeperiod['period_minutes'] = floor(($new_timeperiod['period'] - $new_timeperiod['period_days'] * 86400 - $new_timeperiod['period_hours'] * 3600) / 60);
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

		$bit_dayofweek = zbx_str_revert($dayofweek);
		$bit_month = zbx_str_revert($month);

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

			$radioDaily = new CTag('input');
			$radioDaily->setAttribute('type', 'radio');
			$radioDaily->setAttribute('name', 'new_timeperiod[month_date_type]');
			$radioDaily->setAttribute('value', '0');
			$radioDaily->setAttribute('onclick', 'submit()');

			$radioDaily2 = new CTag('input');
			$radioDaily2->setAttribute('type', 'radio');
			$radioDaily2->setAttribute('name', 'new_timeperiod[month_date_type]');
			$radioDaily2->setAttribute('value', '1');
			$radioDaily2->setAttribute('onclick', 'submit()');

			if ($new_timeperiod['month_date_type']) {
				$radioDaily2->setAttribute('checked', 'checked');
			}
			else {
				$radioDaily->setAttribute('checked', 'checked');
			}

			$tblPeriod->addRow(array(_('Date'), array($radioDaily, _('Day'), SPACE, SPACE, $radioDaily2, _('Day of week'))));

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

			$clndr_icon = new CImg('images/general/bar/cal.gif', 'calendar', 16, 12, 'pointer');
			$clndr_icon->addAction('onclick', 'javascript: var pos = getPosition(this); pos.top += 10; pos.left += 16; CLNDR["new_timeperiod_date"].clndr.clndrshow(pos.top, pos.left);');

			$filtertimetab = new CTable(null, 'calendar');
			$filtertimetab->setAttribute('width', '10%');
			$filtertimetab->setCellPadding(0);
			$filtertimetab->setCellSpacing(0);

			$start_date = zbxDateToTime($new_timeperiod['start_date']);
			$filtertimetab->addRow(array(
				new CNumericBox('new_timeperiod_day', ($start_date > 0) ? date('d', $start_date) : '', 2),
				'/',
				new CNumericBox('new_timeperiod_month', ($start_date > 0) ? date('m', $start_date) : '', 2),
				'/',
				new CNumericBox('new_timeperiod_year', ($start_date > 0) ? date('Y', $start_date) : '', 4),
				SPACE,
				new CNumericBox('new_timeperiod_hour', ($start_date > 0) ? date('H', $start_date) : '', 2),
				':',
				new CNumericBox('new_timeperiod_minute', ($start_date > 0) ? date('i', $start_date) : '', 2),
				$clndr_icon
			));
			zbx_add_post_js('create_calendar(null, ["new_timeperiod_day", "new_timeperiod_month", "new_timeperiod_year", "new_timeperiod_hour", "new_timeperiod_minute"], "new_timeperiod_date", "new_timeperiod_start_date");');

			$tblPeriod->addRow(array(_('Date'), $filtertimetab));
		}

		if ($new_timeperiod['timeperiod_type'] != TIMEPERIOD_TYPE_ONETIME) {
			$tabTime = new CTable(null, 'calendar');
			$tabTime->addRow(array(new CNumericBox('new_timeperiod[hour]', $new_timeperiod['hour'], 2), ':', new CNumericBox('new_timeperiod[minute]', $new_timeperiod['minute'], 2)));
			$tblPeriod->addRow(array(_('At (hour:minute)'), $tabTime));
		}

		$perHours = new CComboBox('new_timeperiod[period_hours]', $new_timeperiod['period_hours']);
		for ($i = 0; $i < 25; $i++) {
			$perHours->addItem($i, $i.SPACE);
		}
		$perMinutes = new CComboBox('new_timeperiod[period_minutes]', $new_timeperiod['period_minutes']);
		for ($i = 0; $i < 60; $i++) {
			$perMinutes->addItem($i, $i.SPACE);
		}
		$tblPeriod->addRow(array(
			_('Maintenance period length'),
			array(
				new CNumericBox('new_timeperiod[period_days]', $new_timeperiod['period_days'], 3),
				_('Days').SPACE.SPACE,
				$perHours,
				SPACE._('Hours'),
				$perMinutes,
				SPACE._('Minutes')
		)));

		$td = new CCol(array(
			new CSubmit('add_timeperiod', $new ? _('Save') : _('Add')),
			SPACE,
			new CSubmit('cancel_new_timeperiod', _('Cancel'))
		));
		$td->setAttribute('colspan', '3');
		$td->setAttribute('style', 'text-align: right;');
		$tblPeriod->setFooter($td);

		return $tblPeriod;
	}

	function import_screen_form($rules){

		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('screen' => S_SCREEN);

		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', isset($rules[$key]['exist']));

			if($key == 'template')
				$cbMissed = null;
			else
				$cbMissed = new CCheckBox('rules['.$key.'][missed]', isset($rules[$key]['missed']));

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CSubmit('import', S_IMPORT));
		return $form;
	}

// HOSTS

// Host import form
	function import_host_form($template=false){
		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array(
			'host' => $template?S_TEMPLATE:S_HOST,
			'template' => S_TEMPLATE_LINKAGE,
			'item' => S_ITEM,
			'trigger' => S_TRIGGER,
			'graph' => S_GRAPH,
			'screens' => S_SCREENS,
		);
		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', true);

			if($key == 'template')
				$cbMissed = null;
			else
				$cbMissed = new CCheckBox('rules['.$key.'][missed]', true);

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CSubmit('import', S_IMPORT));

	return $form;
	}

	function insert_host_inventory_form(){
		$frmHostP = new CFormTable(_('Host Inventory'));

		$table_titles = getHostInventories();
		$table_titles = zbx_toHash($table_titles, 'db_field');
		$sql_fields = implode(', ', array_keys($table_titles));

		$sql = 'SELECT '.$sql_fields.' FROM host_inventory WHERE hostid='.$_REQUEST['hostid'];
		$result = DBselect($sql);

		$row = DBfetch($result);
		foreach($row as $key => $value){
			if(!zbx_empty($value)){
				$frmHostP->addRow($table_titles[$key]['title'], new CSpan(zbx_str2links($value), 'pre'));
			}
		}

		$frmHostP->addItemToBottomRow(new CButtonCancel(url_param('groupid')));

		return $frmHostP;
	}

	function import_map_form($rules){
		global $USER_DETAILS;

		$form = new CFormTable(S_IMPORT, null, 'post', 'multipart/form-data');
		$form->addRow(S_IMPORT_FILE, new CFile('import_file'));

		$table = new CTable();
		$table->setHeader(array(S_ELEMENT, S_UPDATE.SPACE.S_EXISTING, S_ADD.SPACE.S_MISSING), 'bold');

		$titles = array('maps' => S_MAP);
		if($USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN){
			$titles += array('icons' => _('Icon'), 'background' => _('Background'));
		}

		foreach($titles as $key => $title){
			$cbExist = new CCheckBox('rules['.$key.'][exist]', isset($rules[$key]['exist']));

			if($key != 'maps')
				$cbExist->setAttribute('onclick', 'javascript: if(this.checked) return confirm(\'Images for all maps will be updated\')');

			$cbMissed = new CCheckBox('rules['.$key.'][missed]', isset($rules[$key]['missed']));

			$table->addRow(array($title, $cbExist, $cbMissed));
		}

		$form->addRow(S_RULES, $table);

		$form->addItemToBottomRow(new CSubmit('import', S_IMPORT));
		return $form;
	}

	function get_regexp_form(){
		if(isset($_REQUEST['regexpid']) && !isset($_REQUEST['form_refresh'])){
			$sql = 'SELECT re.* '.
				' FROM regexps re '.
				' WHERE '.DBin_node('re.regexpid').
					' AND re.regexpid='.$_REQUEST['regexpid'];
			$regexp = DBfetch(DBSelect($sql));

			$rename			= $regexp['name'];
			$test_string	= $regexp['test_string'];

			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND e.regexpid='.$regexp['regexpid'].
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				$expressions[] = $exp;
			}
		}
		else{
			$rename			= get_request('rename','');
			$test_string	= get_request('test_string','');

			$expressions 	= get_request('expressions',array());
		}

		$tblRE = new CTable('','formtable nowrap');

		$tblRE->addRow(array(S_NAME, new CTextBox('rename', $rename, 60)));
		$tblRE->addRow(array(S_TEST_STRING, new CTextArea('test_string', $test_string, 66, 5)));

		$tabExp = new CTableInfo();

		$td1 = new CCol(S_EXPRESSION);
		$td2 = new CCol(_('Expected result'));
		$td3 = new CCol(S_RESULT);

		$tabExp->setHeader(array($td1,$td2,$td3));

		$final_result = !empty($test_string);

		foreach($expressions as $id => $expression){

			$results = array();
			$paterns = array($expression['expression']);

			if(!empty($test_string)){
				if($expression['expression_type'] == EXPRESSION_TYPE_ANY_INCLUDED){
					$paterns = explode($expression['exp_delimiter'],$expression['expression']);
				}

				if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_TRUE,EXPRESSION_TYPE_FALSE))){
					if($expression['case_sensitive'])
						$results[$id] = preg_match('/'.$paterns[0].'/',$test_string);
					else
						$results[$id] = preg_match('/'.$paterns[0].'/i',$test_string);

					if($expression['expression_type'] == EXPRESSION_TYPE_TRUE)
						$final_result &= $results[$id];
					else
						$final_result &= !$results[$id];
				}
				else{
					$results[$id] = true;

					$tmp_result = false;
					if($expression['case_sensitive']){
						foreach($paterns as $pid => $patern){
							$tmp_result |= (zbx_strstr($test_string,$patern) !== false);
						}
					}
					else{
						foreach($paterns as $pid => $patern){
							$tmp_result |= (zbx_stristr($test_string,$patern) !== false);
						}
					}

					if(uint_in_array($expression['expression_type'], array(EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED)))
						$results[$id] &= $tmp_result;
					else if($expression['expression_type'] == EXPRESSION_TYPE_NOT_INCLUDED){
						$results[$id] &= !$tmp_result;
					}
					$final_result &= $results[$id];
				}
			}

			if(isset($results[$id]) && $results[$id])
				$exp_res = new CSpan(S_TRUE_BIG,'green bold');
			else
				$exp_res = new CSpan(S_FALSE_BIG,'red bold');

			$expec_result = expression_type2str($expression['expression_type']);
			if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
				$expec_result.=' ('._('Delimiter')."='".$expression['exp_delimiter']."')";

			$tabExp->addRow(array(
						$expression['expression'],
						$expec_result,
						$exp_res
					));
		}

		$td = new CCol(S_COMBINED_RESULT,'bold');
		$td->setColSpan(2);

		if($final_result)
			$final_result = new CSpan(S_TRUE_BIG,'green bold');
		else
			$final_result = new CSpan(S_FALSE_BIG,'red bold');

		$tabExp->addRow(array(
					$td,
					$final_result
				));

		$tblRE->addRow(array(S_RESULT,$tabExp));

		$tblFoot = new CTableInfo(null);

		$td = new CCol(array(new CSubmit('save',S_SAVE)));
		$td->setColSpan(2);
		$td->addStyle('text-align: right;');

		$td->addItem(SPACE);
		$td->addItem(new CSubmit('test',S_TEST));

		if(isset($_REQUEST['regexpid'])){
			$td->addItem(SPACE);
			$td->addItem(new CSubmit('clone', S_CLONE));
			$td->addItem(SPACE);
			$td->addItem(new CButtonDelete(S_DELETE_REGULAR_EXPRESSION_Q, url_param('form').url_param('config').url_param('regexpid')));
		}

		$td->addItem(SPACE);
		$td->addItem(new CButtonCancel(url_param("regexpid")));

		$tblFoot->setFooter($td);

	return array($tblRE,$tblFoot);
	}

	function get_expressions_tab(){
		if(isset($_REQUEST['regexpid']) && !isset($_REQUEST['form_refresh'])){
			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND e.regexpid='.$_REQUEST['regexpid'].
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				$expressions[] = $exp;
			}
		}
		else{
			$expressions = get_request('expressions',array());
		}

		$tblExp = new CTableInfo();
		$tblExp->setHeader(array(
				new CCheckBox('all_expressions', null, 'checkAll("regularExpressionsForm", "all_expressions", "g_expressionid");'),
				_('Expression'),
				_('Expected result'),
				_('Case sensitive'),
				_('Edit')
			));

//		zbx_rksort($timeperiods);
		foreach($expressions as $id => $expression){

			$exp_result = expression_type2str($expression['expression_type']);
			if(EXPRESSION_TYPE_ANY_INCLUDED == $expression['expression_type'])
				$exp_result.=' ('._('Delimiter')."='".$expression['exp_delimiter']."')";

			$tblExp->addRow(array(
				new CCheckBox('g_expressionid[]', 'no', null, $id),
				$expression['expression'],
				$exp_result,
				$expression['case_sensitive'] ? _('Yes') : _('No'),
				new CSubmit('edit_expressionid['.$id.']', _('Edit'))
			));

			$tblExp->addItem(new CVar('expressions['.$id.'][expression]',		$expression['expression']));
			$tblExp->addItem(new CVar('expressions['.$id.'][expression_type]',	$expression['expression_type']));
			$tblExp->addItem(new CVar('expressions['.$id.'][case_sensitive]',	$expression['case_sensitive']));
			$tblExp->addItem(new CVar('expressions['.$id.'][exp_delimiter]',	$expression['exp_delimiter']));
		}

		$buttons = array();
		if(!isset($_REQUEST['new_expression'])){
			$buttons[] = new CSubmit('new_expression', _('New'));
			$buttons[] = new CSubmit('delete_expression', _('Delete'));
		}

		$td = new CCol($buttons);
		$td->setAttribute('colspan', '5');
		$td->setAttribute('style', 'text-align: right;');
		$tblExp->setFooter($td);

		return $tblExp;
	}

	function get_expression_form(){
		$tblExp = new CTable();

		/* init new_timeperiod variable */
		$new_expression = get_request('new_expression', array());

		if(is_array($new_expression) && isset($new_expression['id'])){
			$tblExp->addItem(new Cvar('new_expression[id]', $new_expression['id']));
		}

		if(!is_array($new_expression)){
			$new_expression = array();
		}

		if(!isset($new_expression['expression']))			$new_expression['expression']		= '';
		if(!isset($new_expression['expression_type']))		$new_expression['expression_type']	= EXPRESSION_TYPE_INCLUDED;
		if(!isset($new_expression['case_sensitive']))		$new_expression['case_sensitive']	= 0;
		if(!isset($new_expression['exp_delimiter']))		$new_expression['exp_delimiter']	= ',';

		$tblExp->addRow(array(_('Expression'), new CTextBox('new_expression[expression]', $new_expression['expression'], 60)));

		$cmbType = new CComboBox('new_expression[expression_type]', $new_expression['expression_type'], 'javascript: submit();');
		$cmbType->addItem(EXPRESSION_TYPE_INCLUDED, expression_type2str(EXPRESSION_TYPE_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_ANY_INCLUDED, expression_type2str(EXPRESSION_TYPE_ANY_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_NOT_INCLUDED, expression_type2str(EXPRESSION_TYPE_NOT_INCLUDED));
		$cmbType->addItem(EXPRESSION_TYPE_TRUE, expression_type2str(EXPRESSION_TYPE_TRUE));
		$cmbType->addItem(EXPRESSION_TYPE_FALSE, expression_type2str(EXPRESSION_TYPE_FALSE));

		$tblExp->addRow(array(_('Expression type'), $cmbType));

		if(EXPRESSION_TYPE_ANY_INCLUDED == $new_expression['expression_type']){
			$cmbDelimiter = new CComboBox('new_expression[exp_delimiter]', $new_expression['exp_delimiter']);
			$cmbDelimiter->addItem(',', ',');
			$cmbDelimiter->addItem('.', '.');
			$cmbDelimiter->addItem('/', '/');

			$tblExp->addRow(array(_('Delimiter'), $cmbDelimiter));
		}
		else{
			$tblExp->addItem(new Cvar('new_expression[exp_delimiter]', $new_expression['exp_delimiter']));
		}

		$chkbCase = new CCheckBox('new_expression[case_sensitive]', $new_expression['case_sensitive'], null, 1);

		$tblExp->addRow(array(_('Case sensitive'), $chkbCase));

		$tblExpFooter = new CTableInfo($tblExp);

		$oper_buttons = array();
		$oper_buttons[] = new CSubmit('add_expression', isset($new_expression['id']) ? _('Save') : _('Add'));
		$oper_buttons[] = new CSubmit('cancel_new_expression', _('Cancel'));

		$td = new CCol($oper_buttons);
		$td->setAttribute('colspan', 2);
		$td->setAttribute('style', 'text-align: right;');

		$tblExpFooter->setFooter($td);

	return $tblExpFooter;
	}
?>
