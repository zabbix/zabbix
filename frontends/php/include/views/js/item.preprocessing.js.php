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
				->addClass(ZBX_STYLE_COLUMN_35)
				->addClass('list-numbered-item'),
			(new CDiv((new CTextBox('preprocessing[#{rowNum}][params][0]', ''))
				->setAttribute('placeholder', _('pattern'))
			))->addClass(ZBX_STYLE_COLUMN_20),
			(new CDiv((new CTextBox('preprocessing[#{rowNum}][params][1]', ''))
				->setAttribute('placeholder', _('output'))
			))->addClass(ZBX_STYLE_COLUMN_20),
			(new CDiv(new CCheckBox('preprocessing[#{rowNum}][on_fail]')))
				->addClass(ZBX_STYLE_COLUMN_15)
				->addClass(ZBX_STYLE_COLUMN_MIDDLE)
				->addClass(ZBX_STYLE_COLUMN_CENTER),
			(new CDiv((new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->removeId()
			))
				->addClass(ZBX_STYLE_COLUMN_10)
				->addClass(ZBX_STYLE_COLUMN_MIDDLE)
		]))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass('preprocessing-step'),
		(new CDiv([
			(new CDiv([
				new CDiv(new CLabel(_('Custom on fail'))),
				new CDiv(
					(new CRadioButtonList('preprocessing[#{rowNum}][error_handler]',
						ZBX_PREPROC_FAIL_DISCARD_VALUE
					))
						->addValue(_('Discard value'), ZBX_PREPROC_FAIL_DISCARD_VALUE)
						->addValue(_('Set value to'), ZBX_PREPROC_FAIL_SET_VALUE)
						->addValue(_('Set error to'), ZBX_PREPROC_FAIL_SET_ERROR)
						->setModern(true)
						->setEnabled(false)
				),
				new CDiv(
					(new CTextBox('preprocessing[#{rowNum}][error_handler_params]'))
						->setEnabled(false)
						->addStyle('display: none;')
				)
			]))
				->addClass(ZBX_STYLE_COLUMN_75)
				->addClass(ZBX_STYLE_COLUMN_MIDDLE)
		]))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass('on-fail-options')
			->addStyle('display: none;')
	]))
		->addClass('preprocessing-list-item')
		->addClass('sortable')
	?>
</script>
<script type="text/javascript">
	jQuery(function($) {
		$('.open-modal-code-editor')
			.codeEditor()
			.parent()
				.removeClass()
				.addClass('<?= ZBX_STYLE_COLUMN_40 ?>')
				.next()
					.hide();

		var preproc_row_tpl = new Template($('#preprocessing-steps-tmpl').html()),
			preprocessing = $('#preprocessing');

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
				var sortable_count,
					row = $(this).closest('.preprocessing-list-foot');

				row.before(preproc_row_tpl.evaluate({rowNum: preprocessing.find('li.sortable').length}));

				$('.preprocessing-list-head').show();
				sortable_count = preprocessing.find('li.sortable').length;

				if (sortable_count == 1) {
					preprocessing.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
				else if (sortable_count > 1) {
					preprocessing
						.sortable('enable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').removeClass('<?= ZBX_STYLE_DISABLED ?>');
				}
			})
			.on('click', '.element-table-remove', function() {
				var sortable_count;

				$(this).closest('li.sortable').remove();
				sortable_count = preprocessing.find('li.sortable').length;

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
				var row = $(this).closest('.preprocessing-step'),
					type = $(this).val(),
					params = $(this).closest('.preprocessing-step').find('input[type="text"][name*="params"]'),
					on_fail = $(this).closest('.preprocessing-step').find('[name*="on_fail"]');

				$(params)
					.val('')
					.removeAttr('title')
					.hide();

				switch (type) {
					case '<?= ZBX_PREPROC_MULTIPLIER ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('number')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_RTRIM ?>':
					case '<?= ZBX_PREPROC_LTRIM ?>':
					case '<?= ZBX_PREPROC_TRIM ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('list of characters')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_XPATH ?>':
					case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('XPath')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_JSONPATH ?>':
					case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('$.path.to.node')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_REGSUB ?>':
					case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('pattern')) ?>)
							.show();
						$(params[1])
							.attr('placeholder', <?= CJs::encodeJson(_('output')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('min')) ?>)
							.show();
						$(params[1])
							.attr('placeholder', <?= CJs::encodeJson(_('max')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
					case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('pattern')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('seconds')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_SCRIPT ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(_('script')) ?>)
							.attr('maxlength', <?= $data['preprocessing_script_maxlength'] ?>)
							.attr('title', <?= CJs::encodeJson(_('Click to view or edit code')) ?>)
							.addClass('open-modal-code-editor editable')
							.show()
							.after($('<input>').attr({
								'type': 'hidden',
								'id': $(params[0]).attr('id'),
								'name': $(params[0]).attr('name')
							}))
							.codeEditor()
							.parent()
								.removeClass()
								.addClass('<?= ZBX_STYLE_COLUMN_40 ?>')
								.next()
									.hide();
						break;

					case '<?= ZBX_PREPROC_PROMETHEUS_PATTERN ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(
								_('<metric name>{<label name>="<label value>", ...} == <value>')
							) ?>)
							.show();
						$(params[1])
							.attr('placeholder', <?= CJs::encodeJson(_('<label name>')) ?>)
							.show();
						break;

					case '<?= ZBX_PREPROC_PROMETHEUS_TO_JSON ?>':
						$(params[0])
							.attr('placeholder', <?= CJs::encodeJson(
								_('<metric name>{<label name>="<label value>", ...} == <value>')
							) ?>)
							.show();
						$(params[1]).hide();
						break;
				}

				if (type != '<?= ZBX_PREPROC_SCRIPT ?>' && row.find('.open-modal-code-editor')) {
					$(params[0])
						.codeEditor('destroy')
						.removeClass()
						.prop('maxlength', 255)
						.parent()
							.removeClass()
							.addClass('<?= ZBX_STYLE_COLUMN_20 ?>')
							.next()
								.show();
				}

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
