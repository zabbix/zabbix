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
 * Honeycomb widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Widgets\Honeycomb\Widget;

$form = new CWidgetFormView($data);

$groupids_field = array_key_exists('groupids', $data['fields'])
	? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
	: null;

$hostids_field = $data['templateid'] === null
	? (new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
		->setFilterPreselect([
			'id' => $groupids_field->getId(),
			'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
			'submit_as' => 'groupid'
		])
	: null;

$form
	->addField($groupids_field)
	->addField($hostids_field)
	->addField(array_key_exists('evaltype_host', $data['fields'])
		? new CWidgetFieldRadioButtonListView($data['fields']['evaltype_host'])
		: null
	)
	->addField(array_key_exists('host_tags', $data['fields'])
		? new CWidgetFieldTagsView($data['fields']['host_tags'])
		: null
	)
	->addField(
		(new CWidgetFieldPatternSelectItemView($data['fields']['items']))
			->setFilterPreselect($hostids_field !== null
				? [
					'id' => $hostids_field->getId(),
					'accept' => CMultiSelect::FILTER_PRESELECT_ACCEPT_ID,
					'submit_as' => 'hostid'
				]
				: []
			)
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
			->addFieldsGroup(
				getLabelFieldsGroupView($form, _('Primary label'), [
					'label' =>					$data['fields']['primary_label'],
					'label_bold' =>				$data['fields']['primary_label_bold'],
					'label_color' =>			$data['fields']['primary_label_color'],
					'label_decimal_places' =>	$data['fields']['primary_label_decimal_places'],
					'label_size' =>				$data['fields']['primary_label_size'],
					'label_size_type' =>		$data['fields']['primary_label_size_type'],
					'label_type' =>				$data['fields']['primary_label_type'],
					'label_units' =>			$data['fields']['primary_label_units'],
					'label_units_pos' =>		$data['fields']['primary_label_units_pos'],
					'label_units_show' =>		$data['fields']['primary_label_units_show']
				])->addRowClass('fields-group-primary-label')
			)
			->addFieldsGroup(
				getLabelFieldsGroupView($form, _('Secondary label'), [
					'label' =>					$data['fields']['secondary_label'],
					'label_bold' =>				$data['fields']['secondary_label_bold'],
					'label_color' =>			$data['fields']['secondary_label_color'],
					'label_decimal_places' =>	$data['fields']['secondary_label_decimal_places'],
					'label_size' =>				$data['fields']['secondary_label_size'],
					'label_size_type' =>		$data['fields']['secondary_label_size_type'],
					'label_type' =>				$data['fields']['secondary_label_type'],
					'label_units' =>			$data['fields']['secondary_label_units'],
					'label_units_pos' =>		$data['fields']['secondary_label_units_pos'],
					'label_units_show' =>		$data['fields']['secondary_label_units_show']
				])->addRowClass('fields-group-secondary-label')
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
		'thresholds_colors' => Widget::DEFAULT_COLOR_PALETTE
	], JSON_THROW_ON_ERROR).');')
	->show();

function getLabelFieldsGroupView(CWidgetFormView $form, string $group_label, array $fields): CWidgetFieldsGroupView {
	$label_size_field = $form->registerField(new CWidgetFieldIntegerBoxView($fields['label_size']));
	$label_size_type_field = $form->registerField(new CWidgetFieldRadioButtonListView($fields['label_size_type']));
	$label_units_show_field = $form->registerField(new CWidgetFieldCheckBoxView($fields['label_units_show']));
	$label_units_field = $form->registerField(
		(new CWidgetFieldTextBoxView($fields['label_units']))->setAdaptiveWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);

	return (new CWidgetFieldsGroupView($group_label))
		->addField(
			(new CWidgetFieldRadioButtonListView($fields['label_type']))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addClass('js-label-type')
		)
		->addField(
			(new CWidgetFieldTextAreaView($fields['label']))
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
				->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addRowClass('js-label')
		)
		->addField(
			(new CWidgetFieldIntegerBoxView($fields['label_decimal_places']))
				->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
				->addRowClass('js-label-decimal-places')
		)
		->addItem([
			$label_size_field->getLabel(),
			new CFormField([
				($label_size_type_field->getView())
					->addClass('js-label-size-type')
					->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				($label_size_field->getView())
					->addClass('label-size')
					->addClass('js-label-size'),
				'%'
			])
		])
		->addField(
			new CWidgetFieldCheckBoxView($fields['label_bold'])
		)
		->addField(
			new CWidgetFieldColorView($fields['label_color'])
		)
		->addItem(
			(new CTag('hr'))->addClass('js-label-units-hr')
		)
		->addItem([
			(new CDiv([$label_units_show_field->getView(), $label_units_field->getLabel()]))
				->addClass('label-units-show')
				->addClass('js-label-units-show'),
			(new CFormField($label_units_field->getView()))->addClass('js-label-units')
		])
		->addField(
			(new CWidgetFieldSelectView($fields['label_units_pos']))->addRowClass('js-label-units-pos')
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
