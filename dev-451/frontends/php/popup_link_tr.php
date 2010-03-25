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
		'mapid'=>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
		'triggerid'=>	array(T_ZBX_INT, O_OPT,  NULL, 	DB_ID, 			'isset({save})'),
		'desc_exp'=>	array(T_ZBX_STR, O_OPT,  NULL, 	NOT_EMPTY,		'isset({save})'),
		'drawtype'=>	array(T_ZBX_INT, O_OPT,  NULL, 	IN('0,1,2,3,4'),'isset({save})'),
		'color'=>		array(T_ZBX_STR, O_OPT,  NULL, 	NOT_EMPTY,		'isset({save})'),
/* actions */
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	);

	check_fields($fields);
?>
<script type="text/javascript">
<!--<![CDATA[
function add_linktrigger(mapid,triggerid,desc_exp,drawtype,color){
	var parent = window.opener.document;

	if(typeof(parent) == 'undefined'){
		close_window();
		return false;
	}

	var linktrigger = {
		'triggerid': triggerid,
		'desc_exp': desc_exp,
		'drawtype': drawtype,
		'color': color
	};

	window.opener.ZBX_SYSMAPS[mapid].map.linkForm_addLinktrigger(linktrigger);
	window.close();

return true;
}
//]]>
-->
</script>
<?php
	if(isset($_REQUEST['save']) && isset($_REQUEST['triggerid'])){
		$script = 'add_linktrigger("'.$_REQUEST['mapid'].'","'.
								$_REQUEST['triggerid'].'","'.
								$_REQUEST['desc_exp'].'","'.
								$_REQUEST['drawtype'].'","'.
								$_REQUEST['color'].'");';
		insert_js($script);
	}
	else if(isset($_REQUEST['form'])){
		echo SBR;

		$frmCnct = new CFormTable(S_NEW_CONNECTOR, 'popup_link_tr.php');
		$frmCnct->setName('connector_form');
//		$frmCnct->SetHelp('web.sysmap.connector.php');


		$triggerid = get_request('triggerid', 0);
		$drawtype = get_request('drawtype', 0);
		$color = get_request('color', 0);

		$frmCnct->addVar('mapid', $_REQUEST['mapid']);
		$frmCnct->addVar('triggerid', $triggerid);

// START comboboxes preparations
		$cmbType = new CComboBox('drawtype', $drawtype);

		$drawtypes = map_link_drawtypes();
		foreach($drawtypes as $num => $i){
			$value = map_link_drawtype2str($i);
			$cmbType->addItem($i, $value);
		}

		$btnSelect = new CButton('btn1', S_SELECT,
			"return PopUp('popup.php?real_hosts=1&dstfrm=".$frmCnct->getName().
			"&dstfld1=triggerid&dstfld2=desc_exp&srctbl=triggers&srcfld1=triggerid&srcfld2=description&writeonly=1');",
			'T');
		$btnSelect->setType('button');

// END preparation
		$description = ($triggerid > 0) ? expand_trigger_description($triggerid) : '';

		$frmCnct->addRow(S_TRIGGER, array(new CTextBox('desc_exp', $description, 70, 'yes'), SPACE, $btnSelect));
		$frmCnct->addRow(S_TYPE.' ('.S_PROBLEM_BIG.')', $cmbType);
		$frmCnct->addRow(S_COLOR.' ('.S_PROBLEM_BIG.')', new CColor('color', $color));

		$frmCnct->addItemToBottomRow(new CButton('save', (isset($_REQUEST['triggerid'])) ? S_SAVE : S_ADD));
		$frmCnct->addItemToBottomRow(SPACE);
		$frmCnct->addItemToBottomRow(new CButton('cancel', S_CANCEL, 'javascript: window.close();'));

		$frmCnct->Show();
	}

	
	require_once('include/page_footer.php');
?>