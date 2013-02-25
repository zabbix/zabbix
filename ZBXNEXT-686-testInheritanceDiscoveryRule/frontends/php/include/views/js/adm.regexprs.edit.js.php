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
		<td class="wraptext">#{expression}</td>
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

		/**
		 * Class for single expression from global regular expression.
		 * @constructor
		 *
		 * @param {Object} expression Expression data.
		 *                 If expression has 'expressionid' it means that it exists in DB,
		 *                 otherwise it's treated as new expression.
		 *
		 * @property {String} id Unique id of expression.
		 *                    For new expression it's generated, for existing it's equal to 'expressionid'.
		 * @property {Object} data Expression data same as in DB table.
		 */
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

			/**
			 * Template for expression in expressions list.
			 *
			 * @type {Object}
			 */
			expressionRowTpl: new Template($('#expressionRow').html()),

			/**
			 * Render expression row in list of expressions.
			 *
			 * @param {Boolean} isNew If true it appends row to list, otherwise it search for expression row and replace it.
			 */
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

			/**
			 * Remove expression row.
			 */
			remove: function() {
				$('#exprRow_'+this.id).remove();
			},

			/**
			 * Update expression 'data' property with new values and rerender expression row.
			 *
			 * @param {Object} data New expression data values
			 */
			update: function(data) {
				$.extend(this.data, data);
				this.render();
			},

			/**
			 * Converts expression_type numeric value to string.
			 *
			 * @return {String}
			 */
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

			/**
			 * Converts expression case_sensitive numeric value to string.
			 *
			 * @return {String}
			 */
			case2str: function() {
				if (+this.data.case_sensitive) {
					return '<?php echo _('Yes'); ?>';
				}
				else {
					return '<?php echo _('No'); ?>';
				}
			},

			/**
			 * Compare with object.
			 *
			 * @param {Object} obj
			 *
			 * @return {Boolean}
			 */
			equals: function(obj) {
				return this.data.expression === obj.expression
						&& this.data.expression_type === obj.expression_type
						&& this.data.case_sensitive === obj.case_sensitive
						&& this.data.exp_delimiter === obj.exp_delimiter;
			}
		};


		/**
		 * Object to manage expression related GUI elements.
		 * @type {Object}
		 */
		window.zabbixRegExp = {

			/**
			 * List of Expression objects with keys equal to Expression.id.
			 * @type {Object}
			 */
			expressions: {},

			/**
			 * When upen expression form, it holds expression id if we update any or null if we create new.
			 * @type {String|Null}
			 */
			selectedID: null,

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
			 * Add expressions to manipulate with.
			 * For each expression data new Expression object is created.
			 *
			 * @param {Array} expressions List of expressions with DB data
			 */
			addExpressions: function(expressions) {
				var expr;

				for (var i = 0, ln = expressions.length; i < ln; i++) {
					expr = new Expression(expressions[i]);
					this.expressions[expr.id] = expr;
				}
			},

			/**
			 * Validate expression data.
			 *  - expression cannot be empty
			 *  - expression must be unique
			 *
			 * @param {Object} data
			 */
			validateExpression: function(data) {
				if (data.expression === '') {
					alert('<?php echo _('Expression cannot be empty'); ?>');
					return false;
				}
				for (var id in this.expressions) {
					// if we update expression, no error if equals itself
					if (id != this.selectedID && this.expressions[id].equals(data)) {
						alert('<?php echo _('Identical expression already exists'); ?>');
						return false;
					}
				}

				return true;
			},

			/**
			 * Show expression edit form.
			 *
			 * @param {String[]} id Id of expression which data should be shown in form.
			 *                      If id is not passed, form is filled with default values
			 *                      and on save new expression should be created.
			 */
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

			/**
			 * Hide expression form.
			 */
			hideForm: function() {
				$('#exprForm').hide();
			},

			/**
			 * Either update data of existing expression or create new expression with data in form.			 *
			 */
			saveForm: function() {
				var data = {
					expression: $('#expressionNew').val(),
					expression_type: $('#typeNew').val(),
					exp_delimiter: $('#delimiterNew').val(),
					case_sensitive: $('#case_sensitiveNew').prop('checked') ? '1' : '0'
				};

				if (this.validateExpression(data)) {
					if (this.selectedID === null) {
						this.addExpressions([data]);
					}
					else {
						this.expressions[this.selectedID].update(data);
					}

					this.hideForm();
				}
			},

			/**
			 * Remove expression.
			 *
			 * @param {String} id Id of expression
			 */
			removeExpression: function(id) {
				this.expressions[id].remove();
				delete this.expressions[id];
			},

			/**
			 * Send all expressions data to server with test string.
			 *
			 * @param {String} string Test string to test expression against
			 */
			testExpressions: function(string) {
				var ajaxData = {
					testString: string,
					expressions: {}
				},
					url;

				if ($.isEmptyObject(this.expressions)) {
					$('#testResultTable tr:not(.header)').remove();
				}
				else {
					url = new Curl();

					$('#testResultTable').css({
						opacity: 0.5
					});
					$('#testPreloader').show();

					for (var id in this.expressions) {
						ajaxData.expressions[id] = this.expressions[id].data;
					}

					$.post(
							'adm.regexps.php?output=ajax&ajaxaction=test&sid='+url.getArgument('sid'),
							{ajaxdata: ajaxData},
							$.proxy(this.showTestResults, this),
							'json'
					);
				}
			},

			/**
			 * Update test results table with data received form server.
			 *
			 * @param {Object} response ajax response
			 */
			showTestResults: function(response) {
				var tplData, expr, exprResult;

				$('#testResultTable tr:not(.header)').remove();

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

				$('#testResultTable').css({
					opacity: 1
				});
				$('#testPreloader').hide();
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
