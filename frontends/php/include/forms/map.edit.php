<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// include JS + templates
//	include('include/templates/maps.js.php');
?>
<?php
	$config = select_config();

	$data = $data;
	$inputLength = 60;

	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh']))
		$divTabs->setSelected(0);

// Sysmap Form
	$frmSysmap = new CForm();
	$frmSysmap->setName('map.edit.php');
	$frmSysmap->addVar('form', get_request('form', 1));

	$formRefresh = get_request('form_refresh',0);
	$frmSysmap->addVar('form_refresh', $formRefresh+1);

// HOST WIDGET {
	$sysmapList = new CFormList('sysmaplist');

	if(isset($data['sysmapid'])) $frmSysmap->addVar('sysmapid', $data['sysmapid']);

	$sysmapList->addRow(_('Name'), new CTextBox('name', $data['name'], $inputLength));
	$sysmapList->addRow(_('Width'), new CNumericBox('width', $data['width'], 5));
	$sysmapList->addRow(_('Height'), new CNumericBox('height', $data['height'], 5));

	$cmbImg = new CComboBox('backgroundid', $data['backgroundid']);
	$cmbImg->addItem(0, _('No image'));

	$images = CImage::get(array(
		'filter' => array('imagetype' => 2),
		'output' => API_OUTPUT_EXTEND,
	));
	order_result($images, 'name');
	foreach($images as $image){
		$cmbImg->addItem(
			$image['imageid'],
			get_node_name_by_elid($image['imageid'], null, ': ').$image['name']
		);
	}

	$sysmapList->addRow(_('Background image'), $cmbImg);
	$sysmapList->addRow(_('Icon highlight'), new CCheckBox('highlight', $data['highlight'], null, 1));
	$sysmapList->addRow(_('Mark elements on trigger status change'), new CCheckBox('markelements', $data['markelements'], null, 1));
	$sysmapList->addRow(_('Expand single problem'), new CCheckBox('expandproblem', $data['expandproblem'], null, 1));


	$cmbLabel = new CComboBox('label_type', $data['label_type']);
	$cmbLabel->addItems(array(
		0 => _('Label'),
		1 => _('IP address'),
		2 => _('Element name'),
		3 => _('Status only'),
		4 => _('Nothing')
	));
	$sysmapList->addRow(_('Icon label type'), $cmbLabel);

	$cmbLocation = new CComboBox('label_location', $data['label_location']);
	$cmbLocation->addItems(array(0=> _('Bottom'),1=> _('Left'),2=> _('Right'),3=> _('Top')));

	$sysmapList->addRow(_('Icon label location'), $cmbLocation);

	$selectShowUnack = new CComboBox('show_unack', $data['show_unack']);
	$selectShowUnack->addItems(array(
		EXTACK_OPTION_ALL => _('All'),
		EXTACK_OPTION_BOTH => _('Separated'),
		EXTACK_OPTION_UNACK => _('Unacknowledged only'),
	));
	$selectShowUnack->setEnabled($config['event_ack_enable']);
	if(!$config['event_ack_enable']){
		$selectShowUnack->setAttribute('title', _('Acknowledging disabled'));
	}
	$sysmapList->addRow(_('Problem display'), $selectShowUnack);

	$url_table = new Ctable();
	$url_table->setHeader(array(_('Name'), _('URL'), _('Element'), SPACE));

	if(empty($data['urls'])){
		$data['urls'][] = array('name' => '', 'url' => '', 'elementtype' => 0);
	}
	$i = 0;
	foreach($data['urls'] as $url){
		$url_label = new CTextBox('urls['.$i.'][name]', $url['name'], 32);
		$url_link = new CTextBox('urls['.$i.'][url]', $url['url'], 32);

		$url_etype = new CCombobox('urls['.$i.'][elementtype]', $url['elementtype']);
		$url_etype->addItems(sysmap_element_types());
		$rem_button = new CSpan(_('Remove'), 'link_menu');
		$rem_button->addAction('onclick', '$("urlEntry_'.$i.'").remove();');

		$urlRow = new CRow(array($url_label, $url_link, $url_etype, $rem_button));
		$urlRow->setAttribute('id', 'urlEntry_'.$i.'');

		$url_table->addRow($urlRow);
		$i++;
	}

// empty template row {{{
	$tpl_url_label = new CTextBox('urls[#{id}][name]', '', 32);
	$tpl_url_label->setAttribute('disabled', 'disabled');
	$tpl_url_link = new CTextBox('urls[#{id}][url]', '', 32);
	$tpl_url_link->setAttribute('disabled', 'disabled');
	$tpl_url_etype = new CCombobox('urls[#{id}][elementtype]');
	$tpl_url_etype->setAttribute('disabled', 'disabled');
	$tpl_url_etype->addItems(sysmap_element_types());
	$tpl_rem_button = new CSpan(_('Remove'), 'link_menu');
	$tpl_rem_button->addAction('onclick', '$("entry_#{id}").remove();');

	$tpl_urlRow = new CRow(array($tpl_url_label, $tpl_url_link, $tpl_url_etype, $tpl_rem_button));
	$tpl_urlRow->addStyle('display: none');
	$tpl_urlRow->setAttribute('id', 'urlEntryTpl');
	$url_table->addRow($tpl_urlRow);
// }}} empty template row

	$add_button = new CSpan(_('Add'), 'link_menu');
	$add_button->addAction('onclick', 'cloneRow("urlEntryTpl", '.$i.')');
	$add_button_col = new CCol($add_button);
	$add_button_col->setColSpan(4);
	$url_table->addRow($add_button_col);

	$sysmapList->addRow(_('Links'), $url_table);

	$divTabs->addTab('sysmapTab', _('Map'), $sysmapList);
// }


	$frmSysmap->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if($_REQUEST['sysmapid']>0){
		$others[] = new CSubmit('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete system map?'), url_param('form').url_param('sysmapid'));
	}
	$others[] = new CButtonCancel();

	$frmSysmap->addItem(makeFormFooter($main, $others));

return $frmSysmap;
?>
