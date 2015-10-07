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
?>
<?php
include('include/views/js/configuration.sysmap.edit.js.php');

$config = select_config();

// advanced labels
$labelTypes = sysmapElementLabel();
$labelTypesLimited = $labelTypes;
unset($labelTypesLimited[MAP_LABEL_TYPE_IP]);

$labelTypesImage = $labelTypesLimited;
unset($labelTypesImage[MAP_LABEL_TYPE_STATUS]);

// create sysmap form
$frmSysmap = new CForm();
$frmSysmap->setName('map.edit.php');
$frmSysmap->addVar('form', get_request('form', 1));
$frmSysmap->addVar('form_refresh', get_request('form_refresh', 0) + 1);
if (isset($this->data['sysmapid'])) {
	$frmSysmap->addVar('sysmapid', $this->data['sysmapid']);
}

// create sysmap
$sysmapList = new CFormList('sysmaplist');
$sysmapList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
$sysmapList->addRow(_('Width'), new CNumericBox('width', $this->data['width'], 5));
$sysmapList->addRow(_('Height'), new CNumericBox('height', $this->data['height'], 5));

// append background image
$cmbImg = new CComboBox('backgroundid', $this->data['backgroundid']);
$cmbImg->addItem(0, _('No image'));
$images = API::Image()->get(array(
	'filter' => array('imagetype' => 2),
	'output' => API_OUTPUT_EXTEND,
));
order_result($images, 'name');
foreach ($images as $image) {
	$cmbImg->addItem(
		$image['imageid'],
		get_node_name_by_elid($image['imageid'], null, ': ').$image['name']
	);
}
$sysmapList->addRow(_('Background image'), $cmbImg);

// append iconmapping to sysmap
$iconMappingComboBox = new CComboBox('iconmapid', $this->data['iconmapid']);
$iconMappingComboBox->addItem(0, _('<manual>'));
$iconMaps = API::IconMap()->get(array(
	'output' => array('iconmapid', 'name'),
	'preservekeys' => true
));
order_result($iconMaps, 'name');
foreach ($iconMaps as $iconMap) {
	$iconMappingComboBox->addItem($iconMap['iconmapid'], $iconMap['name']);
}
$iconMappingsLink = new CLink(_('show icon mappings'), 'adm.iconmapping.php');
$iconMappingsLink->setAttribute('target', '_blank');
$sysmapList->addRow(_('Automatic icon mapping'), array($iconMappingComboBox, SPACE, $iconMappingsLink));

// append multiple checkboxs
$sysmapList->addRow(_('Icon highlight'), new CCheckBox('highlight', $this->data['highlight'], null, 1));
$sysmapList->addRow(_('Mark elements on trigger status change'), new CCheckBox('markelements', $this->data['markelements'], null, 1));
$sysmapList->addRow(_('Expand single problem'), new CCheckBox('expandproblem', $this->data['expandproblem'], null, 1));
$sysmapList->addRow(_('Advanced labels'), new CCheckBox('label_format', $this->data['label_format'], null, 1));

