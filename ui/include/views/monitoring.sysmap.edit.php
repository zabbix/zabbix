<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

require_once dirname(__FILE__).'/js/monitoring.sysmap.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Network maps'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_SYSMAP_EDIT));

$tabs = new CTabView();

if (!$data['form_refresh']) {
	$tabs->setSelected(0);
}

// Create sysmap form.
$form = (new CForm())
	->setId('sysmap-form')
	->setName('map.edit.php')
	->addVar('form', getRequest('form', 1))
	->addVar('current_user_userid', $data['current_user_userid'])
	->addVar('current_user_fullname', getUserFullname($data['users'][$data['current_user_userid']]));

if (array_key_exists('sysmapid', $data['sysmap'])) {
	$form->addVar('sysmapid', $data['sysmap']['sysmapid']);
}

$user_type = CWebUser::getType();

// Create sysmap form list.
$map_tab = (new CFormList());

// Map owner multiselect.
$multiselect_data = [
	'name' => 'userid',
	'object_name' => 'users',
	'multiple' => false,
	'disabled' => ($user_type != USER_TYPE_SUPER_ADMIN && $user_type != USER_TYPE_ZABBIX_ADMIN),
	'data' => [],
	'popup' => [
		'parameters' => [
			'srctbl' => 'users',
			'srcfld1' => 'userid',
			'srcfld2' => 'fullname',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'userid'
		]
	]
];

$map_ownerid = $data['sysmap']['userid'];

if ($map_ownerid != 0) {
	$multiselect_data['data'][] = array_key_exists($map_ownerid, $data['users'])
		? [
			'id' => $map_ownerid,
			'name' => getUserFullname($data['users'][$map_ownerid])
		]
		: [
			'id' => $map_ownerid,
			'name' => _('Inaccessible user'),
			'inaccessible' => true
		];
}

// Append multiselect to map tab.
$multiselect_userid = (new CMultiSelect($multiselect_data))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired();

$map_tab->addRow((new CLabel(_('Owner'), 'userid_ms'))->setAsteriskMark(), $multiselect_userid);

