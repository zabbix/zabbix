<?php declare(strict_types = 0);
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
 * @var array $data
 */

$this->includeJsFile('administration.userrole.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('User roles'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_USERROLE_EDIT));

$form = (new CForm())
	->setId('userrole-form')
	->setName('user_role_form')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

if ($data['roleid'] !== null) {
	$form->addVar('roleid', $data['roleid']);
}

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name'], $data['readonly'], DB::getFieldLength('role', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
				->setAttribute('maxlength', DB::getFieldLength('role', 'name'))
		)
]);

if ($data['readonly'] || $data['is_own_role']) {
	$form_grid->addItem([
		(new CLabel(_('User type'), 'type')),
		new CFormField([
			(new CTextBox('type', user_type2str()[$data['type']]))
				->setId('type_readonly')
				->setAttribute('readonly', true),
			new CVar('type', $data['type']),
			' ',
			$data['is_own_role']
				? new CSpan(_('User cannot change the user type of own role.'))
				: null
		])
	]);
}
else {
	$form_grid->addItem([
		(new CLabel(_('User type'), 'label-user-type')),
		new CFormField(
			(new CSelect('type'))
				->setId('user-type')
				->setFocusableElementId('label-user-type')
				->setValue($data['type'])
				->addOptions(CSelect::createOptionsFromArray(user_type2str()))
		)
	]);
}

$form_grid->addItem(
	new CFormField(
		(new CTag('h4', true, _('Access to UI elements')))->addClass('input-section-header')
	)
);

foreach ($data['labels']['sections'] as $section_key => $section_label) {
	$ui = [];
	foreach ($data['labels']['rules'][$section_key] as $rule_key => $rule_label) {
		$ui[] = [
			'id' => $rule_key,
			'name' => str_replace('.', '_', $rule_key),
			'label' => $rule_label,
			'value' => 1,
			'checked' => array_key_exists($rule_key, $data['rules']['ui']) && $data['rules']['ui'][$rule_key],
			'unchecked_value' => 0
		];
	}
	$form_grid->addItem([
		new CLabel($section_label, $section_key),
		new CFormField(
			(new CCheckBoxList())
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($ui)
				->setVertical(true)
				->setColumns(3)
				->setEnabled(!$data['readonly'])
		)
	]);
}

if (!$data['readonly']) {
	$form_grid->addItem(
		new CFormField(
			(new CLabel(_('At least one UI element must be checked.')))->setAsteriskMark()
		)
	);
}

$form_grid->addItem([
	new CLabel(_('Default access to new UI elements'), $data['readonly'] ? '' : 'ui.default_access'),
	new CFormField(
		(new CCheckBox('ui_default_access', 1))
			->setId('ui.default_access')
			->setChecked($data['rules']['ui.default_access'])
			->setReadonly($data['readonly'])
			->setUncheckedValue(0)
	)
]);

