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


$auditWidget = new CWidget();

// header
$configForm = new CForm('get');
$configComboBox = new CComboBox('config', 'auditacts.php');
$configComboBox->setAttribute('onchange', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('auditlogs.php', _('Audit log'));
$configComboBox->addItem('auditacts.php', _('Action log'));
$configForm->addItem($configComboBox);
$auditWidget->addPageHeader(_('ACTION LOG'), $configForm);
$auditWidget->addHeader(_('Action log'));
$auditWidget->addHeaderRowNumber();

// create filter
$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterTable = new CTable('', 'filter filter-center');
$filterTable->addRow(array(array(
	bold(_('Recipient')),
	' ',
	new CTextBox('alias', $this->data['alias'], 20),
	new CButton('btn1', _('Select'), 'return PopUp("popup.php?dstfrm='.$filterForm->getName().
		'&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1");',
		'button-form'
	)
)));

$filterButton = new CSubmit('filter_set', _('Filter'), null, 'jqueryinput shadow');
$filterButton->main();

$resetButton = new CSubmit('filter_rst', _('Reset'), null, 'jqueryinput shadow');

$buttonsDiv = new CDiv(array($filterButton, $resetButton));
$buttonsDiv->setAttribute('style', 'padding: 4px 0px;');

$filterTable->addRow(new CCol($buttonsDiv, 'controls'));
$filterForm->addItem($filterTable);

$auditWidget->addFlicker($filterForm, CProfile::get('web.auditacts.filter.state', 1));
$auditWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.auditacts.filter.state', 1));

// create form
$auditForm = new CForm('get');
$auditForm->setName('auditForm');

// create table
$auditTable = new CTableInfo(_('No action log entries found.'));
$auditTable->setHeader(array(
	_('Time'),
	_('Action'),
	_('Type'),
	_('Recipient(s)'),
	_('Message'),
	_('Status'),
	_('Info')
));

foreach ($this->data['alerts'] as $alert) {
	$mediatype = array_pop($alert['mediatypes']);

	if ($alert['status'] == ALERT_STATUS_SENT) {
		$status = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
			? new CSpan(_('Sent'), 'green')
			: new CSpan(_('Executed'), 'green');
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
		$status = new CSpan(array(
			_('In progress').':',
			BR(),
			_n('%1$s retry left', '%1$s retries left', ALERT_MAX_RETRIES - $alert['retries']),
		), 'orange');
	}
	else {
		$status = new CSpan(_('Not sent'), 'red');
	}

	$message = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
		? array(
			bold(_('Subject').':'),
			BR(),
			$alert['subject'],
			BR(),
			BR(),
			bold(_('Message').':'),
			BR(),
			zbx_nl2br($alert['message'])
		)
		: array(
			bold(_('Command').':'),
			BR(),
			zbx_nl2br($alert['message'])
		);

	if (zbx_empty($alert['error'])) {
		$info = '';
	}
	else {
		$info = new CDiv(SPACE, 'status_icon iconerror');
		$info->setHint($alert['error'], 'on');
	}

	$recipient = (isset($alert['userid']) && $alert['userid'])
		? array(bold(getUserFullname($this->data['users'][$alert['userid']])), BR(), $alert['sendto'])
		: $alert['sendto'];

	$auditTable->addRow(array(
		new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']), 'top'),
		new CCol($this->data['actions'][$alert['actionid']]['name'], 'top'),
		new CCol(($mediatype) ? $mediatype['description'] : '-', 'top'),
		new CCol($recipient, 'top'),
		new CCol($message, 'wraptext top'),
		new CCol($status, 'top'),
		new CCol($info, 'top')
	));
}

// append table to form
$auditForm->addItem(array($this->data['paging'], $auditTable, $this->data['paging']));

// append navigation bar js
$objData = array(
	'id' => 'timeline_1',
	'domid' => 'events',
	'loadSBox' => 0,
	'loadImage' => 0,
	'loadScroll' => 1,
	'dynamic' => 0,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.auditacts.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);
zbx_add_post_js('timeControl.addObject("events", '.zbx_jsvalue($data['timeline']).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$auditWidget->addItem($auditForm);

return $auditWidget;
