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
?>
<?php
// include JS + templates
require_once('include/views/js/configuration.discovery.js.php');
?>
<?php
	$data = $data;

	$divTabs = new CTabView(array('remember' => 1));
	if(!isset($_REQUEST['form_refresh'])) $divTabs->setSelected(0);

	$formDsc = new CForm();
	$formDsc->setName('discovery.edit');

	$from_rfr = get_request('form_refresh', 0);
	$formDsc->addVar('form_refresh', $from_rfr+1);
	$formDsc->addVar('form', get_request('form', 1));

	if(isset($_REQUEST['druleid'])) $formDsc->addVar('druleid', $_REQUEST['druleid']);

// Name
	$discoveryList = new CFormList('actionlist');
	$discoveryList->addRow(_('Name'), new CTextBox('name', $data['name'], 60));

// Discovery by proxy
	$cmbProxy = new CComboBox('proxy_hostid', $data['proxy_hostid']);
	$cmbProxy->addItem(0, _('No proxy'));

	$proxies = API::Proxy()->get(array(
		'output' => API_OUTPUT_EXTEND
	));
	order_result($proxies, 'host');
	foreach($proxies as $proxy){
		$cmbProxy->addItem($proxy['proxyid'], $proxy['host']);
	}
	$discoveryList->addRow(_('Discovery by proxy'), $cmbProxy);

// IP range
	$discoveryList->addRow(_('IP range'), new CTextBox('iprange', $data['iprange'], 27));

// Delay (seconds)
	$discoveryList->addRow(_('Delay (seconds)'), new CNumericBox('delay', $data['delay'], 8));

// Checks
	$dcheckList = new CTable(null, 'formElementTable');
	$addDCheckBtn = new CButton('newCheck', _('New'), null, 'link_menu');

	$col = new CCol($addDCheckBtn);
	$col->setAttribute('colspan', 2);

	$buttonRow = new CRow($col);
	$buttonRow->setAttribute('id', 'dcheckListFooter');
	$dcheckList->addRow($buttonRow);

	// Add Discovery Checks
	foreach($data['dchecks'] as $id => $dcheck){
		$key = isset($dcheck['key_']) ? $dcheck['key_'] : '';
		$ports = isset($dcheck['ports']) ? $dcheck['ports'] : '';
		$data['dchecks'][$id]['name'] = discovery_check2str($dcheck['type'], $key, $ports);
	}
	order_result($data['dchecks'], 'name');

	$jsInsert = 'addPopupValues('.zbx_jsvalue(array_values($data['dchecks'])).');';

	$discoveryList->addRow(_('Checks'), new CDiv($dcheckList, 'objectgroup inlineblock border_dotted ui-corner-all', 'dcheckList'));
	// -------

// Device uniqueness criteria
	$cmbUniquenessCriteria = new CRadioButtonList('uniqueness_criteria', $data['uniqueness_criteria']);
	$cmbUniquenessCriteria->addValue(' '._('IP address'), -1);

	$discoveryList->addRow(_('Device uniqueness criteria'), new CDiv($cmbUniquenessCriteria, 'objectgroup inlineblock border_dotted ui-corner-all', 'uniqList'));

	$jsInsert .= 'jQuery("input:radio[name=uniqueness_criteria][value='.zbx_jsvalue($data['uniqueness_criteria']).']").attr("checked", "checked");';

// Status
	$cmbStatus = new CComboBox('status', $data['status']);
	$cmbStatus->addItems(discovery_status2str());
	$discoveryList->addRow(_('Status'), $cmbStatus);


	$divTabs->addTab('druleTab', _('Discovery rule'), $discoveryList);
	$formDsc->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if(isset($_REQUEST['druleid'])){
		$others[] = new CButton('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete discovery rule?'), url_param('form').url_param('druleid'));
	}
	$others[] = new CButtonCancel();

	$footer = makeFormFooter($main, $others);
	$formDsc->addItem($footer);

	zbx_add_post_js($jsInsert);

	return $formDsc;
?>
