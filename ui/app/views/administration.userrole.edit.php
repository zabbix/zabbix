<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$this->addJsFile('multiselect.js');
$this->includeJsFile('administration.userrole.edit.js.php');

$widget = (new CWidget())->setTitle(_('User roles'));

$form = (new CForm())
	->setId('userrole-form')
	->setName('user_role_form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE);

if ($data['roleid'] != 0) {
	$form->addVar('roleid', $data['roleid']);
}

$form_grid = (new CFormGrid())->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_1_1);

$form_grid->addItem([
	(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
	(new CFormField(
		(new CTextBox('name', $data['name'], $data['readonly'], DB::getFieldLength('role', 'name')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('role', 'name'))
	))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
]);

if ($data['readonly']) {
	$form_grid->addItem([
		(new CLabel(_('User type'), 'type')),
		(new CFormField(
			(new CTextBox('type', user_type2str()[$data['type']]))->setAttribute('readonly', true)
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);
}
else {
	$form_grid->addItem([
		(new CLabel(_('User type'), 'type')),
		(new CFormField(
			(new CComboBox('type', $data['type'], null, user_type2str()))->addClass('js-userrole-usertype')
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);
}

$form_grid->addItem(
	(new CFormField((new CTag('h4', true, _('Access to UI elements')))->addClass('input-section-header')))
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
);

foreach ($data['labels']['sections'] as $section_key => $section_label) {
	$ui = [];
	foreach ($data['labels']['rules'][$section_key] as $rule_key => $rule_label) {
		$ui[] = new CDiv(
			(new CCheckBox(str_replace('.', '_', $rule_key), 1))
				->setId($rule_key)
				->setChecked(
					array_key_exists($rule_key, $data['rules'][CRoleHelper::SECTION_UI])
					&& $data['rules'][CRoleHelper::SECTION_UI][$rule_key]
				)
				->setReadonly($data['readonly'])
				->setLabel($rule_label)
				->setUncheckedValue(0)
		);
	}
	$form_grid->addItem([
		new CLabel($section_label, $section_key),
		(new CFormField(
			(new CDiv(
				(new CDiv($ui))
					->addClass(ZBX_STYLE_COLUMNS)
					->addClass(ZBX_STYLE_COLUMNS_3)
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);
}

if (!$data['readonly']) {
	$form_grid->addItem(
		(new CFormField((new CLabel(_('At least one UI element must be checked.')))->setAsteriskMark()))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
	);
}

$form_grid->addItem([
	new CLabel(_('Default access to new UI elements'), $data['readonly'] ? '' : 'ui.default_access'),
	(new CFormField(
		(new CCheckBox('ui_default_access', 1))
			->setId('ui.default_access')
			->setChecked($data['rules'][CRoleHelper::UI_DEFAULT_ACCESS])
			->setReadonly($data['readonly'])
			->setUncheckedValue(0)
	))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
]);

$form_grid->addItem(
	(new CFormField((new CTag('h4', true, _('Access to modules')))->addClass('input-section-header')))
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
);

$modules = [];
foreach ($data['labels']['modules'] as $moduleid => $label) {
	$modules[] = new CDiv(
		(new CCheckBox(CRoleHelper::SECTION_MODULES.'['.$moduleid.']', 1))
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
		(new CFormField(
			(new CDiv(
				(new CDiv($modules))
					->addClass(ZBX_STYLE_COLUMNS)
					->addClass(ZBX_STYLE_COLUMNS_3)
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
	]);
}
else {
	$form_grid->addItem(
		(new CFormField((new CLabel(_('No enabled modules found.')))))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
	);
}

$form_grid
	->addItem([
		new CLabel(_('Default access to new modules'), $data['readonly'] ? '' : 'modules.default_access'),
		(new CFormField(
			(new CCheckBox('modules_default_access', 1))
				->setId('modules.default_access')
				->setChecked($data['rules'][CRoleHelper::MODULES_DEFAULT_ACCESS])
				->setReadonly($data['readonly'])
				->setUncheckedValue(0)
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	])
	->addItem(
		(new CFormField((new CTag('h4', true, _('Access to API')))->addClass('input-section-header')))
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
	)
	->addItem([
		new CLabel(_('Enabled'), $data['readonly'] ? '' : 'api.access'),
		(new CFormField(
			(new CCheckBox('api_access', 1))
				->setId('api.access')
				->setChecked($data['rules'][CRoleHelper::API_ACCESS])
				->setReadonly($data['readonly'])
				->setUncheckedValue(0)
				->addClass('js-userrole-apiaccess')
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	])
	->addItem([
		new CLabel(_('API methods'), 'api.mode'),
		(new CFormField(
			(new CRadioButtonList('api_mode', (int) $data['rules'][CRoleHelper::API_MODE]))
				->setId('api.mode')
				->addValue(_('Allow list'), CRoleHelper::API_MODE_ALLOW)
				->addValue(_('Deny list'), CRoleHelper::API_MODE_DENY)
				->setModern(true)
				->setReadonly($data['readonly'] || !$data['rules'][CRoleHelper::API_ACCESS])
				->addClass('js-userrole-apimode')
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);

$form_grid->addItem(
	(new CFormField(
		(new CMultiSelect([
			'name' => 'api_methods[]',
			'object_name' => 'api_methods',
			'data' => $data['rules'][CRoleHelper::SECTION_API],
			'disabled' => (bool) $data['readonly'] || !$data['rules'][CRoleHelper::API_ACCESS],
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
	))
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
);

$form_grid->addItem(
	(new CFormField((new CTag('h4', true, _('Access to actions')))->addClass('input-section-header')))
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
);

$actions = [];
foreach ($data['labels']['actions'] as $action => $label) {
	$actions[] = new CDiv(
		(new CCheckBox(str_replace('.', '_', $action), 1))
			->setId($action)
			->setChecked(
				array_key_exists($action, $data['rules'][CRoleHelper::SECTION_ACTIONS])
				&& $data['rules'][CRoleHelper::SECTION_ACTIONS][$action]
			)
			->setReadonly($data['readonly'])
			->setLabel($label)
			->setUncheckedValue(0)
	);
}

$form_grid->addItem(
	(new CFormField($actions))
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
);

$form_grid->addItem([
	new CLabel(_('Default access to new actions'), $data['readonly'] ? '' : 'actions.default_access'),
	(new CFormField(
		(new CCheckBox('actions_default_access', 1))
			->setId('actions.default_access')
			->setChecked($data['rules'][CRoleHelper::ACTIONS_DEFAULT_ACCESS])
			->setReadonly($data['readonly'])
			->setUncheckedValue(0)
	))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
]);

$form_grid->addItem(
	(new CFormActions(
		($data['roleid'] != 0)
			? (new CSubmitButton(_('Update'), 'action', 'userrole.update'))
				->setId('update')
				->setEnabled(!$data['readonly'])
			: (new CSubmitButton(_('Add'), 'action', 'userrole.create'))->setId('add'),
		[
			(new CRedirectButton(_('Cancel'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'userrole.list')
					->setArgument('page', CPagerHelper::loadPage('userrole.list', null))
			))->setId('cancel')
		]
	))
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
);

$tabs = (new CTabView())->addTab('user_role_tab', _('User role'), $form_grid);

$form->addItem((new CTabView())->addTab('user_role_tab', _('User role'), $form_grid));
$widget->addItem($form);
$widget->show();
