<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
$discoveryForm = (new CForm())
	->setName('discoveryForm')
	->addVar('form', $this->data['form']);
if (!empty($this->data['druleid'])) {
	$discoveryForm->addVar('druleid', $this->data['druleid']);
}

// create form list
$discoveryFormList = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['drule']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// append proxy to form list
$proxyComboBox = (new CComboBox('proxy_hostid', $this->data['drule']['proxy_hostid']))
	->addItem(0, _('No proxy'));
foreach ($this->data['proxies'] as $proxy) {
	$proxyComboBox->addItem($proxy['proxyid'], $proxy['host']);
}

$discoveryFormList
	->addRow(_('Discovery by proxy'), $proxyComboBox)
	->addRow(_('IP range'),
		(new CTextArea('iprange', $this->data['drule']['iprange'], ['maxlength' => 2048]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Delay (in sec)'),
		(new CNumericBox('delay', $this->data['drule']['delay'], 6))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

$discoveryFormList->addRow(_('Checks'),
	(new CDiv(
		(new CTable())
			->setAttribute('style', 'width: 100%;')
			->setFooter(
				(new CRow(
					(new CCol(
						(new CButton('newCheck', _('New')))->addClass(ZBX_STYLE_BTN_LINK)
					))->setColSpan(2)
				))->setId('dcheckListFooter')
			)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('dcheckList')
);

// append uniqueness criteria to form list
$discoveryFormList->addRow(_('Device uniqueness criteria'),
	(new CDiv(
		(new CRadioButtonList('uniqueness_criteria', (int) $this->data['drule']['uniqueness_criteria']))
			->makeVertical()
			->addValue(_('IP address'), -1, zbx_formatDomId('uniqueness_criteria_ip'))
	))
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);

// append status to form list
$status = (empty($this->data['druleid']) && empty($this->data['form_refresh']))
	? true
	: ($this->data['drule']['status'] == DRULE_STATUS_ACTIVE);

$discoveryFormList->addRow(_('Enabled'), (new CCheckBox('status'))->setChecked($status));

// append tabs to form
$discoveryTabs = (new CTabView())->addTab('druleTab', _('Discovery rule'), $discoveryFormList);

// append buttons to form
if (isset($this->data['druleid']))
{
	$discoveryTabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButton('clone', _('Clone')),
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
