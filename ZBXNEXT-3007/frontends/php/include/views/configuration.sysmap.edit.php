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

$tabs = new CTabView();

$tabs->setSelected(0);

// Create sysmap form.
$form = (new CForm())
	->setName('map.edit.php')
	->addVar('form', getRequest('form', 1));

if (array_key_exists('sysmapid', $data['sysmap'])) {
	$form->addVar('sysmapid', $data['sysmap']['sysmapid']);
}

// Create sysmap form list.
$map_tab = (new CFormList())
	->addRow(_('Owner'),
		'Admin'
	)
	->addRow(_('Name'),
		(new CTextBox('name', $data['sysmap']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Width'),
		(new CNumericBox('width', $data['sysmap']['width'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Height'),
		(new CNumericBox('height', $data['sysmap']['height'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

// Append background image to form list.
$background = (new CComboBox('backgroundid', $data['sysmap']['backgroundid']))
	->addItem(0, _('No image'));
foreach ($data['images'] as $image) {
	$background->addItem($image['imageid'], $image['name']);
}
$map_tab->addRow(_('Background image'), $background);

// Append iconmapping to form list.
$icon_mapping = (new CComboBox('iconmapid', $data['sysmap']['iconmapid']))
	->addItem(0, _('<manual>'));
foreach ($data['iconMaps'] as $iconMap) {
	$icon_mapping->addItem($iconMap['iconmapid'], $iconMap['name']);
}
$icon_mapping_link = (new CLink(_('show icon mappings'), 'adm.iconmapping.php'))
	->setAttribute('target', '_blank');
$map_tab->addRow(_('Automatic icon mapping'), [$icon_mapping, SPACE, $icon_mapping_link]);

// Append multiple checkboxes to form list.
$map_tab->addRow(_('Icon highlight'),
	(new CCheckBox('highlight'))->setChecked($data['sysmap']['highlight'] == 1)
);
$map_tab->addRow(_('Mark elements on trigger status change'),
	(new CCheckBox('markelements'))->setChecked($data['sysmap']['markelements'] == 1)
);
$map_tab->addRow(_('Expand single problem'),
	(new CCheckBox('expandproblem'))->setChecked($data['sysmap']['expandproblem'] == 1)
);
$map_tab->addRow(_('Advanced labels'),
	(new CCheckBox('label_format'))->setChecked($data['sysmap']['label_format'] == 1)
);

// Append hostgroup to form list.
$map_tab->addRow(_('Host group label type'), [
	new CComboBox('label_type_hostgroup', $data['sysmap']['label_type_hostgroup'], null, $data['labelTypesLimited']),
	BR(),
	(new CTextArea('label_string_hostgroup', $data['sysmap']['label_string_hostgroup']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
]);

// Append host to form list.
$map_tab->addRow(_('Host label type'), [
	new CComboBox('label_type_host', $data['sysmap']['label_type_host'], null, $data['labelTypes']),
	BR(),
	(new CTextArea('label_string_host', $data['sysmap']['label_string_host']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
]);

// Append trigger to form list.
$map_tab->addRow(_('Trigger label type'), [
	new CComboBox('label_type_trigger', $data['sysmap']['label_type_trigger'], null, $data['labelTypesLimited']),
	BR(),
	(new CTextArea('label_string_trigger', $data['sysmap']['label_string_trigger']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
]);

// Append map to form list.
$map_tab->addRow(_('Map label type'), [
	new CComboBox('label_type_map', $data['sysmap']['label_type_map'], null, $data['labelTypesLimited']),
	BR(),
	(new CTextArea('label_string_map', $data['sysmap']['label_string_map']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
]);

// Append image to form list.
$map_tab->addRow(_('Image label type'), [
	new CComboBox('label_type_image', $data['sysmap']['label_type_image'], null, $data['labelTypesImage']),
	BR(),
	(new CTextArea('label_string_image', $data['sysmap']['label_string_image']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
]);

// Append icon label to form list.
unset($data['labelTypes'][MAP_LABEL_TYPE_CUSTOM]);
$map_tab->addRow(_('Icon label type'),
	new CComboBox('label_type', $data['sysmap']['label_type'], null, $data['labelTypes'])
);

// Append icon label location to form list.
$map_tab->addRow(_('Icon label location'), new CComboBox('label_location', $data['sysmap']['label_location'], null,
	[
		0 => _('Bottom'),
		1 => _('Left'),
		2 => _('Right'),
		3 => _('Top')
	]
));

// Append show unack to form list.
$show_unack = new CComboBox('show_unack', $data['sysmap']['show_unack'], null, [
	EXTACK_OPTION_ALL => _('All'),
	EXTACK_OPTION_BOTH => _('Separated'),
	EXTACK_OPTION_UNACK => _('Unacknowledged only'),
]);
$show_unack->setEnabled($data['config']['event_ack_enable']);
if (!$data['config']['event_ack_enable']) {
	$show_unack->setAttribute('title', _('Acknowledging disabled'));
}
$map_tab
	->addRow(_('Problem display'), $show_unack)
	->addRow(_('Minimum trigger severity'),
		new CSeverity(['name' => 'severity_min', 'value' => (int) $data['sysmap']['severity_min']])
	);

// Create url table.
$url_table = (new CTable())
	->setNoDataMessage(_('No URLs defined.'))
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), _('URL'), _('Element'), _('Action')]);
if (empty($data['sysmap']['urls'])) {
	$data['sysmap']['urls'][] = ['name' => '', 'url' => '', 'elementtype' => 0];
}
$i = 0;
foreach ($data['sysmap']['urls'] as $url) {
	$url_table->addRow(
		(new CRow([
			(new CTextBox('urls['.$i.'][name]', $url['name']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CTextBox('urls['.$i.'][url]', $url['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			new CComboBox('urls['.$i.'][elementtype]', $url['elementtype'], null, sysmap_element_types()),
			(new CCol(
				(new CButton(null, _('Remove')))
					->onClick('$("urlEntry_'.$i.'").remove();')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('urlEntry_'.$i)
	);
	$i++;
}

// Append empty template row to url table.
$template_url_label = (new CTextBox('urls[#{id}][name]', ''))
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	->setAttribute('disabled', 'disabled');
$template_url_link = (new CTextBox('urls[#{id}][url]', ''))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAttribute('disabled', 'disabled');
$template_url_etype = new CComboBox('urls[#{id}][elementtype]', null, null, sysmap_element_types());
$template_url_etype->setAttribute('disabled', 'disabled');
$template_remove_button = (new CButton(null, _('Remove')))
	->onClick('$("entry_#{id}").remove();')
	->addClass(ZBX_STYLE_BTN_LINK);
$template_url_row = (new CRow([
	$template_url_label,
	$template_url_link,
	$template_url_etype,
	(new CCol($template_remove_button))->addClass(ZBX_STYLE_NOWRAP)
]))
	->addStyle('display: none')
	->setId('urlEntryTpl');
$url_table->addRow($template_url_row);

// Append "add" button to url table.
$add_button = (new CButton(null, _('Add')))
	->onClick('cloneRow("urlEntryTpl", '.$i.')')
	->addClass(ZBX_STYLE_BTN_LINK);
$add_button_column = (new CCol($add_button))->setColSpan(4);
$url_table->addRow($add_button_column);

// Append url table to form list.
$map_tab->addRow(_('URLs'),
	(new CDiv($url_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$sharing_tab = (new CFormList('proxyFormList'))
	->addRow(_('Type'),
	(new CRadioButtonList('private', (int) $data['sysmap']['private']))
		->addValue(_('Private'), SYSMAP_PRIVATE)
		->addValue(_('Public'), SYSMAP_PUBLIC)
		->setModern(true)
	);

$constructor_tab = '';

// Append data to form.
$tabs->addTab('sysmapTab', _('Map'), $map_tab)
	->addTab('sharingTab', _('Sharing'), $sharing_tab)
	->addTab('constructorTab', _('Constructor'), $constructor_tab);

// Append buttons to form.
if (hasRequest('sysmapid') && getRequest('sysmapid') > 0) {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new	CButton('clone', _('Clone')),
			new CButtonDelete(_('Delete network map?'), url_param('form').url_param('sysmapid')),
			new CButtonCancel()
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($tabs);

// Append form to widget.
$widget->addItem($form);

return $widget;
