<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/js/configuration.discovery.edit.js.php';

$discoveryWidget = new CWidget();
$discoveryWidget->addPageHeader(_('CONFIGURATION OF DISCOVERY RULE'));

// create form
$discoveryForm = new CForm();
$discoveryForm->setName('discoveryForm');
$discoveryForm->addVar('form', $this->data['form']);
$discoveryForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
if (!empty($this->data['druleid'])) {
	$discoveryForm->addVar('druleid', $this->data['druleid']);
}

// create form list
$discoveryFormList = new CFormList('discoveryFormList');
$nameTextBox = new CTextBox('name', $this->data['drule']['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$discoveryFormList->addRow(_('Name'), $nameTextBox);

// append proxy to form list
$proxyComboBox = new CComboBox('proxy_hostid', $this->data['drule']['proxy_hostid']);
$proxyComboBox->addItem(0, _('No proxy'));
foreach ($this->data['proxies'] as $proxy) {
	$proxyComboBox->addItem($proxy['proxyid'], $proxy['host']);
}
$discoveryFormList->addRow(_('Discovery by proxy'), $proxyComboBox);
$discoveryFormList->addRow(_('IP range'), new CTextBox('iprange', $this->data['drule']['iprange'], ZBX_TEXTBOX_SMALL_SIZE));
$discoveryFormList->addRow(_('Delay (in sec)'), new CNumericBox('delay', $this->data['drule']['delay'], 8));

// append checks to form list
$checkTable = new CTable(null, 'formElementTable');
$checkTable->addRow(new CRow(
	new CCol(
		new CButton('newCheck', _('New'), null, 'link_menu'),
		null,
		2
	),
	null,
	'dcheckListFooter'
));
$discoveryFormList->addRow(_('Checks'),
	new CDiv($checkTable, 'objectgroup inlineblock border_dotted ui-corner-all', 'dcheckList'));

// append uniqueness criteria to form list
$uniquenessCriteriaRadio = new CRadioButtonList('uniqueness_criteria', $this->data['drule']['uniqueness_criteria']);
$uniquenessCriteriaRadio->addValue(' '._('IP address'), -1);
$discoveryFormList->addRow(_('Device uniqueness criteria'),
	new CDiv($uniquenessCriteriaRadio, 'objectgroup inlineblock border_dotted ui-corner-all', 'uniqList'));

// append status to form list
$discoveryFormList->addRow(_('Enabled'),
	new CCheckBox('status', !empty($this->data['druleid']) ? ($this->data['drule']['status'] == 0 ? 'yes' : 'no') : 'yes', null, 1));

// append tabs to form
$discoveryTabs = new CTabView();
$discoveryTabs->addTab('druleTab', _('Discovery rule'), $discoveryFormList);
$discoveryForm->addItem($discoveryTabs);

// append buttons to form
$deleteButton = new CButtonDelete(_('Delete discovery rule?'), url_param('form').url_param('druleid'));
if (empty($this->data['druleid'])) {
	$deleteButton->setAttribute('disabled', 'disabled');
}
$discoveryForm->addItem(makeFormFooter(
	array(new CSubmit('save', _('Save'))),
	array(
		new CSubmit('clone', _('Clone')),
		$deleteButton,
		new CButtonCancel()
	)
));

$discoveryWidget->addItem($discoveryForm);

return $discoveryWidget;