$form_grid
	->addItem(
		new CFormField(
			(new CTag('h4', true, _('Access to services')))->addClass('input-section-header')
		)
	)
	->addItem([
		new CLabel(_('Read-write access to services'), 'service-write-access'),
		new CFormField(
			(new CRadioButtonList('service_write_access', (int) $data['rules']['service_write_access']))
				->setId('service-write-access')
				->addValue(_('None'), CRoleHelper::SERVICES_ACCESS_NONE)
				->addValue(_('All'), CRoleHelper::SERVICES_ACCESS_ALL)
				->addValue(_('Service list'), CRoleHelper::SERVICES_ACCESS_LIST)
				->setModern(true)
				->setReadonly($data['readonly'])
		)
	])
	->addItem(
		(new CFormField(
			(new CMultiSelect([
				'name' => 'service_write_list[]',
				'object_name' => 'services',
				'data' => CArrayHelper::renameObjectsKeys($data['rules']['service_write_list'], ['serviceid' => 'id']),
				'custom_select' => true
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
			->addClass('js-service-write-access')
			->addStyle('display: none;')
	)
	->addItem([
		(new CLabel(_('Read-write access to services with tag'), 'service-write-tag-tag'))
			->addClass('js-service-write-access')
			->addStyle('display: none;'),
		(new CFormField(
			new CHorList([
				(new CTextBox('service_write_tag_tag', $data['rules']['service_write_tag']['tag']))
					->setId('service-write-tag-tag')
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				(new CTextBox('service_write_tag_value', $data['rules']['service_write_tag']['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			])))
				->addClass('js-service-write-access')
				->addStyle('display: none;')
	])
	->addItem([
		new CLabel(_('Read-only access to services'), 'service-read-access'),
		new CFormField(
			(new CRadioButtonList('service_read_access', (int) $data['rules']['service_read_access']))
				->setId('service-read-access')
				->addValue(_('None'), CRoleHelper::SERVICES_ACCESS_NONE)
				->addValue(_('All'), CRoleHelper::SERVICES_ACCESS_ALL)
				->addValue(_('Service list'), CRoleHelper::SERVICES_ACCESS_LIST)
				->setModern(true)
				->setReadonly($data['readonly'])
		)
	])
	->addItem(
		(new CFormField(
			(new CMultiSelect([
				'name' => 'service_read_list[]',
				'object_name' => 'services',
				'data' => CArrayHelper::renameObjectsKeys($data['rules']['service_read_list'], ['serviceid' => 'id']),
				'custom_select' => true
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
			->addClass('js-service-read-access')
			->addStyle('display: none;')
	)
	->addItem([
		(new CLabel(_('Read-only access to services with tag'), 'service-read-tag-tag'))
			->addClass('js-service-read-access')
			->addStyle('display: none;'),
		(new CFormField(
			new CHorList([
				(new CTextBox('service_read_tag_tag', $data['rules']['service_read_tag']['tag']))
					->setId('service-read-tag-tag')
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				(new CTextBox('service_read_tag_value', $data['rules']['service_read_tag']['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			])))
			->addClass('js-service-read-access')
			->addStyle('display: none;')
	]);

$form_grid->addItem(
	new CFormField(
		(new CTag('h4', true, _('Access to modules')))->addClass('input-section-header')
	)
);

$modules = [];
foreach ($data['labels']['modules'] as $moduleid => $label) {
	$modules[] = new CDiv(
		(new CCheckBox('modules['.$moduleid.']', 1))
			->setChecked(
				array_key_exists($moduleid, $data['rules']['modules']) ? $data['rules']['modules'][$moduleid] : true
			)
			->setReadonly($data['readonly'])
			->setLabel($label)
			->setUncheckedValue(0)
	);
}

if ($modules) {
	$form_grid->addItem([
		new CFormField(
			(new CDiv(
				(new CDiv($modules))
					->addClass(ZBX_STYLE_COLUMNS)
					->addClass(ZBX_STYLE_COLUMNS_3)
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	]);
}
else {
	$form_grid->addItem(
		new CFormField(
			new CLabel(_('No enabled modules found.'))
		)
	);
}

$form_grid
	->addItem([
		new CLabel(_('Default access to new modules'), $data['readonly'] ? '' : 'modules.default_access'),
		new CFormField(
			(new CCheckBox('modules_default_access', 1))
				->setId('modules.default_access')
				->setChecked($data['rules']['modules.default_access'])
				->setReadonly($data['readonly'])
				->setUncheckedValue(0)
		)
	])
	->addItem(
		new CFormField(
			(new CTag('h4', true, _('Access to API')))->addClass('input-section-header')
		)
	)
	->addItem([
		new CLabel(_('Enabled'), $data['readonly'] ? '' : 'api-access'),
		new CFormField(
			(new CCheckBox('api_access', 1))
				->setId('api-access')
				->setChecked($data['rules']['api.access'])
				->setReadonly($data['readonly'])
				->setUncheckedValue(0)
		)
	])
	->addItem([
		new CLabel(_('API methods'), 'api.mode'),
		new CFormField(
			(new CRadioButtonList('api_mode', (int) $data['rules']['api.mode']))
				->setId('api.mode')
				->addValue(_('Allow list'), ZBX_ROLE_RULE_API_MODE_ALLOW)
				->addValue(_('Deny list'), ZBX_ROLE_RULE_API_MODE_DENY)
				->setModern(true)
				->setReadonly($data['readonly'] || !$data['rules']['api.access'])
				->addClass('js-userrole-apimode')
		)
	])
	->addItem(
		new CFormField(
			(new CMultiSelect([
				'name' => 'api_methods[]',
				'object_name' => 'api_methods',
				'data' => $data['rules']['api'],
				'disabled' => $data['readonly'] || !$data['rules']['api.access'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'api_methods',
						'srcfld1' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => zbx_formatDomId('api_methods'.'[]'),
						'user_type' => $data['type'],
						'disable_selected' => true
					]
				]
			]))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addClass('js-userrole-ms')
		)
	)
	->addItem(
		new CFormField(
			(new CTag('h4', true, _('Access to actions')))->addClass('input-section-header')
		)
);

$actions = [];
foreach ($data['labels']['actions'] as $action => $label) {
	$actions[] = new CDiv(
		(new CCheckBox(str_replace('.', '_', $action), 1))
			->setId($action)
			->setChecked(array_key_exists($action, $data['rules']['actions']) && $data['rules']['actions'][$action])
			->setReadonly($data['readonly'])
			->setLabel($label)
			->setUncheckedValue(0)
	);
}

$form_grid->addItem(
	new CFormField($actions)
);

$form_grid->addItem([
	new CLabel(_('Default access to new actions'), $data['readonly'] ? '' : 'actions.default_access'),
	new CFormField(
		(new CCheckBox('actions_default_access', 1))
			->setId('actions.default_access')
			->setChecked($data['rules']['actions.default_access'])
			->setReadonly($data['readonly'])
			->setUncheckedValue(0)
	)
]);

$cancel_button = (new CRedirectButton(_('Cancel'),
	(new CUrl('zabbix.php'))
		->setArgument('action', 'userrole.list')
		->setArgument('page', CPagerHelper::loadPage('userrole.list', null))
))->setId('cancel');

$buttons = [$cancel_button];

if ($data['roleid'] !== null) {
	$buttons = [
		(new CSimpleButton(_('Clone')))->setId('clone'),
		(new CRedirectButton(_('Delete'),
			(new CUrl('zabbix.php'))->setArgument('action', 'userrole.delete')
				->setArgument('roleids', [$data['roleid']])
				->setArgumentSID(),
			_('Delete selected role?')
		))
			->setId('delete')
			->setEnabled(!$data['readonly']),
		$cancel_button
	];
}

$form_grid->addItem(
	new CFormActions(
		($data['roleid'] !== null)
			? (new CSubmitButton(_('Update'), 'action', 'userrole.update'))
				->setId('update')
				->setEnabled(!$data['readonly'])
			: (new CSubmitButton(_('Add'), 'action', 'userrole.create'))->setId('add'),
		$buttons
	)
);

$tabs = (new CTabView())->addTab('user_role_tab', _('User role'), $form_grid);

$form->addItem((new CTabView())->addTab('user_role_tab', _('User role'), $form_grid));
$widget->addItem($form);
$widget->show();

(new CScriptTag('
	view.init('.json_encode([
		'readonly' => $data['readonly']
	]).');
'))
	->setOnDocumentReady()
	->show();
