<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
require_once('include/config.inc.php');
require_once('include/maps.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_LINK_STATUS_INDICATORS';
$page['file'] = 'popup_link_tr.php';

define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');
?>
<?php
//			VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array(
		'mapid'=>				array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
		'linktriggers'=>		array(T_ZBX_INT, O_OPT,  P_SYS, DB_ID, 			'isset({save})'),
		'new_linktriggers'=>	array(T_ZBX_INT, O_OPT,  P_SYS, DB_ID, 			null),
		'del_linktriggers'=>	array(T_ZBX_INT, O_OPT,  NULL, 	DB_ID, 			null),
		'drawtype'=>			array(T_ZBX_INT, O_OPT,  NULL, 	IN('0,1,2,3,4'),'isset({save})'),
		'color'=>				array(T_ZBX_STR, O_OPT,  NULL, 	NOT_EMPTY,		'isset({save})'),
// actions
		'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'remove'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// other
		'form'=>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	);

	check_fields($fields);
?>
<?php
	$_REQUEST['linktriggers'] = get_request('linktriggers', array());

	if(isset($_REQUEST['save']) && isset($_REQUEST['linktriggers'])){

		if(!empty($_REQUEST['linktriggers'])){
			$triggers = array();
			$options = array(
					'nodeids' => get_current_nodeid(true),
					'triggerids'=> $_REQUEST['linktriggers'],
					'editable'=> 1,
					'select_hosts' => array('hostid', 'host'),
					'output' => API_OUTPUT_EXTEND
				);

			$dbTriggers = CTrigger::get($options);
			order_result($dbTriggers, 'description');

			foreach($dbTriggers as $tnum => $trigger){
				$host = reset($trigger['hosts']);

				$triggers[$trigger['triggerid']] = $host['host'].':'.expand_trigger_description_by_data($trigger);
			}
		}

		$json = new CJSON();
		$script = 'addLinkTriggers('.zbx_jsvalue($_REQUEST['mapid']).','.
								$json->encode($triggers).','.
								zbx_jsvalue($_REQUEST['drawtype']).','.
								zbx_jsvalue($_REQUEST['color']).');';

		zbx_add_post_js($script);
	}
	else if(isset($_REQUEST['new_linktriggers'])){
		$_REQUEST['linktriggers'] = array_merge($_REQUEST['linktriggers'], $_REQUEST['new_linktriggers']);
		array_unique($_REQUEST['linktriggers']);

		unset($_REQUEST['new_linktriggers']);
	}
	else if(isset($_REQUEST['remove']) && isset($_REQUEST['del_linktriggers'])){
		$_REQUEST['linktriggers'] = array_diff($_REQUEST['linktriggers'], $_REQUEST['del_linktriggers']);
		array_unique($_REQUEST['linktriggers']);

		unset($_REQUEST['new_linktriggers']);
	}


	if(isset($_REQUEST['form'])){
		echo SBR;

		$frmCnct = new CFormTable(S_NEW_INDICATORS, 'popup_link_tr.php');
		$frmCnct->setName('connector_form');

		$triggers = array();
		if(!empty($_REQUEST['linktriggers'])){
			$options = array(
					'nodeids' => get_current_nodeid(true),
					'triggerids'=> $_REQUEST['linktriggers'],
					'editable'=> 1,
					'select_hosts' => array('hostid', 'host'),
					'output' => API_OUTPUT_EXTEND
				);

			$triggers = CTrigger::get($options);
			order_result($triggers, 'description');
		}
		$triggerids = zbx_objectValues($triggers, 'triggerid');
		$frmCnct->addVar('linktriggers', $triggerids);

		$triggerid = get_request('triggerid', 0);
		$drawtype = get_request('drawtype', 0);
		$color = get_request('color', 'DD0000');

		$frmCnct->addVar('mapid', $_REQUEST['mapid']);
		$frmCnct->addVar('triggerid', $triggerid);

// START comboboxes preparations
		$cmbType = new CComboBox('drawtype', $drawtype);

		$drawtypes = map_link_drawtypes();
		foreach($drawtypes as $num => $i){
			$value = map_link_drawtype2str($i);
			$cmbType->addItem($i, $value);
		}
//---
		$btnSelect = new CButton('btn1', S_SELECT,
			"return PopUp('popup.php?srctbl=triggers".
				'&srcfld1=triggerid'.
				'&real_hosts=1'.
				'&reference=linktrigger'.
				'&multiselect=1'.
				"&writeonly=1');",
				'T');
		$btnSelect->setType('button');

		$btnRemove = new CButton('remove', S_REMOVE);

// END preparation

		$trList = new CListBox('del_linktriggers[]', null, 15);
		if(empty($triggers)) $trList->setAttribute('style', 'width: 300px;');

		foreach($triggers as $tnum => $trigger){
			$dbTriggers = CTrigger::get($options);
			order_result($dbTriggers, 'description');

			$host = reset($trigger['hosts']);
			$trList->addItem($trigger['triggerid'], $host['host'].':'.expand_trigger_description_by_data($trigger));
		}

		$frmCnct->addRow(S_TRIGGERS, array($trList, BR(), $btnSelect, $btnRemove));
		$frmCnct->addRow(S_TYPE.' ('.S_PROBLEM_BIG.')', $cmbType);
		$frmCnct->addRow(S_COLOR.' ('.S_PROBLEM_BIG.')', new CColor('color', $color));

		$frmCnct->addItemToBottomRow(new CButton('save', (isset($_REQUEST['triggerid'])) ? S_SAVE : S_ADD));
		$frmCnct->addItemToBottomRow(SPACE);
		$frmCnct->addItemToBottomRow(new CButton('cancel', S_CANCEL, 'javascript: window.close();'));

		$frmCnct->show();
	}

?>
<script type="text/javascript">
//<!--<![CDATA[
function addPopupValues(list){
	if(!isset('object', list)) return false;

	if(list.object == 'linktrigger'){
		for(var i=0; i < list.values.length; i++){
			create_var('connector_form', 'new_linktriggers['+i+']', list.values[i], false);
		}

		create_var('connector_form','add_dependence', 1, true);
	}
}

function addLinkTriggers(mapid,triggers,drawtype,color){
	var parent = window.opener.document;

	if(typeof(parent) == 'undefined'){
		close_window();
		return false;
	}

	for(var triggerid in triggers){
		if(!isset(triggerid, triggers)) continue;

		var linktrigger = {
			'triggerid': triggerid,
			'desc_exp': triggers[triggerid],
			'drawtype': drawtype,
			'color': color
		};

		window.opener.ZBX_SYSMAPS[mapid].map.linkForm_addLinktrigger(linktrigger);
	}
	
	window.close();

return true;
}
//]]> -->
</script>
<?php

require_once('include/page_footer.php');

?>