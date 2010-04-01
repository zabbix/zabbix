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
include_once('include/config.inc.php');

if(isset($_REQUEST['download'])){
	$page['type'] = PAGE_TYPE_XML;
	$page['file'] = 'new_locale.inc.php';
}
else{
	$page['title'] = "S_LOCALES";
	$page['file'] = 'locales.php';
	$page['encoding'] = 'UTF-8';
	$page['hist_arg'] = array('');
}

if(!defined('ZBX_ALLOW_UNICODE')) define('ZBX_ALLOW_UNICODE',1);

include_once('include/page_header.php');

//---------------------------------- CHECKS ------------------------------------

//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
// action
		'action'=>				array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),
		'download'=>			array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,		null),

// form
		'next'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	null),
		'prev'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	null),
		'srclang'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({next})'),
		'extlang'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({next}) || isset({download})'),
		'fill'=>				array(T_ZBX_INT, O_OPT,  NULL,			IN(array(0,1,2)),	null),
		'langTo'=>				array(T_ZBX_STR, O_OPT,  NULL,			null,	'isset({download})'),

		'form'=>				array(T_ZBX_STR, O_OPT,  NULL,		  	IN('0,1'),	null)
	);

check_fields($fields);

if(isset($_REQUEST['action'])){

	if(isset($_REQUEST['download'])){
$output = '<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
	global $TRANSLATION;

	$TRANSLATION=array('."\n\n\t";


		foreach($_REQUEST['langTo'] as $key => $value){
			$value= addslashes($value);
			$output.= "'".zbx_strtoupper($key)."'=>\t\t\t'".$value."',\n\t";
		}

$output.='
	);
?>';
		print($output);
		die();
	}
}


