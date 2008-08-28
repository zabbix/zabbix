<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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

	$page["title"] = "S_LINK_STATUS_INDICATORS";
	$page["file"] = "popup_link_tr.php";
	
	define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');

?>
<?php

//			VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"dstfrm"=>		array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null),
		
		"linkid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			null),
		"triggerid"=>	array(T_ZBX_INT, O_OPT,  NULL, 	DB_ID, 			'isset({save})'),
		
		"drawtype"=>	array(T_ZBX_INT, O_OPT,  NULL, 	IN('0,1,2,3,4'),'isset({save})'),
		"color"=>		array(T_ZBX_STR, O_OPT,  NULL, 	NOT_EMPTY,		'isset({save})'),
		
/* actions */
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	);

	check_fields($fields);
?>
<script type="text/javascript">
<!--<![CDATA[
function add_var_to_opener_obj(obj, name, value){
		var parent = window.opener.document;
		if(typeof(parent) == 'undefined'){
			close_window();
			return false;	
		}
		
		var dest = parent.getElementsByName(name);
		if(is_array(dest) && (typeof(dest[0]) == 'undefined')){
			dest[0].value = value;
		}
		else{
			new_variable = parent.createElement('input');
			new_variable.type = 'hidden';
			new_variable.name = name;
			new_variable.value = value;
			
			obj.appendChild(new_variable);
		}		
}

function add_trigger_link(formname,triggerid,drawtype,color){
	var form = window.opener.document.forms[formname];
	
	if(!form){
		close_window();
		return false;
	}

	add_var_to_opener_obj(form,'triggers['+triggerid+'][triggerid]',triggerid);
	add_var_to_opener_obj(form,'triggers['+triggerid+'][drawtype]',drawtype);
	add_var_to_opener_obj(form,'triggers['+triggerid+'][color]',color);
	
	form.submit();
	window.close();
return true;
}

//]]>
-->
</script>
<?php
if(isset($_REQUEST['save']) && isset($_REQUEST['triggerid']) && isset($_REQUEST['dstfrm'])){

	echo '<script language="JavaScript" type="text/javascript">
	<!--
			add_trigger_link("'.$_REQUEST['dstfrm'].'","'.
							$_REQUEST['triggerid'].'","'.
							$_REQUEST['drawtype'].'","'.
							$_REQUEST['color'].'");
	-->
	</script>';
}
else if(isset($_REQUEST['form'])){
	echo SBR;
	$frmCnct = new CFormTable("New connector","popup_link_tr.php");
	$frmCnct->SetHelp("web.sysmap.connector.php");

	$frmCnct->AddVar("dstfrm",$_REQUEST["dstfrm"]);	
	
	if(isset($_REQUEST["linkid"]) && isset($_REQUEST['triggerid'])){
		$frmCnct->AddVar("linkid",$_REQUEST["linkid"]);
		
		$db_link=DBfetch(DBselect('SELECT * FROM sysmaps_link_triggers WHERE linkid='.$_REQUEST["linkid"].' AND triggerid='.$_REQUEST['triggerid']));
	
		$triggerid		= $_REQUEST['triggerid'];
		$drawtype	= $db_link["drawtype"];
		$color		= $db_link["color"];
	
	}
	else{
		$triggerid	= get_request("triggerid",	0);
		$drawtype	= get_request("drawtype",	0);
		$color		= get_request("color",		0);
	}
	$frmCnct->AddVar("triggerid",$triggerid);
	
	/* START comboboxes preparations */
	
	$cmbType = new CComboBox("drawtype",$drawtype);
	
	foreach(map_link_drawtypes() as $i){
		$value = map_link_drawtype2str($i);
		$cmbType->AddItem($i, $value);
	}
	
	$btnSelect = new CButton('btn1',S_SELECT,
				"return PopUp('popup.php?dstfrm=".$frmCnct->GetName().
				"&dstfld1=triggerid&dstfld2=trigger&srctbl=triggers&srcfld1=triggerid&srcfld2=description');",
				'T');
	$btnSelect->SetType('button');
	/* END preparation */
	$description = ($triggerid>0)?expand_trigger_description($triggerid):'';
	
	$frmCnct->AddRow(S_TRIGGER, 
					 array(new CTextBox('trigger',$description,70,'yes'),
							SPACE,
							$btnSelect
						));
	
	$frmCnct->AddRow(S_TYPE.' ('.S_PROBLEM_BIG.')',$cmbType);
	$frmCnct->AddRow(S_COLOR.' ('.S_PROBLEM_BIG.')', new CColor('color',$color));
	
	$frmCnct->AddItemToBottomRow(new CButton("save",(isset($_REQUEST['triggerid']))?S_SAVE:S_ADD));
	$frmCnct->AddItemToBottomRow(SPACE);
	$frmCnct->AddItemToBottomRow(new CButton("cancel",S_CANCEL,'javascript: window.close();'));
	
	$frmCnct->Show();
}

include_once "include/page_footer.php";
?>