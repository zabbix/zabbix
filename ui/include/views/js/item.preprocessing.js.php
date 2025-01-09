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
		->setId('preprocessing_#{rowNum}_type')
		->setValue(ZBX_PREPROC_REGSUB)
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
			new CLabel(_('Custom on fail')),
			(new CRadioButtonList('preprocessing[#{rowNum}][error_handler]', ZBX_PREPROC_FAIL_DISCARD_VALUE))
				->addValue(_('Discard value'), ZBX_PREPROC_FAIL_DISCARD_VALUE)
				->addValue(_('Set value to'), ZBX_PREPROC_FAIL_SET_VALUE)
				->addValue(_('Set error to'), ZBX_PREPROC_FAIL_SET_ERROR)
				->setModern(true)
				->setEnabled(false),
			(new CTextBox('preprocessing[#{rowNum}][error_handler_params]'))
				->setEnabled(false)
				->addStyle('display: none;')
		]))
			->addClass('on-fail-options')
			->addStyle('display: none;')
	]))
		->addClass('preprocessing-list-item')
		->setAttribute('data-step', '#{rowNum}');
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-single-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', '#{placeholder}') ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-double-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', '#{placeholder_0}').
			(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))->setAttribute('placeholder', '#{placeholder_1}')
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-multiline-tmpl">
	<?= (new CMultilineInput('preprocessing[#{rowNum}][params][0]', '', ['add_post_js' => false, 'use_tab' => false]))
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-custom-width-chkbox-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', '#{value_0}'))
			->setAttribute('placeholder', '#{placeholder_0}')
			->setWidth('#{width_0}')
			->setAttribute('maxlength', 1).
		(new CTextBox('preprocessing[#{rowNum}][params][1]', '#{value_1}'))
			->setAttribute('placeholder', '#{placeholder_1}')
			->setWidth('#{width_1}')
			->setAttribute('maxlength', 1).
		(new CCheckBox('preprocessing[#{rowNum}][params][2]', '#{chkbox_value}'))
			->setLabel('#{chkbox_label}')
			->setChecked('#{chkbox_default}')
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-custom-prometheus-pattern-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))
			->setAttribute('placeholder', '#{placeholder_0}').
		(new CSelect('preprocessing[#{rowNum}][params][1]'))
			->addOptions(CSelect::createOptionsFromArray([
				ZBX_PREPROC_PROMETHEUS_VALUE => _('value'),
				ZBX_PREPROC_PROMETHEUS_LABEL => _('label'),
				ZBX_PREPROC_PROMETHEUS_SUM => 'sum',
				ZBX_PREPROC_PROMETHEUS_MIN => 'min',
				ZBX_PREPROC_PROMETHEUS_MAX => 'max',
				ZBX_PREPROC_PROMETHEUS_AVG => 'avg',
				ZBX_PREPROC_PROMETHEUS_COUNT => 'count'
			]))
			->addClass('js-preproc-param-prometheus-pattern-function').
		(new CTextBox('preprocessing[#{rowNum}][params][2]', ''))
			->setAttribute('placeholder', '#{placeholder_2}')
			->setEnabled(false)
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-snmp-walk-value-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', _('OID')).
		(new CSelect('preprocessing[#{rowNum}][params][1]'))
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
								(new CTextBox('preprocessing[#{rowNum}][params][]', ''))
									->removeId()
									->setAttribute('placeholder', _('Field name'))
							),
							new CCol(
								(new CTextBox('preprocessing[#{rowNum}][params][]', ''))
									->removeId()
									->setAttribute('placeholder', _('OID prefix'))
							),
							new CCol(
								(new CSelect('preprocessing[#{rowNum}][params][]'))
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
						]))->addClass('group-json-row')
					)
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
				(new CTextBox('preprocessing[#{rowNum}][params][]', ''))
					->removeId()
					->setAttribute('placeholder', _('Field name'))
			),
			new CCol(
				(new CTextBox('preprocessing[#{rowNum}][params][]', ''))
					->removeId()
					->setAttribute('placeholder', _('OID prefix'))
			),
			new CCol(
				(new CSelect('preprocessing[#{rowNum}][params][]'))
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
		]))->addClass('group-json-row');
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-check-not-supported-row-tmpl">
	<?= (new CSelect('preprocessing[#{rowNum}][params][0]'))
			->addOptions(CSelect::createOptionsFromArray([
				ZBX_PREPROC_MATCH_ERROR_ANY => _('any error'),
				ZBX_PREPROC_MATCH_ERROR_REGEX => _('error matches'),
				ZBX_PREPROC_MATCH_ERROR_NOT_REGEX => _('error does not match')
			]))
				->setAttribute('placeholder', _('error-matching'))
				->addClass('js-preproc-param-error-matching')
				->setValue(ZBX_PREPROC_MATCH_ERROR_ANY).
		(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))
			->removeId()
			->setAttribute('placeholder', _('pattern'))
			->addClass(ZBX_STYLE_VISIBILITY_HIDDEN);
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-snmp-get-value-tmpl">
	<?= (new CSelect('preprocessing[#{rowNum}][params][0]'))
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
			const preproc_param_single_tmpl = new Template($('#preprocessing-steps-parameters-single-tmpl').html());
			const preproc_param_double_tmpl = new Template($('#preprocessing-steps-parameters-double-tmpl').html());
			const preproc_param_custom_width_chkbox_tmpl =
				new Template($('#preprocessing-steps-parameters-custom-width-chkbox-tmpl').html());
			const preproc_param_multiline_tmpl = new Template(
				$('#preprocessing-steps-parameters-multiline-tmpl').html()
			);
			const preproc_param_prometheus_pattern_tmpl = new Template(
				$('#preprocessing-steps-parameters-custom-prometheus-pattern-tmpl').html()
			);
			const preproc_param_snmp_walk_value_tmpl = new Template(
				$('#preprocessing-steps-parameters-snmp-walk-value-tmpl').html()
			);
			const preproc_param_snmp_walk_to_json_tmpl = new Template(
				$('#preprocessing-steps-parameters-snmp-walk-to-json-tmpl').html()
			);
			const preproc_param_check_not_supported_tmpl = new Template(
				$('#preprocessing-steps-parameters-check-not-supported-row-tmpl').html()
			);
			const preproc_param_snmp_get_value_tmpl = new Template(
				$('#preprocessing-steps-parameters-snmp-get-value-tmpl').html()
			);

			switch (type) {
				case '<?= ZBX_PREPROC_MULTIPLIER ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('number')) ?>
					})).css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_RTRIM ?>':
				case '<?= ZBX_PREPROC_LTRIM ?>':
				case '<?= ZBX_PREPROC_TRIM ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('list of characters')) ?>
					})).css('width', <?= ZBX_TEXTAREA_SMALL_WIDTH ?>);

				case '<?= ZBX_PREPROC_XPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('XPath')) ?>
					}));

				case '<?= ZBX_PREPROC_JSONPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('$.path.to.node')) ?>
					}));

				case '<?= ZBX_PREPROC_REGSUB ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(_('pattern')) ?>,
						placeholder_1: <?= json_encode(_('output')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(_('min')) ?>,
						placeholder_1: <?= json_encode(_('max')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
				case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('pattern')) ?>
					}));

				case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('seconds')) ?>
					})).css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_SCRIPT ?>':
					return $(preproc_param_multiline_tmpl.evaluate({rowNum: index})).multilineInput({
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
					return $(preproc_param_prometheus_pattern_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(
							_('<metric name>{<label name>="<label value>", ...} == <value>')
						) ?>,
						placeholder_2: <?= json_encode(_('<label name>')) ?>
					}));

				case '<?= ZBX_PREPROC_PROMETHEUS_TO_JSON ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(
							_('<metric name>{<label name>="<label value>", ...} == <value>')
						) ?>
					}));

				case '<?= ZBX_PREPROC_CSV_TO_JSON ?>':
					return $(preproc_param_custom_width_chkbox_tmpl.evaluate({
						rowNum: index,
						width_0: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>,
						width_1: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>,
						placeholder_0: <?= json_encode(_('delimiter')) ?>,
						value_0: ',',
						placeholder_1: <?= json_encode(_('qualifier')) ?>,
						value_1: '"',
						chkbox_label: <?= json_encode(_('With header row')) ?>,
						chkbox_value: <?= ZBX_PREPROC_CSV_HEADER ?>,
						chkbox_default: true
					}));

				case '<?= ZBX_PREPROC_STR_REPLACE ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(_('search string')) ?>,
						placeholder_1: <?= json_encode(_('replacement')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_NOT_SUPPORTED ?>':
					return $(preproc_param_check_not_supported_tmpl.evaluate({
						rowNum: index
					}));

				case '<?= ZBX_PREPROC_SNMP_WALK_VALUE ?>':
					return $(preproc_param_snmp_walk_value_tmpl.evaluate({
						rowNum: index
					}));

				case '<?= ZBX_PREPROC_SNMP_WALK_TO_JSON ?>':
					return $(preproc_param_snmp_walk_to_json_tmpl.evaluate({
						rowNum: index
					}));

				case '<?= ZBX_PREPROC_SNMP_GET_VALUE ?>':
					return $(preproc_param_snmp_get_value_tmpl.evaluate({
						rowNum: index
					}));

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

				openItemTestDialog(step_nums, true, false, this, -1);
			})
			.on('click', '.preprocessing-step-test', function() {
				var str = $(this).attr('name'),
					step_nr = $(this).attr('data-step'),
					num = str.substr(14, str.length - 21);

				openItemTestDialog([num], false, false, this, num);
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
			.on('change', 'input[type="text"][name*="params"]', function() {
				$(this).attr('title', $(this).val());
			})
			.on('change', 'input[name*="on_fail"]', function() {
				var $on_fail_options = $(this).closest('.preprocessing-list-item').find('.on-fail-options');

				if ($(this).is(':checked')) {
					$on_fail_options.find('input').prop('disabled', false);
					$on_fail_options.show();
				}
				else {
					$on_fail_options.find('input').prop('disabled', true);
					$on_fail_options.hide();
				}
			})
			.on('change', 'input[name*="error_handler]"]', function() {
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
						.show();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
					$error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= json_encode(_('error message')) ?>)
						.show();
				}
			})
			.on('change', '.js-preproc-param-prometheus-pattern-function', function() {
				$(this).next('input').prop('disabled', $(this).val() !== '<?= ZBX_PREPROC_PROMETHEUS_LABEL ?>');
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

				[...container.querySelectorAll('.js-group-json-action-delete')].map((btn) => {
					btn.disabled = false;
				});

				container
					.querySelector('tbody')
					.insertAdjacentHTML('beforeend', template.evaluate({rowNum: row_numb}));
			});
	});
</script>
