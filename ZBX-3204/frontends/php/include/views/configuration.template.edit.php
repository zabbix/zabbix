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

// include JS + templates
include('include/views/js/configuration.host.edit.macros.js.php');

$divTabs = new CTabView(array('remember' => 1));
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}


$templateid = get_request('templateid', 0);
$host = get_request('template_name', '');
$visiblename = get_request('visiblename', '');
$newgroup = get_request('newgroup', '');
$templates = get_request('templates', array());
$clear_templates = get_request('clear_templates', array());
$macros = get_request('macros', array());

$frm_title = _('Template');

if ($templateid > 0) {
	$dbTemplates = API::Template()->get(array(
		'templateids' => $templateid,
		'selectGroups' => API_OUTPUT_EXTEND,
		'selectParentTemplates' => API_OUTPUT_EXTEND,
		'selectMacros' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND
	));
	$dbTemplate = reset($dbTemplates);

	$frm_title .= SPACE.' ['.$dbTemplate['name'].']';

	$original_templates = array();
	foreach ($dbTemplate['parentTemplates'] as $tnum => $tpl) {
		$original_templates[$tpl['templateid']] = $tpl['name'];
	}
}
else {
	$original_templates = array();
}

$frmHost = new CForm();
$frmHost->setName('tpl_for');

$frmHost->addVar('form', get_request('form', 1));
$frmHost->addVar('clear_templates', $clear_templates);
$frmHost->addVar('groupid', $_REQUEST['groupid']);

if ($templateid) {
	$frmHost->addVar('templateid', $templateid);
}

if (($templateid > 0) && !isset($_REQUEST['form_refresh'])) {
	$host = $dbTemplate['host'];
	$visiblename = $dbTemplate['name'];
// display empry visible nam if equal to host name
	if ($visiblename == $host) {
		$visiblename = '';
	}

// get template groups from db
	$groups = $dbTemplate['groups'];
	$groups = zbx_objectValues($groups, 'groupid');

	$macros = order_macros($dbTemplate['macros'], 'macro');

// get template hosts from db
	$hosts_linked_to = API::Host()->get(array(
		'templateids' => $templateid,
		'editable' => 1,
		'templated_hosts' => 1
	));

	$hosts_linked_to = zbx_objectValues($hosts_linked_to, 'hostid');
	$hosts_linked_to = zbx_toHash($hosts_linked_to, 'hostid');
	$templates = $original_templates;
}
else {
	$groups = get_request('groups', array());
	if (isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0) && !uint_in_array($_REQUEST['groupid'], $groups)) {
		array_push($groups, $_REQUEST['groupid']);
	}
	$hosts_linked_to = get_request('hosts', array());
}

$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
$clear_templates = array_diff($clear_templates, array_keys($templates));
natcasesort($templates);
$frmHost->addVar('clear_templates', $clear_templates);

// TEMPLATE WIDGET {
$templateList = new CFormList('hostlist');

// FORM ITEM : Template name text box [  ]
$template_nameTB = new CTextBox('template_name', $host, 54);
$template_nameTB->setAttribute('maxlength', 64);
$templateList->addRow(_('Template name'), $template_nameTB);

$visiblenameTB = new CTextBox('visiblename', $visiblename, 54);
$visiblenameTB->setAttribute('maxlength', 64);
$templateList->addRow(_('Visible name'), $visiblenameTB);

// FORM ITEM : Groups tween box [  ] [  ]
// get all Groups
$group_tb = new CTweenBox($frmHost, 'groups', $groups, 10);
$options = array(
	'editable' => 1,
	'output' => API_OUTPUT_EXTEND
);
$all_groups = API::HostGroup()->get($options);
order_result($all_groups, 'name');

foreach ($all_groups as $gnum => $group) {
	$group_tb->addItem($group['groupid'], $group['name']);
}
$templateList->addRow(_('Groups'), $group_tb->get(_('In groups'), _('Other groups')));

// FORM ITEM : new group text box [  ]
global $USER_DETAILS;
$newgroupTB = new CTextBox('newgroup', $newgroup);
$newgroupTB->setAttribute('maxlength', 64);
$tmp_label = _('New group');
if ($USER_DETAILS['type'] != USER_TYPE_SUPER_ADMIN) {
	$tmp_label .= SPACE._('(Only superadmins can create group)');
	$newgroupTB->setReadonly(true);
}
$templateList->addRow(array(
	new CLabel($tmp_label, 'newgroup'),
	BR(),
	$newgroupTB
), null, null, null, 'new');

