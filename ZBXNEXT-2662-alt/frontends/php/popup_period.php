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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Period');
$page['file'] = 'popup_period.php';
$page['scripts'] = array('class.calendar.js');

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>			array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,			null),
		'config'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1,2,3'),		NULL),

		'period_id'=>			array(T_ZBX_INT, O_OPT,  null,	null,			null),
		'caption'=>				array(T_ZBX_STR, O_OPT,  null,	null,			null),
		'report_timesince'=>	array(T_ZBX_STR, O_OPT,  null,	null,		'isset({add}) || isset({update})'),
		'report_timetill'=>		array(T_ZBX_STR, O_OPT,  null,	null,		'isset({add}) || isset({update})'),

		'color'=>				array(T_ZBX_CLR, O_OPT,  null,	null,		'isset({add}) || isset({update})'),

/* actions */
		'add'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'update'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT, null,	null,	null)
	);

	check_fields($fields);

	insert_js_function('add_period');
	insert_js_function('update_period');

	$_REQUEST['report_timesince'] = zbxDateToTime(getRequest('report_timesince', date(TIMESTAMP_FORMAT_ZERO_TIME, time() - SEC_PER_DAY)));
	$_REQUEST['report_timetill'] = zbxDateToTime(getRequest('report_timetill', date(TIMESTAMP_FORMAT_ZERO_TIME)));

	$caption = getRequest('caption', '');
	$autoCaption = '';

	if (hasRequest('report_timesince') && hasRequest('report_timetill')) {
		$autoCaption = zbx_date2str(DATE_TIME_FORMAT, getRequest('report_timesince')).' - '.
			zbx_date2str(DATE_TIME_FORMAT, getRequest('report_timetill')
		);
	}

	if (zbx_empty($caption)) {
		$caption = $autoCaption;
	}

	if(hasRequest('add') || hasRequest('update')) {
		if(hasRequest('period_id')) {
			insert_js("update_period('".
				getRequest('period_id')."',".
				zbx_jsvalue(getRequest('dstfrm')).",".
				zbx_jsvalue($caption).",'".
				getRequest('report_timesince')."','".
				getRequest('report_timetill')."','".
				getRequest('color')."');\n");
		}
		else{
			insert_js("add_period(".
				zbx_jsvalue(getRequest('dstfrm')).",".
				zbx_jsvalue($caption).",'".
				getRequest('report_timesince')."','".
				getRequest('report_timetill')."','".
				getRequest('color')."');\n");
		}
	}
	else{
		echo BR();

		$frmPd = new CFormTable(_('Period'));
		$frmPd->setName('period');

		$frmPd->addVar('dstfrm',$_REQUEST['dstfrm']);

		$config		= getRequest('config',	 	1);

		$color		= getRequest('color', 		'009900');

		$report_timesince = getRequest('report_timesince', time() - SEC_PER_DAY);
		$report_timetill = getRequest('report_timetill', time());

		$frmPd->addVar('config',$config);
		$frmPd->addVar('report_timesince', date(TIMESTAMP_FORMAT_ZERO_TIME, $report_timesince));
		$frmPd->addVar('report_timetill', date(TIMESTAMP_FORMAT_ZERO_TIME, $report_timetill));

		if(isset($_REQUEST['period_id']))
			$frmPd->addVar('period_id',$_REQUEST['period_id']);


		$frmPd->addRow(
			array(
				new CVisibilityBox('caption_visible', hasRequest('caption') && $caption != $autoCaption, 'caption',
					_('Default')
				),
				_('Caption')
			),
			new CTextBox('caption', $caption, 42)
		);

		$reporttimetab = new CTable(null, 'calendar');

		$timeSinceRow = createDateSelector('report_timesince', $report_timesince, 'report_timetill');
		array_unshift($timeSinceRow, _('From'));
		$reporttimetab->addRow($timeSinceRow);

		$timeTillRow = createDateSelector('report_timetill', $report_timetill, 'report_timesince');
		array_unshift($timeTillRow, _('Till'));
		$reporttimetab->addRow($timeTillRow);

		$frmPd->addRow(_('Period'), $reporttimetab);

		if($config != 1)
			$frmPd->addRow(_('Colour'), new CColor('color',$color));
		else
			$frmPd->addVar('color',$color);


		if (hasRequest('period_id')) {
			$frmPd->addItemToBottomRow(new CSubmit('update', _('Update')));
		}
		else {
			$frmPd->addItemToBottomRow(new CSubmit('add', _('Add')));
		}

		$frmPd->addItemToBottomRow(new CButtonCancel(null,'close_window();'));
		$frmPd->Show();
	}

require_once dirname(__FILE__).'/include/page_footer.php';
