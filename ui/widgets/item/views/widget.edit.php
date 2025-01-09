<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Item value widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\Item\Widget;

$form = new CWidgetFormView($data);

$form
	->addField(
		(new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
			->setPopupParameter('value_types', [
				ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_UINT64,
				ITEM_VALUE_TYPE_TEXT
			])
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['show']))->setColumns(2)
	)
	->addField($data['templateid'] === null
		? new CWidgetFieldMultiSelectOverrideHostView($data['fields']['override_hostid'])
		: null
	)
	->addFieldset(
		(new CWidgetFormFieldsetCollapsibleView(_('Advanced configuration')))
			->addFieldsGroup(
				getDescriptionFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-description')
			)
			->addFieldsGroup(
				getValueFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-value')
			)
			->addFieldsGroup(
				getTimeFieldsGroupView($form, $data['fields'])->addRowClass('fields-group-time')
			)
			->addFieldsGroup(
				getChangeIndicatorFieldsGroupView($data['fields'])->addRowClass('fields-group-change-indicator')
			)
			->addItem(
				(new CWidgetFieldSparklineView($data['fields']['sparkline']))->addRowClass('js-sparkline-row')
			)
			->addField(
				new CWidgetFieldColorView($data['fields']['bg_color'])
			)
			->addFieldsGroup(
				getThresholdFieldsGroupView($data['fields'])->addRowClass('js-row-thresholds')
			)
			->addField(
				(new CWidgetFieldSelectView($data['fields']['aggregate_function']))
					->setFieldHint(
						makeWarningIcon(_('With this setting only numeric items will be displayed.'))
							->addStyle('display: none')
							->setId('item-aggregate-function-warning')
					)
			)
			->addField(
				(new CWidgetFieldTimePeriodView($data['fields']['time_period']))
					->setDateFormat(ZBX_FULL_DATE_TIME)
					->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
					->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			)
			->addField(
				(new CWidgetFieldRadioButtonListView($data['fields']['history']))->setFieldHint(
					makeWarningIcon(
						_('This setting applies only to numeric data. Non-numeric data will always be taken from history.')
					)->setId('item-history-data-warning')
				)
			)
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_item_form.init('.json_encode([
		'thresholds_colors' => Widget::DEFAULT_COLOR_PALETTE
	], JSON_THROW_ON_ERROR).');')
	->show();

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
				->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH - 30)
				->removeLabel()
		)
		->addField(
			new CWidgetFieldRadioButtonListView($fields['desc_h_pos'])
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
	$decimal_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['decimal_size']));
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
			$decimal_size_field->getLabel(),
			(new CFormField([$decimal_size_field->getView(), '%']))->addClass('field-size')
		])
		->addItem(
			new CTag('hr')
		)
		->addField(
			new CWidgetFieldRadioButtonListView($fields['value_h_pos'])
		)
		->addItem([
			$value_size_field->getLabel(),
			(new CFormField([$value_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldRadioButtonListView($fields['value_v_pos'])
		)
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
		->addField(
			(new CWidgetFieldSelectView($fields['units_pos']))->setFieldHint(
				makeHelpIcon(_('Position is ignored for s, uptime and unixtime units.'))
			)
		)
		->addItem([
			$units_size_field->getLabel(),
			(new CFormField([$units_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			(new CWidgetFieldCheckBoxView($fields['units_bold']))->addLabelClass('offset-3')
		)
		->addField(
			(new CWidgetFieldColorView($fields['units_color']))->addLabelClass('offset-3')
		);
}

function getTimeFieldsGroupView(CWidgetFormView $form, array $fields): CWidgetFieldsGroupView {
	$time_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['time_size']));

	return (new CWidgetFieldsGroupView(_('Time')))
		->addField(
			new CWidgetFieldRadioButtonListView($fields['time_h_pos'])
		)
		->addItem([
			$time_size_field->getLabel(),
			(new CFormField([$time_size_field->getView(), '%']))->addClass('field-size')
		])
		->addField(
			new CWidgetFieldRadioButtonListView($fields['time_v_pos'])
		)
		->addField(
			new CWidgetFieldCheckBoxView($fields['time_bold'])
		)
		->addField(
			(new CWidgetFieldColorView($fields['time_color']))->addLabelClass('offset-3')
		);
}

function getChangeIndicatorFieldsGroupView(array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Change indicator')))
		->addItem(
			(new CSvgArrow(['up' => true, 'fill_color' => $fields['up_color']->getValue()]))
				->setId('change-indicator-up')
				->setSize(14, 20))
		->addField(
			(new CWidgetFieldColorView($fields['up_color']))->removeLabel()
		)
		->addItem(
			(new CSvgArrow(['down' => true, 'fill_color' => $fields['down_color']->getValue()]))
				->setId('change-indicator-down')
				->setSize(14, 20),
		)
		->addField(
			(new CWidgetFieldColorView($fields['down_color']))->removeLabel()
		)
		->addItem(
			(new CSvgArrow(['up' => true, 'down' => true, 'fill_color' => $fields['updown_color']->getValue()]))
				->setId('change-indicator-updown')
				->setSize(14, 20)
		)
		->addField(
			(new CWidgetFieldColorView($fields['updown_color']))->removeLabel()
		);
}

function getThresholdFieldsGroupView(array $fields): CWidgetFieldsGroupView {
	return (new CWidgetFieldsGroupView(_('Thresholds')))
		->setFieldHint(
			makeWarningIcon(_('This setting applies only to numeric data.'))->setId('item-thresholds-warning')
		)
		->addField(
			(new CWidgetFieldThresholdsView($fields['thresholds']))->removeLabel()
		);
}
