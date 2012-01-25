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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/forms.inc.php');
require_once('include/discovery.inc.php');

$page['title']	= 'S_CONFIGURATION_OF_DISCOVERY';
$page['file']	= 'discoveryconf.php';
$page['hist_arg'] = array();
$page['scripts'] = array();
$page['type'] = detect_page_type();

require_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'druleid'=>				array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,		null),
	'name'=>				array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
	'proxy_hostid'=>		array(T_ZBX_INT, O_OPT,	 null,	DB_ID,		'isset({save})'),
	'iprange'=>				array(T_ZBX_STR, O_OPT,  null,	null,		'isset({save})'),
	'delay'=>				array(T_ZBX_INT, O_OPT,	 null,	null, 		'isset({save})'),
	'status'=>				array(T_ZBX_INT, O_OPT,	 null,	IN('0,1'), 	'isset({save})'),
	'uniqueness_criteria'=>	array(T_ZBX_INT, O_OPT,  null, NULL,		'isset({save})', _('Device uniqueness criteria')),
	'g_druleid'=>			array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),
	'dchecks'=>				array(null, O_OPT, null, null, null),
// Actions
	'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
	'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'clone'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
	'form'=>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh'=>		array(T_ZBX_INT, O_OPT,	null,	null,	null),

	'output'=>				array(T_ZBX_STR, O_OPT, P_ACT,	null,			null),
	'ajaxaction'=>				array(T_ZBX_STR, O_OPT, P_ACT,	null,			null),
	'ajaxdata'=>				array(T_ZBX_STR, O_OPT, P_ACT,	null,			null),
);

check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');
$_REQUEST['dchecks'] = get_request('dchecks', array());

?>
<?php
if(get_request('output') == 'ajax'){
	$ajaxResponse = new ajaxResponse;

	if(get_request('ajaxaction') && get_request('ajaxaction') == 'validate'){
		$ajaxdata = get_request('ajaxdata', array());
		foreach($ajaxdata as $check){
			switch($check['field']){
				case 'port':
					if(!validate_port_list($check['value']))
						$ajaxResponse->error(_('Incorrect port range.'));
					break;
				case 'itemKey':
					$itemKey = new CItemKey($check['value']);
					if(!$itemKey->isValid())
						$ajaxResponse->error(_('Incorrect key: '.$itemKey->getError()));
					break;
			}
		}
	}

	$ajaxResponse->send();

	require_once('include/page_footer.php');
	exit();
}


if(get_request('save')){
	$dChecks = get_request('dchecks', array());
	$uniq = get_request('uniqueness_criteria', 0);
	foreach($dChecks as $dcnum => $check){
		$dChecks[$dcnum]['uniq'] = ($uniq == $dcnum ? 1 : 0);
	}

	$discoveryRule = array(
		'name' => get_request('name'),
		'proxy_hostid' => get_request('proxy_hostid'),
		'iprange' => get_request('iprange'),
		'delay' => get_request('delay'),
		'status' => get_request('status'),
		'dchecks' => $dChecks,
	);

	if(isset($_REQUEST['druleid'])){
		$discoveryRule['druleid'] = get_request('druleid');
		$result = API::drule()->update($discoveryRule);

		$msg_ok = _('Discovery rule updated');
		$msg_fail = _('Cannot update discovery rule');
	}
	else{
		$result = API::drule()->create($discoveryRule);

		$msg_ok = _('Discovery rule created');
		$msg_fail = _('Cannot create discovery rule');
	}

	show_messages($result, $msg_ok, $msg_fail);

	if($result){
		$druleid = reset($result['druleids']);
		add_audit(isset($discoveryRule['druleid']) ? AUDIT_ACTION_UPDATE : AUDIT_ACTION_ADD,
			AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleid.'] '.$discoveryRule['name']);

		unset($_REQUEST['form']);
	}
}
else if(get_request('delete') && get_request('druleid')){
	$result = delete_discovery_rule($_REQUEST['druleid']);
	show_messages($result, _('Discovery rule deleted'), _('Cannot delete discovery rule'));

	if($result){
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_DISCOVERY_RULE, '['.$_REQUEST['druleid'].']');
		unset($_REQUEST['form']);
		unset($_REQUEST['druleid']);
	}

}
// ------- GO --------
else if(str_in_array($_REQUEST['go'], array('activate','disable')) && isset($_REQUEST['g_druleid'])){
	$status = ($_REQUEST['go'] == 'activate')?DRULE_STATUS_ACTIVE:DRULE_STATUS_DISABLED;

	$go_result = false;
	foreach($_REQUEST['g_druleid'] as $drid){
		if(DBexecute('update drules set status='.$status.' where druleid='.$drid)){
			$rule_data = get_discovery_rule_by_druleid($drid);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_DISCOVERY_RULE,
				'['.$drid.'] '.$rule_data['name']);
			$go_result = true;
		}
	}
	show_messages($go_result, _('Discovery rules updated'));
}
else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['g_druleid'])){
	$go_result = false;
	foreach($_REQUEST['g_druleid'] as $drid){
		if(delete_discovery_rule($drid)){
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_DISCOVERY_RULE,
				'['.$drid.']');
			$go_result = true;
		}
	}
	show_messages($go_result, _('Discovery rules deleted'));
}

