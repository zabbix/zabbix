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
			(new CDiv((new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->removeId()
			))->addClass('step-action')
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
	<?php
		echo (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))
			->setAttribute('placeholder', '#{placeholder}')
			->addClass('parameter-0');
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-double-tmpl">
	<?php
		echo (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))
				->setAttribute('placeholder', '#{placeholder_0}')
				->addClass('parameter-0').
			(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))
				->setAttribute('placeholder', '#{placeholder_1}')
				->addClass('parameter-1');
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-multiline-tmpl">
	<?php
		echo (new CMultilineInput('preprocessing[#{rowNum}][params][0]', '', [
			'add_post_js' => false
		]));
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {

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
						title: '<?= _('JavaScript') ?>',
						hint: '<?= _('Click to view or edit code') ?>',
						placeholder: '<?= _('script') ?>',
						maxlength: <?= $data['preprocessing_script_maxlength'] ?>
					});

				default:
					return '';
			}
		}

		var preprocessing = $('#preprocessing'),
			step_index = preprocessing.find('li.sortable').length;

		preprocessing.sortable({
			disabled: preprocessing.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').hasClass('<?= ZBX_STYLE_DISABLED ?>'),
			items: 'li.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6
		});

		preprocessing
			.on('click', '.element-table-add', function() {
				var preproc_row_tmpl = new Template($('#preprocessing-steps-tmpl').html()),
					$row = $(preproc_row_tmpl.evaluate({rowNum: step_index})),
					type = $('select[name*="type"]', $row).val();

				$('.step-parameters', $row).html(makeParameterInput(step_index, type));
				$(this).closest('.preprocessing-list-foot').before($row);

				$('.preprocessing-list-head').show();

				var sortable_count = preprocessing.find('li.sortable').length;

				if (sortable_count == 1) {
					preprocessing.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
				else if (sortable_count > 1) {
					preprocessing
						.sortable('enable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').removeClass('<?= ZBX_STYLE_DISABLED ?>');
				}

				step_index++;
			})
			.on('click', '.element-table-remove', function() {
				$(this).closest('li.sortable').remove();

				var sortable_count = preprocessing.find('li.sortable').length;

				if (sortable_count == 0) {
					$('.preprocessing-list-head').hide();
				}
				else if (sortable_count == 1) {
					preprocessing
						.sortable('disable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
			})
			.on('change', 'select[name*="type"]', function() {
				var $row = $(this).closest('.preprocessing-list-item'),
					type = $(this).val(),
					on_fail = $row.find('[name*="on_fail"]');

				$('.step-parameters', $row).html(makeParameterInput($row.data('step'), type));

				// Disable "Custom on fail" for some of the preprocessing types.
				switch (type) {
					case '<?= ZBX_PREPROC_RTRIM ?>':
					case '<?= ZBX_PREPROC_LTRIM ?>':
					case '<?= ZBX_PREPROC_TRIM ?>':
					case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
					case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
					case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
					case '<?= ZBX_PREPROC_THROTTLE_VALUE ?>':
					case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					case '<?= ZBX_PREPROC_SCRIPT ?>':
						on_fail
							.prop('checked', false)
							.prop('disabled', true)
							.trigger('change');
						break;

					default:
						on_fail.prop('disabled', false);
						break;
				}
			})
			.on('change', 'input[name*="params"]', function() {
				$(this).attr('title', $(this).val());
			})
			.on('change', 'input[name*="on_fail"]', function() {
				var on_fail_options = $(this).closest('.preprocessing-list-item').find('.on-fail-options');

				if ($(this).is(':checked')) {
					on_fail_options.find('input').prop('disabled', false);
					on_fail_options.show();
				}
				else {
					on_fail_options.find('input').prop('disabled', true);
					on_fail_options.hide();
				}
			})
			.on('change', 'input[name*="error_handler]"]', function() {
				var error_handler = $(this).val(),
					error_handler_params = $(this).closest('.on-fail-options').find('[name*="error_handler_params"]');

				if (error_handler == '<?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>') {
					error_handler_params
						.prop('disabled', true)
						.hide();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_VALUE ?>') {
					error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= CJs::encodeJson(_('value')) ?>)
						.show();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
					error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= CJs::encodeJson(_('error message')) ?>)
						.show();
				}
			});
	});
</script>
