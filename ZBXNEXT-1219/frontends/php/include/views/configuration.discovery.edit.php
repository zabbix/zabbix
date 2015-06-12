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


require_once dirname(__FILE__).'/js/configuration.discovery.edit.js.php';

$widget = (new CWidget())->setTitle(_('Discovery rules'));

// create form
$discoveryForm = new CForm();
$discoveryForm->setName('discoveryForm');
$discoveryForm->addVar('form', $this->data['form']);
if (!empty($this->data['druleid'])) {
	$discoveryForm->addVar('druleid', $this->data['druleid']);
}

// create form list
$discoveryFormList = new CFormList();
$nameTextBox = new CTextBox('name', $this->data['drule']['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->setAttribute('autofocus', 'autofocus');
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
$checkTable = (new CTable())->addClass('formElementTable');
$checkTable->addRow((
	new CRow(
		(new CCol(
			(new CButton('newCheck', _('New')))->addClass(ZBX_STYLE_BTN_LINK)
		))->setColSpan(2)
	)
)->setId('dcheckListFooter'));
$discoveryFormList->addRow(_('Checks'),
	(new CDiv($checkTable))
		->addClass('objectgroup')
		->addClass('inlineblock')
		->addClass('border_dotted')
		->setId('dcheckList')
);

// append uniqueness criteria to form list
$uniquenessCriteriaRadio = new CRadioButtonList('uniqueness_criteria', $this->data['drule']['uniqueness_criteria']);
$uniquenessCriteriaRadio->addValue(SPACE._('IP address'), -1, true, zbx_formatDomId('uniqueness_criteria_ip'));
$discoveryFormList->addRow(_('Device uniqueness criteria'),
	(new CDiv($uniquenessCriteriaRadio))
		->addClass('objectgroup')
		->addClass('inlineblock')
		->addClass('border_dotted')
		->setId('uniqList')
);

// append status to form list
$status = (empty($this->data['druleid']) && empty($this->data['form_refresh']))
	? true
	: ($this->data['drule']['status'] == DRULE_STATUS_ACTIVE);

$discoveryFormList->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked($status));

// append tabs to form
$discoveryTabs = new CTabView();
$discoveryTabs->addTab('druleTab', _('Discovery rule'), $discoveryFormList);

// append buttons to form
if (isset($this->data['druleid']))
{
	$discoveryTabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete discovery rule?'), url_param('form').url_param('druleid')),
			new CButtonCancel()
		]
	));
}
else {
	$discoveryTabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$discoveryForm->addItem($discoveryTabs);

$widget->addItem($discoveryForm);

return $widget;
