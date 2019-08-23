<script type="text/x-jquery-tmpl" id="preprocessing-steps-tmpl">
	<?php
	$preproc_types_cbbox = new CComboBox('preprocessing[#{rowNum}][type]', '');

	foreach (get_preprocessing_types(null, true, $data['preprocessing_types']) as $group) {
		$cb_group = new COptGroup($group['label']);

		foreach ($group['types'] as $type => $label) {
			$cb_group->addItem(new CComboItem($type, $label));
		}

		$preproc_types_cbbox->addItem($cb_group);
	}

	echo (new CListItem([
		(new CDiv([
			(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON),
			(new CDiv($preproc_types_cbbox))
				->addClass('list-numbered-item')
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
		->addClass('sortable')
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
	<?= (new CMultilineInput('preprocessing[#{rowNum}][params][0]', '', ['add_post_js' => false])) ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		/**
		 * Collect current preprocessing step properties.
		 *
		 * @param {array} step_nums  List of step numbers to collect.
		 *
		 * @return array
		 */
		function getPreprocessingSteps(step_nums) {
			var steps = [];

			step_nums.each(function(num) {
				var type = $('[name="preprocessing[' + num + '][type]"]', $preprocessing).val(),
					error_handler = $('[name="preprocessing[' + num + '][on_fail]"]').is(':checked')
						? $('[name="preprocessing[' + num + '][error_handler]"]:checked').val()
						: <?= ZBX_PREPROC_FAIL_DEFAULT ?>,
					params = [];

				var on_fail = {
					error_handler: error_handler,
					error_handler_params: (error_handler == <?= ZBX_PREPROC_FAIL_SET_VALUE ?>
							|| error_handler == <?= ZBX_PREPROC_FAIL_SET_ERROR ?>)
						? $('[name="preprocessing[' + num + '][error_handler_params]"]').val()
						: ''
				};

				if ($('[name="preprocessing[' + num + '][params][0]"]', $preprocessing).length) {
					params.push($('[name="preprocessing[' + num + '][params][0]"]', $preprocessing).val());
				}
				if ($('[name="preprocessing[' + num + '][params][1]"]', $preprocessing).length) {
					params.push($('[name="preprocessing[' + num + '][params][1]"]', $preprocessing).val());
				}

				steps.push($.extend({
					type: type,
					params: params.join("\n")
				}, on_fail));
			});

			return steps;
		}

		/**
		 * Creates preprocessing test modal window.
		 *
		 * @param {array}  step_nums          List of step numbers to collect.
		 * @param {bool}   show_final_result  Either the final result should be displayed.
		 * @param {object} trigger_elmnt      UI element triggered function.
		 */
		function openPreprocessingTestDialog(step_nums, show_final_result, trigger_elmnt) {
			var $step_obj = $(trigger_elmnt).closest('.preprocessing-list-item, .preprocessing-list-foot');

			PopUp('popup.preproctest.edit', $.extend({
				delay: $('#delay').val() || '',
				value_type: $('#value_type').val() || <?= CControllerPopupPreprocTest::ZBX_DEFAULT_VALUE_TYPE ?>,
				steps: getPreprocessingSteps(step_nums),
				hostid: <?= $data['hostid'] ?>,
				test_type: <?= $data['preprocessing_test_type'] ?>,
				step_obj: $step_obj.attr('data-step') || -1,
				show_final_result: show_final_result ? 1 : 0
			}, {'data': $step_obj.data('test-data') || []}), 'preprocessing-test', trigger_elmnt);
		}

		function makeParameterInput(index, type) {
			var preproc_param_single_tmpl = new Template($('#preprocessing-steps-parameters-single-tmpl').html()),
				preproc_param_double_tmpl = new Template($('#preprocessing-steps-parameters-double-tmpl').html()),
				preproc_param_multiline_tmpl = new Template($('#preprocessing-steps-parameters-multiline-tmpl').html());

			switch (type) {
				case '<?= ZBX_PREPROC_MULTIPLIER ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(_('number')) ?>
					})).css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_RTRIM ?>':
				case '<?= ZBX_PREPROC_LTRIM ?>':
				case '<?= ZBX_PREPROC_TRIM ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(_('list of characters')) ?>
					})).css('width', <?= ZBX_TEXTAREA_SMALL_WIDTH ?>);

				case '<?= ZBX_PREPROC_XPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(_('XPath')) ?>
					}));

				case '<?= ZBX_PREPROC_JSONPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(_('$.path.to.node')) ?>
					}));

				case '<?= ZBX_PREPROC_REGSUB ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= CJs::encodeJson(_('pattern')) ?>,
						placeholder_1: <?= CJs::encodeJson(_('output')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= CJs::encodeJson(_('min')) ?>,
						placeholder_1: <?= CJs::encodeJson(_('max')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
				case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(_('pattern')) ?>
					}));

				case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(_('seconds')) ?>
					})).css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_SCRIPT ?>':
					return $(preproc_param_multiline_tmpl.evaluate({rowNum: index})).multilineInput({
						title: <?= CJs::encodeJson(_('JavaScript')) ?>,
						placeholder: <?= CJs::encodeJson(_('script')) ?>,
						placeholder_textarea: 'return value',
						label_before: 'function (value) {',
						label_after: '}',
						grow: 'auto',
						rows: 0,
						maxlength: <?= (int) $data['preprocessing_script_maxlength'] ?>
					});

				case '<?= ZBX_PREPROC_PROMETHEUS_PATTERN ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= CJs::encodeJson(
							_('<metric name>{<label name>="<label value>", ...} == <value>')
						) ?>,
						placeholder_1: <?= CJs::encodeJson(_('<label name>')) ?>
					}));

				case '<?= ZBX_PREPROC_PROMETHEUS_TO_JSON ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= CJs::encodeJson(
							_('<metric name>{<label name>="<label value>", ...} == <value>')
						) ?>
					}));

				default:
					return '';
			}
		}

		var $preprocessing = $('#preprocessing'),
			step_index = $preprocessing.find('li.sortable').length;

		$preprocessing.sortable({
			disabled: $preprocessing.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').hasClass('<?= ZBX_STYLE_DISABLED ?>'),
			items: 'li.sortable',
			axis: 'y',
			containment: 'parent',
			cursor: IE ? 'move' : 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6
		});

		$preprocessing
			.on('click', '.element-table-add', function() {
				var preproc_row_tmpl = new Template($('#preprocessing-steps-tmpl').html()),
					$row = $(preproc_row_tmpl.evaluate({rowNum: step_index})),
					type = $('select[name*="type"]', $row).val();

				$('.step-parameters', $row).html(makeParameterInput(step_index, type));
				$(this).closest('.preprocessing-list-foot').before($row);

				$('.preprocessing-list-head').show();

				var sortable_count = $preprocessing.find('li.sortable').length;

				if (sortable_count == 1) {
					$('#preproc_test_all').show();
					$preprocessing
						.sortable('disable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
				else if (sortable_count > 1) {
					$preprocessing
						.sortable('enable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').removeClass('<?= ZBX_STYLE_DISABLED ?>');
				}

				step_index++;
			})
			.on('click', '#preproc_test_all', function() {
				var step_nums = [];
				$('select[name^="preprocessing"][name$="[type]"]', $preprocessing).each(function() {
					var str = $(this).attr('name');
					step_nums.push(str.substr(14, str.length - 21));
				});

				openPreprocessingTestDialog(step_nums, true, this);
			})
			.on('click', '.preprocessing-step-test', function() {
				var str = $(this).attr('name'),
					num = str.substr(14, str.length - 21);

				openPreprocessingTestDialog([num], false, this);
			})
			.on('click', '.element-table-remove', function() {
				$(this).closest('li.sortable').remove();

				var sortable_count = $preprocessing.find('li.sortable').length;

				if (sortable_count == 0) {
					$('#preproc_test_all').hide();
					$('.preprocessing-list-head').hide();
				}
				else if (sortable_count == 1) {
					$preprocessing
						.sortable('disable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
			})
			.on('change', 'select[name*="type"]', function() {
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
						$on_fail
							.prop('checked', false)
							.prop('disabled', true)
							.trigger('change');
						break;

					default:
						$on_fail.prop('disabled', false);
						break;
				}
			})
			.on('change', 'input[name*="params"]', function() {
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
						.attr('placeholder', <?= CJs::encodeJson(_('value')) ?>)
						.show();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
					$error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= CJs::encodeJson(_('error message')) ?>)
						.show();
				}
			});
	});
</script>