// FORM ITEM : linked Hosts tween box [  ] [  ]
$twb_groupid = get_request('twb_groupid', 0);
if ($twb_groupid == 0) {
	$gr = reset($all_groups);
	$twb_groupid = $gr['groupid'];
}
$cmbGroups = new CComboBox('twb_groupid', $twb_groupid, 'submit()');
foreach ($all_groups as $gnum => $group) {
	$cmbGroups->addItem($group['groupid'], $group['name']);
}

$host_tb = new CTweenBox($frmHost, 'hosts', $hosts_linked_to, 20);

// get hosts from selected twb_groupid combo
$params = array(
	'groupids' => $twb_groupid,
	'templated_hosts' => 1,
	'editable' => 1,
	'output' => API_OUTPUT_EXTEND
);
$db_hosts = API::Host()->get($params);
order_result($db_hosts, 'name');

foreach ($db_hosts as $hnum => $db_host) {
	if (isset($hosts_linked_to[$db_host['hostid']])) {
		continue;
	} // add all except selected hosts
	$host_tb->addItem($db_host['hostid'], $db_host['name']);
}

// select selected hosts and add them
$params = array(
	'hostids' => $hosts_linked_to,
	'templated_hosts' => 1,
	'editable' => 1,
	'output' => API_OUTPUT_EXTEND
);
$db_hosts = API::Host()->get($params);
order_result($db_hosts, 'name');
foreach ($db_hosts as $hnum => $db_host) {
	$host_tb->addItem($db_host['hostid'], $db_host['name']);
}

$templateList->addRow(_('Hosts / templates'), $host_tb->Get(_('In'), array(
	_('Other | group').SPACE,
	$cmbGroups
)));

