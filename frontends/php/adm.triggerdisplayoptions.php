<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

$page['title'] = _('Configuration of trigger displaying options');
$page['file'] = 'adm.triggerdisplayoptions.php';

require_once dirname(__FILE__).'/include/page_header.php';

$fields = [
	'custom_color' =>			[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Use custom event status colors')],
	'problem_unack_color' =>	[T_ZBX_CLR, O_OPT, null, null, 'isset({update}) && isset({custom_color})',
		_('Unacknowledged PROBLEM events')
	],
	'problem_ack_color' =>		[T_ZBX_CLR, O_OPT, null, null, 'isset({update}) && isset({custom_color})',
		_('Acknowledged PROBLEM events')
	],
	'ok_unack_color' =>			[T_ZBX_CLR, O_OPT, null, null, 'isset({update}) && isset({custom_color})',
		_('Unacknowledged OK events')
	],
	'ok_ack_color' =>			[T_ZBX_CLR, O_OPT, null, null, 'isset({update}) && isset({custom_color})',
		_('Acknowledged OK events')
	],
	'problem_unack_style' =>	[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')],
	'problem_ack_style' =>		[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')],
	'ok_unack_style' =>			[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')],
	'ok_ack_style' =>			[T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')],
	'ok_period' =>				[T_ZBX_STR, O_OPT, null, null, 'isset({update})', _('Display OK triggers for')],
	'blink_period' =>			[T_ZBX_STR, O_OPT, null, null, 'isset({update})',
		_('On status change triggers blink for')
	],
	// actions
	'update'=>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null, null, null]
];
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	$update_values = [
		'custom_color' => getRequest('custom_color', 0),
		'problem_unack_style' => getRequest('problem_unack_style', 0),
		'problem_ack_style' => getRequest('problem_ack_style', 0),
		'ok_unack_style' => getRequest('ok_unack_style', 0),
		'ok_ack_style' => getRequest('ok_ack_style', 0),
		'ok_period' => getRequest('ok_period'),
		'blink_period' => getRequest('blink_period')
	];

	if ($update_values['custom_color'] == 1) {
		$update_values['problem_unack_color'] = getRequest('problem_unack_color');
		$update_values['problem_ack_color'] = getRequest('problem_ack_color');
		$update_values['ok_unack_color'] = getRequest('ok_unack_color');
		$update_values['ok_ack_color'] = getRequest('ok_ack_color');
	}

	DBstart();
	$result = update_config($update_values);
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$config = select_config();

// form has been submitted
if (hasRequest('form_refresh')) {
	$data = [
		'custom_color' => getRequest('custom_color', 0),
		'problem_unack_color' => getRequest('problem_unack_color', $config['problem_unack_color']),
		'problem_ack_color' => getRequest('problem_ack_color', $config['problem_ack_color']),
		'ok_unack_color' => getRequest('ok_unack_color', $config['ok_unack_color']),
		'ok_ack_color' => getRequest('ok_ack_color', $config['ok_ack_color']),
		'problem_unack_style' => getRequest('problem_unack_style', 0),
		'problem_ack_style' => getRequest('problem_ack_style', 0),
		'ok_unack_style' => getRequest('ok_unack_style', 0),
		'ok_ack_style' => getRequest('ok_ack_style', 0),
		'ok_period' => getRequest('ok_period', $config['ok_period']),
		'blink_period' => getRequest('blink_period', $config['blink_period'])
	];
}
else {
	$data = [
		'custom_color' => $config['custom_color'],
		'problem_unack_color' => $config['problem_unack_color'],
		'problem_ack_color' => $config['problem_ack_color'],
		'ok_unack_color' => $config['ok_unack_color'],
		'ok_ack_color' => $config['ok_ack_color'],
		'problem_unack_style' => $config['problem_unack_style'],
		'problem_ack_style' => $config['problem_ack_style'],
		'ok_unack_style' => $config['ok_unack_style'],
		'ok_ack_style' => $config['ok_ack_style'],
		'ok_period' => $config['ok_period'],
		'blink_period' => $config['blink_period']
	];
}

$view = new CView('administration.general.trigger.options.edit', $data);
$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
