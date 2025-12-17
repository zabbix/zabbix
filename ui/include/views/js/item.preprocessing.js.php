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
?>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-tmpl">
	<?php
	$preproc_types_select = (new CSelect('preprocessing[#{rowNum}][type]'))
		->setErrorLabel(_('Name'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setId('preprocessing_#{rowNum}_type')
		->setValue(ZBX_PREPROC_REGSUB)
		->setAttribute('data-prevent-validation-on-change', '')
		->setWidthAuto();

	foreach (get_preprocessing_types(null, true, $data['preprocessing_types']) as $group) {
		$opt_group = new CSelectOptionGroup($group['label']);

		foreach ($group['types'] as $type => $label) {
			$opt_group->addOption(new CSelectOption($type, $label));
		}

		$preproc_types_select->addOptionGroup($opt_group);
	}

	echo (new CListItem([
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
				->setAttribute('data-prevent-validation-on-change', '')
				->setId('preprocessing-#{rowNum}-error-handler')
				->setFocusableElementId('label-preprocessing-#{rowNum}-error-handler')
				->setValue(ZBX_PREPROC_FAIL_DISCARD_VALUE)
				->addOptions(CSelect::createOptionsFromArray([
					ZBX_PREPROC_FAIL_DISCARD_VALUE => _('Discard value'),
					ZBX_PREPROC_FAIL_SET_VALUE => _('Set value to'),
					ZBX_PREPROC_FAIL_SET_ERROR => _('Set error to')
				]))
				->setDisabled(),
			(new CTextBox('preprocessing[#{rowNum}][error_handler_params]'))
				->setErrorLabel(_('Error message'))
				->setErrorContainer('preprocessing-#{rowNum}-error-container')
				->setEnabled(false)
				->addStyle('display: none;')
		]))
			->addClass('on-fail-options')
			->addStyle('display: none;'),
		(new CDiv())->setId("preprocessing-#{rowNum}-error-container")
	]))
		->addClass('preprocessing-list-item')
		->setAttribute('data-step', '#{rowNum}');

	echo (new CListItem(''))
		->setId("preprocessing-#{rowNum}-error-container")
		->addClass('error-container-row');
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-multiplier-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setErrorLabel(_('Number'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('number')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-prometheus-to-json-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setErrorLabel(_('Pattern'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('<metric name>{<label name>="<label value>", ...} == <value>')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-trim-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setAttribute('data-notrim', '')
		->setErrorLabel(_('List of characters'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('list of characters')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-xpath-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setAttribute('data-notrim', '')
		->setErrorLabel(_('XPath'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('XPath')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-regex-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setAttribute('data-notrim', '')
		->setErrorLabel(_('Pattern'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('pattern')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-json-path-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setAttribute('data-notrim', '')
		->setErrorLabel(_('JSON path'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('$.path.to.node')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-throttle-timed-value-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setErrorLabel(_('Seconds'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('seconds')) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-regsub-tmpl">
<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('pattern')).
		(new CTextBox('preprocessing[#{rowNum}][params_1]', ''))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Output'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('output'))
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-replace-tmpl">
<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Search string'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('search string')).
		(new CTextBox('preprocessing[#{rowNum}][params_1]', ''))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Replacement'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('replacement'))
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-validate-range-tmpl">
<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
			->setErrorLabel(_('Min'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('min')).
		(new CTextBox('preprocessing[#{rowNum}][params_1]', ''))
			->setErrorLabel(_('Max'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('max'))
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-script-tmpl">
	<?= (new CMultilineInput('preprocessing[#{rowNum}][params_0]', '', ['add_post_js' => false, 'use_tab' => false]))
		->setAttribute('data-notrim', '')
		->setErrorLabel(_('Script'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-custom-width-chkbox-tmpl">
	<?= implode('', [(new CTextBox('preprocessing[#{rowNum}][params_0]', ','))
			->setAttribute('placeholder', _('delimiter'))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAttribute('maxlength', 1)
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Delimiter'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container'),
		(new CTextBox('preprocessing[#{rowNum}][params_1]', '"'))
			->setAttribute('placeholder', _('qualifier'))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAttribute('maxlength', 1)
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Qualifier'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container'),
		(new CCheckBox('preprocessing[#{rowNum}][params_2]', ZBX_PREPROC_CSV_HEADER))
			->setLabel(_('With header row'))
			->setChecked(true)
	]) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-prometheus-pattern-tmpl">
	<?= implode('', [(new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('<metric name>{<label name>="<label value>", ...} == <value>')),
		(new CSelect('preprocessing[#{rowNum}][params_1]'))
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
			->addClass('js-preproc-param-prometheus-pattern-function'),
		(new CTextBox('preprocessing[#{rowNum}][params_2]', ''))
			->setErrorLabel(_('Label'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->setAttribute('placeholder', _('<label name>'))
			->setEnabled(false)])
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-snmp-walk-value-tmpl">
<?= (new CTextBox('preprocessing[#{rowNum}][params_0]', ''))
		->setAttribute('data-notrim', '')
		->setErrorLabel(_('OID'))
		->setErrorContainer('preprocessing-#{rowNum}-error-container')
		->setAttribute('placeholder', _('OID')).
	(new CSelect('preprocessing[#{rowNum}][params_1]'))
		->setValue(ZBX_PREPROC_SNMP_UNCHANGED)
		->setAdaptiveWidth(202)
		->addOptions([
			new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
			new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
			new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
			new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
		])
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-snmp-walk-to-json-tmpl">
	<?php
		echo (new CDiv(
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
						(new CRow([
							new CCol(
								(new CTextBox('preprocessing[#{rowNum}][params_set_snmp][0][name]', ''))
									->setAttribute('data-notrim', '')
									->setErrorLabel(_('Field name'))
									->setErrorContainer('preprocessing-#{rowNum}-params_set_snmp-0-error-container')
									->removeId()
									->setAttribute('placeholder', _('Field name'))
							),
							new CCol(
								(new CTextBox('preprocessing[#{rowNum}][params_set_snmp][0][oid_prefix]', ''))
									->setAttribute('data-notrim', '')
									->setErrorLabel(_('OID prefix'))
									->setErrorContainer('preprocessing-#{rowNum}-params_set_snmp-0-error-container')
									->removeId()
									->setAttribute('placeholder', _('OID prefix'))
							),
							new CCol(
								(new CSelect('preprocessing[#{rowNum}][params_set_snmp][0][format]'))
									->setValue(ZBX_PREPROC_SNMP_UNCHANGED)
									->setWidth(ZBX_TEXTAREA_PREPROC_TREAT_SELECT)
									->addOptions([
										new CSelectOption(ZBX_PREPROC_SNMP_UNCHANGED, _('Unchanged')),
										new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
										new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
										new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
									])
							),
							(new CCol(
								(new CButtonLink(_('Remove')))
									->addClass('js-group-json-action-delete')
									->setEnabled(false)
							))->addClass(ZBX_STYLE_NOWRAP)
						]))->setAttribute('data-index', '0')->addClass('group-json-row')
					)
					->addItem((new CRow(
						(new CCol())
							->addClass(ZBX_STYLE_ERROR_CONTAINER)
							->setId('preprocessing-#{rowNum}-params_set_snmp-0-error-container')
							->setColSpan(4)
					)))
					->addItem(
						(new CTag('tfoot', true))
							->addItem(
								(new CCol(
									(new CButtonLink(_('Add')))->addClass('js-group-json-action-add')
								))->setColSpan(4)
							)
					)
					->setAttribute('data-index', '#{rowNum}')
			))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR);
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-snmp-walk-to-json-row-tmpl">
	<?php
		echo (new CRow([
			new CCol(
				(new CTextBox('preprocessing[#{rowNum}][params_set_snmp][#{rowIndex}][name]', ''))
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('Field name'))
					->setErrorContainer('preprocessing-#{rowNum}-params_set_snmp-#{rowIndex}-error-container')
					->removeId()
					->setAttribute('placeholder', _('Field name'))
			),
			new CCol(
				(new CTextBox('preprocessing[#{rowNum}][params_set_snmp][#{rowIndex}][oid_prefix]', ''))
					->setAttribute('data-notrim', '')
					->setErrorLabel(_('OID prefix'))
					->setErrorContainer('preprocessing-#{rowNum}-params_set_snmp-#{rowIndex}-error-container')
					->removeId()
					->setAttribute('placeholder', _('OID prefix'))
			),
			new CCol(
				(new CSelect('preprocessing[#{rowNum}][params_set_snmp][#{rowIndex}][format]'))
					->setValue(ZBX_PREPROC_SNMP_UNCHANGED)
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
		]))->setAttribute('data-index', '#{rowIndex}')->addClass('group-json-row');
		echo (new CRow(
			(new CCol())
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
				->setId('preprocessing-#{rowNum}-params_set_snmp-#{rowIndex}-error-container')
				->setColSpan(4)
		))
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-check-not-supported-row-tmpl">
	<?= (new CSelect('preprocessing[#{rowNum}][params_0_not_supported]'))
			->addOptions(CSelect::createOptionsFromArray([
				ZBX_PREPROC_MATCH_ERROR_ANY => _('any error'),
				ZBX_PREPROC_MATCH_ERROR_REGEX => _('error matches'),
				ZBX_PREPROC_MATCH_ERROR_NOT_REGEX => _('error does not match')
			]))
				->setAttribute('data-prevent-validation-on-change', '')
				->setAttribute('placeholder', _('error-matching'))
				->addClass('js-preproc-param-error-matching')
				->setValue(ZBX_PREPROC_MATCH_ERROR_ANY).
		(new CTextBox('preprocessing[#{rowNum}][params_1_not_supported]', ''))
			->setAttribute('data-notrim', '')
			->setErrorLabel(_('Pattern'))
			->setErrorContainer('preprocessing-#{rowNum}-error-container')
			->removeId()
			->setAttribute('placeholder', _('pattern'))
			->addClass(ZBX_STYLE_VISIBILITY_HIDDEN);
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-snmp-get-value-tmpl">
	<?= (new CSelect('preprocessing[#{rowNum}][params_0]'))
			->setValue(ZBX_PREPROC_SNMP_UTF8_FROM_HEX)
			->setAdaptiveWidth(202)
			->addOptions([
				new CSelectOption(ZBX_PREPROC_SNMP_UTF8_FROM_HEX, _('UTF-8 from Hex-STRING')),
				new CSelectOption(ZBX_PREPROC_SNMP_MAC_FROM_HEX, _('MAC from Hex-STRING')),
				new CSelectOption(ZBX_PREPROC_SNMP_INT_FROM_BITS, _('Integer from BITS'))
			])
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		function makeParameterInput(index, type) {
			const preproc_param_multiplier_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-multiplier-tmpl').innerHTML
			);
			const preproc_param_trim_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-trim-tmpl').innerHTML
			);
			const preproc_param_xpath_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-xpath-tmpl').innerHTML
			);
			const preproc_param_regex_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-regex-tmpl').innerHTML
			);
			const preproc_param_json_path_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-json-path-tmpl').innerHTML
			);
			const preproc_param_throttle_timed_value = new Template(
				document.getElementById('preprocessing-steps-parameters-throttle-timed-value-tmpl').innerHTML
			);
			const preproc_param_validate_range = new Template(
				document.getElementById('preprocessing-steps-parameters-validate-range-tmpl').innerHTML
			);
			const preproc_param_replace_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-replace-tmpl').innerHTML
			);
			const preproc_param_regsub_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-regsub-tmpl').innerHTML
			);
			const preproc_param_csv_to_json_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-custom-width-chkbox-tmpl').innerHTML
			);
			const preproc_param_script_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-script-tmpl').innerHTML
			);
			const preproc_param_prometheus_pattern_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-prometheus-pattern-tmpl').innerHTML
			);
			const preproc_param_prometheus_to_json_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-prometheus-to-json-tmpl').innerHTML
			);
			const preproc_param_snmp_walk_value_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-snmp-walk-value-tmpl').innerHTML
			);
			const preproc_param_snmp_walk_to_json_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-snmp-walk-to-json-tmpl').innerHTML
			);
			const preproc_param_check_not_supported_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-check-not-supported-row-tmpl').innerHTML
			);
			const preproc_param_snmp_get_value_tmpl = new Template(
				document.getElementById('preprocessing-steps-parameters-snmp-get-value-tmpl').innerHTML
			);

			switch (type) {
				case '<?= ZBX_PREPROC_MULTIPLIER ?>':
					return $(preproc_param_multiplier_tmpl.evaluate({rowNum: index}))
						.css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_RTRIM ?>':
				case '<?= ZBX_PREPROC_LTRIM ?>':
				case '<?= ZBX_PREPROC_TRIM ?>':
					return $(preproc_param_trim_tmpl.evaluate({rowNum: index}))
						.css('width', <?= ZBX_TEXTAREA_SMALL_WIDTH ?>);

				case '<?= ZBX_PREPROC_XPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
					return $(preproc_param_xpath_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_JSONPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
					return $(preproc_param_json_path_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_REGSUB ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
					return $(preproc_param_regsub_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
					return $(preproc_param_validate_range.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
				case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
					return $(preproc_param_regex_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					return $(preproc_param_throttle_timed_value.evaluate({rowNum: index}))
						.css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_SCRIPT ?>':
					return $(preproc_param_script_tmpl.evaluate({rowNum: index})).multilineInput({
						title: <?= json_encode(_('JavaScript')) ?>,
						placeholder: <?= json_encode(_('script')) ?>,
						placeholder_textarea: 'return value',
						label_before: 'function (value) {',
						label_after: '}',
						grow: 'auto',
						rows: 0,
						maxlength: <?= DB::getFieldLength('item_preproc', 'params') ?>
					});

				case '<?= ZBX_PREPROC_PROMETHEUS_PATTERN ?>':
					return $(preproc_param_prometheus_pattern_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_PROMETHEUS_TO_JSON ?>':
					return $(preproc_param_prometheus_to_json_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_CSV_TO_JSON ?>':
					return $(preproc_param_csv_to_json_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_STR_REPLACE ?>':
					return $(preproc_param_replace_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_VALIDATE_NOT_SUPPORTED ?>':
					return $(preproc_param_check_not_supported_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_SNMP_WALK_VALUE ?>':
					return $(preproc_param_snmp_walk_value_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_SNMP_WALK_TO_JSON ?>':
					return $(preproc_param_snmp_walk_to_json_tmpl.evaluate({rowNum: index}));

				case '<?= ZBX_PREPROC_SNMP_GET_VALUE ?>':
					return $(preproc_param_snmp_get_value_tmpl.evaluate({rowNum: index}));

				default:
					return '';
			}
		}

		var $preprocessing = $('#preprocessing');

		if ($preprocessing.length === 0) {
			const prep_elem = document.querySelector('#preprocessing-field');

			if (!prep_elem) {
				return false;
			}

			let obj = prep_elem;
			if (prep_elem.tagName === 'SPAN') {
				obj = prep_elem.originalObject;
			}

			$preprocessing = $(obj.querySelector('#preprocessing'));
		}

		new CSortable($preprocessing[0], {
			selector_span: ':not(.error-container-row)',
			selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			enable_sorting: $preprocessing[0].dataset.readonly == 0,
			freeze_start: 1,
			freeze_end: 1
		})
			.on(CSortable.EVENT_SORT, () => {
				$preprocessing[0].querySelectorAll('.preprocessing-list-item').forEach((list_item, index) => {
					list_item.querySelector('[name*="sortorder"]').value = index;
				});
			});

		const change_event = new CustomEvent('item.preprocessing.change');

		let step_index = $preprocessing.find('.preprocessing-list-item').length;

		$preprocessing
			.on('click', '.element-table-add', function() {
				let sortable_count = $preprocessing.find('.preprocessing-list-item').length;
				const preproc_row_tmpl = new Template($('#preprocessing-steps-tmpl').html());
				const $row = $(preproc_row_tmpl.evaluate({
					rowNum: step_index,
					sortorder: sortable_count++
				}));
				const type = $('z-select[name*="type"]', $row).val();
				const massupdate_form = document.getElementById('massupdate-form');

				$('.step-parameters', $row).html(makeParameterInput(step_index, type));
				$(this).closest('.preprocessing-list-foot').before($row);

				$('.preprocessing-list-head').show();

				if (sortable_count == 1) {
					$('#preproc_test_all').show();

					if (massupdate_form !== null) {
						$preprocessing.find('button.btn-link.element-table-remove').attr('disabled', 'disabled');
					}
				}
				else if (sortable_count > 1) {
					if (massupdate_form !== null) {
						$preprocessing.find('.preprocessing-list-item').each(function() {
							$(this).find('button.btn-link.element-table-remove').removeAttr('disabled');
						});
					}
				}

				$preprocessing[0].dispatchEvent(change_event);
				step_index++;
			})
			.on('click', '#preproc_test_all', function() {
				var step_nums = [];
				$('z-select[name^="preprocessing"][name$="[type]"]', $preprocessing).each(function() {
					var str = $(this).attr('name');
					step_nums.push(str.substr(14, str.length - 21));
				});

				if (window.item_edit_form) {
					for (const field of Object.values(item_edit_form.form.findFieldByName('preprocessing').getFields())) {
						field.setChanged();
					}

					const types_test_key = <?= json_encode(CControllerPopupItemTest::$item_types_has_key_mandatory) ?>;
					const type = parseInt(item_edit_form.form.findFieldByName('type').getValue());
					const validate_fields = types_test_key.includes(type)
						? ['preprocessing', 'key']
						: ['preprocessing'];

					if (validate_fields.includes('key')) {
						item_edit_form.form.findFieldByName('key').setChanged();
					}

					item_edit_form.form.validateFieldsForAction(validate_fields).then((result) => {
						if (!result) {
							return;
						}

						openItemTestDialog(step_nums, true, false, this, -1);
					});
				}
				else {
					openItemTestDialog(step_nums, true, false, this, -1);
				}
			})
			.on('click', '.preprocessing-step-test', function() {
				var str = $(this).attr('name'),
					num = str.substr(14, str.length - 21);

				if (window.item_edit_form) {
					for (const field of Object.values(item_edit_form.form.findFieldByName('preprocessing').getFields())) {
						if (field.getPath().startsWith(`/preprocessing/${num}`)) {
							field.setChanged();
						}
					}

					const types_test_key = <?= json_encode(CControllerPopupItemTest::$item_types_has_key_mandatory) ?>;
					const type = parseInt(item_edit_form.form.findFieldByName('type').getValue());
					const validate_fields = types_test_key.includes(type)
						? [`preprocessing/${num}`, 'key']
						: [`preprocessing/${num}`];

					if (validate_fields.includes('key')) {
						item_edit_form.form.findFieldByName('key').setChanged();
					}

					item_edit_form.form.validateFieldsForAction(validate_fields).then((result) => {
						if (!result) {
							return;
						}

						openItemTestDialog([num], false, false, this, num);
					});
				}
				else {
					openItemTestDialog([num], false, false, this, num);
				}
			})
			.on('click', '.element-table-remove', function() {
				$(this).closest('.preprocessing-list-item').remove();

				const sortable_count = $preprocessing.find('.preprocessing-list-item').length;

				if (sortable_count == 0) {
					$('#preproc_test_all').hide();
					$('.preprocessing-list-head').hide();
				}
				else if (sortable_count == 1) {
					if (document.getElementById('massupdate-form') !== null) {
						$preprocessing.find('button.btn-link.element-table-remove').attr('disabled', 'disabled');
					}
				}

				if (sortable_count > 0) {
					let i = 0;

					$preprocessing.find('.preprocessing-list-item').each(function() {
						$(this).find('[name*="sortorder"]').val(i++);
					});
				}

				$preprocessing[0].dispatchEvent(change_event);
			})
			.on('change', 'z-select[name*="type"]', function() {
				if (window.item_edit_form) {
					const {step} = this.closest('[data-step]').dataset;

					window.item_edit_form.form.validateChanges([`preprocessing[${step}][on_fail]`]);
				}

				var $row = $(this).closest('.preprocessing-list-item'),
					type = $(this).val(),
					$on_fail = $row.find('[name*="on_fail"]');

				$('.step-parameters', $row).html(makeParameterInput($row.data('step'), type));

				// Disable "Custom on fail" for some of the preprocessing types.
				switch (type) {
					case '<?= ZBX_PREPROC_RTRIM ?>':
					case '<?= ZBX_PREPROC_LTRIM ?>':
					case '<?= ZBX_PREPROC_TRIM ?>':
					case '<?= ZBX_PREPROC_THROTTLE_VALUE ?>':
					case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					case '<?= ZBX_PREPROC_SCRIPT ?>':
					case '<?= ZBX_PREPROC_STR_REPLACE ?>':
						$on_fail
							.prop('checked', false)
							.prop('disabled', true);
						$row.find('[name*="[test]"]').prop('disabled', false);
						break;

					case '<?= ZBX_PREPROC_VALIDATE_NOT_SUPPORTED ?>':
						$on_fail
							.prop('checked', true)
							.prop('readonly', true);
						break;

					default:
						$on_fail
							.prop('checked', false)
							.prop('disabled', false)
							.prop('readonly', false);
						$row.find('[name*="[test]"]').prop('disabled', false);
						break;
				}

				$on_fail.trigger('change');
			})
			.on('change', 'z-select[name*="params_0_not_supported"]', function() {
				if (window.item_edit_form) {
					const {step} = this.closest('[data-step]').dataset;
					const field = item_edit_form.form.findFieldByName(`preprocessing[${step}][params_1_not_supported]`);

					if (field.hasChanged()) {
						window.item_edit_form.form.validateChanges([`preprocessing[${step}][params_0_not_supported]`]);
					}
				}
			})
			.on('change', 'input[type="text"][name*="params"]', function() {
				$(this).attr('title', $(this).val());
			})
			.on('change', 'input[name*="on_fail"]', function() {
				var $on_fail_options = $(this).closest('.preprocessing-list-item').find('.on-fail-options');

				if ($(this).is(':checked')) {
					$on_fail_options
						.find('z-select[name*="[error_handler]"], input[name*="error_handler_params"]')
						.prop('disabled', false);
					$on_fail_options.show();
				}
				else {
					$on_fail_options
						.find('z-select[name*="[error_handler]"], input[name*="error_handler_params"]')
						.prop('disabled', true);
					$on_fail_options.hide();
				}
			})
			.on('change', 'z-select[name*="[error_handler]"]', function() {
				if (window.item_edit_form) {
					const {step} = this.closest('[data-step]').dataset;
					const field = item_edit_form.form.findFieldByName(`preprocessing[${step}][error_handler_params]`);

					if (field.hasChanged()) {
						window.item_edit_form.form.validateChanges([`preprocessing[${step}][error_handler]`]);
					}
				}

				var error_handler = $(this).val(),
					$error_handler_params = $(this).closest('.on-fail-options').find('[name*="error_handler_params"]');

				if (error_handler == '<?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>') {
					$error_handler_params
						.prop('disabled', true)
						.hide();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_VALUE ?>') {
					$error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= json_encode(_('value')) ?>)
						.attr('data-error-label', '')
						.show();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
					$error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= json_encode(_('error message')) ?>)
						.attr('data-error-label', <?= json_encode(_('Error message')) ?>)
						.show();
				}
			})
			.on('change', '.js-preproc-param-prometheus-pattern-function', function() {
				const $input = $(this).next('input');
				const disabled = $(this).val() !== '<?= ZBX_PREPROC_PROMETHEUS_LABEL ?>';

				$input.prop('disabled', disabled);
				if (window.item_edit_form) {
					const field = window.item_edit_form.form.findFieldByName($input[0].name);
					if (disabled) {
						field.unsetErrors();
						field.showErrors();
					}
					else if (field.hasChanged()) {
						field.onBlur();
					}
				}
			})
			.on('change', '.js-preproc-param-error-matching', function() {
				$(this).next('input')
					.toggleClass('<?= ZBX_STYLE_VISIBILITY_HIDDEN ?>', this.value == <?= ZBX_PREPROC_MATCH_ERROR_ANY ?>);
			})
			.on('click', '.js-group-json-action-delete', function() {
				const table = this.closest('.group-json-mapping');
				const row = this.closest('.group-json-row');
				const count = table.querySelectorAll('.group-json-row').length;

				if (count == 1) {
					return;
				}

				row.nextElementSibling.remove();
				row.remove();

				if (count == 2) {
					table.querySelector('.js-group-json-action-delete').disabled = true;
				}
			})
			.on('click', '.js-group-json-action-add', function() {
				const template = new Template(
					document
						.getElementById('preprocessing-steps-parameters-snmp-walk-to-json-row-tmpl')
						.innerHTML
				);
				const container = this.closest('.group-json-mapping');

				const row_numb = container.dataset.index;
				const row_index = container.querySelectorAll('.group-json-row').values().reduce((carry, node) => {
					return Math.max(node.dataset.index, carry);
				}, -1) + 1;

				[...container.querySelectorAll('.js-group-json-action-delete')].map((btn) => {
					btn.disabled = false;
				});

				container
					.querySelector('tbody')
					.insertAdjacentHTML('beforeend', template.evaluate({rowNum: row_numb, rowIndex: row_index}));
			});
	});
</script>
