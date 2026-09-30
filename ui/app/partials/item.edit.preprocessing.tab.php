<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * @var CPartial $this
 * @var array    $data
 */


$preproc_types_select = (new CSelect('preprocessing[#{rowNum}][type]'))
	->setErrorLabel(_('Name'))
	->setErrorContainer('preprocessing-#{rowNum}-error-container')
	->setId('preprocessing_#{rowNum}_type')
	->setValue('#{type}')
	->setAttribute('data-prevent-validation-on-change', '')
	->setWidthAuto();

$default_step_type = null;

foreach (get_preprocessing_types(null, true, $data['preprocessing_types']) as $group) {
	$opt_group = new CSelectOptionGroup($group['label']);

	foreach ($group['types'] as $type => $label) {
		if (!$default_step_type) {
			$default_step_type = $type;
		}

		$opt_group->addOption(new CSelectOption($type, $label));
	}

	$preproc_types_select->addOptionGroup($opt_group);
}

(new CDiv([
	(new CTemplateTag('', [
		(new CListItem([
			(new CDiv([
				(new CDiv(new CVar('preprocessing[#{rowNum}][sortorder]', '#{sortorder}')))->addClass(ZBX_STYLE_DRAG_ICON),
				(new CDiv($preproc_types_select))
					->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)
					->addClass('step-name'),
				(new CDiv())->addClass('step-parameters'),
				(new CDiv(new CCheckBox('preprocessing[#{rowNum}][on_fail]')))->addClass('step-on-fail'),
				(new CDiv([
					(new CButton('preprocessing[#{rowNum}][test]', _('Test')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('preprocessing-step-test')
						->removeId(),
					(new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
						->removeId()
				]))->addClass('step-action')
			]))->addClass('preprocessing-step'),
			(new CDiv([
				new CLabel(_('Custom on fail'), 'label-preprocessing-#{rowNum}-error-handler'),
				(new CSelect('preprocessing[#{rowNum}][error_handler]'))
					->setId('preprocessing-#{rowNum}-error-handler')
					->setFocusableElementId('label-preprocessing-#{rowNum}-error-handler')
					->setValue('#{error_handler}')
					->addOptions(CSelect::createOptionsFromArray([
						ZBX_PREPROC_FAIL_DISCARD_VALUE => _('Discard value'),
						ZBX_PREPROC_FAIL_SET_VALUE => _('Set value to'),
						ZBX_PREPROC_FAIL_SET_ERROR => _('Set error to')
					])),
				(new CTextAreaFlexible('preprocessing[#{rowNum}][error_handler_params]', '#{error_handler_params}'))
					->setErrorLabel(_('Error message'))
					->setErrorContainer('preprocessing-#{rowNum}-error-container')
			]))
				->addClass('on-fail-options'),
			(new CDiv())->setId("preprocessing-#{rowNum}-error-container")
		]))
			->addClass('preprocessing-list-item')
			->setAttribute('data-step', '#{rowNum}'),
		(new CListItem(''))
			->setId("preprocessing-#{rowNum}-error-container")
			->addClass('error-container-row')
	]))->addClass('js-preprocessing-steps-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setErrorLabel(_('Number'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('number'))
			->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH)
	]))->addClass('preprocessing-steps-parameters-multiplier-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('List of characters'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('list of characters'))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	]))->addClass('preprocessing-steps-parameters-trim-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('XPath'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('XPath'))
	]))->addClass('preprocessing-steps-parameters-xpath-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('pattern'))
	]))->addClass('preprocessing-steps-parameters-regex-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('JSON path'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('$.path.to.node'))
	]))->addClass('preprocessing-steps-parameters-json-path-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setErrorLabel(_('Seconds'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('seconds'))
			->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH)
	]))->addClass('preprocessing-steps-parameters-throttle-timed-value-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setErrorLabel(_('Min'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('min')),
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_1]', '#{params[1]}'))
			->setErrorLabel(_('Max'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('max'))
	]))->addClass('preprocessing-steps-parameters-validate-range-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Search string'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('search string')),
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_1]', '#{params[1]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Replacement'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('replacement'))
	]))->addClass('preprocessing-steps-parameters-replace-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('pattern')),
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_1]', '#{params[1]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Output'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('output'))
	]))->addClass('preprocessing-steps-parameters-regsub-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('placeholder', _('delimiter'))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAttribute('maxlength', 1)
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Delimiter'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container'),
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_1]', '#{params[1]}'))
			->setAttribute('placeholder', _('qualifier'))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAttribute('maxlength', 1)
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Qualifier'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container'),
		(new CCheckBox('preprocessing[#{rowNum}][params_2]', ZBX_PREPROC_CSV_HEADER))
			->setLabel(_('With header row'))
			->setUncheckedValue(ZBX_PREPROC_CSV_NO_HEADER)
			->setChecked(true)
	]))->addClass('preprocessing-steps-parameters-csv-to-json-tmpl'),
	(new CTemplateTag('', [
		(new CMultilineInput('preprocessing[#{rowNum}][params_0]', '',
			['add_post_js' => false, 'use_tab' => false]
		))
			->setAttribute('data-value-init', '#{params[0]}')
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Script'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
	]))->addClass('preprocessing-steps-parameters-script-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('<metric name>{<label name>="<label value>", ...} == <value>')),
		(new CSelect('preprocessing[#{rowNum}][params_1]'))
			->setValue('#{params[1]}')
			->addOptions(CSelect::createOptionsFromArray([
				ZBX_PREPROC_PROMETHEUS_VALUE => _('value'),
				ZBX_PREPROC_PROMETHEUS_LABEL => _('label'),
				ZBX_PREPROC_PROMETHEUS_SUM => 'sum',
				ZBX_PREPROC_PROMETHEUS_MIN => 'min',
				ZBX_PREPROC_PROMETHEUS_MAX => 'max',
				ZBX_PREPROC_PROMETHEUS_AVG => 'avg',
				ZBX_PREPROC_PROMETHEUS_COUNT => 'count'
			]))
			->addClass('js-preproc-param-prometheus-pattern-function'),
		(new CTextBox('preprocessing[#{rowNum}][params_2]', '#{params[2]}'))
			->setErrorLabel(_('Label'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->addClass('js-preproc-param-prometheus-pattern-label')
			->setAttribute('placeholder', _('<label name>'))
	]))->addClass('preprocessing-steps-parameters-prometheus-pattern-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('<metric name>{<label name>="<label value>", ...} == <value>'))
	]))->addClass('preprocessing-steps-parameters-prometheus-to-json-tmpl'),
	(new CTemplateTag('', [
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_0]', '#{params[0]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('OID'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('OID')),
		(new CSelect('preprocessing[#{rowNum}][params_1]'))
			->setValue('#{params[1]}')
			->setAdaptiveWidth(202)
			->addOptions([
				new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
				new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
				new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
				new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
			])
	]))->addClass('preprocessing-steps-parameters-snmp-walk-value-tmpl'),
	(new CTemplateTag('', [
		(new CDiv(
			(new CTable())
				->addClass('group-json-mapping')
				->setHeader(
					(new CRowHeader([
						new CColHeader(_('Field name')),
						new CColHeader(_('OID prefix')),
						new CColHeader(_('Format')),
						(new CColHeader(''))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass(ZBX_STYLE_GREY)
				)
				->addItem(
					(new CTag('tfoot', true))
						->addItem(
							(new CCol(
								(new CButtonLink(_('Add')))->addClass('js-group-json-action-add')
							))->setColSpan(4)
						)
				)
		))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	]))->addClass('preprocessing-steps-parameters-snmp-walk-to-json-tmpl'),
	(new CTemplateTag('', [
		(new CSelect('preprocessing[#{rowNum}][params_0_not_supported]'))
			->addOptions(CSelect::createOptionsFromArray([
				ZBX_PREPROC_MATCH_ERROR_ANY => _('any error'),
				ZBX_PREPROC_MATCH_ERROR_REGEX => _('error matches'),
				ZBX_PREPROC_MATCH_ERROR_NOT_REGEX => _('error does not match')
			]))
			->setAttribute('placeholder', _('error-matching'))
			->addClass('js-preproc-param-error-matching')
			->setValue('#{params[0]}'),
		(new CTextAreaFlexible('preprocessing[#{rowNum}][params_1_not_supported]', '#{params[1]}'))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->removeId()
			->setAttribute('placeholder', _('pattern'))
			->addClass('js-preproc-param-error-matching-input')
	]))->addClass('preprocessing-steps-parameters-check-not-supported-tmpl'),
	(new CTemplateTag('', [
		(new CSelect('preprocessing[#{rowNum}][params_0]'))
			->setValue('#{params[0]}')
			->setAdaptiveWidth(202)
			->addOptions([
				new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
				new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
				new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
			])
	]))->addClass('preprocessing-steps-parameters-snmp-get-value-tmpl'),
	(new CTemplateTag('', [
		(new CRow([
			new CCol(
				(new CTextBox('preprocessing[#{rowNum}][params_set_snmp][#{rowIndex}][name]', '#{name}'))
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('Field name'))
					->setErrorContainer('preprocessing-#{rowNum}-params_set_snmp-#{rowIndex}-error-container')
					->removeId()
					->setAttribute('placeholder', _('Field name'))
			),
			new CCol(
				(new CTextBox('preprocessing[#{rowNum}][params_set_snmp][#{rowIndex}][oid_prefix]', '#{oid_prefix}'))
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('OID prefix'))
					->setErrorContainer('preprocessing-#{rowNum}-params_set_snmp-#{rowIndex}-error-container')
					->removeId()
					->setAttribute('placeholder', _('OID prefix'))
			),
			new CCol(
				(new CSelect('preprocessing[#{rowNum}][params_set_snmp][#{rowIndex}][format]'))
					->setValue('#{format}')
					->setWidth(ZBX_TEXTAREA_PREPROC_TREAT_SELECT)
					->addOptions([
						new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
						new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
						new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
						new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
					])
			),
			(new CCol(
				(new CButtonLink(_('Remove')))->addClass('js-group-json-action-delete')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setAttribute('data-index', '#{rowIndex}')->addClass('group-json-row'),
		(new CRow(
			(new CCol())
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
				->setId('preprocessing-#{rowNum}-params_set_snmp-#{rowIndex}-error-container')
				->setColSpan(4)
		))->addClass('error-container-row')
	]))->addClass('preprocessing-steps-parameters-snmp-walk-to-json-row-tmpl')
]))
	->addClass('js-templates')
	->show();

$helper_icon_texts = [
	_('Preprocessing is a transformation before saving the value to the database. It is possible to define a sequence of preprocessing steps, and those are executed in the order they are set.')
];

if (in_array(ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, $data['preprocessing_types'])) {
	$helper_icon_texts = array_merge($helper_icon_texts, [
		BR(), BR(),
		_('However, if "Check for not supported value" steps are configured, they are always placed and executed first (with "any error" being the last of them).')
	]);
}

$formgrid = (new CFormGrid())
	->setId('item_preproc_list')
	->addItem([
		new CLabel([_('Preprocessing steps'), makeHelpIcon($helper_icon_texts)]),
		new CFormField(
			(new CList())
				->setId('preprocessing')
				->addClass('preprocessing-list')
				->addClass(ZBX_STYLE_LIST_NUMBERED)
				->setAttribute('data-field-type', 'set')
				->setAttribute('data-field-name', 'preprocessing')
				->setAttribute('data-steptype', $default_step_type)
				->addItem(
					(new CListItem([
						(new CDiv(_('Name')))->addClass('step-name'),
						(new CDiv(_('Parameters')))->addClass('step-parameters'),
						(new CDiv(_('Custom on fail')))
							->addClass('step-on-fail')
							->setTitle(_('Custom on fail')),
						(new CDiv(_('Actions')))->addClass('step-action')
					]))
						->addClass('preprocessing-list-head')
				)
				->addItem(
					(new CListItem([
						(new CDiv(
							(new CButton('param_add', _('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-add')
						))->addClass('step-action'),
						(new CDiv(
							(new CButton('preproc_test_all', _('Test all steps')))
								->addClass(ZBX_STYLE_BTN_LINK)
						))->addClass('step-action')
					]))->addClass('preprocessing-list-foot')
				)
		)
	]);

if (array_key_exists('value_types', $data)) {
	$formgrid->addItem([
		(new CLabel(_('Type of information'), 'label-value-type-steps'))
			->addClass('js-item-preprocessing-type'),
		(new CFormField((new CSelect('value_type_steps'))
			->setFocusableElementId('label-value-type-steps')
			->setValue($data['value_type'])
			->addOptions(CSelect::createOptionsFromArray($data['value_types']))
		))->addClass('js-item-preprocessing-type')
	]);
}

$formgrid->show();
