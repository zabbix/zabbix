<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
?>
<?php
	require_once dirname(__FILE__).'/include/config.inc.php';
	require_once dirname(__FILE__).'/include/js.inc.php';

	$dstfrm		= get_request('dstfrm',		0);	// destination form

	$page['title'] = _('Period');
	$page['file'] = 'popup_period.php';
	$page['scripts'] = array('class.calendar.js');

	define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>			array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,			null),
		'config'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1,2,3'),		NULL),

		'period_id'=>			array(T_ZBX_INT, O_OPT,  null,	null,			null),
		'caption'=>				array(T_ZBX_STR, O_OPT,  null,	null,			null),
		'report_timesince'=>	array(T_ZBX_STR, O_OPT,  null,	null,		'isset({save})'),
		'report_timetill'=>		array(T_ZBX_STR, O_OPT,  null,	null,		'isset({save})'),

		'color'=>				array(T_ZBX_CLR, O_OPT,  null,	null,		'isset({save})'),

/* actions */
		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_STR, O_OPT, null,	null,	null)
	);

	check_fields($fields);

	insert_js_function('add_period');
	insert_js_function('update_period');

	$_REQUEST['report_timesince'] = zbxDateToTime(get_request('report_timesince', date(TIMESTAMP_FORMAT, time() - SEC_PER_DAY)));
	$_REQUEST['report_timetill'] = zbxDateToTime(get_request('report_timetill', date(TIMESTAMP_FORMAT)));

	$_REQUEST['caption'] = get_request('caption','');
	if(zbx_empty($_REQUEST['caption']) && isset($_REQUEST['report_timesince']) && isset($_REQUEST['report_timetill'])){
		$_REQUEST['caption'] = zbx_date2str(POPUP_PERIOD_CAPTION_DATE_FORMAT,  $_REQUEST['report_timesince']).' - '.
								zbx_date2str(POPUP_PERIOD_CAPTION_DATE_FORMAT, $_REQUEST['report_timetill']);
	}

	if(isset($_REQUEST['save'])){
		if(isset($_REQUEST['period_id'])){
			insert_js("update_period('".
				$_REQUEST['period_id']."',".
				zbx_jsvalue($_REQUEST['dstfrm']).",".
				zbx_jsvalue($_REQUEST['caption']).",'".
				$_REQUEST['report_timesince']."','".
				$_REQUEST['report_timetill']."','".
				$_REQUEST['color']."');\n");
		}
		else{
			insert_js("add_period(".
				zbx_jsvalue($_REQUEST['dstfrm']).",".
				zbx_jsvalue($_REQUEST['caption']).",'".
				$_REQUEST['report_timesince']."','".
				$_REQUEST['report_timetill']."','".
				$_REQUEST['color']."');\n");
		}
	}
	else{
		echo SBR;

		$frmPd = new CFormTable(_('Period'));
		$frmPd->setName('period');

		$frmPd->addVar('dstfrm',$_REQUEST['dstfrm']);

		$config		= get_request('config',	 	1);

		$caption	= get_request('caption', 	'');
		$color		= get_request('color', 		'009900');

		$report_timesince = get_request('report_timesince', time() - SEC_PER_DAY);
		$report_timetill = get_request('report_timetill', time());

		$frmPd->addVar('config',$config);
		$frmPd->addVar('report_timesince', date(TIMESTAMP_FORMAT, $report_timesince));
		$frmPd->addVar('report_timetill', date(TIMESTAMP_FORMAT, $report_timetill));

		if(isset($_REQUEST['period_id']))
			$frmPd->addVar('period_id',$_REQUEST['period_id']);


		$frmPd->addRow(array( new CVisibilityBox('caption_visible', !zbx_empty($caption), 'caption', _('Default')),
			_('Caption')), new CTextBox('caption',$caption,42));

		$reporttimetab = new CTable(null, 'calendar');

		$timeSinceRow = createDateSelector('report_timesince', $report_timesince, 'report_timetill');
		array_unshift($timeSinceRow, _('From'));
		$reporttimetab->addRow($timeSinceRow);

		$timeTillRow = createDateSelector('report_timetill', $report_timetill, 'report_timesince');
		array_unshift($timeTillRow, _('Till'));
		$reporttimetab->addRow($timeTillRow);

		$frmPd->addRow(_('Period'), $reporttimetab);
//*/
		if($config != 1)
			$frmPd->addRow(_('Colour'), new CColor('color',$color));
		else
			$frmPd->addVar('color',$color);


		$frmPd->addItemToBottomRow(new CSubmit('save', isset($_REQUEST['period_id']) ? _('Update') : _('Add')));

		$frmPd->addItemToBottomRow(new CButtonCancel(null,'close_window();'));
		$frmPd->Show();
	}

require_once dirname(__FILE__).'/include/page_footer.php';
