<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$auditWidget = (new CWidget())->setTitle(_('Action log'));

// create filter
$filterForm = new CFilter('web.auditacts.filter.state');

$filterColumn = new CFormList();
$filterColumn->addRow(_('Recipient'), [
	(new CTextBox('alias', $this->data['alias']))
		->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus'),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('btn1', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.php?dstfrm=zbx_filter&dstfld1=alias&srctbl=users&srcfld1=alias");')
]);

$filterForm->addColumn($filterColumn);
$filterForm->addNavigator();
$auditWidget->addItem($filterForm);

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
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
		$status = (new CSpan([
			_('In progress').':',
			BR(),
			_n('%1$s retry left', '%1$s retries left', ALERT_MAX_RETRIES - $alert['retries']),
		]))->addClass(ZBX_STYLE_YELLOW);
	}
	else {
		$status = (new CSpan(_('Not sent')))->addClass(ZBX_STYLE_RED);
	}

	$message = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
		? [
			bold(_('Subject').':'),
			BR(),
			$alert['subject'],
			BR(),
			BR(),
			bold(_('Message').':'),
			BR(),
			zbx_nl2br($alert['message'])
		]
		: [
			bold(_('Command').':'),
			BR(),
			zbx_nl2br($alert['message'])
		];

	if (zbx_empty($alert['error'])) {
		$info = '';
	}
	else {
		$info = makeErrorIcon($alert['error']);
	}

	$recipient = (isset($alert['userid']) && $alert['userid'])
		? [bold(getUserFullname($this->data['users'][$alert['userid']])), BR(), $alert['sendto']]
		: $alert['sendto'];

	$auditTable->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
		$this->data['actions'][$alert['actionid']]['name'],
		($mediatype) ? $mediatype['description'] : '',
		$recipient,
		$message,
		$status,
		$info
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
	'loadScroll' => 1,
	'dynamic' => 0,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.auditacts.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
];
zbx_add_post_js('timeControl.addObject("events", '.zbx_jsvalue($data['timeline']).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$auditWidget->addItem($auditForm);

return $auditWidget;
