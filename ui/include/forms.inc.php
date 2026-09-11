<?php
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
 * Get list of item pre-processing data and return a prepared HTML object.
 *
 * @param array  $preprocessing                            Array of item pre-processing steps.
 * @param string $preprocessing[]['type']                  Pre-processing step type.
 * @param array  $preprocessing[]['params']                Additional parameters used by pre-processing.
 * @param string $preprocessing[]['error_handler']         Action type used in case of pre-processing step failure.
 * @param string $preprocessing[]['error_handler_params']  Error handler parameters.
 * @param bool   $readonly                                 True if fields should be read only.
 * @param array  $types                                    Supported pre-processing types.
 *
 * @return CList
 */
function getItemPreprocessing(array $preprocessing, $readonly, array $types) {
	$script_maxlength = DB::getFieldLength('item_preproc', 'params');
	$preprocessing_list = (new CList())
		->setId('preprocessing')
		->addClass('preprocessing-list')
		->addClass(ZBX_STYLE_LIST_NUMBERED)
		->setAttribute('data-readonly', $readonly)
		->setAttribute('data-field-type', 'set')
		->setAttribute('data-field-name', 'preprocessing')
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
				->addStyle(!$preprocessing ? 'display: none;' : null)
		);

	$i = 0;

	foreach ($preprocessing as $step) {
		$step = CItemGeneralHelper::normalizeFormDataPreprocessingStep($step);

		// Create a select with preprocessing types.
		$preproc_types_select = (new CSelect('preprocessing['.$i.'][type]'))
			->setErrorLabel(_('Name'))
			->setErrorContainer("preprocessing-$i-error-container")
			->setId('preprocessing_'.$i.'_type')
			->setValue($step['type'])
			->setAttribute('data-prevent-validation-on-change', '')
			->setReadonly($readonly)
			->setWidthAuto();

		foreach (get_preprocessing_types(null, true, $types) as $group) {
			$opt_group = new CSelectOptionGroup($group['label']);

			foreach ($group['types'] as $type => $label) {
				$opt_group->addOption((new CSelectOption($type, $label))->setDisabled(
					$step['type'] != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED && $type == $step['type']
				));
			}

			$preproc_types_select->addOptionGroup($opt_group);
		}

		// Depending on preprocessing type, display corresponding params field and placeholders.
		$params = '';

		if (!in_array($step['type'], [ZBX_PREPROC_SNMP_WALK_TO_JSON, ZBX_PREPROC_VALIDATE_NOT_SUPPORTED])) {
			// Create a primary param text box, so it can be hidden if necessary.
			$step_param_0_value = array_key_exists('params', $step) && array_key_exists(0, $step['params'])
				? $step['params'][0]
				: '';
			$step_param_0 = (new CTextAreaFlexible('preprocessing['.$i.'][params_0]', $step_param_0_value))
				->setReadonly($readonly);

			// Create a secondary param text box, so it can be hidden if necessary.
			$step_param_1_value = (array_key_exists('params', $step) && array_key_exists(1, $step['params']))
				? $step['params'][1]
				: '';
			$step_param_1 = (new CTextAreaFlexible('preprocessing['.$i.'][params_1]', $step_param_1_value))
				->setReadonly($readonly);
		}
		elseif ($step['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
			// Create a primary param text box, so it can be hidden if necessary.
			$step_param_0_value = array_key_exists('params', $step) && array_key_exists(0, $step['params'])
				? $step['params'][0]
				: '';
			$step_param_0 = (new CTextAreaFlexible('preprocessing['.$i.'][params_0_not_supported]', $step_param_0_value))
				->setReadonly($readonly);

			// Create a secondary param text box, so it can be hidden if necessary.
			$step_param_1_value = (array_key_exists('params', $step) && array_key_exists(1, $step['params']))
				? $step['params'][1]
				: '';
			$step_param_1 = (new CTextAreaFlexible('preprocessing['.$i.'][params_1_not_supported]', $step_param_1_value))
				->setReadonly($readonly);
		}

		// Add corresponding placeholders and show or hide text boxes.
		switch ($step['type']) {
			case ZBX_PREPROC_MULTIPLIER:
				$params = $step_param_0
					->setErrorLabel(_('Number'))
					->setErrorContainer("preprocessing-$i-error-container")
					->setAttribute('placeholder', _('number'))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);
				break;

			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
				$params = $step_param_0
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('List of characters'))
					->setErrorContainer("preprocessing-$i-error-container")
					->setAttribute('placeholder', _('list of characters'))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);
				break;

			case ZBX_PREPROC_XPATH:
			case ZBX_PREPROC_ERROR_FIELD_XML:
				$params = $step_param_0
					->setAttribute('placeholder', _('XPath'))
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('XPath'))
					->setErrorContainer("preprocessing-$i-error-container");
				break;

			case ZBX_PREPROC_JSONPATH:
			case ZBX_PREPROC_ERROR_FIELD_JSON:
				$params = $step_param_0
					->setErrorLabel(_('JSON path'))
					->setErrorContainer("preprocessing-$i-error-container")
					->setAttribute('data-notrim', '')
					->setAttribute('placeholder', _('$.path.to.node'));
				break;

			case ZBX_PREPROC_REGSUB:
			case ZBX_PREPROC_ERROR_FIELD_REGEX:
				$params = [
					$step_param_0
						->setAttribute('data-notrim', '')
						->setErrorLabel(_('Pattern'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('pattern')),
					$step_param_1
						->setAttribute('data-notrim', '')
						->setErrorLabel(_('Output'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('output'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_RANGE:
				$params = [
					$step_param_0
						->setErrorLabel(_('Min'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('min')),
					$step_param_1
						->setErrorLabel(_('Max'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('max'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_REGEX:
			case ZBX_PREPROC_VALIDATE_NOT_REGEX:
				$params = $step_param_0
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('Pattern'))
					->setErrorContainer("preprocessing-$i-error-container")
					->setAttribute('placeholder', _('pattern'));
				break;

			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
				$params = $step_param_0
					->setAttribute('placeholder', _('seconds'))
					->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH)
					->setErrorLabel(_('Seconds'))
					->setErrorContainer("preprocessing-$i-error-container");
				break;

			case ZBX_PREPROC_SCRIPT:
				$params = new CMultilineInput('preprocessing['.$i.'][params_0]', $step_param_0_value, [
					'title' => _('JavaScript'),
					'placeholder' => _('script'),
					'placeholder_textarea' => 'return value',
					'label_before' => 'function (value) {',
					'label_after' => '}',
					'grow' => 'auto',
					'rows' => 0,
					'maxlength' => $script_maxlength,
					'readonly' => $readonly
				]);
				$params
					->setErrorLabel(_('Script'))
					->setErrorContainer("preprocessing-$i-error-container")
					->setAttribute('data-notrim', '');
				break;

			case ZBX_PREPROC_PROMETHEUS_PATTERN:
				$step_param_2_value = (array_key_exists('params', $step) && array_key_exists(2, $step['params']))
					? $step['params'][2]
					: '';

				if ($step_param_1_value === ZBX_PREPROC_PROMETHEUS_FUNCTION) {
					$step_param_1_value = $step_param_2_value;
					$step_param_2_value = '';
				}

				$params = [
					$step_param_0
						->setErrorLabel(_('Pattern'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('<metric name>{<label name>="<label value>", ...} == <value>')),
					(new CSelect('preprocessing['.$i.'][params_1]'))
						->setAttribute('data-prevent-validation-on-change', '')
						->addOptions(CSelect::createOptionsFromArray([
							ZBX_PREPROC_PROMETHEUS_VALUE => _('value'),
							ZBX_PREPROC_PROMETHEUS_LABEL => _('label'),
							ZBX_PREPROC_PROMETHEUS_SUM => 'sum',
							ZBX_PREPROC_PROMETHEUS_MIN => 'min',
							ZBX_PREPROC_PROMETHEUS_MAX => 'max',
							ZBX_PREPROC_PROMETHEUS_AVG => 'avg',
							ZBX_PREPROC_PROMETHEUS_COUNT => 'count'
						]))
						->addClass('js-preproc-param-prometheus-pattern-function')
						->setValue($step_param_1_value)
						->setReadonly($readonly),
					(new CTextBox('preprocessing['.$i.'][params_2]', $step_param_2_value))
						->setErrorLabel(_('Label'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setTitle($step_param_2_value)
						->setAttribute('placeholder', _('<label name>'))
						->setEnabled($step_param_1_value === ZBX_PREPROC_PROMETHEUS_LABEL)
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_PROMETHEUS_TO_JSON:
				$params = $step_param_0
					->setErrorLabel(_('Pattern'))
					->setErrorContainer("preprocessing-$i-error-container")
					->setAttribute('placeholder', _('<metric name>{<label name>="<label value>", ...} == <value>'));
				break;

			case ZBX_PREPROC_CSV_TO_JSON:
				$step_param_2_value = (array_key_exists('params', $step) && array_key_exists(2, $step['params']))
					? $step['params'][2]
					: ZBX_PREPROC_CSV_NO_HEADER;

				$params = [
					$step_param_0
						->setAttribute('data-notrim', '')
						->setAttribute('placeholder', _('delimiter'))
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAttribute('maxlength', 1),
					$step_param_1
						->setAttribute('data-notrim', '')
						->setAttribute('placeholder', _('qualifier'))
						->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
						->setAttribute('maxlength', 1),
					(new CCheckBox('preprocessing['.$i.'][params_2]', ZBX_PREPROC_CSV_HEADER))
						->setLabel(_('With header row'))
						->setUncheckedValue(ZBX_PREPROC_CSV_NO_HEADER)
						->setChecked($step_param_2_value == ZBX_PREPROC_CSV_HEADER)
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_STR_REPLACE:
				$params = [
					$step_param_0
						->setAttribute('data-notrim', '')
						->setErrorLabel(_('Search string'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('search string')),
					$step_param_1
						->setAttribute('data-notrim', '')
						->setErrorLabel(_('Replacement'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('replacement'))
				];
				break;

			case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
				if ($step_param_0_value == '') {
					$step_param_0_value = ZBX_PREPROC_MATCH_ERROR_ANY;
				}

				$params = [
					(new CSelect('preprocessing['.$i.'][params_0_not_supported]'))
						->addOptions(CSelect::createOptionsFromArray([
							ZBX_PREPROC_MATCH_ERROR_ANY => _('any error'),
							ZBX_PREPROC_MATCH_ERROR_REGEX => _('error matches'),
							ZBX_PREPROC_MATCH_ERROR_NOT_REGEX => _('error does not match')
						]))
							->setAttribute('placeholder', _('error-matching'))
							->addClass('js-preproc-param-error-matching')
							->setErrorContainer('preprocessing-'.$i.'-error-container')
							->setValue($step_param_0_value)
							->setReadonly($readonly),
					$step_param_1
						->setAttribute('data-notrim', '')
						->setErrorLabel( _('Pattern'))
						->setErrorContainer("preprocessing-$i-error-container")
						->setAttribute('placeholder', _('pattern'))
						->setReadonly($readonly)
						->addClass(
							$step_param_0_value == ZBX_PREPROC_MATCH_ERROR_ANY ? ZBX_STYLE_DISPLAY_NONE : null
						)
				];
				break;


			case ZBX_PREPROC_SNMP_WALK_VALUE:
				$params = [
					$step_param_0
						->setAttribute('placeholder', _('OID'))
						->setErrorLabel(_('OID'))
						->setErrorContainer("preprocessing-$i-error-container"),
					(new CSelect('preprocessing['.$i.'][params_1]'))
						->setValue($step_param_1_value)
						->setAdaptiveWidth(202)
						->addOptions([
							new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
							new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
							new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
							new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
						])
						->setReadonly($readonly)
				];
				break;

			case ZBX_PREPROC_SNMP_WALK_TO_JSON:
				$mapping_rows = [];
				$count = count($step['params']);

				for ($j = 0; $j < $count; $j += 3) {
					$row = count($mapping_rows);
					$mapping_rows[] = [
						(new CRow([
							new CCol(
								(new CTextBox('preprocessing['.$i.'][params_set_snmp]['.$row.'][name]', $step['params'][$j]))
									->setErrorLabel(_('Field name'))
									->setErrorContainer("preprocessing-$i-params_set_snmp-$row-error-container")
									->setReadonly($readonly)
									->removeId()
									->setAttribute('placeholder', _('Field name'))
							),
							new CCol(
								(new CTextBox('preprocessing['.$i.'][params_set_snmp]['.$row.'][oid_prefix]', $step['params'][$j + 1]))
									->setErrorLabel(_('OID prefix'))
									->setErrorContainer("preprocessing-$i-params_set_snmp-$row-error-container")
									->setReadonly($readonly)
									->removeId()
									->setAttribute('placeholder', _('OID prefix'))
							),
							new CCol(
								(new CSelect('preprocessing['.$i.'][params_set_snmp]['.$row.'][format]'))
									->setValue($step['params'][$j + 2])
									->setWidth(ZBX_TEXTAREA_PREPROC_TREAT_SELECT)
									->addOptions([
										new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
										new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
										new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
										new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
									])
									->setReadonly($readonly)
							),
							(new CCol(
								(new CButtonLink(_('Remove')))
									->addClass('js-group-json-action-delete')
									->setEnabled(!$readonly && $count > 3)
							))->addClass(ZBX_STYLE_NOWRAP)
						]))->setAttribute('data-index', $row)->addClass('group-json-row'),
						(new CRow(
							(new CCol())
								->addClass(ZBX_STYLE_ERROR_CONTAINER)
								->setId("preprocessing-$i-params_set_snmp-$row-error-container")
								->setColSpan(4)
						))
					];
				}

				$params = (new CDiv())
					->addItem([
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
							->addItem($mapping_rows)
							->addItem(
								(new CTag('tfoot', true))
									->addItem(
										(new CCol(
											(new CButtonLink(_('Add')))
												->addClass('js-group-json-action-add')
												->setEnabled(!$readonly)
										))->setColSpan(4)
									)
							)
							->setAttribute('data-index', $i)
					])->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR);
				break;

			case ZBX_PREPROC_SNMP_GET_VALUE:
				$params = (new CSelect('preprocessing['.$i.'][params_0]'))
					->setValue($step_param_0_value)
					->setAdaptiveWidth(202)
					->addOptions([
						new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
						new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
						new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
					])
					->setReadonly($readonly);
				break;
		}

		// Create checkbox "Custom on fail" and enable or disable depending on preprocessing type.
		$on_fail = new CCheckBox('preprocessing['.$i.'][on_fail]');

		switch ($step['type']) {
			case ZBX_PREPROC_RTRIM:
			case ZBX_PREPROC_LTRIM:
			case ZBX_PREPROC_TRIM:
			case ZBX_PREPROC_THROTTLE_VALUE:
			case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			case ZBX_PREPROC_SCRIPT:
			case ZBX_PREPROC_STR_REPLACE:
				$on_fail->setEnabled(false);
				break;

			case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
				$on_fail
					->setReadonly(true)
					->setChecked(true);
				break;

			default:
				$on_fail->setReadonly($readonly);

				if ($step['error_handler'] != ZBX_PREPROC_FAIL_DEFAULT) {
					$on_fail->setChecked(true);
				}
				break;
		}

		$error_handler = (new CSelect('preprocessing['.$i.'][error_handler]'))
			->setAttribute('data-prevent-validation-on-change', '')
			->setId('preprocessing-'.$i.'-error-handler')
			->setFocusableElementId('label-preprocessing-'.$i.'-error-handler')
			->setValue($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT
				? ZBX_PREPROC_FAIL_DISCARD_VALUE
				: (int) $step['error_handler']
			)
			->addOptions(CSelect::createOptionsFromArray([
				ZBX_PREPROC_FAIL_DISCARD_VALUE => _('Discard value'),
				ZBX_PREPROC_FAIL_SET_VALUE => _('Set value to'),
				ZBX_PREPROC_FAIL_SET_ERROR => _('Set error to')
			]))
			->setDisabled($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT);

		$error_handler_params = (new CTextAreaFlexible('preprocessing['.$i.'][error_handler_params]',
			$step['error_handler_params'])
		)->setErrorContainer("preprocessing-$i-error-container")->setTitle($step['error_handler_params']);

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT
				|| $step['error_handler'] == ZBX_PREPROC_FAIL_DISCARD_VALUE) {
			$error_handler_params
				->setEnabled(false)
				->addStyle('display: none;');
		}

		$error_handler_params->setErrorLabel(_('Error message'));

		$on_fail_options = (new CDiv([
			new CLabel(_('Custom on fail'), 'label-preprocessing-'.$i.'-error-handler'),
			$error_handler->setReadonly($readonly),
			$error_handler_params->setReadonly($readonly)
		]))->addClass('on-fail-options');

		if ($step['error_handler'] == ZBX_PREPROC_FAIL_DEFAULT) {
			$on_fail_options->addStyle('display: none;');
		}

		$preprocessing_list->addItem(
			(new CListItem([
				(new CDiv([
					(new CDiv(new CVar('preprocessing['.$i.'][sortorder]', $step['sortorder'])))
						->addClass(ZBX_STYLE_DRAG_ICON),
					(new CDiv($preproc_types_select))
						->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM)
						->addClass('step-name'),
					(new CDiv($params))->addClass('step-parameters'),
					(new CDiv($on_fail))->addClass('step-on-fail'),
					(new CDiv([
						(new CButton('preprocessing['.$i.'][test]', _('Test')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('preprocessing-step-test')
							->removeId(),
						(new CButton('preprocessing['.$i.'][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
							->setEnabled(!$readonly)
							->removeId()
					]))->addClass('step-action')
				]))->addClass('preprocessing-step'),
				$on_fail_options,
				(new CDiv())->setId("preprocessing-$i-error-container")
			]))
				->addClass('preprocessing-list-item')
				->setAttribute('data-step', $i)
		);

		$i++;
	}

	$preprocessing_list->addItem(
		(new CListItem([
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$readonly)
			))->addClass('step-action'),
			(new CDiv(
				(new CButton('preproc_test_all', _('Test all steps')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addStyle(($i > 0) ? null : 'display: none')
			))->addClass('step-action')
		]))->addClass('preprocessing-list-foot')
	);

	return $preprocessing_list;
}

/**
 * Renders tag table row.
 *
 * @param array	     $tag
 * @param string     $tag['tag']                          Tag name.
 * @param string     $tag['value']                        Tag value.
 * @param int        $tag['type']                         (optional) Tag ownership type.
 * @param int        $tag['automatic']                    (optional) Tag automatic flag.
 * @param array      $tag['parent_templates']             (optional) List of templates that tags are inherited from.
 * @param array      $options
 * @param bool       $options['show_inherited_tags']      (optional) Render row in inherited tag mode. This enables usage of $tag['type'].
 * @param bool       $options['with_automatic']           (optional) Render row with 'automatic' input. This enables usage of $tag['automatic'].
 * @param string     $options['field_name']               (optional) Re-define default field name.
 * @param bool       $options['readonly']                 (optional) Render row in read-only mode.
 * @param string     $options['source']                   (optional) The origin of tag.
 * @param bool       $options['has_inline_validation']    (optional)
 *
 * @return array
 */
function renderTagTableRow($index, array $tag, array $options = []) {
	$options += [
		'readonly' => false,
		'field_name' => 'tags',
		'with_automatic' => false,
		'show_inherited_tags' => false,
		'has_inline_validation' => false,
		'source' => null
	];

	if ($options['with_automatic'] && !array_key_exists('automatic', $tag)) {
		$tag['automatic'] = ZBX_TAG_MANUAL;
	}

	$textarea_options = array_intersect_key($options, array_flip(['readonly']));

	$tag += [
		'type' => ZBX_PROPERTY_OWN,
		'parent_templates' => []
	];

	$tag_field = (new CTextAreaFlexible($options['field_name'].'['.$index.'][tag]', $tag['tag']))
		->setErrorContainer($options['has_inline_validation'] ? 'tag_'.$index.'_error_container' : null)
		->setErrorLabel($options['has_inline_validation'] ? _('Name') : null)
		->setAdaptiveWidth(ZBX_TEXTAREA_TAG_WIDTH)
		->setMaxlength(DB::getFieldLength('host_tag', 'tag'))
		->setAttribute('placeholder', _('tag'))
		->setReadonly($textarea_options['readonly']);

	$type_field = $options['show_inherited_tags']
		? new CVar($options['field_name'].'['.$index.'][type]', $tag['type'])
		: null;

	$automatic_field = $options['with_automatic']
		? new CVar($options['field_name'].'['.$index.'][automatic]', $tag['automatic'])
		: null;

	$value_field = (new CTextAreaFlexible($options['field_name'].'['.$index.'][value]', $tag['value']))
		->setErrorContainer($options['has_inline_validation'] ? 'tag_'.$index.'_error_container' : null)
		->setErrorLabel($options['has_inline_validation'] ? _('Value') : null)
		->setAdaptiveWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
		->setMaxlength(DB::getFieldLength('host_tag', 'value'))
		->setAttribute('placeholder', _('value'))
		->setReadonly($textarea_options['readonly']);

	if (array_key_exists('maxlength', $textarea_options)) {
		$tag_field->setMaxlength($textarea_options['maxlength']);
		$value_field->setMaxlength($textarea_options['maxlength']);
	}

	if ($options['with_automatic'] && $tag['automatic'] == ZBX_TAG_AUTOMATIC) {
		switch ($options['source']) {
			case 'host':
				$actions = (new CSpan(_('(created by host discovery)')))->addClass(ZBX_STYLE_GREY);
				break;

			case 'trigger':
				$actions = (new CSpan(_('(created by LLD)')))->addClass(ZBX_STYLE_GREY);
				break;

			default:
				$actions = null;
				break;
		}
	}
	else {
		$actions = $options['show_inherited_tags'] && ($tag['type'] & ZBX_PROPERTY_INHERITED) != 0
			? (new CButton($options['field_name'].'['.$index.'][disable]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-disable')
				->setEnabled(!$options['readonly'])
			: (new CButton($options['field_name'].'['.$index.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(!$options['readonly']);
	}

	if ($tag['type'] == ZBX_PROPERTY_INHERITED) {
		$value_field->setAttribute('data-skip-from-submit', '');
		$tag_field->setAttribute('data-skip-from-submit', '');

		if ($type_field !== null) {
			$type_field->setAttribute('data-skip-from-submit', '');
		}

		if ($automatic_field !== null) {
			$automatic_field->setAttribute('data-skip-from-submit', '');
		}
	}

	$fields_row = (new CRow([
		(new CCol([$tag_field, $type_field, $automatic_field]))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($value_field))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
		(new CCol($actions))
			->addClass(ZBX_STYLE_NOWRAP)
			->addClass(ZBX_STYLE_TOP),
		$options['show_inherited_tags']
			? new CCol(makeParentTemplatesList($tag['parent_templates']))
			: null
	]))->addClass('form_row');

	$error_container_row = $options['has_inline_validation']
		? (new CRow(
			(new CCol())
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
				->setId('tag_'.$index.'_error_container')
				->setColSpan($options['show_inherited_tags'] ? 4 : 3)
		))
		: null;

	return array_filter([$fields_row, $error_container_row]);
}

/**
 * Function to render templates as HTML links or span tags, based on user permissions to edit each particular template.
 */
function makeParentTemplatesList(array $parent_templates): array {
	if (!$parent_templates) {
		return [];
	}

	CArrayHelper::sort($parent_templates, ['name']);

	$allowed_ui_conf_templates = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	$template_list = [];

	foreach ($parent_templates as $templateid => $template) {
		if ($allowed_ui_conf_templates && $template['permission'] == PERM_READ_WRITE) {
			$template_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $templateid)
				->getUrl();

			$template_list[] = new CLink($template['name'], $template_url);
		}
		else {
			$template_list[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}

		$template_list[] = ', ';
	}

	array_pop($template_list);

	return $template_list;
}

/**
 * Renders tag table.
 *
 * @param array  $tags
 * @param array  $tags[]['tag']
 * @param array  $tags[]['value']
 * @param bool   $readonly         (optional)
 * @param array  $options          (optional)
 * @param bool   $options['with_automatic']
 * @param string $options['field_name']
 *
 * @return CTable
 */
function renderTagTable(array $tags, $readonly = false, array $options = []) {
	$table = (new CTable())
		->addStyle('width: 100%; max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER);

	$with_automatic = array_key_exists('with_automatic', $options) && $options['with_automatic'];

	$row_options = [
		'readonly' => $readonly,
		'with_automatic' => $with_automatic
	];

	if (array_key_exists('field_name', $options)) {
		$row_options['field_name'] = $options['field_name'];
	}

	if (array_key_exists('has_inline_validation', $options)) {
		$row_options['has_inline_validation'] = $options['has_inline_validation'];
	}

	foreach ($tags as $index => $tag) {
		$tag = ['automatic' => $with_automatic ? $tag['automatic'] : ZBX_TAG_MANUAL] + $tag;

		$table->addRow(renderTagTableRow($index, $tag, $row_options));
	}

	return $table->setFooter(new CCol(
		(new CButton('tag_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->setEnabled(!$readonly)
	));
}
