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

use Zabbix\Widgets\Fields\CWidgetFieldColumnsList;

$form = new CWidgetFormView($data);

$form
	->addField(
		new CWidgetFieldMultiSelectItemView($data['fields']['itemid'], $data['captions']['ms']['items']['itemid'])
	)
	->addField(
		new CWidgetFieldRadioButtonListView($data['fields']['angle'])
	)
	->addField(
		new CWidgetFieldNumericBoxView($data['fields']['min'])
	)
	->addField(
		new CWidgetFieldNumericBoxView($data['fields']['max'])
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
	->addFieldsGroup(_('Needle'), getNeedleFieldsGroupViews($form, $data['fields']),
		'fields-group-needle'
	)
	->addFieldsGroup(_('Min/Max'), getMinMaxFieldsGroupViews($form, $data['fields']),
		'fields-group-minmax'
	)
	->addField(
		new CWidgetFieldColorView($data['fields']['empty_color']),
		'js-row-empty-color'
	)
	->addField(
		new CWidgetFieldColorView($data['fields']['bg_color']),
		'js-row-bg-color'
	)
	->addFieldsGroup([_('Thresholds'),
		makeWarningIcon(_('This setting applies only to numeric data.'))
			->setId('gauge-thresholds-warning')
		],
		getThresholdFieldsGroupViews($form, $data['fields']),
		'fields-group-thresholds'
	)
	->addField(array_key_exists('dynamic', $data['fields'])
		? new CWidgetFieldCheckBoxView($data['fields']['dynamic'])
		: null
	)
	->includeJsFile('widget.edit.js.php')
	->addJavaScript('widget_gauge_form.init('.json_encode([
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
	$value_size = new CWidgetFieldIntegerBoxView($fields['value_size']);
	$value_color = new CWidgetFieldColorView($fields['value_color']);
	$value_arc_size = new CWidgetFieldIntegerBoxView($fields['value_arc_size']);
	$units_show = new CWidgetFieldCheckBoxView($fields['units_show']);
	$units = (new CWidgetFieldTextBoxView($fields['units']))->setAdaptiveWidth(ZBX_TEXTAREA_BIG_WIDTH);
	$units_size = new CWidgetFieldIntegerBoxView($fields['units_size']);
	$units_bold = new CWidgetFieldCheckBoxView($fields['units_bold']);
	$units_color = new CWidgetFieldColorView($fields['units_color']);

	return [
		new CWidgetFieldIntegerBoxView($fields['decimal_places']),

		$form->makeCustomField($value_size, [
			$value_size->getLabel(),
			(new CFormField([$value_size->getView(), '%']))->addClass('field-size')
		]),

		new CWidgetFieldCheckBoxView($fields['value_bold']),

		$form->makeCustomField($value_color, [
			$value_color->getLabel()->addClass('offset-3'),
			new CFormField($value_color->getView())
		]),

		new CWidgetFieldCheckBoxView($fields['value_arc']),

		$form->makeCustomField($value_arc_size, [
			$value_arc_size->getLabel(),
			(new CFormField([$value_arc_size->getView(), '%']))->addClass('field-size')
		]),

		new CTag('hr'),

		(new CDiv([
			$units_show->getView(),
			$units->getLabel()
		]))->addClass('units-show'),

		(new CFormField(
			$units->getView()
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID),

		$form->makeCustomField($units_size, [
			$units_size->getLabel(),
			(new CFormField([$units_size->getView(), '%']))->addClass('field-size')
		]),

		$form->makeCustomField($units_bold, [
			$units_bold->getLabel()->addClass('offset-3'),
			new CFormField($units_bold->getView())
		]),

		(new CWidgetFieldSelectView($fields['units_pos']))
			->setHelpHint(_('Position is ignored for s, uptime and unixtime units.')),

		$form->makeCustomField($units_color, [
			$units_color->getLabel()->addClass('offset-3'),
			new CFormField($units_color->getView())
		])
	];
}

function getNeedleFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$needle_show = new CWidgetFieldCheckBoxView($fields['needle_show']);
	$needle_color = new CWidgetFieldColorView($fields['needle_color']);

	return [
		(new CDiv([
			$needle_show->getView(),
			$needle_color->getLabel()
		]))->addClass('needle-show'),

		new CFormField($needle_color->getView())
	];
}

function getMinMaxFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$minmax_show = new CWidgetFieldCheckBoxView($fields['minmax_show']);
	$minmax_size = new CWidgetFieldIntegerBoxView($fields['minmax_size']);
	$minmax_show_units = new CWidgetFieldCheckBoxView($fields['minmax_show_units']);

	return [
		(new CDiv([
			$minmax_show->getView(),
			$minmax_size->getLabel()
		]))->addClass('minmax-show'),

		$form->makeCustomField($minmax_size, [
			(new CFormField([$minmax_size->getView(), '%']))->addClass('field-size')
		]),

		$form->makeCustomField($minmax_show_units, [
			$minmax_show_units->getLabel(),
			(new CFormField($minmax_show_units->getView()))->addStyle('width: 91px')
		])
	];
}

function getThresholdFieldsGroupViews(CWidgetFormView $form, array $fields): array {
	$thresholds = (new CWidgetFieldThresholdsView($fields['thresholds']));
	$th_arc_size = new CWidgetFieldIntegerBoxView($fields['th_arc_size']);

	return [
		$form->makeCustomField($thresholds, [
			(new CFormField($thresholds->getView()))->addStyle('grid-column: 1 / -1')
		]),

		new CTag('hr'),

		new CWidgetFieldCheckBoxView($fields['th_show_labels']),

		new CWidgetFieldCheckBoxView($fields['th_show_arc']),

		$form->makeCustomField($th_arc_size, [
			$th_arc_size->getLabel()->addClass('offset-3'),
			(new CFormField([$th_arc_size->getView(), '%']))->addClass('field-size')
		]),
	];
}
