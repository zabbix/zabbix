<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

/**
 * Honeycomb widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Zabbix\Widgets\Fields\CWidgetFieldColumnsList;

$form = new CWidgetFormView($data);

$groupids = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$form
	->addField($groupids)
	->addField($data['templateid'] === null
		? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
			->setFilterPreselect(['id' => $groupids->getId(), 'submit_as' => 'groupid'])
		: null
	)
	->addField(array_key_exists('evaltype_host', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['evaltype_host'])
		: null
	)
	->addField(
		array_key_exists('host_tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['host_tags'])
		: null
	)
	->addField(
		(new CWidgetFieldItemPatternSelectView($data['fields']['items']))->setPlaceholder(_('item pattern'))
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['evaltype_item'])
	)
	->addField(
		new CWidgetFieldTagsView($data['fields']['item_tags'])
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['maintenance'])
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['show']))->setColumns(2)
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addField(
				(new CWidgetFieldRadioButtonListView($data['fields']['primary_label_type']))
					->setFieldHint(
						makeHelpIcon([
							_('Supported macros:'),
							(new CList([
								'{HOST.*}',
								'{ITEM.*}',
								'{INVENTORY.*}',
								_('User macros')
							]))->addClass(ZBX_STYLE_LIST_DASHED)
						])
					)
				->addRowClass('fields-group-primary-label')
			)
			->addFieldsGroup(
				getPrimaryLabelFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-primary-label')
			)
			->addField(
				(new CWidgetFieldRadioButtonListView($data['fields']['secondary_label_type']))
					->setFieldHint(
						makeHelpIcon([
							_('Supported macros:'),
							(new CList([
								'{HOST.*}',
								'{ITEM.*}',
								'{INVENTORY.*}',
								_('User macros')
							]))->addClass(ZBX_STYLE_LIST_DASHED)
						])
					)
					->addRowClass('fields-group-secondary-label')
			)
			->addFieldsGroup(
				getSecondaryLabelFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-secondary-label')
			)
			->addField(
				new CWidgetFieldColorView($data['fields']['bg_color'])
			)
			->addFieldsGroup(
				getThresholdFieldsGroupView($form, $data['fields'])
			)
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_honeycomb_form.init('.json_encode([
			'thresholds_colors' => CWidgetFieldColumnsList::THRESHOLDS_DEFAULT_COLOR_PALETTE
		], JSON_THROW_ON_ERROR).');')
	->show();

function getPrimaryLabelFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$label_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['primary_label_size']));
	$label_size_type_field = $form->registerField(
		new CWidgetFieldRadioButtonListView($fields['primary_label_size_type'])
	);

	return (new CWidgetFieldsGroupView(''))
		->addField(
			(new CWidgetFieldTextAreaView($fields['primary_label']))
				->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH - 30)
		)
		->addField(
			(new CWidgetFieldIntegerBoxView($fields['primary_label_decimal_places']))
				->addClass('field-fluid')
				->addRowClass('js-primary-value-field')
		)
		->addItem([
			$label_size_field->getLabel(),
			new CFormField([
				($label_size_type_field->getView())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				($label_size_field->getView())
					->addClass('custom_size_input')
					->setId('primary_label_custom_size'),
				'%'
			])
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['primary_label_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['primary_label_color']))
		)
		->addItem(
			(new CTag('hr'))->addClass('js-primary-value-field')
		)
		->addField(
			(new CWidgetFieldCheckBoxView($fields['primary_label_units_show']))
				->addClass('units')
				->addRowClass('js-primary-value-field')
		)
		->addField(
			(new CWidgetFieldTextBoxView($fields['primary_label_units']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->addRowClass('js-primary-value-field')
		)
		->addField(
			(new CWidgetFieldSelectView($fields['primary_label_units_pos']))->addRowClass('js-primary-value-field')
		);
}

function getSecondaryLabelFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$label_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['secondary_label_size']));
	$label_size_type_field = $form->registerField(
		new CWidgetFieldRadioButtonListView($fields['secondary_label_size_type'])
	);

	return (new CWidgetFieldsGroupView(''))
		->addField(
			(new CWidgetFieldTextAreaView($fields['secondary_label']))
				->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH - 30)
		)
		->addField(
			(new CWidgetFieldIntegerBoxView($fields['secondary_label_decimal_places']))
				->addClass('field-fluid')
				->addRowClass('js-secondary-value-field')
		)
		->addItem([
			$label_size_field->getLabel(),
			new CFormField([
				($label_size_type_field->getView())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				($label_size_field->getView())
					->addClass('custom_size_input')
					->setId('secondary_label_custom_size'),
				'%'
			])
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['secondary_label_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['secondary_label_color']))->addClass('field-fluid')
		)
		->addItem(
			(new CTag('hr'))->addClass('js-secondary-value-field')
		)
		->addField(
			(new CWidgetFieldCheckBoxView($fields['secondary_label_units_show']))
				->addClass('units')
				->addRowClass('js-secondary-value-field')
		)
		->addField(
			(new CWidgetFieldTextBoxView($fields['secondary_label_units']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->addRowClass('js-secondary-value-field')
		)
		->addField(
			(new CWidgetFieldSelectView($fields['secondary_label_units_pos']))->addRowClass('js-secondary-value-field')
		);
}

function getThresholdFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$color_interpolation_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['interpolation']));

	return (new CWidgetFieldsGroupView(_('Thresholds')))
		->setFieldHint(
			makeWarningIcon(_('This setting applies only to numeric data.'))
		)
		->addItem([
			(new CDiv([$color_interpolation_field->getView(), $color_interpolation_field->getLabel()]))
				->addClass('form-row-interpolation')
		])
		->addField(
			(new CWidgetFieldThresholdsView($fields['thresholds']))->removeLabel()
		);
}
