<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once('include/triggers.inc.php');
	require_once('include/forms.inc.php');
	require_once('include/js.inc.php');

	$dstfrm		= get_request('dstfrm',		0);	// destination form

	$page['title'] = "S_MEDIA";
	$page['file'] = 'popup_media.php';

	define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');

	if($USER_DETAILS['alias'] == ZBX_GUEST_USER) {
		access_deny();
	}
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>		array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		NULL),

		'media'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	NULL,			NULL),
		'mediatypeid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({add})'),
		'sendto'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({add})'),
		'period'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({add})'),
		'active'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({add})'),

		'severity'=>	array(T_ZBX_INT, O_OPT,	NULL,	NOT_EMPTY,	NULL),
/* actions */
		'add'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);

	insert_js_function('add_media');

	if(isset($_REQUEST['add'])){
		if( !validate_period($_REQUEST['period']) ){
			error(S_INCORRECT_TIME_PERIOD);
		}
		else{
			$severity = 0;
			$_REQUEST['severity'] = get_request('severity',array());
			foreach($_REQUEST['severity'] as $id)
				$severity |= 1 << $id;

			echo '<script language="JavaScript" type="text/javascript"><!--
					add_media("'.$_REQUEST['dstfrm'].'",'.
								$_REQUEST['media'].','.
								zbx_jsvalue($_REQUEST['mediatypeid']).',"'.
								$_REQUEST['sendto'].'","'.
								$_REQUEST['period'].'",'.
								$_REQUEST['active'].','.
								$severity.');'."\n".
				'--></script>';
		}
	}

	echo SBR;

	if(isset($_REQUEST['media']) && !isset($_REQUEST['form_refresh'])){
		$rq_severity	= get_request('severity',63);

		$severity = array();
		for($i=0; $i<6; $i++){
			if($rq_severity & (1 << $i)) $severity[$i] = $i;
		}
	}
	else{
		$severity	= get_request('severity',array(0,1,2,3,4,5));
	}

	$media		= get_request('media',-1);
	$sendto		=  get_request('sendto','');
	$mediatypeid	= get_request('mediatypeid',0);
	$active		= get_request('active',0);
	$period		= get_request('period','1-7,00:00-23:59');


	$frmMedia = new CFormTable(S_NEW_MEDIA);
	$frmMedia->SetHelp('web.media.php');

	$frmMedia->addVar('media',$media);
	$frmMedia->addVar('dstfrm',$_REQUEST['dstfrm']);

	$cmbType = new CComboBox('mediatypeid',$mediatypeid);
	$sql = 'SELECT mediatypeid,description '.
			' FROM media_type'.
			' WHERE '.DBin_node('mediatypeid').
			' ORDER BY type';
	$types = DBselect($sql);
	while($type=DBfetch($types)){
		$cmbType->addItem(
				$type['mediatypeid'],
				get_node_name_by_elid($type['mediatypeid'], null, ': ').$type['description']
				);
	}
	$frmMedia->addRow(S_TYPE,$cmbType);

	$frmMedia->addRow(S_SEND_TO, new CTextBox('sendto',$sendto,20));
	$frmMedia->addRow(S_WHEN_ACTIVE, new CTextBox('period',$period,48));

	$frm_row = array();
	for($i=0; $i<=5; $i++){
		array_push($frm_row,
			array(
				new CCheckBox(
					'severity['.$i.']',
					str_in_array($i,$severity)?'yes':'no',
					null,		/* action */
					$i),		/* value */
				get_severity_description($i)
			),
			BR());
	}
	$frmMedia->addRow(S_USE_IF_SEVERITY,$frm_row);

	$cmbStat = new CComboBox('active',$active);
	$cmbStat->addItem(0,S_ENABLED);
	$cmbStat->addItem(1,S_DISABLED);
	$frmMedia->addRow(S_STATUS,$cmbStat);

	$frmMedia->addItemToBottomRow(new CButton('add', ($media > -1)?S_SAVE:S_ADD));
	$frmMedia->addItemToBottomRow(SPACE);
	$frmMedia->addItemToBottomRow(new CButtonCancel(null, 'close_window();'));
	$frmMedia->Show();


include_once('include/page_footer.php');
?>
