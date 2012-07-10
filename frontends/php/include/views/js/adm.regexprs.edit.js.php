<script type="text/x-jquery-tmpl" id="expressionRow">
	<tr id="exprRow_#{id}">
		<td><button type="button" class="link_menu exprEdit" data-id="#{id}">#{expression}</button></td>
		<td>#{type}</td>
		<td>#{case_sensitive}</td>
		<td>
			<button class="input link_menu exprRemove" type="button" data-id="#{id}"><?php echo _('Remove'); ?></button>
		</td>
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

				if (+this.data.expression_type === <?php echo EXPRESSION_TYPE_ANY_INCLUDED; ?>) {
					tplData.type += ' (' + '<?php echo _('Delimiter'); ?>' + '="' + this.data.exp_delimiter + '")';
				}

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
				switch (+this.data.expression_type) {
					case <?php echo EXPRESSION_TYPE_INCLUDED; ?>:
						return '<?php echo _('Character string included'); ?>';
					case <?php echo EXPRESSION_TYPE_ANY_INCLUDED; ?>:
						return '<?php echo _('Any character string included'); ?>';
					case <?php echo EXPRESSION_TYPE_NOT_INCLUDED; ?>:
						return '<?php echo _('Character string not included'); ?>';
					case <?php echo EXPRESSION_TYPE_TRUE; ?>:
						return '<?php echo _('Result is TRUE'); ?>';
					case <?php echo EXPRESSION_TYPE_FALSE; ?>:
						return '<?php echo _('Result is FALSE'); ?>';
				}
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

		$('#clone').click(function() {
			$('#regexpid').remove();
			$('#clone').remove();
			$('#delete').remove();
			$('#cancel').addClass('ui-corner-left');
			for (var id in zabbixRegExp.expressions) {
				delete zabbixRegExp.expressions[id].data['expressionid'];
			}

			console.log(zabbixRegExp.expressions);
		});

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