if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

?>
<?php
/* header */
$form_button = new CForm('get');
if(!isset($_REQUEST['form'])){
	$form_button->cleanItems();

	$buttons = new CDiv(array(
		new CSubmit('form', S_CREATE_RULE)
	));
	$buttons->useJQueryStyle();
	$form_button->addItem($buttons);
}

$dscry_wdgt = new CWidget();

if(isset($_REQUEST['form'])){
	$drule = null;

	if(isset($_REQUEST['druleid'])){
		$drules = API::DRule()->get(array(
			'druleids' => $_REQUEST['druleid'],
			'output' => API_OUTPUT_EXTEND,
			'selectDChecks' => API_OUTPUT_EXTEND,
			'editable' => true
		));
		$drule = reset($drules);

		$drule['uniqueness_criteria'] = -1;

		foreach($drule['dchecks'] as $dcnum => $dcheck)
			if($dcheck['uniq']) $drule['uniqueness_criteria'] = $dcheck['dcheckid'];
	}

	$uniqueness_criteria = -1;
	if(isset($drule['name']) && !isset($_REQUEST['form_refresh'])){
	}
	else{
		$drule['proxy_hostid'] = get_request('proxy_hostid', 0);
		$drule['name'] = get_request('name', '');
		$drule['iprange'] = get_request('iprange', '192.168.0.1-255');
		$drule['delay'] = get_request('delay', SEC_PER_HOUR);
		$drule['status'] = get_request('status', DRULE_STATUS_ACTIVE);

		$drule['dchecks'] = get_request('dchecks', array());
		$drule['uniqueness_criteria'] = get_request('uniqueness_criteria', -1);
	}

	$discoveryForm = new CView('configuration.discovery.edit', $drule);
	$dscry_wdgt->addItem($discoveryForm->render());
}
else{
	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$dscry_wdgt->addHeader(_('DISCOVERY'));
	$dscry_wdgt->addHeader($numrows, $form_button);

	$form = new CForm();
	$form->setName('frmdrules');

	$tblDiscovery = new CTableInfo(_('No discovery rules defined'));
	$tblDiscovery->setHeader(array(
		new CCheckBox('all_drules',null,"checkAll('".$form->getName()."','all_drules','g_druleid');"),
		make_sorting_header(_('Name'), 'name'),
		_('IP range'),
		_('Delay'),
		_('Checks'),
		_('Status')
	));

	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();
	$drules = API::DRule()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => $sortfield,
		'sortorder' => $sortorder,
		'selectDChecks' => API_OUTPUT_EXTEND,
		'editable' => true
	));

	// getting paging element
	$paging = getPagingLine($drules);

	foreach($drules as $rule_data){
		$checks = array();
		foreach($rule_data['dchecks'] as $check_data)
			$checks[$check_data['type']] = discovery_check_type2str($check_data['type']);
		order_result($checks);

		$status = new CCol(new CLink(discovery_status2str($rule_data['status']),
			'?g_druleid%5B%5D='.$rule_data['druleid'].
			(($rule_data["status"] == DRULE_STATUS_ACTIVE) ? '&go=disable' : '&go=activate'),
			discovery_status2style($rule_data['status'])
		));

		$description = array();
		if ($rule_data['proxy_hostid']) {
			$proxy = get_host_by_hostid($rule_data['proxy_hostid']);
			array_push($description, $proxy['host'].':');
		}

		array_push($description, new CLink($rule_data['name'], '?form=update&druleid='.$rule_data['druleid']));

		$tblDiscovery->addRow(array(
			new CCheckBox('g_druleid['.$rule_data['druleid'].']', null, null, $rule_data['druleid']),
			$description,
			$rule_data['iprange'],
			$rule_data['delay'],
			implode(', ', $checks),
			$status
		));
	}

// gobox
	$goBox = new CComboBox('go');
	$goOption = new CComboItem('activate', _('Enable selected'));
	$goOption->setAttribute('confirm', _('Enable selected discovery rules?'));
	$goBox->addItem($goOption);

	$goOption = new CComboItem('disable', _('Disable selected'));
	$goOption->setAttribute('confirm', _('Disable selected discovery rules?'));
	$goBox->addItem($goOption);

	$goOption = new CComboItem('delete', _('Delete selected'));
	$goOption->setAttribute('confirm', _('Delete selected discovery rules?'));
	$goBox->addItem($goOption);

	// goButton name is necessary!!!
	$goButton = new CSubmit('goButton', S_GO.' (0)');
	$goButton->setAttribute('id','goButton');

	zbx_add_post_js('chkbxRange.pageGoName = "g_druleid";');
	$footer = get_table_header(array($goBox, $goButton));


	$form->addItem(array($paging, $tblDiscovery, $paging, $footer));
	$dscry_wdgt->addItem($form);
}

$dscry_wdgt->show();
?>
<?php

require_once('include/page_footer.php');

?>
