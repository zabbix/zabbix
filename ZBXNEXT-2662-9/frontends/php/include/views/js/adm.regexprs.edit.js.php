<script type="text/x-jquery-tmpl" id="row_expr">
	<?= (new CRow([
			(new CComboBox('expressions[#{rowNum}][expression_type]', null, null, expression_type2str()))
				->onChange('onChangeExpressionType(this, #{rowNum})'),
			(new CTextBox('expressions[#{rowNum}][expression]', '', false, 255))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CComboBox('expressions[#{rowNum}][exp_delimiter]', null, null, expressionDelimiters()))
				->addStyle('display: none;'),
			new CCheckBox('expressions[#{rowNum}][case_sensitive]'),
			(new CButton('expressions[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->setAttribute('data-index', '#{rowNum}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="testTableRow">
	<?= (new CRow([
			'#{type}', '#{expression}', (new CSpan('#{result}'))->addClass('#{resultClass}')
		]))
			->addClass('test_row')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="testCombinedTableRow">
	<?= (new CRow([
			(new CCol(_('Combined result')))->setColspan(2), (new CSpan('#{result}'))->addClass('#{resultClass}')
		]))
			->addClass('test_row')
			->toString()
	?>
</script>

<script>
	function onChangeExpressionType(obj, index) {
		if (obj.value === '<?= EXPRESSION_TYPE_ANY_INCLUDED ?>') {
			jQuery('#expressions_' + index + '_exp_delimiter').show();
		}
		else {
			jQuery('#expressions_' + index + '_exp_delimiter').hide();
		}
	}

	(function($) {
		/**
		 * Object to manage expression related GUI elements.
		 * @type {Object}
		 */
		window.zabbixRegExp = {

			/**
			 * Template for expression row of testing results table.
			 * @type {String}
			 */
			testTableRowTpl: new Template($('#testTableRow').html()),

			/**
			 * Template for combined result row in testing results table.
			 * @type {String}
			 */
			testCombinedTableRowTpl: new Template($('#testCombinedTableRow').html()),

			/**
			 * Send all expressions data to server with test string.
			 *
			 * @param {String} string Test string to test expression against
			 */
			testExpressions: function(string) {
				var url = new Curl(),
					ajaxData = {
						testString: string,
						expressions: {}
					};

				$('#testResultTable').css({opacity: 0.5});

				$('#tbl_expr .form_row').each(function() {
					var index = $(this).data('index');

					ajaxData.expressions[index] = {
						expression : $('#expressions_' + index + '_expression').val(),
						expression_type : $('#expressions_' + index + '_expression_type').val(),
						exp_delimiter : $('#expressions_' + index + '_exp_delimiter').val(),
						case_sensitive : $('#expressions_' + index + '_case_sensitive').val()
					}
				});

				$.post(
					'adm.regexps.php?output=ajax&ajaxaction=test&sid=' + url.getArgument('sid'),
					{ajaxdata: ajaxData},
					$.proxy(this.showTestResults, this),
					'json'
				);
			},

			/**
			 * Update test results table with data received form server.
			 *
			 * @param {Object} response ajax response
			 */
			showTestResults: function(response) {
				var tplData, hasErrors, obj = this;

				$('#testResultTable .test_row').remove();

				hasErrors = false;

				$('#tbl_expr .form_row').each(function() {
					var index = $(this).data('index'),
						expr_result = response.data.expressions[index],
						result;

					if (response.data.errors[index]) {
						hasErrors = true;
						result = response.data.errors[index];
					}
					else {
						result = expr_result ? <?= CJs::encodeJson(_('TRUE')) ?> : <?= CJs::encodeJson(_('FALSE')) ?>;
					}

					switch ($('#expressions_' + index + '_expression_type').val()) {
						case '<?= EXPRESSION_TYPE_INCLUDED ?>':
							expression_type_str = <?= CJs::encodeJson(_('Character string included')) ?>;
							break;

						case '<?= EXPRESSION_TYPE_ANY_INCLUDED ?>':
							expression_type_str = <?= CJs::encodeJson(_('Any character string included')) ?>;
							break;

						case '<?= EXPRESSION_TYPE_NOT_INCLUDED ?>':
							expression_type_str = <?= CJs::encodeJson(_('Character string not included')) ?>;
							break;

						case '<?= EXPRESSION_TYPE_TRUE ?>':
							expression_type_str = <?= CJs::encodeJson(_('Result is TRUE')) ?>;
							break;

						case '<?= EXPRESSION_TYPE_FALSE ?>':
							expression_type_str = <?= CJs::encodeJson(_('Result is FALSE')) ?>;
							break;

						default:
							expression_type_str = '';
					}

					console.log($('#expressions_' + index + '_expression_type').val());

					$('#testResultTable').append(obj.testTableRowTpl.evaluate({
						expression: $('#expressions_' + index + '_expression').val(),
						type: expression_type_str,
						result: result,
						resultClass: expr_result ? '<?= ZBX_STYLE_GREEN ?>' : '<?= ZBX_STYLE_RED ?>'
					}));
				});

				if (hasErrors) {
					tplData = {
						resultClass: '<?= ZBX_STYLE_RED ?>',
						result: <?= CJs::encodeJson(_('UNKNOWN')) ?>
					};
				}
				else {
					tplData = {
						resultClass: response.data.final ? '<?= ZBX_STYLE_GREEN ?>' : '<?= ZBX_STYLE_RED ?>',
						result: response.data.final ? <?= CJs::encodeJson(_('TRUE')) ?> : <?= CJs::encodeJson(_('FALSE')) ?>
					};
				}

				$('#testResultTable').append(this.testCombinedTableRowTpl.evaluate(tplData));
				$('#testResultTable').css({opacity: 1});
			}
		};
	}(jQuery));

	jQuery(function($) {
		$('#testExpression, #tab_test').click(function() {
			zabbixRegExp.testExpressions($('#test_string').val());
		});

		// on clone we remove regexpid hidden field and also expressionid from expressions
		// it's needed because after clone all expressions should be added as new for cloned reg. exp
		$('#clone').click(function() {
			$('#regexpid, #clone, #delete, #tbl_expr .form_row input[type=hidden]').remove();
			$('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});
			$('#name').focus();
		});

		$('#tbl_expr').dynamicRows({
			template: '#row_expr'
		});
	});
</script>