if(isset($_REQUEST['make'])){
	show_table_header(S_LOCALES);

	$frmLcls = new CFormTable(S_CREATE.SPACE.S_LOCALE_SMALL,'locales.php','post',null,'form');

	if($_REQUEST['extlang'] == 'new'){
		define('S_NEW_LOCALE_STEP_1','Download newly created locale file by pressing "Download".');
		define('S_NEW_LOCALE_STEP_2','Place it to "/PATH_TO_ZABBIX_FRONTEND/include/locales".');
		define('S_NEW_LOCALE_STEP_3_1','The locale file name must be made of a prefix: "ISO 639-1 language code"_"ISO 3166-1 alpha-2 country code". Like "en_gb"');
		define('S_NEW_LOCALE_STEP_3_2','and a postfix ".inc.php". Like "en_gb.inc.php".');
		define('S_NEW_LOCALE_STEP_4_1','To make new locale visible by ZABBIX frontend - extend the php script "/PATH_TO_ZABBIX_FRONTEND/include/locales.inc.php".');
		define('S_NEW_LOCALE_STEP_4_2','ll find an array containing "keys" => "values".');
		define('S_NEW_LOCALE_STEP_4_3','Extend this array with your locale like "your_prefix" => "display_like".');
		define('S_NEW_LOCALE_STEP_4_4','You can set "display_like" value in your locale file by adding it to your locale file or you can leave it hardcoded string.');
		define('S_NEW_LOCALE_STEP_4_5','For example see implementation of other locales.');

		$frmLcls->addRow(S_STEP.SPACE.'1:',S_NEW_LOCALE_STEP_1);
		$frmLcls->addRow(S_STEP.SPACE.'2:',S_NEW_LOCALE_STEP_2);
		$frmLcls->addRow(S_STEP.SPACE.'3:',array(S_NEW_LOCALE_STEP_3_1,BR(),
												S_NEW_LOCALE_STEP_3_2));
		$frmLcls->addRow(S_STEP.SPACE.'4:',array(S_NEW_LOCALE_STEP_4_1,BR(),
												S_NEW_LOCALE_STEP_4_2,BR(),
												S_NEW_LOCALE_STEP_4_3,BR(),
												S_NEW_LOCALE_STEP_4_4,BR(),
												S_NEW_LOCALE_STEP_4_5));
	}
	else{

		$frmLcls->addRow(S_STEP.SPACE.'1:','Download newly created locale file by pressing "Download".');
		$frmLcls->addRow(S_STEP.SPACE.'2:','Place it to "/PATH_TO_ZABBIX_FRONTEND/include/locales".');
		$frmLcls->addRow(S_STEP.SPACE.'3:','Replace previous locale file with one you have downloaded.');
	}

	$lang = serialize($_REQUEST['langTo']);
	$frmLcls->addVar('lang',$lang);
	$frmLcls->addItemToBottomRow(new CButton('download',S_DOWNLOAD,'PopUp("locales")'));
	$frmLcls->Show();
}
else if(isset($_REQUEST['next'])){
	$help = new CHelp('web.view.php','left');
	$help_table = new CTableInfo();
	$help_table->setAttribute('style', 'width: 600px;');

	if($_REQUEST['extlang'] == 'new'){
		define('S_NEW_LOCALE_STEP_1','Download newly created locale file by pressing "Download".');
		define('S_NEW_LOCALE_STEP_2','Place it to "/PATH_TO_ZABBIX_FRONTEND/include/locales".');
		define('S_NEW_LOCALE_STEP_3_1','The locale file name must be made of a prefix: "ISO 639-1 language code"-"ISO 3166-1 alpha-2 country code". Like "en-gb"');
		define('S_NEW_LOCALE_STEP_3_2','and a postfix ".inc.php". Like "en-gb.inc.php".');
		define('S_NEW_LOCALE_STEP_4_1','To make new locale visible by ZABBIX frontend - extend the php script "/PATH_TO_ZABBIX_FRONTEND/include/locales.inc.php".');
		define('S_NEW_LOCALE_STEP_4_2','There You will find an array containing "keys" => "values".');
		define('S_NEW_LOCALE_STEP_4_3','Extend this array with Your locale like "your_prefix" => "display_like".');
		define('S_NEW_LOCALE_STEP_4_4','You can set "display_like" value in new locale file by adding it or You may leave it as hardcoded string.');
		define('S_NEW_LOCALE_STEP_4_5','For example see implementation of other locales.');

		$help_table->addRow(array(S_STEP.SPACE.'1:',S_NEW_LOCALE_STEP_1));
		$help_table->addRow(array(S_STEP.SPACE.'2:',S_NEW_LOCALE_STEP_2));
		$help_table->addRow(array(S_STEP.SPACE.'3:',array(S_NEW_LOCALE_STEP_3_1,BR(),
												S_NEW_LOCALE_STEP_3_2)));
		$help_table->addRow(array(S_STEP.SPACE.'4:',array(S_NEW_LOCALE_STEP_4_1,BR(),
												S_NEW_LOCALE_STEP_4_2,BR(),
												S_NEW_LOCALE_STEP_4_3,BR(),
												S_NEW_LOCALE_STEP_4_4,BR(),
												S_NEW_LOCALE_STEP_4_5)));
	}
	else{

		$help_table->addRow(array(S_STEP.SPACE.'1:','Download newly created locale file by pressing "Download".'));
		$help_table->addRow(array(S_STEP.SPACE.'2:','Place it to "/PATH_TO_ZABBIX_FRONTEND/include/locales".'));
		$help_table->addRow(array(S_STEP.SPACE.'3:','Replace previous locale file with one you have downloaded.'));
	}

	$help->SetHint($help_table);

	show_table_header(array($help,S_LOCALES));

	$frmLcls = new CFormTable(S_CREATE.SPACE.S_LOCALE_SMALL.SPACE.S_FROM_SMALL.SPACE.$ZBX_LOCALES[$_REQUEST['srclang']],'locales.php?action=1','post',null,'form');
	$frmLcls->setAttribute('id','locales');
	$frmLcls->SetHelp($help);

	$fileFrom = 'include/locales/'.$_REQUEST['srclang'].'.inc.php';
	if(preg_match('/^[a-z0-9_]+$/i', $_REQUEST['srclang']) && file_exists($fileFrom)){
		include($fileFrom);
		if(!isset($TRANSLATION) || !is_array($TRANSLATION)){
			error('Passed SOURCE is NOT valid PHP file.');
		}
		$transFrom = $TRANSLATION;
	}
	unset($TRANSLATION);

	$frmLcls->addVar('extlang',$_REQUEST['extlang']);
	if(preg_match('/^[a-z0-9_]+$/i', $_REQUEST['extlang']) && ($_REQUEST['extlang'] != 'new')){
		$fileTo = 'include/locales/'.$_REQUEST['extlang'].'.inc.php';
		if(file_exists($fileTo)){
			include($fileTo);

			if(!isset($TRANSLATION) || !is_array($TRANSLATION)){
				error('Passed DEST is NOT valid PHP file.');
			}
			$transTo = $TRANSLATION;
//			header('Content-Type: text/html; charset='.$TRANSLATION['S_HTML_CHARSET']);
		}
	}
	unset($TRANSLATION);

	$fill = get_request('fill',0);
	foreach($transFrom as $key => $value){
		if(isset($transTo[$key]) && !empty($transTo[$key])){
			$valueTo = $transTo[$key];
			unset($transTo[$key]);
		}
		else if(($_REQUEST['extlang'] != 'new') && ($fill == 0)){
			continue;
		}
		else if($fill == 1){
			$valueTo = '';
		}
		else{
			$valueTo=$value;
		}

		if(defined('ZBX_MBSTRINGS_ENABLED')){
			$value = mb_convert_encoding($value,'UTF-8',mb_detect_encoding($value));
			$valueTo = mb_convert_encoding($valueTo,'UTF-8',mb_detect_encoding($valueTo));
		}

		$frmLcls->addRow($value, new Ctextbox('langTo['.$key.']',$valueTo,80));
		$value='';
	}

	$frmLcls->addItemToBottomRow(new CButton('prev','<< '.S_PREVIOUS));
	$frmLcls->addItemToBottomRow(SPACE);

	$frmLcls->addItemToBottomRow(new CButton('download',S_DOWNLOAD));
	$frmLcls->Show();
}
else{
	show_table_header(S_LOCALES);
	echo SBR;

	$frmLcls = new CFormTable(S_CREATE.SPACE.S_LOCALE_SMALL,'locales.php','post',null,'form');
	$frmLcls->setAttribute('id','locales');

	$cmbLang = new CComboBox('srclang',get_request('srclang','en_gb'));
	foreach($ZBX_LOCALES as $id => $name){
		$cmbLang->addItem($id,$name);
	}
	$frmLcls->addRow(S_TAKE_DEF_LOCALE,$cmbLang);

	$cmbExtLang = new CComboBox('extlang',get_request('extlang','new'));
	$cmbExtLang->addItem('new',S_CREATE.SPACE.S_NEW_SMALL);
	foreach($ZBX_LOCALES as $id => $name){
		$cmbExtLang->addItem($id,$name);
	}
	$frmLcls->addRow(S_LOCALE_TO_EXTEND,$cmbExtLang);

	$cmbFill = new CComboBox('fill',get_request('fill',1));
		$cmbFill->addItem('0',S_DO_NOT_ADD);
		$cmbFill->addItem('1',S_LEAVE_EMPTY);
		$cmbFill->addItem('2',S_FILL_WITH_DEFAULT_VALUE);

	$frmLcls->addRow(S_NEW_ENTRIES, $cmbFill);

	$frmLcls->addItemToBottomRow(new CButton('next',S_NEXT.' >>'));
	$frmLcls->Show();
}


include_once "include/page_footer.php";
?>