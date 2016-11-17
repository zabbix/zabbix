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


require_once dirname(__FILE__).'/js/configuration.sysmap.edit.js.php';

$sysmapWidget = new CWidget();
$sysmapWidget->addPageHeader(_('CONFIGURATION OF NETWORK MAPS'));

// create sysmap form
$sysmapForm = new CForm();
$sysmapForm->setName('map.edit.php');
$sysmapForm->addVar('form', get_request('form', 1));
$sysmapForm->addVar('form_refresh', get_request('form_refresh', 0) + 1);
if (isset($this->data['sysmap']['sysmapid'])) {
	$sysmapForm->addVar('sysmapid', $this->data['sysmap']['sysmapid']);
}

// create sysmap form list
$sysmapList = new CFormList('sysmaplist');

$nameTextBox = new CTextBox('name', $this->data['sysmap']['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$sysmapList->addRow(_('Name'), $nameTextBox);
$sysmapList->addRow(_('Width'), new CNumericBox('width', $this->data['sysmap']['width'], 5));
$sysmapList->addRow(_('Height'), new CNumericBox('height', $this->data['sysmap']['height'], 5));

// append background image to form list
$imageComboBox = new CComboBox('backgroundid', $this->data['sysmap']['backgroundid']);
$imageComboBox->addItem(0, _('No image'));
foreach ($this->data['images'] as $image) {
	$imageComboBox->addItem($image['imageid'], $image['name']);
}
$sysmapList->addRow(_('Background image'), $imageComboBox);

// append iconmapping to form list
$iconMappingComboBox = new CComboBox('iconmapid', $this->data['sysmap']['iconmapid']);
$iconMappingComboBox->addItem(0, _('<manual>'));
foreach ($this->data['iconMaps'] as $iconMap) {
	$iconMappingComboBox->addItem($iconMap['iconmapid'], $iconMap['name']);
}
$iconMappingsLink = new CLink(_('show icon mappings'), 'adm.iconmapping.php');
$iconMappingsLink->setAttribute('target', '_blank');
$sysmapList->addRow(_('Automatic icon mapping'), array($iconMappingComboBox, SPACE, $iconMappingsLink));

// append multiple checkboxes to form list
$sysmapList->addRow(_('Icon highlight'), new CCheckBox('highlight', $this->data['sysmap']['highlight'], null, 1));
$sysmapList->addRow(_('Mark elements on trigger status change'), new CCheckBox('markelements', $this->data['sysmap']['markelements'], null, 1));
$sysmapList->addRow(_('Expand single problem'), new CCheckBox('expandproblem', $this->data['sysmap']['expandproblem'], null, 1));
$sysmapList->addRow(_('Advanced labels'), new CCheckBox('label_format', $this->data['sysmap']['label_format'], null, 1));

// append hostgroup to form list
$labelTypeHostgroupComboBox = new CComboBox('label_type_hostgroup', $this->data['sysmap']['label_type_hostgroup'], null, $this->data['labelTypesLimited']);
$customLabelHostgroupTextArea = new CTextArea('label_string_hostgroup', $this->data['sysmap']['label_string_hostgroup']);
if ($this->data['sysmap']['label_type_hostgroup'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHostgroupTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Host group label type'), array($labelTypeHostgroupComboBox, BR(), $customLabelHostgroupTextArea));

// append host to form list
$labelTypeHostComboBox = new CComboBox('label_type_host', $this->data['sysmap']['label_type_host'], null, $this->data['labelTypes']);
$customLabelHostTextArea = new CTextArea('label_string_host', $this->data['sysmap']['label_string_host']);
if ($this->data['sysmap']['label_type_host'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHostTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Host label type'), array($labelTypeHostComboBox, BR(), $customLabelHostTextArea));

// append trigger to form list
$labelTypeTriggerComboBox = new CComboBox('label_type_trigger', $this->data['sysmap']['label_type_trigger'], null, $this->data['labelTypesLimited']);
$customLabelTriggerTextArea = new CTextArea('label_string_trigger', $this->data['sysmap']['label_string_trigger']);
if ($this->data['sysmap']['label_type_trigger'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelTriggerTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Trigger label type'), array($labelTypeTriggerComboBox, BR(), $customLabelTriggerTextArea));

// append map to form list
$labelTypeMapComboBox = new CComboBox('label_type_map', $this->data['sysmap']['label_type_map'], null, $this->data['labelTypesLimited']);
$customLabelMapTextArea = new CTextArea('label_string_map', $this->data['sysmap']['label_string_map']);
if ($this->data['sysmap']['label_type_map'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelMapTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Map label type'), array($labelTypeMapComboBox, BR(), $customLabelMapTextArea));

// append image to form list
$labelTypeImageComboBox = new CComboBox('label_type_image', $this->data['sysmap']['label_type_image'], null, $this->data['labelTypesImage']);
$customLabelImageTextArea = new CTextArea('label_string_image', $this->data['sysmap']['label_string_image']);
if ($this->data['sysmap']['label_type_image'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelImageTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Image label type'), array($labelTypeImageComboBox, BR(), $customLabelImageTextArea));

// append icon label to form list
unset($this->data['labelTypes'][MAP_LABEL_TYPE_CUSTOM]);
$sysmapList->addRow(_('Icon label type'), new CComboBox('label_type', $this->data['sysmap']['label_type'], null, $this->data['labelTypes']));

// append icon label location to form list
$locationComboBox = new CComboBox('label_location', $this->data['sysmap']['label_location']);
$locationComboBox->addItems(array(0 => _('Bottom'), 1 => _('Left'), 2 => _('Right'), 3 => _('Top')));
$sysmapList->addRow(_('Icon label location'), $locationComboBox);

// append show unack to form list
$showUnackComboBox = new CComboBox('show_unack', $this->data['sysmap']['show_unack']);
$showUnackComboBox->addItems(array(
	EXTACK_OPTION_ALL => _('All'),
	EXTACK_OPTION_BOTH => _('Separated'),
	EXTACK_OPTION_UNACK => _('Unacknowledged only'),
));
$showUnackComboBox->setEnabled($this->data['config']['event_ack_enable']);
if (!$this->data['config']['event_ack_enable']) {
	$showUnackComboBox->setAttribute('title', _('Acknowledging disabled'));
}
$sysmapList->addRow(_('Problem display'), $showUnackComboBox);

// append severity min to form list
$sysmapList->addRow(_('Minimum trigger severity'), new CSeverity(array('name' => 'severity_min', 'value' => $this->data['sysmap']['severity_min'])));

// create url table
$urlTable = new CTable(_('No URLs defined.'), 'formElementTable');
$urlTable->setAttribute('style', 'min-width: 500px;');
$urlTable->setHeader(array(_('Name'), _('URL'), _('Element'), SPACE));
if (empty($this->data['sysmap']['urls'])) {
	$this->data['sysmap']['urls'][] = array('name' => '', 'url' => '', 'elementtype' => 0);
}
$i = 0;
foreach ($this->data['sysmap']['urls'] as $url) {
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
$templateUrlLabel = new CTextBox('urls[#{id}][name]', '', 32);
$templateUrlLabel->setAttribute('disabled', 'disabled');
$templateUrlLink = new CTextBox('urls[#{id}][url]', '', 32);
$templateUrlLink->setAttribute('disabled', 'disabled');
$templateUrlEtype = new CCombobox('urls[#{id}][elementtype]');
$templateUrlEtype->setAttribute('disabled', 'disabled');
$templateUrlEtype->addItems(sysmap_element_types());
$templateRemoveButton = new CSpan(_('Remove'), 'link_menu');
$templateRemoveButton->addAction('onclick', '$("entry_#{id}").remove();');
$templateUrlRow = new CRow(array($templateUrlLabel, $templateUrlLink, $templateUrlEtype, $templateRemoveButton));
$templateUrlRow->addStyle('display: none');
$templateUrlRow->setAttribute('id', 'urlEntryTpl');
$urlTable->addRow($templateUrlRow);

// append "add" button to url table
$addButton = new CSpan(_('Add'), 'link_menu');
$addButton->addAction('onclick', 'cloneRow("urlEntryTpl", '.$i.')');
$addButtonColumn = new CCol($addButton);
$addButtonColumn->setColSpan(4);
$urlTable->addRow($addButtonColumn);

// append url table to form list
$sysmapList->addRow(_('URLs'), new CDiv($urlTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append sysmap to form
$sysmapTab = new CTabView();
$sysmapTab->addTab('sysmapTab', _('Map'), $sysmapList);
$sysmapForm->addItem($sysmapTab);

// append buttons to form
$others = array();
if (isset($_REQUEST['sysmapid']) && $_REQUEST['sysmapid'] > 0) {
	$others[] = new CButton('clone', _('Clone'));
	$others[] = new CButtonDelete(_('Delete network map?'), url_param('form').url_param('sysmapid'));
}
$others[] = new CButtonCancel();

$sysmapForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), $others));

// append form to widget
$sysmapWidget->addItem($sysmapForm);

return $sysmapWidget;
