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


$auditWidget = new CWidget();

// header
$configForm = new CForm('get');
$configComboBox = new CComboBox('config', 'auditacts.php');
$configComboBox->setAttribute('onchange', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('auditlogs.php', _('Logs'));
$configComboBox->addItem('auditacts.php', _('Actions'));
$configForm->addItem($configComboBox);
$auditWidget->addPageHeader(_('AUDIT ACTIONS'), $configForm);
$auditWidget->addHeader(_('Actions'));
$auditWidget->addHeaderRowNumber();

// create filter
$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterTable = new CTable('', 'filter');
$filterTable->addRow(array(
	array(
		bold(_('Recipient')),
		SPACE,
		new CTextBox('alias', $this->data['alias'], 20),
		new CButton('btn1', _('Select'), 'return PopUp("popup.php?dstfrm='.$filterForm->getName().
			'&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1");', 'filter-select-button')
	)
));
$filterButton = new CButton('filter', _('Filter'), "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
$filterButton->useJQueryStyle('main');
$resetButton = new CButton('filter_rst', _('Reset'), 'javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst", 1); location.href = uri.getUrl();');
$resetButton->useJQueryStyle();
$buttonsDiv = new CDiv(array($filterButton, SPACE, $resetButton));
$buttonsDiv->setAttribute('style', 'padding: 4px 0px;');

$filterTable->addRow(new CCol($buttonsDiv, 'controls'));
$filterForm->addItem($filterTable);

$auditWidget->addFlicker($filterForm, CProfile::get('web.auditacts.filter.state', 1));
$auditWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.auditacts.filter.state', 1));

// create form
$auditForm = new CForm('get');
$auditForm->setName('auditForm');

// create table
$auditTable = new CTableInfo(_('No audit entries found.'));
$auditTable->setHeader(array(
	is_show_all_nodes() ? _('Nodes') : null,
	_('Time'),
	_('Type'),
	_('Status'),
	_('Retries left'),
	_('Recipient(s)'),
	_('Message'),
	_('Error')
));
foreach ($this->data['alerts'] as $alert) {
	$mediatype = array_pop($alert['mediatypes']);
	if ($mediatype['mediatypeid'] == 0) {
		$mediatype = array('description' => '');
	}

	if ($alert['status'] == ALERT_STATUS_SENT) {
		if ($alert['alerttype'] == ALERT_TYPE_MESSAGE) {
			$status = new CSpan(_('sent'), 'green');
		}
		else {
			$status = new CSpan(_('executed'), 'green');
		}
		$retries = new CSpan(SPACE, 'green');
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
		$status = new CSpan(_('In progress'), 'orange');
		$retries = new CSpan(ALERT_MAX_RETRIES - $alert['retries'], 'orange');
	}
	else {
		$status = new CSpan(_('not sent'), 'red');
		$retries = new CSpan(0, 'red');
	}

	$message = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
		? array(
			bold(_('Subject').NAME_DELIMITER),
			BR(),
			$alert['subject'],
			BR(),
			BR(),
			bold(_('Message').NAME_DELIMITER),
			BR(),
			zbx_nl2br($alert['message'])
		)
		: array(
			bold(_('Command').NAME_DELIMITER),
			BR(),
			zbx_nl2br($alert['message'])
		);

	$error = empty($alert['error']) ? new CSpan(SPACE, 'off') : new CSpan($alert['error'], 'on');

	$auditTable->addRow(array(
		get_node_name_by_elid($alert['alertid']),
		new CCol(zbx_date2str(_('d M Y H:i:s'), $alert['clock']), 'top'),
		new CCol($mediatype['description'], 'top'),
		new CCol($status, 'top'),
		new CCol($retries, 'top'),
		new CCol($alert['sendto'], 'top'),
		new CCol($message, 'wraptext top'),
		new CCol($error, 'wraptext top')
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
zbx_add_post_js('timeControl.addObject(\'events\', '.zbx_jsvalue($data['timeline']).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$auditWidget->addItem($auditForm);

return $auditWidget;
