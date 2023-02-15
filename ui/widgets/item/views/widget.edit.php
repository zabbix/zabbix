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
 * Item value widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Zabbix\Widgets\Fields\CWidgetFieldColumnsList;

$form = new CWidgetFormView($data);

$form
	->addField(
		new CWidgetFieldMultiSelectItemView($data['fields']['itemid'], $data['captions']['ms']['items']['itemid'])
	)
	->addField(
		(new CWidgetFieldCheckBoxListView($data['fields']['show']))
			->addClass(ZBX_STYLE_GRID_COLUMNS)
			->addClass(ZBX_STYLE_GRID_COLUMNS_2)
	)
	->addField(
		new CWidgetFieldCheckBoxView($data['fields']['adv_conf'])
	)
	->addFieldsGroup([
		_('Description'),
		makeHelpIcon([
			_('Supported macros:'),
			(new CList([
				'{HOST.*}',
				'{ITEM.*}',
				'{INVENTORY.*}',
				_('User macros')
			]))->addClass(ZBX_STYLE_LIST_DASHED)
		])
	], getDescriptionFieldsGroupViews($form, $data['fields']),
		'fields-group-description'
	)
	->addFieldsGroup(_('Value'), getValueFieldsGroupViews($form, $data['fields']),
		'fields-group-value'
	)
	->addFieldsGroup(_('Time'), getTimeFieldsGroupViews($form, $data['fields']),
		'fields-group-time'
	)
	->addFieldsGroup(_('Change indicator'), getChangeIndicatorFieldsGroupViews($form, $data['fields']),
		'fields-group-change-indicator'
	)
	->addField(
		new CWidgetFieldColorView($data['fields']['bg_color']),
		'js-row-bg-color'
	)
	->addField(
		(new CWidgetFieldThresholdsView($data['fields']['thresholds']))
			->setHint(
				makeWarningIcon(_('This setting applies only to numeric data.'))->setId('item-value-thresholds-warning')
			),
		'js-row-thresholds'
	)
	->addField(array_key_exists('dynamic', $data['fields'])
		? new CWidgetFieldCheckBoxView($data['fields']['dynamic'])
		: null
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_item_form.init('.json_encode([
		'thresholds_colors' => CWidgetFieldColumnsList::THRESHOLDS_DEFAULT_COLOR_PALETTE
	], JSON_THROW_ON_ERROR).');')
	->show();

function getDescriptionFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$description = (new CWidgetFieldTextAreaView($fields['description']))
		->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH - 38);
	$desc_size = new CWidgetFieldIntegerBoxView($fields['desc_size']);
	$desc_color = new CWidgetFieldColorView($fields['desc_color']);

	return [
		$form->makeCustomField($description, [
			new CFormField(
				$description->getView()->setAttribute('maxlength', DB::getFieldLength('widget_field', 'value_str'))
			)
		]),

		new CWidgetFieldRadioButtonListView($fields['desc_h_pos']),

		$form->makeCustomField($desc_size, [
			$desc_size->getLabel(),
			(new CFormField([$desc_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldRadioButtonListView($fields['desc_v_pos']),

		new CWidgetFieldCheckBoxView($fields['desc_bold']),

		$form->makeCustomField($desc_color, [
			$desc_color->getLabel()->addClass('offset-3'),
			new CFormField($desc_color->getView())
		])
	];
}

function getValueFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$decimal_size = new CWidgetFieldIntegerBoxView($fields['decimal_size']);
	$value_size = new CWidgetFieldIntegerBoxView($fields['value_size']);
	$value_color = new CWidgetFieldColorView($fields['value_color']);
	$units_show = new CWidgetFieldCheckBoxView($fields['units_show']);
	$units = (new CWidgetFieldTextBoxView($fields['units']))->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH);
	$units_size = new CWidgetFieldIntegerBoxView($fields['units_size']);
	$units_bold = new CWidgetFieldCheckBoxView($fields['units_bold']);
	$units_color = new CWidgetFieldColorView($fields['units_color']);

	return [
		new CWidgetFieldIntegerBoxView($fields['decimal_places']),

		$form->makeCustomField($decimal_size, [
			$decimal_size->getLabel(),
			(new CFormField([$decimal_size->getView(), '%']))->addClass('field-size')
		]),

		new CTag('hr'),

		new CWidgetFieldRadioButtonListView($fields['value_h_pos']),

		$form->makeCustomField($value_size, [
			$value_size->getLabel(),
			(new CFormField([$value_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldRadioButtonListView($fields['value_v_pos']),

		new CWidgetFieldCheckBoxView($fields['value_bold']),

		$form->makeCustomField($value_color, [
			$value_color->getLabel()->addClass('offset-3'),
			new CFormField($value_color->getView())
		]),

		new CTag('hr'),

		(new CDiv([
			$units_show->getView(),
			$units->getLabel()
		]))->addClass('units-show'),

		(new CFormField(
			$units->getView()
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID),

		(new CWidgetFieldSelectView($fields['units_pos']))
			->setHelpHint(_('Position is ignored for s, uptime and unixtime units.')),

		$form->makeCustomField($units_size, [
			$units_size->getLabel(),
			(new CFormField([$units_size->getView(), '%']))->addClass('field-size')
		]),

		$form->makeCustomField($units_bold, [
			$units_bold->getLabel()->addClass('offset-3'),
			new CFormField($units_bold->getView())
		]),

		$form->makeCustomField($units_color, [
			$units_color->getLabel()->addClass('offset-3'),
			new CFormField($units_color->getView())
		])
	];
}

function getTimeFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$time_size = new CWidgetFieldIntegerBoxView($fields['time_size']);
	$time_color = new CWidgetFieldColorView($fields['time_color']);

	return [
		new CWidgetFieldRadioButtonListView($fields['time_h_pos']),

		$form->makeCustomField($time_size, [
			$time_size->getLabel(),
			(new CFormField([$time_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldRadioButtonListView($fields['time_v_pos']),

		new CWidgetFieldCheckBoxView($fields['time_bold']),

		$form->makeCustomField($time_color, [
			$time_color->getLabel()->addClass('offset-3'),
			new CFormField($time_color->getView())
		])
	];
}

function getChangeIndicatorFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$up_color = new CWidgetFieldColorView($fields['up_color']);
	$down_color = new CWidgetFieldColorView($fields['down_color']);
	$updown_color = new CWidgetFieldColorView($fields['updown_color']);

	return [
		(new CSvgArrow(['up' => true, 'fill_color' => $fields['up_color']->getValue()]))
			->setId('change-indicator-up')
			->setSize(14, 20),
		$form->makeCustomField($up_color, [
			new CFormField($up_color->getView())
		]),

		(new CSvgArrow(['down' => true, 'fill_color' => $fields['down_color']->getValue()]))
			->setId('change-indicator-down')
			->setSize(14, 20),
		$form->makeCustomField($down_color, [
			new CFormField($down_color->getView())
		]),

		(new CSvgArrow(['up' => true, 'down' => true, 'fill_color' => $fields['updown_color']->getValue()]))
			->setId('change-indicator-updown')
			->setSize(14, 20),
		$form->makeCustomField($updown_color, [
			new CFormField($updown_color->getView())
		])
	];
}
