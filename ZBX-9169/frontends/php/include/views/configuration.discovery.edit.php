<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


require_once dirname(__FILE__).'/js/configuration.discovery.edit.js.php';

$discoveryWidget = new CWidget();
$discoveryWidget->addPageHeader(_('CONFIGURATION OF DISCOVERY RULES'));

// create form
$discoveryForm = new CForm();
$discoveryForm->setName('discoveryForm');
$discoveryForm->addVar('form', $this->data['form']);
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
$uniquenessCriteriaRadio->addValue(SPACE._('IP address'), -1, true, zbx_formatDomId('uniqueness_criteria_ip'));
$discoveryFormList->addRow(_('Device uniqueness criteria'),
	new CDiv($uniquenessCriteriaRadio, 'objectgroup inlineblock border_dotted ui-corner-all', 'uniqList'));

// append status to form list
$status = (empty($this->data['druleid']) && empty($this->data['form_refresh']))
	? true
	: ($this->data['drule']['status'] == DRULE_STATUS_ACTIVE);

$discoveryFormList->addRow(_('Enabled'), new CCheckBox('status', $status, null, 1));

// append tabs to form
$discoveryTabs = new CTabView();
$discoveryTabs->addTab('druleTab', _('Discovery rule'), $discoveryFormList);
$discoveryForm->addItem($discoveryTabs);

// append buttons to form
if (isset($this->data['druleid']))
{
	$discoveryForm->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete discovery rule?'), url_param('form').url_param('druleid')),
			new CButtonCancel()
		)
	));
}
else {
	$discoveryForm->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		new CButtonCancel()
	));
}

$discoveryWidget->addItem($discoveryForm);

return $discoveryWidget;
