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
 */

$auditWidget = (new CWidget())
	->setTitle(_('Action log'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_AUDITACTS_LIST));

// create filter
$filterColumn = new CFormList();
$filterColumn->addRow(new CLabel(_('Recipients'), 'filter_userids__ms'), [
	(new CMultiSelect([
		'name' => 'filter_userids[]',
		'object_name' => 'users',
		'data' => $data['filter_userids'],
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
]);

$auditWidget->addItem(
	(new CFilter())
		->setResetUrl(new CUrl('auditacts.php'))
		->setProfile($data['timeline']['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addTimeSelector($data['timeline']['from'], $data['timeline']['to'])
		->addFilterTab(_('Filter'), [$filterColumn])
);

// create form
$auditForm = (new CForm('get'))->setName('auditForm');

// create table
$auditTable = (new CTableInfo())
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

	$auditTable->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
		$this->data['actions'][$alert['actionid']]['name'],
		($mediatype) ? $mediatype['name'] : '',
		$recipient,
		$message,
		$status,
		makeInformationList($info_icons)
	]);
}

// append table to form
$auditForm->addItem([$auditTable, $this->data['paging']]);

// append navigation bar js
$objData = [
	'id' => 'timeline_1',
	'domid' => 'events',
	'loadSBox' => 0,
	'loadImage' => 0,
	'dynamic' => 0
];
zbx_add_post_js('timeControl.addObject("events", '.zbx_jsvalue($data['timeline']).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$auditWidget->addItem($auditForm);

$auditWidget->show();
