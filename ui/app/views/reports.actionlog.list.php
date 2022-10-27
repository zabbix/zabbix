<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */


$this->addJsFile('gtlc.js');
$this->addJsFile('class.calendar.js');

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', $data['action']));

$widget = (new CWidget())
	->setTitle(_('Action log'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_ACTIONLOG_LIST))
	->addItem($filter
		->addVar('action', $data['action'])
		->setProfile($data['timeline']['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($data['timeline']['from'], $data['timeline']['to'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow(new CLabel(_('Recipients'), 'filter_userids__ms'), [
					(new CMultiSelect([
						'name' => 'filter_userids[]',
						'object_name' => 'users',
						'data' => $data['userids'],
						'placeholder' => '',
						'popup' => [
							'parameters' => [
								'srctbl' => 'users',
								'srcfld1' => 'userid',
								'srcfld2' => 'fullname',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_userids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				])
		])
	);

$table = (new CTableInfo())
	->setHeader([
		_('Time'),
		_('Action'),
		_('Type'),
		_('Recipient'),
		_('Message'),
		_('Status'),
		_('Info')
	]);

foreach ($this->data['alerts'] as $alert) {
	$mediatype = array_pop($alert['mediatypes']);

	if ($alert['status'] == ALERT_STATUS_SENT) {
		$status = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
			? (new CSpan(_('Sent')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Executed')))->addClass(ZBX_STYLE_GREEN);
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW) {
		$status = (new CSpan([
			_('In progress').':',
			BR(),
			_n('%1$s retry left', '%1$s retries left', $mediatype['maxattempts'] - $alert['retries'])
		]))->addClass(ZBX_STYLE_YELLOW);
	}
	else {
		$status = (new CSpan(_('Failed')))->addClass(ZBX_STYLE_RED);
	}

	$message = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
		? [
			bold(_('Subject').':'),
			BR(),
			(new CDiv($alert['subject']))->addClass(ZBX_STYLE_WORDBREAK),
			BR(),
			BR(),
			bold(_('Message').':'),
			BR(),
			(new CDiv(zbx_nl2br($alert['message'])))->addClass(ZBX_STYLE_WORDBREAK)
		]
		: [
			bold(_('Command').':'),
			BR(),
			(new CDiv(zbx_nl2br($alert['message'])))->addClass(ZBX_STYLE_WORDBREAK)
		];

	$info_icons = [];
	if ($alert['error'] !== '') {
		$info_icons[] = makeErrorIcon($alert['error']);
	}

	$recipient = (isset($alert['userid']) && $alert['userid'])
		? makeEventDetailsTableUser($alert + ['action_type' => ZBX_EVENT_HISTORY_ALERT], $data['users'])
		: zbx_nl2br($alert['sendto']);

	$table->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
		$this->data['actions'][$alert['actionid']]['name'],
		($mediatype) ? $mediatype['name'] : '',
		$recipient,
		$message,
		$status,
		makeInformationList($info_icons)
	]);
}

$obj = [
	'id' => 'timeline_1',
	'domid' => 'events',
	'loadSBox' => 0,
	'loadImage' => 0,
	'dynamic' => 0
];

(new CScriptTag('timeControl.addObject("actionlog", '.json_encode($data['timeline']).', '.json_encode($obj).');'.
	'timeControl.processObjects();')
)->show();

$widget
	->addItem(
		(new CForm('get'))
			->setName('auditForm')
			->addItem([$table, $data['paging']])
	)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
