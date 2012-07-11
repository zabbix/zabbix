<script type="text/x-jquery-tmpl" id="expressionRow">
	<tr id="exprRow_#{id}">
		<td>#{expression}</td>
		<td>#{type}</td>
		<td>#{case_sensitive}</td>
		<td>
			<button class="input link_menu exprEdit" type="button" data-id="#{id}"><?php echo _('Edit'); ?></button>&nbsp;
			<button class="input link_menu exprRemove" type="button" data-id="#{id}"><?php echo _('Remove'); ?></button>
		</td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="testTableRow">
	<tr class="even_row">
		<td>#{expression}</td>
		<td>#{type}</td>
		<td><span class="bold #{resultClass}">#{result}</span></td>
	</tr>
</script>

<script type="text/x-jquery-tmpl" id="testCombinedTableRow">
	<tr class="odd_row">
		<td colspan="2"><?php echo _('Combined result'); ?></td>
		<td><span class="bold #{resultClass}">#{result}</span></td>
	</tr>
</script>

<script>
	(function($) {
		'use strict';

		function Expression(expression) {
			this.data = expression;

			if (typeof expression.expressionid === 'undefined') {
				this.id = getUniqueId();
			}
			else {
				this.id = expression.expressionid;
			}

			this.render(true);
		}
		Expression.prototype = {
			expressionRowTpl: new Template($('#expressionRow').html()),
			data: {},
			id: null,

			render: function(isNew) {
				var tplData = {
					id: this.id,
					expression: this.data.expression,
					type: this.type2str(),
					case_sensitive: this.case2str()
				};

				if (isNew) {
					$('#exprTable tr.footer').before(this.expressionRowTpl.evaluate(tplData));
				}
				else {
					$('#exprRow_'+this.id).replaceWith(this.expressionRowTpl.evaluate(tplData));
				}
			},

			remove: function() {
				$('#exprRow_'+this.id).remove();
			},

			update: function(data) {
				$.extend(this.data, data);
				this.render();
			},

			type2str: function() {
				var str;

				switch (+this.data.expression_type) {
					case <?php echo EXPRESSION_TYPE_INCLUDED; ?>:
						str = '<?php echo _('Character string included'); ?>';
						break;
					case <?php echo EXPRESSION_TYPE_ANY_INCLUDED; ?>:
						str = '<?php echo _('Any character string included'); ?>';
						break;
					case <?php echo EXPRESSION_TYPE_NOT_INCLUDED; ?>:
						str = '<?php echo _('Character string not included'); ?>';
						break;
					case <?php echo EXPRESSION_TYPE_TRUE; ?>:
						str = '<?php echo _('Result is TRUE'); ?>';
						break;
					case <?php echo EXPRESSION_TYPE_FALSE; ?>:
						str = '<?php echo _('Result is FALSE'); ?>';
						break;
				}

				if (+this.data.expression_type === <?php echo EXPRESSION_TYPE_ANY_INCLUDED; ?>) {
					str += ' (' + '<?php echo _('delimiter'); ?>' + '="' + this.data.exp_delimiter + '")';
				}

				return str;
			},

			case2str: function() {
				if (+this.data.case_sensitive) {
					return '<?php echo _('Yes'); ?>';
				}
				else {
					return '<?php echo _('No'); ?>';
				}
			}
		};


		window.zabbixRegExp = {
			expressions: {},
			selectedID: null,
			testTableRowTpl: new Template($('#testTableRow').html()),
			testCombinedTableRowTpl: new Template($('#testCombinedTableRow').html()),

			addExpressions: function(expressions) {
				var expr;

				for (var i = 0, ln = expressions.length; i < ln; i++) {
					expr = new Expression(expressions[i]);
					this.expressions[expr.id] = expr;
				}
			},

			showForm: function(id) {
				var data;

				if (typeof id === 'undefined') {
					data = {
						expression: '',
						expression_type: '0',
						exp_delimiter: ',',
						case_sensitive: '1'
					};
					this.selectedID = null;

					$('#saveExpression').val('<?php echo _('Add'); ?>');
				}
				else {
					data = this.expressions[id].data;
					this.selectedID = id;

					$('#saveExpression').val('<?php echo _('Update'); ?>');
				}

				$('#expressionNew').val(data.expression);
				$('#typeNew').val(data.expression_type);
				$('#delimiterNew').val(data.exp_delimiter);
				$('#case_sensitiveNew').prop('checked', +data.case_sensitive);

				// when type is updated fire change event to show/hide delimiter row
				$('#typeNew').change();

				$('#exprForm').show();
			},

			hideForm: function() {
				$('#exprForm').hide();
			},

			saveForm: function() {
				var data = {
					expression: $('#expressionNew').val(),
					expression_type: $('#typeNew').val(),
					exp_delimiter: $('#delimiterNew').val(),
					case_sensitive: +$('#case_sensitiveNew').prop('checked')
				};

				if (this.selectedID === null) {
					this.addExpressions([data]);
				}
				else {
					this.expressions[this.selectedID].update(data);
				}

				this.hideForm();
			},

			removeExpression: function(id) {
				this.expressions[id].remove();
				delete this.expressions[id];
			},

			testExpressions: function(string) {
				var ajaxData = {
					testString: string,
					expressions: {}
				},
					url = new Curl();

				for (var id in this.expressions) {
					ajaxData.expressions[id] = this.expressions[id].data;
				}

				$.post(
					'adm.regexps.php?output=ajax&ajaxaction=test&sid='+url.getArgument('sid'),
					{ajaxdata: ajaxData},
					$.proxy(this.showTestResults, this),
					'json'
				);
			},

			showTestResults: function(response) {
				var tplData, expr, exprResult;

				jQuery('#testResultTable tr:not(.header)').remove();

				for (var id in this.expressions) {
					expr = this.expressions[id];
					exprResult = response.data.expressions[id];

					tplData = {
						expression: expr.data.expression,
						type: expr.type2str(),
						result: exprResult ? '<?php echo _('TRUE'); ?>' : '<?php echo _('FALSE'); ?>',
						resultClass: exprResult ? 'green' : 'red'
					};

					$('#testResultTable').append(this.testTableRowTpl.evaluate(tplData));
				}

				tplData = {
					resultClass: response.data.final ? 'green' : 'red',
					result: response.data.final ? '<?php echo _('TRUE'); ?>' : '<?php echo _('FALSE'); ?>'
				};
				$('#testResultTable').append(this.testCombinedTableRowTpl.evaluate(tplData));
			}

		};
	}(jQuery));

	jQuery(function($) {
		'use strict';

		$('#exprTable').on('click', 'button.exprRemove', function() {
			zabbixRegExp.removeExpression($(this).data('id'));
		});

		$('#exprTable').on('click', 'input.exprAdd', function() {
			zabbixRegExp.showForm();
		});

		$('#exprTable').on('click', 'button.exprEdit', function() {
			zabbixRegExp.showForm($(this).data('id'));
		});

		$('#saveExpression').click(function() {
			zabbixRegExp.saveForm();
		});

		$('#cancelExpression').click(function() {
			zabbixRegExp.hideForm();
		});

		$('#testExpression, #tab_test').click(function() {
			zabbixRegExp.testExpressions($('#test_string').val());
		});

		// on submit we need to add all expressions data as hidden fields to form
		$('#zabbixRegExpForm').submit(function() {
			var form = $('#zabbixRegExpForm'),
				expr,
				counter = 0;

			for (var id in zabbixRegExp.expressions) {
				expr = zabbixRegExp.expressions[id].data;

				for (var fieldName in expr) {
					$("<input>").attr({
						'type': 'hidden',
						'name': 'expressions['+counter+']['+fieldName + ']'
					}).val(expr[fieldName]).appendTo(form);
				}
				counter++;
			}
		});

		// on clone we remove regexpid hidden field and also expressionid from expressions
		// it's needed because after clone all expressions should be added as new for cloned reg. exp
		$('#clone').click(function() {
			$('#regexpid').remove();
			$('#clone').remove();
			$('#delete').remove();
			$('#cancel').addClass('ui-corner-left');
			for (var id in zabbixRegExp.expressions) {
				delete zabbixRegExp.expressions[id].data['expressionid'];
			}
		});

		// handler for type select in form, show/hide delimiter select
		$('#typeNew').change(function() {
			if ($(this).val() !== '<?php echo EXPRESSION_TYPE_ANY_INCLUDED; ?>') {
				$('#delimiterNewRow').hide();
			}
			else {
				$('#delimiterNewRow').show();
			}
		});
	});
</script>