// FULL CLONE {
if ($_REQUEST['form'] == 'full_clone') {
	// template applications
	$templateApps = API::Application()->get(array(
		'hostids' => $templateid,
		'inherited' => false,
		'output' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));
	if (!empty($templateApps)) {
		$applicationsList = array();
		foreach ($templateApps as $tplAppId => $templateApp) {
			$applicationsList[$tplAppId] = $templateApp['name'];
		}
		order_result($applicationsList);

		$listBox = new CListBox('applications', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($applicationsList);
		$templateList->addRow(_('Applications'), $listBox);
	}

// Items
	$hostItems = API::Item()->get(array(
		'hostids' => $templateid,
		'inherited' => false,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'output' => API_OUTPUT_EXTEND,
	));
	if (!empty($hostItems)) {
		$itemsList = array();
		foreach ($hostItems as $hostItem) {
			$itemsList[$hostItem['itemid']] = itemName($hostItem);
		}
		order_result($itemsList);

		$listBox = new CListBox('items', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($itemsList);

		$templateList->addRow(_('Items'), $listBox);
	}

// Triggers
	$hostTriggers = API::Trigger()->get(array(
		'inherited' => false,
		'hostids' => $templateid,
		'output' => API_OUTPUT_EXTEND,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL))
	));
	if (!empty($hostTriggers)) {
		$triggersList = array();
		foreach ($hostTriggers as $hostTrigger) {
			$triggersList[$hostTrigger['triggerid']] = $hostTrigger['description'];
		}
		order_result($triggersList);

		$listBox = new CListBox('triggers', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($triggersList);

		$templateList->addRow(_('Triggers'), $listBox);
	}

// Graphs
	$hostGraphs = API::Graph()->get(array(
		'inherited' => false,
		'hostids' => $templateid,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
		'output' => API_OUTPUT_EXTEND,
	));
	if (!empty($hostGraphs)) {
		$graphsList = array();
		foreach ($hostGraphs as $hostGraph) {
			$graphsList[$hostGraph['graphid']] = $hostGraph['name'];
		}
		order_result($graphsList);

		$listBox = new CListBox('graphs', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($graphsList);

		$templateList->addRow(_('Graphs'), $listBox);
	}

// Discovery rules
	$hostDiscoveryRules = API::DiscoveryRule()->get(array(
		'inherited' => false,
		'hostids' => $templateid,
		'output' => API_OUTPUT_EXTEND,
	));
	if (!empty($hostDiscoveryRules)) {
		$discoveryRuleList = array();
		foreach ($hostDiscoveryRules as $discoveryRule) {
			$discoveryRuleList[$discoveryRule['itemid']] = itemName($discoveryRule);
		}
		order_result($discoveryRuleList);
		$hostDiscoveryRuleids = array_keys($discoveryRuleList);

		$listBox = new CListBox('discoveryRules', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($discoveryRuleList);

		$templateList->addRow(_('Discovery rules'), $listBox);

// Item prototypes
		$hostItemPrototypes = API::Itemprototype()->get(array(
			'hostids' => $templateid,
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		));
		if (!empty($hostItemPrototypes)) {
			$prototypeList = array();
			foreach ($hostItemPrototypes as $itemPrototype) {
				$prototypeList[$itemPrototype['itemid']] = itemName($itemPrototype);
			}
			order_result($prototypeList);

			$listBox = new CListBox('itemsPrototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$templateList->addRow(_('Item prototypes'), $listBox);
		}

// Trigger prototypes
		$hostTriggerPrototypes = API::TriggerPrototype()->get(array(
			'hostids' => $templateid,
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND
		));
		if (!empty($hostTriggerPrototypes)) {
			$prototypeList = array();
			foreach ($hostTriggerPrototypes as $triggerPrototype) {
				$prototypeList[$triggerPrototype['triggerid']] = $triggerPrototype['description'];
			}
			order_result($prototypeList);

			$listBox = new CListBox('triggerprototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$templateList->addRow(_('Trigger prototypes'), $listBox);
		}

// Graph prototypes
		$hostGraphPrototypes = API::GraphPrototype()->get(array(
			'hostids' => $templateid,
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		));
		if (!empty($hostGraphPrototypes)) {
			$prototypeList = array();
			foreach ($hostGraphPrototypes as $graphPrototype) {
				$prototypeList[$graphPrototype['graphid']] = $graphPrototype['name'];
			}
			order_result($prototypeList);

			$listBox = new CListBox('graphPrototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$templateList->addRow(_('Graph prototypes'), $listBox);
		}
	}
}

$divTabs->addTab('templateTab', _('Template'), $templateList);
// FULL CLONE }

// } TEMPLATE WIDGET

// TEMPLATES{
$tmplList = new CFormList('tmpllist');
foreach ($templates as $tid => $temp_name) {
	$frmHost->addVar('templates['.$tid.']', $temp_name);
	$tmplList->addRow($temp_name, array(
		new CSubmit('unlink['.$tid.']', _('Unlink'), null, 'link_menu'),
		SPACE,
		SPACE,
		isset($original_templates[$tid]) ? new CSubmit('unlink_and_clear['.$tid.']', _('Unlink and clear'), null, 'link_menu') : SPACE
	));
}

$tmplAdd = new CButton('add', _('Add'),
		'return PopUp("popup.php?srctbl=templates&srcfld1=hostid&srcfld2=host'.
				'&dstfrm='.$frmHost->getName().'&dstfld1=new_template&templated_hosts=1'.
				'&excludeids['.$templateid.']='.$templateid.
				url_param($templates, false, 'existed_templates').'",450,450)',
	'link_menu'
);

$tmplList->addRow($tmplAdd, SPACE);

$divTabs->addTab('tmplTab', _('Linked templates'), $tmplList);
// } TEMPLATES

// macros
if (empty($macros)) {
	$macros = array(array('macro' => '', 'value' => ''));
}
$macrosView = new CView('common.macros', array(
	'macros' => $macros
));
$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());

$frmHost->addItem($divTabs);

// Footer
$main = array(new CSubmit('save', _('Save')));
$others = array();
if (($templateid > 0) && ($_REQUEST['form'] != 'full_clone')) {
	$others[] = new CSubmit('clone', _('Clone'));
	$others[] = new CSubmit('full_clone', _('Full clone'));
	$others[] = new CButtonDelete(_('Delete template?'), url_param('form').url_param('templateid').url_param('groupid'));
	$others[] = new CButtonQMessage('delete_and_clear', _('Delete and clear'), _('Delete and clear template? (Warning: all linked hosts will be cleared!)'), url_param('form').url_param('templateid').url_param('groupid'));
}
$others[] = new CButtonCancel(url_param('groupid'));

$frmHost->addItem(makeFormFooter($main, $others));

return $frmHost;