// append hostgroup to sysmap
$labelTypeHostgroup = new CComboBox('label_type_hostgroup', $this->data['label_type_hostgroup'], null, $labelTypesLimited);
$customLabelHostgroup = new CTextarea('label_string_hostgroup', $this->data['label_string_hostgroup']);
if ($this->data['label_type_hostgroup'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHostgroup->addClass('hidden');
}
$sysmapList->addRow(_('Host group label type'), array($labelTypeHostgroup, BR(), $customLabelHostgroup));

// append host to sysmap
$labelTypeHost = new CComboBox('label_type_host', $this->data['label_type_host'], null, $labelTypes);
$customLabelHost = new CTextarea('label_string_host', $this->data['label_string_host']);
if ($this->data['label_type_host'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHost->addClass('hidden');
}
$sysmapList->addRow(_('Host label type'), array($labelTypeHost, BR(), $customLabelHost));

// append trigger to sysmap
$labelTypeTrigger = new CComboBox('label_type_trigger', $this->data['label_type_trigger'], null, $labelTypesLimited);
$customLabelTrigger = new CTextarea('label_string_trigger', $this->data['label_string_trigger']);
if ($this->data['label_type_trigger'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelTrigger->addClass('hidden');
}
$sysmapList->addRow(_('Trigger label type'), array($labelTypeTrigger, BR(), $customLabelTrigger));

// append map to sysmap
$labelTypeMap = new CComboBox('label_type_map', $this->data['label_type_map'], null, $labelTypesLimited);
$customLabelMap = new CTextarea('label_string_map', $this->data['label_string_map']);
if ($this->data['label_type_map'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelMap->addClass('hidden');
}
$sysmapList->addRow(_('Map label type'), array($labelTypeMap, BR(), $customLabelMap));

// append image to sysmap
$labelTypeImage = new CComboBox('label_type_image', $this->data['label_type_image'], null, $labelTypesImage);
$customLabelImage = new CTextarea('label_string_image', $this->data['label_string_image']);
if ($this->data['label_type_image'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelImage->addClass('hidden');
}
$sysmapList->addRow(_('Image label type'), array($labelTypeImage, BR(), $customLabelImage));

// append icon label to sysmap
unset($labelTypes[MAP_LABEL_TYPE_CUSTOM]);
$sysmapList->addRow(_('Icon label type'), new CComboBox('label_type', $this->data['label_type'], null, $labelTypes));

// append icon label location to sysmap
$cmbLocation = new CComboBox('label_location', $this->data['label_location']);
$cmbLocation->addItems(array(0 => _('Bottom'), 1 => _('Left'), 2 => _('Right'), 3 => _('Top')));
$sysmapList->addRow(_('Icon label location'), $cmbLocation);

// append show unack to sysmap
$selectShowUnack = new CComboBox('show_unack', $this->data['show_unack']);
$selectShowUnack->addItems(array(
	EXTACK_OPTION_ALL => _('All'),
	EXTACK_OPTION_BOTH => _('Separated'),
	EXTACK_OPTION_UNACK => _('Unacknowledged only'),
));
$selectShowUnack->setEnabled($config['event_ack_enable']);
if (!$config['event_ack_enable']) {
	$selectShowUnack->setAttribute('title', _('Acknowledging disabled'));
}
$sysmapList->addRow(_('Problem display'), $selectShowUnack);

// create url table
$urlTable = new CTable(_('No URLs defined.'), 'formElementTable');
$urlTable->setAttribute('style', 'min-width: 500px;');
$urlTable->setHeader(array(_('Name'), _('URL'), _('Element'), SPACE));
if (empty($this->data['urls'])) {
	$this->data['urls'][] = array('name' => '', 'url' => '', 'elementtype' => 0);
}
$i = 0;
foreach ($this->data['urls'] as $url) {
	$urlLabel = new CTextBox('urls['.$i.'][name]', $url['name'], 32);
	$urlLink = new CTextBox('urls['.$i.'][url]', $url['url'], 32);
	$urlEtype = new CCombobox('urls['.$i.'][elementtype]', $url['elementtype']);
	$urlEtype->addItems(sysmap_element_types());
	$removeButton = new CSpan(_('Remove'), 'link_menu');
	$removeButton->addAction('onclick', '$("urlEntry_'.$i.'").remove();');

	$urlRow = new CRow(array($urlLabel, $urlLink, $urlEtype, $removeButton));
	$urlRow->setAttribute('id', 'urlEntry_'.$i);

	$urlTable->addRow($urlRow);
	$i++;
}

// append empty template row to url table
$tplUrlLabel = new CTextBox('urls[#{id}][name]', '', 32);
$tplUrlLabel->setAttribute('disabled', 'disabled');
$tplUrlLink = new CTextBox('urls[#{id}][url]', '', 32);
$tplUrlLink->setAttribute('disabled', 'disabled');
$tplUrlEtype = new CCombobox('urls[#{id}][elementtype]');
$tplUrlEtype->setAttribute('disabled', 'disabled');
$tplUrlEtype->addItems(sysmap_element_types());
$tplRemoveButton = new CSpan(_('Remove'), 'link_menu');
$tplRemoveButton->addAction('onclick', '$("entry_#{id}").remove();');
$tplUrlRow = new CRow(array($tplUrlLabel, $tplUrlLink, $tplUrlEtype, $tplRemoveButton));
$tplUrlRow->addStyle('display: none');
$tplUrlRow->setAttribute('id', 'urlEntryTpl');
$urlTable->addRow($tplUrlRow);

// append "add" button to url table
$addButton = new CSpan(_('Add'), 'link_menu');
$addButton->addAction('onclick', 'cloneRow("urlEntryTpl", '.$i.')');
$addButtonColumn = new CCol($addButton);
$addButtonColumn->setColSpan(4);
$urlTable->addRow($addButtonColumn);

// append url table to sysmap
$sysmapList->addRow(_('URLs'), new CDiv($urlTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append sysmap to form
$divTabs = new CTabView(array('remember' => 1));
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}
$divTabs->addTab('sysmapTab', _('Map'), $sysmapList);
$frmSysmap->addItem($divTabs);

// footer buttons
$main = array(new CSubmit('save', _('Save')));
$others = array();
if (isset($_REQUEST['sysmapid']) && ($_REQUEST['sysmapid'] > 0)) {
	$others[] = new CButton('clone', _('Clone'));
	$others[] = new CButtonDelete(_('Delete network map?'), url_param('form').url_param('sysmapid'));
}
$others[] = new CButtonCancel();
$frmSysmap->addItem(makeFormFooter($main, $others));

return $frmSysmap;
?>
