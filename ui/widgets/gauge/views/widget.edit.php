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
 * Gauge widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$form = new CWidgetFormView($data);

$form
	->addField(
		(new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
			->setPopupParameter('value_types', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
	)
	->addField(
		new CWidgetFieldNumericBoxView($data['fields']['min'])
	)
	->addField(
		new CWidgetFieldNumericBoxView($data['fields']['max'])
	)
	->addFieldsGroup(
		getColorsFieldsGroupView($data['fields'])->addRowClass('fields-group-colors')
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['show']))->setColumns(3)
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addField(
				new CWidgetFieldRadioButtonListView($data['fields']['angle'])
			)
			->addFieldsGroup(
				getDescriptionFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-description')
			)
			->addFieldsGroup(
				getValueFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-value')
			)
			->addFieldsGroup(
				getValueArcFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-value-arc')
			)
			->addFieldsGroup(
				getNeedleFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-needle')
			)
			->addFieldsGroup(
				getScaleFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-scale')
			)
			->addFieldsGroup(
				getThresholdFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-thresholds')
			)
	)
	->addField($data['templateid'] === null
		? new CWidgetFieldMultiSelectOverrideHostView($data['fields']['override_hostid'])
		: null
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_gauge_form.init('.json_encode([
		'thresholds_colors' => ['FF465C', 'FFD54F', '0EC9AC', '524BBC', 'ED1248', 'D1E754', '2AB5FF', '385CC7',
			'EC1594', 'BAE37D', '6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF', 'F3601B', '1CAE59', '45CFDB',
			'894BBC', '6D6D6D'
		]
	], JSON_THROW_ON_ERROR).');')
	->show();


function getColorsFieldsGroupView(array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Colors')))
		->addField(
			new CWidgetFieldColorView($fields['value_arc_color'])
		)
		->addField(
			new CWidgetFieldColorView($fields['empty_color'])
		)
		->addField(
			new CWidgetFieldColorView($fields['bg_color'])
		);
}

function getDescriptionFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$desc_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['desc_size']));

	return (new CWidgetFieldsGroupView(_('Description')))
		->addLabelClass(ZBX_STYLE_FIELD_LABEL_ASTERISK)
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
		->addField(
			(new CWidgetFieldTextAreaView($fields['description']))
				->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH - 38)
				->removeLabel()
		)
		->addItem([
			$desc_size_field->getLabel(),
			(new CFormField([$desc_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldRadioButtonListView($fields['desc_v_pos'])
		)
		->addField(
			new CWidgetFieldCheckBoxView($fields['desc_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['desc_color']))->addLabelClass('offset-3')
		);
}

function getValueFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$value_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['value_size']));
	$units_show_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['units_show']));
	$units_field = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['units']))->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH)
	);
	$units_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['units_size']));

	return (new CWidgetFieldsGroupView(_('Value')))
		->addField(
			new CWidgetFieldIntegerBoxView($fields['decimal_places'])
		)
		->addItem([
			$value_size_field->getLabel(),
			(new CFormField([$value_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['value_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['value_color']))->addLabelClass('offset-3')
		)
		->addItem(
			new CTag('hr')
		)
		->addItem([
			(new CDiv([$units_show_field->getView(), $units_field->getLabel()]))->addClass('units-show'),
			(new CFormField($units_field->getView()))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
		])
		->addItem([
			$units_size_field->getLabel(),
			(new CFormField([$units_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			(new CWidgetFieldCheckBoxView($fields['units_bold']))->addLabelClass('offset-3')
		)
		->addField(
			(new CWidgetFieldSelectView($fields['units_pos']))->setFieldHint(
				makeHelpIcon(_('Position is ignored for s, uptime and unixtime units.'))
			)
		)
		->addField(
			(new CWidgetFieldColorView($fields['units_color']))->addLabelClass('offset-3')
		);
}

function getValueArcFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$value_arc_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['value_arc_size']));

	return (new CWidgetFieldsGroupView(_('Value arc')))
		->addItem([
			$value_arc_size_field->getLabel(),
			(new CFormField([$value_arc_size_field->getView(), '%']))->addClass('field-size')
		]);
}

function getNeedleFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Needle')))
		->addField(
			new CWidgetFieldColorView($fields['needle_color'])
		);
}

function getScaleFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$scale_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['scale_size']));
	$scale_show_units_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['scale_show_units']));

	return (new CWidgetFieldsGroupView(_('Scale')))
		->addItem([
			(new CDiv([$scale_show_units_field->getLabel()]))->addClass('scale-show'),
			(new CFormField($scale_show_units_field->getView()))
		])
		->addItem([
			$scale_size_field->getLabel(),
			(new CFormField([$scale_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(new CWidgetFieldIntegerBoxView($fields['scale_decimal_places']));
}

function getThresholdFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$th_arc_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['th_arc_size']));

	return (new CWidgetFieldsGroupView(_('Thresholds')))
		->addField(
			(new CWidgetFieldThresholdsView($fields['thresholds']))->removeLabel()
		)
		->addItem(
			new CTag('hr')
		)
		->addField(
			new CWidgetFieldCheckBoxView($fields['th_show_labels'])
		)
		->addField(
			new CWidgetFieldCheckBoxView($fields['th_show_arc'])
		)
		->addItem([
			$th_arc_size_field->getLabel()->addClass('offset-3'),
			(new CFormField([$th_arc_size_field->getView(), '%']))->addClass('field-size')
		]);
}
