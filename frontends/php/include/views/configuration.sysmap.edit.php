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


require_once dirname(__FILE__).'/js/configuration.sysmap.edit.js.php';

$sysmapWidget = (new CWidget())->setTitle(_('Network maps'));

// create sysmap form
$sysmapForm = new CForm();
$sysmapForm->setName('map.edit.php');
$sysmapForm->addVar('form', getRequest('form', 1));

if (isset($this->data['sysmap']['sysmapid'])) {
	$sysmapForm->addVar('sysmapid', $this->data['sysmap']['sysmapid']);
}

// create sysmap form list
$sysmapList = new CFormList();

$nameTextBox = new CTextBox('name', $this->data['sysmap']['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->setAttribute('autofocus', 'autofocus');
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
$sysmapList->addRow(_('Automatic icon mapping'), [$iconMappingComboBox, SPACE, $iconMappingsLink]);

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
$sysmapList->addRow(_('Host group label type'), [$labelTypeHostgroupComboBox, BR(), $customLabelHostgroupTextArea]);

// append host to form list
$labelTypeHostComboBox = new CComboBox('label_type_host', $this->data['sysmap']['label_type_host'], null, $this->data['labelTypes']);
$customLabelHostTextArea = new CTextArea('label_string_host', $this->data['sysmap']['label_string_host']);
if ($this->data['sysmap']['label_type_host'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHostTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Host label type'), [$labelTypeHostComboBox, BR(), $customLabelHostTextArea]);

// append trigger to form list
$labelTypeTriggerComboBox = new CComboBox('label_type_trigger', $this->data['sysmap']['label_type_trigger'], null, $this->data['labelTypesLimited']);
$customLabelTriggerTextArea = new CTextArea('label_string_trigger', $this->data['sysmap']['label_string_trigger']);
if ($this->data['sysmap']['label_type_trigger'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelTriggerTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Trigger label type'), [$labelTypeTriggerComboBox, BR(), $customLabelTriggerTextArea]);

// append map to form list
$labelTypeMapComboBox = new CComboBox('label_type_map', $this->data['sysmap']['label_type_map'], null, $this->data['labelTypesLimited']);
$customLabelMapTextArea = new CTextArea('label_string_map', $this->data['sysmap']['label_string_map']);
if ($this->data['sysmap']['label_type_map'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelMapTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Map label type'), [$labelTypeMapComboBox, BR(), $customLabelMapTextArea]);

// append image to form list
$labelTypeImageComboBox = new CComboBox('label_type_image', $this->data['sysmap']['label_type_image'], null, $this->data['labelTypesImage']);
$customLabelImageTextArea = new CTextArea('label_string_image', $this->data['sysmap']['label_string_image']);
if ($this->data['sysmap']['label_type_image'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelImageTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Image label type'), [$labelTypeImageComboBox, BR(), $customLabelImageTextArea]);

// append icon label to form list
unset($this->data['labelTypes'][MAP_LABEL_TYPE_CUSTOM]);
$sysmapList->addRow(_('Icon label type'), new CComboBox('label_type', $this->data['sysmap']['label_type'], null, $this->data['labelTypes']));

// append icon label location to form list
$sysmapList->addRow(_('Icon label location'), new CComboBox('label_location', $data['sysmap']['label_location'], null,
	[
		0 => _('Bottom'),
		1 => _('Left'),
		2 => _('Right'),
		3 => _('Top')
	]
));

// append show unack to form list
$showUnackComboBox = new CComboBox('show_unack', $this->data['sysmap']['show_unack'], null, [
	EXTACK_OPTION_ALL => _('All'),
	EXTACK_OPTION_BOTH => _('Separated'),
	EXTACK_OPTION_UNACK => _('Unacknowledged only'),
]);
$showUnackComboBox->setEnabled($this->data['config']['event_ack_enable']);
if (!$this->data['config']['event_ack_enable']) {
	$showUnackComboBox->setAttribute('title', _('Acknowledging disabled'));
}
$sysmapList->addRow(_('Problem display'), $showUnackComboBox);

// append severity min to form list
$sysmapList->addRow(_('Minimum trigger severity'), new CSeverity(['name' => 'severity_min', 'value' => $this->data['sysmap']['severity_min']]));

// create url table
$urlTable = (new CTable(_('No URLs defined.')))->
	addClass('formElementTable')->
	setAttribute('style', 'min-width: 500px;')->
	setHeader([_('Name'), _('URL'), _('Element'), SPACE]);
if (empty($this->data['sysmap']['urls'])) {
	$this->data['sysmap']['urls'][] = ['name' => '', 'url' => '', 'elementtype' => 0];
}
$i = 0;
foreach ($this->data['sysmap']['urls'] as $url) {
	$urlLabel = new CTextBox('urls['.$i.'][name]', $url['name'], 32);
	$urlLink = new CTextBox('urls['.$i.'][url]', $url['url'], 32);
	$urlEtype = new CComboBox('urls['.$i.'][elementtype]', $url['elementtype'], null, sysmap_element_types());
	$removeButton = new CSpan(_('Remove'), ZBX_STYLE_LINK_ACTION.' link_menu');
	$removeButton->onClick('$("urlEntry_'.$i.'").remove();');

	$urlRow = new CRow([$urlLabel, $urlLink, $urlEtype, $removeButton]);
	$urlRow->setAttribute('id', 'urlEntry_'.$i);

	$urlTable->addRow($urlRow);
	$i++;
}

// append empty template row to url table
$templateUrlLabel = new CTextBox('urls[#{id}][name]', '', 32);
$templateUrlLabel->setAttribute('disabled', 'disabled');
$templateUrlLink = new CTextBox('urls[#{id}][url]', '', 32);
$templateUrlLink->setAttribute('disabled', 'disabled');
$templateUrlEtype = new CComboBox('urls[#{id}][elementtype]', null, null, sysmap_element_types());
$templateUrlEtype->setAttribute('disabled', 'disabled');
$templateRemoveButton = new CSpan(_('Remove'), ZBX_STYLE_LINK_ACTION.' link_menu');
$templateRemoveButton->onClick('$("entry_#{id}").remove();');
$templateUrlRow = new CRow([$templateUrlLabel, $templateUrlLink, $templateUrlEtype, $templateRemoveButton]);
$templateUrlRow->addStyle('display: none');
$templateUrlRow->setAttribute('id', 'urlEntryTpl');
$urlTable->addRow($templateUrlRow);

// append "add" button to url table
$addButton = (new CSpan(_('Add'), ZBX_STYLE_LINK_ACTION.' link_menu'))->
	onClick('cloneRow("urlEntryTpl", '.$i.')');
$addButtonColumn = (new CCol($addButton))->setColSpan(4);
$urlTable->addRow($addButtonColumn);

// append url table to form list
$sysmapList->addRow(_('URLs'), new CDiv($urlTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append sysmap to form
$sysmapTab = new CTabView();
$sysmapTab->addTab('sysmapTab', _('Map'), $sysmapList);

// append buttons to form
if (hasRequest('sysmapid') && getRequest('sysmapid') > 0) {
	$sysmapTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new	CButton('clone', _('Clone')),
			new CButtonDelete(_('Delete network map?'), url_param('form').url_param('sysmapid')),
			new CButtonCancel()
		]
	));
}
else {
	$sysmapTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$sysmapForm->addItem($sysmapTab);

// append form to widget
$sysmapWidget->addItem($sysmapForm);

return $sysmapWidget;
