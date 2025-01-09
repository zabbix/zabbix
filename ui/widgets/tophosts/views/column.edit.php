<?php
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
 * @var CView $this
 * @var array $data
 */

use Widgets\TopHosts\Includes\CWidgetFieldColumnsList;
use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

$form = (new CForm())
	->setId('tophosts_column_edit_form')
	->setName('tophosts_column')
	->addStyle('display: none;')
	->addVar('action', $data['action'])
	->addVar('update', 1);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

$scripts = [];

if (array_key_exists('edit', $data)) {
	$form->addVar('edit', 1);
}

// Name.
$form_grid->addItem([
	(new CLabel(_('Name'), 'column_name'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('name', $data['name'], false))
			->setId('column_name')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
]);

// Data.
$form_grid->addItem([
	new CLabel(_('Data'), 'data'),
	new CFormField(
		(new CSelect('data'))
			->setValue($data['data'])
			->addOptions(CSelect::createOptionsFromArray([
				CWidgetFieldColumnsList::DATA_ITEM_VALUE => _('Item value'),
				CWidgetFieldColumnsList::DATA_HOST_NAME => _('Host name'),
				CWidgetFieldColumnsList::DATA_TEXT => _('Text')
			]))
			->setFocusableElementId('data')
	)
]);

// Item name.
$parameters = [
	'srctbl' => 'items',
	'srcfld1' => 'name',
	'dstfrm' => $form->getName(),
	'dstfld1' => 'item',
	'value_types' => [
		ITEM_VALUE_TYPE_FLOAT,
		ITEM_VALUE_TYPE_STR,
		ITEM_VALUE_TYPE_LOG,
		ITEM_VALUE_TYPE_UINT64,
		ITEM_VALUE_TYPE_TEXT,
		ITEM_VALUE_TYPE_BINARY
	]
];

if ($data['templateid'] === '') {
	$parameters['real_hosts'] = 1;
	$parameters['resolve_macros'] = 1;
}
else {
	$parameters += [
		'hostid' => $data['templateid'],
		'hide_host_filter' => true
	];
}

$item_select = (new CPatternSelect([
	'name' => 'item',
	'object_name' => 'items',
	'data' => $data['item'] === '' ? '' : [$data['item']],
	'multiple' => false,
	'popup' => [
		'parameters' => $parameters
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$scripts[] = $item_select->getPostJS();

$form_grid->addItem([
	(new CLabel(_('Item name'), 'item_ms'))
		->setAsteriskMark()
		->addClass('js-item-row'),
	(new CFormField($item_select))->addClass('js-item-row')
]);

// Text.
$form_grid->addItem([
	(new CLabel(_('Text'), 'text'))
		->setAsteriskMark()
		->addClass('js-text-row'),
	(new CFormField(
		(new CTextBox('text', $data['text']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Text, supports {INVENTORY.*}, {HOST.*} macros'))
	))->addClass('js-text-row')
]);

// Base color.
$form_grid->addItem([
	new CLabel(_('Base color'), 'lbl_base_color'),
	new CFormField(new CColor('base_color', $data['base_color']))
]);

// Display item value as.
$form_grid->addItem([
	(new CLabel(_('Display item value as'), 'display_value_as'))->addClass('js-display-value-as-row'),
	(new CFormField(
		(new CRadioButtonList('display_value_as', (int) $data['display_value_as']))
			->addValue(_('Numeric'), CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC)
			->addValue(_('Text'), CWidgetFieldColumnsList::DISPLAY_VALUE_AS_TEXT)
			->addValue(_('Binary'), CWidgetFieldColumnsList::DISPLAY_VALUE_AS_BINARY)
			->setModern()
	))->addClass('js-display-value-as-row')
]);

// Display.
$form_grid->addItem([
	(new CLabel(_('Display'), 'display'))->addClass('js-display-row'),
	(new CFormField(
		(new CRadioButtonList('display', (int) $data['display']))
			->addValue(_('As is'), CWidgetFieldColumnsList::DISPLAY_AS_IS)
			->addValue(_('Bar'), CWidgetFieldColumnsList::DISPLAY_BAR)
			->addValue(_('Indicators'), CWidgetFieldColumnsList::DISPLAY_INDICATORS)
			->addValue(_('Sparkline'), CWidgetFieldColumnsList::DISPLAY_SPARKLINE)
			->setModern()
	))->addClass('js-display-row')
]);

// Sparkline.
$sparkline = (new CWidgetFieldSparklineView(
	(new CWidgetFieldSparkline('sparkline', _('Sparkline')))
		->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
		->acceptDashboard()
		->acceptWidget()
		->setValue($data['sparkline'])
))->setFormName($form->getName());

$form_grid->addItem([
	$sparkline->getLabel()->addClass('js-sparkline-row'),
	$sparkline->getView()->addClass('js-sparkline-row')
]);

$scripts[] = $sparkline->getJavaScript();

// Min.
$form_grid->addItem([
	(new CLabel(_('Min'), 'min'))->addClass('js-min-max-row'),
	(new CFormField(
		(new CTextBox('min', $data['min']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-max-row')
]);

// Max.
$form_grid->addItem([
	(new CLabel(_('Max'), 'max'))->addClass('js-min-max-row'),
	(new CFormField(
		(new CTextBox('max', $data['max']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-max-row')
]);

// Thresholds.
$threshold_header_row = [
	'',
	_('Threshold'),
	(new CColHeader(''))->setWidth('100%')
];

$thresholds = (new CDiv([
	(new CTable())
		->setId('thresholds_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($threshold_header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(count($threshold_header_row))
		)),
	(new CTemplateTag('thresholds-row-tmpl'))
		->addItem((new CRow([
			(new CColor('thresholds[#{rowNum}][color]', '#{color}'))->appendColorPickerJs(false),
			(new CTextBox('thresholds[#{rowNum}][threshold]', '#{threshold}', false))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('thresholds[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$form_grid->addItem([
	(new CLabel(_('Thresholds'), 'thresholds_table'))->addClass('js-thresholds-row'),
	(new CFormField($thresholds))->addClass('js-thresholds-row')
]);

// Decimal places.
$form_grid->addItem([
	(new CLabel(_('Decimal places'), 'decimal_places'))->addClass('js-decimals-row'),
	(new CFormField(
		(new CNumericBox('decimal_places', $data['decimal_places'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	))->addClass('js-decimals-row')
]);

// Highlights.
$highlight_header_row = [
	'',
	_('Regular expression'),
	(new CColHeader(''))->setWidth('100%')
];

$highlights = (new CDiv([
	(new CTable())
		->setId('highlights_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($highlight_header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(count($highlight_header_row))
		)),
	(new CTemplateTag('highlights-row-tmpl'))
		->addItem((new CRow([
			(new CColor('highlights[#{rowNum}][color]', '#{color}'))->appendColorPickerJs(false),
			(new CTextBox('highlights[#{rowNum}][pattern]', '#{pattern}', false))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired(),
			(new CButton('highlights[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$form_grid->addItem([
	(new CLabel(_('Highlights'), 'highlights_table'))->addClass('js-highlights-row'),
	(new CFormField($highlights))->addClass('js-highlights-row')
]);

// Show thumbnail.
$form_grid->addItem([
	(new CLabel(_('Show thumbnail'), 'show_thumbnail'))->addClass('js-show-thumbnail-row'),
	(new CFormField(
		(new CCheckBox('show_thumbnail'))->setChecked($data['show_thumbnail'])
	))->addClass('js-show-thumbnail-row')
]);

$time_period_field_view = (new CWidgetFieldTimePeriodView($data['time_period_field']))
	->setDateFormat(ZBX_FULL_DATE_TIME)
	->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
	->setFormName('tophosts_column');

// Advanced configuration.
$advanced_configuration_fieldset = (new CFormFieldsetCollapsible(_('Advanced configuration')))
	->setId('advanced-configuration')
	->addItem([
		new CLabel(_('Aggregation function'), 'column_aggregate_function'),
		new CFormField(
			(new CSelect('aggregate_function'))
				->setId('aggregate_function')
				->setValue($data['aggregate_function'])
				->addOptions(CSelect::createOptionsFromArray([
					AGGREGATE_NONE => CItemHelper::getAggregateFunctionName(AGGREGATE_NONE),
					AGGREGATE_MIN => CItemHelper::getAggregateFunctionName(AGGREGATE_MIN),
					AGGREGATE_MAX => CItemHelper::getAggregateFunctionName(AGGREGATE_MAX),
					AGGREGATE_AVG => CItemHelper::getAggregateFunctionName(AGGREGATE_AVG),
					AGGREGATE_COUNT => CItemHelper::getAggregateFunctionName(AGGREGATE_COUNT),
					AGGREGATE_SUM => CItemHelper::getAggregateFunctionName(AGGREGATE_SUM),
					AGGREGATE_FIRST => CItemHelper::getAggregateFunctionName(AGGREGATE_FIRST),
					AGGREGATE_LAST => CItemHelper::getAggregateFunctionName(AGGREGATE_LAST)
				]))
				->setFocusableElementId('column_aggregate_function')
		)
	]);

foreach ($time_period_field_view->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
	$advanced_configuration_fieldset->addItem([
		$label,
		(new CFormField($view))->addClass($class)
	]);
}

$advanced_configuration_fieldset
	->addItem([
		(new CLabel(_('History data'), 'history'))->addClass('js-history-row'),
		(new CFormField(
			(new CRadioButtonList('history', (int) $data['history']))
				->addValue(_('Auto'), CWidgetFieldColumnsList::HISTORY_DATA_AUTO)
				->addValue(_('History'), CWidgetFieldColumnsList::HISTORY_DATA_HISTORY)
				->addValue(_('Trends'), CWidgetFieldColumnsList::HISTORY_DATA_TRENDS)
				->setModern()
		))->addClass('js-history-row')
	]);

$form_grid
	->addItem($advanced_configuration_fieldset)
	->addItem(new CScriptTag([
		'document.forms.tophosts_column.fields = {};',
		$time_period_field_view->getJavaScript()
	]));

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			tophosts_column_edit_form.init('.json_encode([
				'form_id' => $form->getId(),
				'thresholds' => $data['thresholds'],
				'highlights' => $data['highlights'],
				'colors' => $data['colors'],
				'groupids' => array_key_exists('groupids', $data) ? $data['groupids'] : [],
				'hostids' => array_key_exists('hostids', $data) ? $data['hostids'] : []
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

$output = [
	'header'		=> array_key_exists('edit', $data) ? _('Update column') : _('New column'),
	'script_inline'	=> implode('', $scripts).$this->readJsFile('column.edit.js.php', null, ''),
	'body'			=> $form->toString(),
	'buttons'		=> [
		[
			'title'		=> array_key_exists('edit', $data) ? _('Update') : _('Add'),
			'keepOpen'	=> true,
			'isSubmit'	=> true,
			'action'	=> 'tophosts_column_edit_form.submit();'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output, JSON_THROW_ON_ERROR);
