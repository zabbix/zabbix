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

$widget = (new CWidget())->setTitle(_('Network maps'));

// create sysmap form
$sysmapForm = (new CForm())
	->setName('map.edit.php')
	->addVar('form', getRequest('form', 1));

if (isset($this->data['sysmap']['sysmapid'])) {
	$sysmapForm->addVar('sysmapid', $this->data['sysmap']['sysmapid']);
}

// create sysmap form list
$sysmapList = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['sysmap']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Width'),
		(new CNumericBox('width', $this->data['sysmap']['width'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Height'),
		(new CNumericBox('height', $this->data['sysmap']['height'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

// append background image to form list
$imageComboBox = (new CComboBox('backgroundid', $this->data['sysmap']['backgroundid']))
	->addItem(0, _('No image'));
foreach ($this->data['images'] as $image) {
	$imageComboBox->addItem($image['imageid'], $image['name']);
}
$sysmapList->addRow(_('Background image'), $imageComboBox);

// append iconmapping to form list
$iconMappingComboBox = (new CComboBox('iconmapid', $this->data['sysmap']['iconmapid']))
	->addItem(0, _('<manual>'));
foreach ($this->data['iconMaps'] as $iconMap) {
	$iconMappingComboBox->addItem($iconMap['iconmapid'], $iconMap['name']);
}
$iconMappingsLink = (new CLink(_('show icon mappings'), 'adm.iconmapping.php'))
	->setAttribute('target', '_blank');
$sysmapList->addRow(_('Automatic icon mapping'), [$iconMappingComboBox, SPACE, $iconMappingsLink]);

// append multiple checkboxes to form list
$sysmapList->addRow(_('Icon highlight'),
	(new CCheckBox('highlight'))->setChecked($this->data['sysmap']['highlight'] == 1)
);
$sysmapList->addRow(_('Mark elements on trigger status change'),
	(new CCheckBox('markelements'))->setChecked($this->data['sysmap']['markelements'] == 1)
);
$sysmapList->addRow(_('Expand single problem'),
	(new CCheckBox('expandproblem'))->setChecked($this->data['sysmap']['expandproblem'] == 1)
);
$sysmapList->addRow(_('Advanced labels'),
	(new CCheckBox('label_format'))->setChecked($this->data['sysmap']['label_format'] == 1)
);

// append hostgroup to form list
$labelTypeHostgroupComboBox = new CComboBox('label_type_hostgroup', $this->data['sysmap']['label_type_hostgroup'], null, $this->data['labelTypesLimited']);
$customLabelHostgroupTextArea =
	(new CTextArea('label_string_hostgroup', $this->data['sysmap']['label_string_hostgroup']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
if ($this->data['sysmap']['label_type_hostgroup'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHostgroupTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Host group label type'), [$labelTypeHostgroupComboBox, BR(), $customLabelHostgroupTextArea]);

// append host to form list
$labelTypeHostComboBox = new CComboBox('label_type_host', $this->data['sysmap']['label_type_host'], null, $this->data['labelTypes']);
$customLabelHostTextArea = (new CTextArea('label_string_host', $this->data['sysmap']['label_string_host']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
if ($this->data['sysmap']['label_type_host'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelHostTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Host label type'), [$labelTypeHostComboBox, BR(), $customLabelHostTextArea]);

// append trigger to form list
$labelTypeTriggerComboBox = new CComboBox('label_type_trigger', $this->data['sysmap']['label_type_trigger'], null, $this->data['labelTypesLimited']);
$customLabelTriggerTextArea = (new CTextArea('label_string_trigger', $this->data['sysmap']['label_string_trigger']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
if ($this->data['sysmap']['label_type_trigger'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelTriggerTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Trigger label type'), [$labelTypeTriggerComboBox, BR(), $customLabelTriggerTextArea]);

// append map to form list
$labelTypeMapComboBox = new CComboBox('label_type_map', $this->data['sysmap']['label_type_map'], null, $this->data['labelTypesLimited']);
$customLabelMapTextArea = (new CTextArea('label_string_map', $this->data['sysmap']['label_string_map']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
if ($this->data['sysmap']['label_type_map'] != MAP_LABEL_TYPE_CUSTOM) {
	$customLabelMapTextArea->addClass('hidden');
}
$sysmapList->addRow(_('Map label type'), [$labelTypeMapComboBox, BR(), $customLabelMapTextArea]);

// append image to form list
$labelTypeImageComboBox = new CComboBox('label_type_image', $this->data['sysmap']['label_type_image'], null, $this->data['labelTypesImage']);
$customLabelImageTextArea = (new CTextArea('label_string_image', $this->data['sysmap']['label_string_image']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
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
$sysmapList
	->addRow(_('Problem display'), $showUnackComboBox)
	->addRow(_('Minimum trigger severity'), new CSeverity(['name' => 'severity_min', 'value' => $this->data['sysmap']['severity_min']]));

// create url table
$urlTable = (new CTable())
	->setNoDataMessage(_('No URLs defined.'))
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->setHeader([_('Name'), _('URL'), _('Element'), SPACE]);
if (empty($this->data['sysmap']['urls'])) {
	$this->data['sysmap']['urls'][] = ['name' => '', 'url' => '', 'elementtype' => 0];
}
$i = 0;
foreach ($this->data['sysmap']['urls'] as $url) {
	$urlTable->addRow(
		(new CRow([
			(new CTextBox('urls['.$i.'][name]', $url['name']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CTextBox('urls['.$i.'][url]', $url['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			new CComboBox('urls['.$i.'][elementtype]', $url['elementtype'], null, sysmap_element_types()),
			(new CButton(null, _('Remove')))
				->onClick('$("urlEntry_'.$i.'").remove();')
				->addClass(ZBX_STYLE_BTN_LINK)
		]))->setId('urlEntry_'.$i)
	);
	$i++;
}

// append empty template row to url table
$templateUrlLabel = (new CTextBox('urls[#{id}][name]', ''))
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	->setAttribute('disabled', 'disabled');
$templateUrlLink = (new CTextBox('urls[#{id}][url]', ''))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('disabled', 'disabled');
$templateUrlEtype = new CComboBox('urls[#{id}][elementtype]', null, null, sysmap_element_types());
$templateUrlEtype->setAttribute('disabled', 'disabled');
$templateRemoveButton = (new CButton(null, _('Remove')))
	->onClick('$("entry_#{id}").remove();')
	->addClass(ZBX_STYLE_BTN_LINK);
$templateUrlRow = (new CRow([$templateUrlLabel, $templateUrlLink, $templateUrlEtype, $templateRemoveButton]))
	->addStyle('display: none')
	->setId('urlEntryTpl');
$urlTable->addRow($templateUrlRow);

// append "add" button to url table
$addButton = (new CButton(null, _('Add')))
	->onClick('cloneRow("urlEntryTpl", '.$i.')')
	->addClass(ZBX_STYLE_BTN_LINK);
$addButtonColumn = (new CCol($addButton))->setColSpan(4);
$urlTable->addRow($addButtonColumn);

// append url table to form list
$sysmapList->addRow(_('URLs'), (new CDiv($urlTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

// append sysmap to form
$sysmapTab = (new CTabView())->addTab('sysmapTab', _('Map'), $sysmapList);

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
$widget->addItem($sysmapForm);

return $widget;