$map_tab->addRow((new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['sysmap']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('sysmaps', 'name'))
	)
	->addRow((new CLabel(_('Width'), 'width'))->setAsteriskMark(),
		(new CNumericBox('width', $data['sysmap']['width'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Height'), 'height'))->setAsteriskMark(),
		(new CNumericBox('height', $data['sysmap']['height'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	);

// Append background image to form list.
$background = (new CSelect('backgroundid'))
	->setValue($data['sysmap']['backgroundid'])
	->setFocusableElementId('label-backgroundid')
	->addOption(new CSelectOption(0, _('No image')));

foreach ($data['images'] as $image) {
	$background->addOption(new CSelectOption($image['imageid'], $image['name']));
}

$map_tab->addRow(new CLabel(_('Background image'), $background->getFocusableElementId()), $background);

// Append iconmapping to form list.
$icon_mapping = (new CSelect('iconmapid'))
	->setValue($data['sysmap']['iconmapid'])
	->setFocusableElementId('label-iconmapid')
	->addOption(new CSelectOption(0, _('<manual>')));

foreach ($data['iconMaps'] as $iconMap) {
	$icon_mapping->addOption(new CSelectOption($iconMap['iconmapid'], $iconMap['name']));
}

$icon_mapping_link = (new CLink(_('show icon mappings'), (new CUrl('zabbix.php'))
		->setArgument('action', 'iconmap.list')
		->getUrl()
	))
	->setTarget('_blank');
$map_tab->addRow(new CLabel(_('Automatic icon mapping'), $icon_mapping->getFocusableElementId()),
	[$icon_mapping, SPACE, $icon_mapping_link]
);

// Append multiple checkboxes to form list.
$map_tab->addRow(_('Icon highlight'),
	(new CCheckBox('highlight'))->setChecked($data['sysmap']['highlight'] == 1)
);
$map_tab->addRow(_('Mark elements on trigger status change'),
	(new CCheckBox('markelements'))->setChecked($data['sysmap']['markelements'] == 1)
);

$map_tab->addRow(_('Display problems'),
	(new CRadioButtonList('expandproblem', (int) $data['sysmap']['expandproblem']))
		->addValue(_('Expand single problem'), SYSMAP_SINGLE_PROBLEM)
		->addValue(_('Number of problems'), SYSMAP_PROBLEMS_NUMBER)
		->addValue(_('Number of problems and expand most critical one'), SYSMAP_PROBLEMS_NUMBER_CRITICAL)
		->setModern(true)
);

$map_tab->addRow(_('Advanced labels'),
	(new CCheckBox('label_format'))->setChecked($data['sysmap']['label_format'] == 1)
);

// Append hostgroup to form list.
$map_tab
	->addRow(new CLabel(_('Host group label type'), 'label-label-type-hostgroup'),
		(new CSelect('label_type_hostgroup'))
			->setId('label_type_hostgroup')
			->setFocusableElementId('label-label-type-hostgroup')
			->setValue($data['sysmap']['label_type_hostgroup'])
			->addOptions(CSelect::createOptionsFromArray($data['labelTypesLimited']))
	)
	->addRow(null,
		(new CTextArea('label_string_hostgroup', $data['sysmap']['label_string_hostgroup']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append host to form list.
$map_tab
	->addRow(new CLabel(_('Host label type'), 'label-label-type-host'),
		(new CSelect('label_type_host'))
			->setId('label_type_host')
			->setFocusableElementId('label-label-type-host')
			->setValue($data['sysmap']['label_type_host'])
			->addOptions(CSelect::createOptionsFromArray($data['labelTypes']))
	)
	->addRow(null,
		(new CTextArea('label_string_host', $data['sysmap']['label_string_host']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append trigger to form list.
$map_tab
	->addRow(new CLabel(_('Trigger label type'), 'label-label-type-trigger'),
		(new CSelect('label_type_trigger'))
			->setId('label_type_trigger')
			->setFocusableElementId('label-label-type-trigger')
			->setValue($data['sysmap']['label_type_trigger'])
			->addOptions(CSelect::createOptionsFromArray($data['labelTypesLimited']))
	)
	->addRow(null,
		(new CTextArea('label_string_trigger', $data['sysmap']['label_string_trigger']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append map to form list.
$map_tab
	->addRow(new CLabel(_('Map label type'), 'label-label-type-map'),
		(new CSelect('label_type_map'))
			->setId('label_type_map')
			->setFocusableElementId('label-label-type-map')
			->setValue($data['sysmap']['label_type_map'])
			->addOptions(CSelect::createOptionsFromArray($data['labelTypesLimited']))
	)
	->addRow(null,
		(new CTextArea('label_string_map', $data['sysmap']['label_string_map']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append image to form list.
$map_tab
	->addRow(new CLabel(_('Image label type'), 'label-label-type-image'),
		(new CSelect('label_type_image'))
			->setId('label_type_image')
			->setFocusableElementId('label-label-type-image')
			->setValue($data['sysmap']['label_type_image'])
			->addOptions(CSelect::createOptionsFromArray($data['labelTypesImage']))
	)
	->addRow(null,
		(new CTextArea('label_string_image', $data['sysmap']['label_string_image']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// Append map element label to form list.
unset($data['labelTypes'][MAP_LABEL_TYPE_CUSTOM]);
$map_tab->addRow(new CLabel(_('Map element label type'), 'label-label-type'),
	(new CSelect('label_type'))
		->setId('label_type')
		->setFocusableElementId('label-label-type')
		->setValue($data['sysmap']['label_type'])
		->addOptions(CSelect::createOptionsFromArray($data['labelTypes']))
);

// Append map element label location to form list.
$map_tab->addRow(new CLabel(_('Map element label location'), 'label-label-location'),
	(new CSelect('label_location'))
		->setFocusableElementId('label-label-location')
		->setValue($data['sysmap']['label_location'])
		->addOptions(CSelect::createOptionsFromArray([
			0 => _('Bottom'),
			1 => _('Left'),
			2 => _('Right'),
			3 => _('Top')
		]))
);

// Append show unack to form list.
$map_tab->addRow(new CLabel(_('Problem display'), 'label-show-unack'),
	(new CSelect('show_unack'))
		->setFocusableElementId('label-show-unack')
		->setValue($data['sysmap']['show_unack'])
		->addOptions(CSelect::createOptionsFromArray([
			EXTACK_OPTION_ALL => _('All'),
			EXTACK_OPTION_BOTH => _('Separated'),
			EXTACK_OPTION_UNACK => _('Unacknowledged only')
		]))
);

$map_tab->addRow(_('Minimum severity'),
	new CSeverity('severity_min', (int) $data['sysmap']['severity_min'])
);

$map_tab->addRow(_('Show suppressed problems'),
	(new CCheckBox('show_suppressed'))->setChecked($data['sysmap']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
);

// Create url table.
$url_table = (new CTable())
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
			(new CSelect('urls['.$i.'][elementtype]'))
				->setValue($url['elementtype'])
				->addOptions(CSelect::createOptionsFromArray(sysmap_element_types())),
			(new CCol(
				(new CButton(null, _('Remove')))
					->onClick('$("#url-row-'.$i.'").remove();')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('url-row-'.$i)
	);
	$i++;
}

// Append "add" button to url table.
$url_table->addRow(
	(new CCol(
		(new CButton(null, _('Add')))
			->setId('add-url')
			->addClass(ZBX_STYLE_BTN_LINK))
	)->setColSpan(4)
);

// Append url table to form list.
$map_tab->addRow(_('URLs'),
	(new CDiv($url_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$tabs->addTab('sysmap_tab', _('Map'), $map_tab);

// User group sharing table.
$user_group_shares_table = (new CTable())
	->setId('user-group-share-table')
	->setHeader([_('User groups'), _('Permissions'), _('Action')])
	->setAttribute('style', 'width: 100%;');

$add_user_group_btn = ([(new CButton(null, _('Add')))
	->onClick(
		'return PopUp("popup.generic", '.json_encode([
			'srctbl' => 'usrgrp',
			'srcfld1' => 'usrgrpid',
			'srcfld2' => 'name',
			'dstfrm' => $form->getName(),
			'multiselect' => '1'
		]).', {dialogue_class: "modal-popup-generic"});'
	)
	->addClass(ZBX_STYLE_BTN_LINK)]);

$user_group_shares_table->addRow(
	(new CRow(
		(new CCol($add_user_group_btn))->setColSpan(3)
	))->setId('user_group_list_footer')
);

$user_groups = [];

foreach ($data['sysmap']['userGroups'] as $user_group) {
	$user_groupid = $user_group['usrgrpid'];
	$user_groups[] = [
		'usrgrpid' => $user_groupid,
		'name' => $data['user_groups'][$user_groupid]['name'],
		'permission' => $user_group['permission']
	];
}

$js_insert = 'window.addPopupValues('.json_encode(['object' => 'usrgrpid', 'values' => $user_groups]).');';

// User sharing table.
$user_shares_table = (new CTable())
	->setId('user-share-table')
	->setHeader([_('Users'), _('Permissions'), _('Action')])
	->setAttribute('style', 'width: 100%;');

$add_user_btn = ([(new CButton(null, _('Add')))
	->onClick(
		'return PopUp("popup.generic", '.json_encode([
			'srctbl' => 'users',
			'srcfld1' => 'userid',
			'srcfld2' => 'fullname',
			'dstfrm' => $form->getName(),
			'multiselect' => '1'
		]).', {dialogue_class: "modal-popup-generic"});'
	)
	->addClass(ZBX_STYLE_BTN_LINK)]);

$user_shares_table->addRow(
	(new CRow(
		(new CCol($add_user_btn))->setColSpan(3)
	))->setId('user_list_footer')
);

$users = [];

foreach ($data['sysmap']['users'] as $user) {
	$userid = $user['userid'];
	$users[] = [
		'id' => $userid,
		'name' => getUserFullname($data['users'][$userid]),
		'permission' => $user['permission']
	];
}

$js_insert .= 'window.addPopupValues('.json_encode(['object' => 'userid', 'values' => $users]).');';

zbx_add_post_js($js_insert);

$sharing_tab = (new CFormList('sharing_form'))
	->addRow(_('Type'),
	(new CRadioButtonList('private', (int) $data['sysmap']['private']))
		->addValue(_('Private'), PRIVATE_SHARING)
		->addValue(_('Public'), PUBLIC_SHARING)
		->setModern(true)
	)
	->addRow(_('List of user group shares'),
		(new CDiv($user_group_shares_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	)
	->addRow(_('List of user shares'),
		(new CDiv($user_shares_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	);

// Append data to form.
$tabs->addTab('sharing_tab', _('Sharing'), $sharing_tab, TAB_INDICATOR_SHARING);

// Append buttons to form.
if (hasRequest('sysmapid') && getRequest('sysmapid') > 0 && getRequest('form') !== 'full_clone') {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new	CButton('clone', _('Clone')),
			new CButton('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete selected map?'), url_params(['form', 'sysmapid'])),
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

$widget->show();
