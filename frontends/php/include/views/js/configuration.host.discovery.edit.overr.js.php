<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="lldoverride-row-templated">
	<?= (new CRow([
			'',
			(new CSpan('1:'))->setAttribute('data-row-num', ''),
			(new CLink('#{name}', 'javascript:lldoverrides.overrides.open(#{no});')),
			'#{stop_verbose}',
			''
		]))->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="lldoverride-row">
	<?= (new CRow([
			(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan('1:'))->setAttribute('data-row-num', ''),
			(new CLink('#{name}', 'javascript:lldoverrides.overrides.open(#{no});')),
			'#{stop_verbose}',
			(new CCol((new CButton(null, _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="override-filters-row">
	<?=
		(new CRow([[
				new CSpan('#{formulaId}'),
				new CVar('overrides_filters[#{rowNum}][formulaid]', '#{formulaId}')
			],
			(new CTextBox('overrides_filters[#{rowNum}][macro]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->addClass(ZBX_STYLE_UPPERCASE)
				->addClass('macro')
				->setAttribute('placeholder', '{#MACRO}')
				->setAttribute('data-formulaid', '#{formulaId}'),
			(new CComboBox('overrides_filters[#{rowNum}][operator]', CONDITION_OPERATOR_REGEXP, null, [
				CONDITION_OPERATOR_REGEXP => _('matches'),
				CONDITION_OPERATOR_NOT_REGEXP => _('does not match')
			]))->addClass('operator'),
			(new CTextBox('overrides_filters[#{rowNum}][value]', '', false, 255))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
				->setAttribute('placeholder', _('regular expression')),
			(new CCol(
				(new CButton('overrides_filters#{rowNum}_remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="lldoverride-operation-row-templated">
	<?= (new CRow([
			'#{condition}',
			'#{actions}',
			(new CCol(
				// TODO VM: replace by button.
				(new CLink(_('View'), 'javascript:lldoverrides.operations.open(#{no});'))
			))->addClass(ZBX_STYLE_NOWRAP) // TODO VM: why do I need this class?
		]))->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="lldoverride-operation-row">
	<?= (new CRow([
			'#{condition}',
			'#{actions}',
			(new CCol([
				// TODO VM: replace by button.
				(new CLink(_('Edit'), 'javascript:lldoverrides.operations.open(#{no});'))
					->addStyle('margin-right:5px;'), // TODO VM: do with some class (probably already exists)
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			]))->addClass(ZBX_STYLE_NOWRAP) // TODO VM: why do I need this class?
		]))->toString()
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		window.lldoverrides = {
			templated:                           <?= $data['templated'] ? 1 : 0 ?>,
			ZBX_STYLE_DRAG_ICON:                 <?= zbx_jsvalue(ZBX_STYLE_DRAG_ICON) ?>,
			ZBX_STYLE_TD_DRAG_ICON:              <?= zbx_jsvalue(ZBX_STYLE_TD_DRAG_ICON) ?>,
			ZBX_STYLE_DISABLED:                  <?= zbx_jsvalue(ZBX_STYLE_DISABLED) ?>,
			msg: {
				yes:                        <?= json_encode(_('Yes')) ?>,
				no:                         <?= json_encode(_('No')) ?>,
				item_prototype:             <?= json_encode(_('Item prototype')) ?>,
				trigger_prototype:          <?= json_encode(_('Trigger prototype')) ?>,
				graph_prototype:            <?= json_encode(_('Graph prototype')) ?>,
				host_prototype:             <?= json_encode(_('Host prototype')) ?>,
				equals:                     <?= json_encode(_('equals')) ?>,
				does_not_equal:             <?= json_encode(_('does not equal')) ?>,
				contains:                   <?= json_encode(_('contains')) ?>,
				does_not_contain:           <?= json_encode(_('does not contain')) ?>,
				matches:                    <?= json_encode(_('matches')) ?>,
				does_not_match:             <?= json_encode(_('does not match')) ?>,

				// TODO VM: do I need all of them?
				data_not_encoded:           <?= json_encode(_('Data is not properly encoded.')) ?>,
				name_filed_length_exceeded: <?= json_encode(_('Name of the form field should not exceed 255 characters.')) ?>,
				value_without_name:         <?= json_encode(_('Values without names are not allowed in form fields.')) ?>,
				ok:                         <?= json_encode(_('Ok')) ?>,
				error:                      <?= json_encode(_('Error')) ?>
			}
		};

		window.lldoverrides.override_row_template = new Template(jQuery(lldoverrides.templated
			? '#lldoverride-row-templated'
			: '#lldoverride-row'
		).html());

		window.lldoverrides.operations_row_template = new Template(jQuery(lldoverrides.templated
			? '#lldoverride-operation-row-templated'
			: '#lldoverride-operation-row'
		).html());
// TODO VM: fix styles for dragged placeholder.
		window.lldoverrides.overrides = new Overrides($('#overridesTab'), <?= json_encode(array_values($data['overrides'])) ?>);

		window.lldoverrides.$form = $('form[name="itemForm"]').on('submit', function(e) {
			var hidden_form = this.querySelector('#hidden-form');

			hidden_form && hidden_form.remove();
			hidden_form = document.createElement('div');
			hidden_form.id = 'hidden-form';

			hidden_form.appendChild(lldoverrides.overrides.toFragment());

			this.appendChild(hidden_form);
		});
	});

// TODO VM: untouched
	/**
	 * Implementation of jQuery.val for radio buttons. Use this methon within scoped jQuery object;
	 * Use with jQuery collection of input nodes.
	 *
	 * @param {string} value  Check button by value. Read value if no param is given.
	 *
	 * @return {string}
	 */
	function radioVal(value) {
		if (typeof value === 'undefined') {
			return this.filter(':checked').val();
		}
		this.filter('[value="' + value + '"]').get(0).checked = true;
	}

	/**
	 * Returns common $.sortable options.
	 *
	 * @return {object}
	 */
	function sortableOpts() {
		return {
			items: 'tbody tr.sortable',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.' + lldoverrides.ZBX_STYLE_DRAG_ICON,
			tolerance: 'pointer',
			opacity: 0.6,
			start: function(e, ui) {
				ui.placeholder.height(ui.item.height());
			}
		};
	}

// TODO VM: do I need it?
	/**
	 * A helper method for truncating string in middle.
	 *
	 * @param {string} str  String to be shortened into mid-elliptic.
	 * @param {int}    max  Max length of resulting string (inclusive).
	 */
//	function midEllipsis(str, max) {
//		if (str.length < max) {
//			return str;
//		}

//		var sep = '...',
//			max = max - sep.length,
//			len = max / 2,
//			pt1 = str.slice(0, Math.floor(len)),
//			pt2 = str.slice(- Math.ceil(len));

//		return pt1 + sep + pt2;
//	}

	// TODO VM: untouched, but unneded here.
	/**
	 * Trait method for DynamicRows, that renders disabled state.
	 *
	 * @param {bool} disable
	 */
	function dynamicRowsToggleDisable(disable) {
		this.options.disabled = disable;

		this.$element.sortable('option', 'disabled', disable);
		this.$element.toggleClass('disabled', disable);
		this.$element.find('input, button').prop('disabled', disable);

		if (!disable) {
			this.$element.trigger('dynamic_rows.updated', this);
		}
	}

	// TODO VM: untouched?
	/**
	 * Helper that drys up repetitive code. Extracts pair objects from rows, in the order they are in DOM.
	 * Not a part of DynamicRows plugin as this method follows tight conventions with templates.
	 *
	 * @param {callable} cb  Callback will get passed pair object, and it's data index as second argument.
	 * @param {bool} allowed_empty  This can be set to true, to return empty pairs also.
	 */
	function eachPair(cb, allow_empty) {
		this.$element.find('[data-index]').each(function(i, node) {
			var name = node.querySelector('[data-type="name"]').value,
				value = node.querySelector('[data-type="value"]').value;

			if (name || value || allow_empty) {
				cb({name: name, value: value}, node.getAttribute('data-index'));
			}
		});
	}

	// TODO VM: untouched
	/**
	 * Writes data index as new nodes attribute. Bind remove event.
	 */
	function dynamicRowsBindNewRow($el) {
		$el.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {
			e.new_node.setAttribute('data-index', e.data_index);
			e.new_node.querySelector('.element-table-remove')
				.addEventListener('click', dynamic_rows.removeRow.bind(dynamic_rows, e.data_index));

			// TODO VM: do I need it?
			// Because IE does not have NodeList.prototype.forEach method.
			Array.prototype.forEach.call(e.new_node.querySelectorAll('input'), function(input_node) {
				input_node.addEventListener('keyup', function(e) {
					$el.trigger('dynamic_rows.updated', dynamic_rows);
				});
			});
		});
	}

	// TODO VM: untouched
	/**
	 * Implements disabling remove button if last pair is considered empty.
	 */
	function dynamicRowsBindRemoveDisable($el) {
		$el.on('dynamic_rows.updated', function(e, dynamic_rows) {
			var $remove_btns = dynamic_rows.$element.find('.element-table-remove');

			if (dynamic_rows.length == 1) {
				return eachPair.call(dynamic_rows, function(pair) {
					$remove_btns.prop('disabled', !pair.name && !pair.value);
				}, true);
			}

			$remove_btns.prop('disabled', false);
		});
	}

	/**
	 * Implements disabling sortable if less than two data rows.
	 */
	function dynamicRowsBindSortableDisable($el) {
		$el.on('dynamic_rows.updated', function(e, dynamic_rows) {
			if (dynamic_rows.length < 2) {
				dynamic_rows.$element.sortable('option', 'disabled', true);
				dynamic_rows.$element.find('.' + lldoverrides.ZBX_STYLE_DRAG_ICON)
					.addClass(lldoverrides.ZBX_STYLE_DISABLED);
			}
			else {
				dynamic_rows.$element.sortable('option', 'disabled', false);
				dynamic_rows.$element.find('.' + lldoverrides.ZBX_STYLE_DRAG_ICON)
					.removeClass(lldoverrides.ZBX_STYLE_DISABLED);
			}
		});
	}

	// TODO VM: untouched
	/**
	 * A helper method for creating hidden input nodes.
	 *
	 * @param {string} name
	 * @param {string} value
	 * @param {string?} prefix
	 *
	 * @return {Node}
	 */
	function hiddenInput(name, value, prefix) {
		var input = window.document.createElement('input');

		input.type = 'hidden';
		input.value = value;
		input.name = prefix ? prefix + '[' + name + ']' : name;

		return input;
	}

	/**
	 * @param {jQuery} $element
	 * @param {Object} options
	 * @param {Array} data
	 */
	function DynamicRows($element, options, data) {
		if (!(options.add_before instanceof Node)) {
			throw 'Error: options.add_before must be instanceof Node.';
		}

		if (!(options.template instanceof Template)) {
			throw 'Error: options.template must be instanceof Template.';
		}

		this.data = {};
		this.$element = $element;
		this.options = jQuery.extend({}, {
			// Please note, this option does not work if data_index option is in use.
			ensure_min_rows: 0,
			// If this option is used, it represents key of data object whose value will be used as data_index.
			data_index: null
		}, options);

		this.data_index = 0;
		this.length = 0;
		data && this.setData(data);
	}

	/**
	 * All events are dispatched on $element. Second argument is instance, first argument is event object.
	 *
	 * @param {string} evt_key
	 * @param {object} evt_data  Optional object to be merged into event data.
	 *
	 * @return {jQuery.Event}
	 */
	DynamicRows.prototype.dispatch = function(evt_key, evt_data) {
		var evt = jQuery.Event('dynamic_rows.' + evt_key, evt_data);

		this.$element.trigger(evt, [this]);

		return evt;
	}

	/**
	 * Adds a row before the given row.
	 *
	 * @param {object} data          Data to be passed into template.
	 * @param {integer?} data_index  Optional index, if data with given index exists, then in place update will happen.
	 *                               In case of update all events dispatched as if add new was performed.
	 */
	DynamicRows.prototype.addRow = function(row_data, data_index) {
		if (this.options.disabled) {
			return;
		}

		if (!data_index) {
			data_index = this.options.data_index
				? row_data[this.options.data_index]
				: ++this.data_index;
		}

		var new_row = {
			node: this.createRowNode(row_data),
			data: row_data || {}
		};

		var evt_before_add = this.dispatch('beforeadd', {
			new_data: new_row.data,
			new_node: new_row.node,
			add_before: this.options.add_before,
			data_index: data_index
		});

		if (evt_before_add.isDefaultPrevented()) {
			return;
		}

		if (this.data[data_index]) {
			evt_before_add.add_before.parentNode.replaceChild(evt_before_add.new_node, this.data[data_index].node);
		}
		else {
			evt_before_add.add_before.parentNode.insertBefore(evt_before_add.new_node, evt_before_add.add_before);
			this.length ++;
		}

		this.data[data_index] = new_row;

		this.dispatch('updated');
	};

	/**
	* Replaces current data with new one.
	* Be aware that min rows are ensured and events are triggered only for add.
	* Removing happens outside this API, to not to call.
	*
	* @param {array} data  Array of data for row templates.
	*
	* @return {DynamicRows}
	*/
	DynamicRows.prototype.setData = function(data) {
		if (!(data  instanceof Array)) {
			throw 'Expected Array.';
		}

		for (var i in this.data) {
			this.unlinkIndex(i);
		}

		data.forEach(function(obj) {
			this.addRow(obj);
		}.bind(this));

		this.ensureMinRows();

		return this;
	};

	/**
	 * Adds empty rows if needed.
	 */
	DynamicRows.prototype.ensureMinRows = function() {
		var rows_to_add = this.options.ensure_min_rows - this.length;
		while (rows_to_add > 0) {
			rows_to_add--;
			this.addRow();
		}
	};

	/**
	 * Renders Node from template.
	 *
	 * @param {object} data  Data to be passed into template.
	 *
	 * @return {Node}
	 */
	DynamicRows.prototype.createRowNode = function(data) {
		var evt = this.dispatch('beforerender', {view_data: data}); // TODO VM: unused here
		var html_str = this.options.template.evaluate(evt.view_data);

		return jQuery(html_str).get(0);
	};


	/**
	 * Removes data at given index. Method is to be used by plugin internally, does not dispatch events.
	 *
	 * @param {int} data_index
	 *
	 * @return {object}  Object that just got removed.
	 */
	DynamicRows.prototype.unlinkIndex = function(data_index) {
		this.data[data_index].node.remove();
		var ref = this.data[data_index];

		delete this.data[data_index];
		this.length --;

		return ref;
	}

	/**
	 * Removes the given row.
	 *
	 * @param {int} data_index
	 */
	DynamicRows.prototype.removeRow = function(data_index) {
		if (this.options.disabled) {
			return;
		}

		var removed_row = this.unlinkIndex(data_index);

		this.dispatch('afterremove', {removed_row: removed_row, data_index: data_index});
		this.dispatch('updated');

		this.ensureMinRows();
	};

	/**
	 * Represents overrides tab in discovery rules layout.
	 *
	 * @param {jQuery} $tab
	 * @param {array}  overrides  Initial overrides objects data array.
	 */
	function Overrides($tab, overrides) {
		this.data = {};
		this.new_id = 0;
		this.sort_index = [];

		overrides.forEach(function(override, no) {
			this.data[no + 1] = new Override(override);
			this.sort_index.push(no + 1);
		}.bind(this));

		this.$container = jQuery('.lld-overrides-table', $tab);
		this.$container.find('.element-table-add').on('click', this.openNew.bind(this));

		this.$container.on('dynamic_rows.beforerender', function(e, dynamic_rows) {
			e.view_data.stop_verbose = (e.view_data.stop === '1') ? lldoverrides.msg.yes : lldoverrides.msg.no;
		});

		this.overrides_dynamic_rows = new DynamicRows(this.$container, {
			add_before: this.$container.find('.element-table-add').closest('tr')[0],
			template: lldoverrides.override_row_template
		});

		if (!lldoverrides.templated) {
			this.$container.sortable(sortableOpts());
			this.$container.sortable('option', 'update', this.onSortOrderChange.bind(this));
			this.$container.on('dynamic_rows.afterremove', function(e, dynamic_rows) {
				delete this.data[e.data_index];
				this.onSortOrderChange();
			}.bind(this));

			dynamicRowsBindSortableDisable(this.$container);
			dynamicRowsBindNewRow(this.$container);
		}
		else {
			this.$container.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {
				e.new_node.setAttribute('data-index', e.data_index);
			});
		}

		this.renderData();
	}

	/**
	 * The parts of form that are easier to maintain in functional objects are transformed into hidden input fields.
	 *
	 * @return {DocumentFragment}
	 */
	Overrides.prototype.toFragment = function() {
		var frag = document.createDocumentFragment(),
			iter_step = 0;

		this.sort_index.forEach(function(id) {
			var override = this.data[id],
				prefix_override = 'overrides[' + (iter_step ++) + ']',
				prefix_filter = prefix_override + '[filter]',
				iter_filters = 0;

			// TODO VM: fix naming to be consistent with API.
			frag.appendChild(hiddenInput('step',     iter_step,                        prefix_override)); // TODO VM: maybe should add "-1"
			frag.appendChild(hiddenInput('name',     override.data.name,               prefix_override));
			frag.appendChild(hiddenInput('stop',     override.data.stop,               prefix_override));
			frag.appendChild(hiddenInput('evaltype', override.data.overrides_evaltype, prefix_filter));
			frag.appendChild(hiddenInput('formula',  override.data.overrides_formula,  prefix_filter)); // TODO VM: may be missing, don't add?

			override.data.overrides_filters.forEach(function(override_filter) {
				var prefix = prefix_filter + '[conditions][' + (iter_filters ++) + ']';
				frag.appendChild(hiddenInput('formulaid', override_filter.formulaid, prefix));
				frag.appendChild(hiddenInput('macro',     override_filter.macro,     prefix));
				frag.appendChild(hiddenInput('value',     override_filter.value,     prefix));
				frag.appendChild(hiddenInput('operator',  override_filter.operator,  prefix));
			});

			// TODO VM: add operations
		}.bind(this));

		return frag;
	};

	/**
	 * This method maintains property for iterating overrides in the order that rows have in DOM at the moment,
	 * also updates visual counter in DOM for override rows.
	 */
	Overrides.prototype.onSortOrderChange = function() {
		var order = [];
		this.$container.find('[data-index]').each(function(index) {
			this.querySelector('[data-row-num]').innerText = (index + 1) + ':';
			order.push(this.attributes.getNamedItem('data-index').value);
		});
		this.sort_index = order;
	};

	/**
	 * Used to validate override names with server, on PopUp form validate event.
	 *
	 * @return {array}  Array of strings.
	 */
	Overrides.prototype.getOverrideNames = function() {
		var names = [];

		for (var no in this.data) {
			names.push(this.data[no].data.name);
		}

		return names;
	};

	/**
	 * This method hydrates the parsed html PopUp form with data from specific override.
	 *
	 * @param {int} no  Override index.
	 */
	Overrides.prototype.onStepOverlayReadyCb = function(no) {
		var override_ref = this.data[no] ? this.data[no] : this.new_override;
		this.edit_form = new OverrideEditForm(jQuery('#lldoverride_form'), override_ref);
	};

	/**
	 * Creates new override id and opens form for it.
	 */
	Overrides.prototype.openNew = function() {
		this.new_id -= 1;

		this.new_override = new Override({no: this.new_id});
		this.new_override.open(this.new_id, this.$container.find('.element-table-add'));
	};

	/**
	 * Renders overrides in DOM.
	 */
	Overrides.prototype.renderData = function() {
		this.sort_index.forEach(function(data_index) {
			this.overrides_dynamic_rows.addRow(this.data[data_index].data, data_index);
		}.bind(this));

		this.onSortOrderChange();
	}

	/**
	 * Opens popup for a override.
	 *
	 * @param {integer} no
	 */
	Overrides.prototype.open = function(no) {
		this.data[no]
			.open(no, this.$container.find('[data-index="' + no + '"] a'));
	};

	// TODO VM: update defautls
	/**
	 * This object represents a override of web scenario.
	 *
	 * @param {object} data  Optional override initial data.
	 */
	function Override(data) {
		// TODO VM: doublecheck, what should be in defaults
		var defaults = {
			name: '',
			stop: '0',
			filter: {
				'evaltype': '0',
				'formula': '',
				'conditions': []
			},
			operations: []
		};
		this.data = jQuery.extend(true, defaults, data); // TODO VM: why it is other way around in httptest??

		// TODO VM: reorganize id's to match these values, then remove this part.
		this.data.no = this.data.step;
		this.data.overrides_evaltype = this.data.filter.evaltype;
		this.data.overrides_formula = this.data.filter.formula;
		this.data.overrides_filters = this.data.filter.conditions;
		delete this.data.filter;
	}

	/**
	 * Merges old data with new data.
	 */
	Override.prototype.update = function(data) {
		jQuery.extend(this.data, data);
	};

	/**
	 * Opens override popup - edit or create form.
	 * Note: a callback this.onStepOverlayReadyCb is called from within popup form once it is parsed.
	 *
	 * @param {int}  step     Override index.
	 * @param {Node} refocus  A node to set focus to, when popup is closed.
	 */
	Override.prototype.open = function(no, refocus) {
		// TODO VM: update parameters
		return PopUp('popup.lldoverride', {
			no:                 no,
			templated:          lldoverrides.templated,
			name:               this.data.name,
			old_name:           this.data.name,
			stop:               this.data.stop,
			overrides_evaltype: this.data.overrides_evaltype,
			overrides_formula:  this.data.overrides_formula,
			overrides_filters:  this.data.overrides_filters,
			operations:         this.data.operations,
			overrides_names:    lldoverrides.overrides.getOverrideNames()
		}, null, refocus);
	};

	/**
	 * Represents popup form.
	 *
	 * @param {jQuery} $form
	 * @param {Override} override_ref  Reference to override instance from Overrides object.
	 */
	function OverrideEditForm($form, override_ref) {
		this.$form = $form;
		this.override = override_ref;

		// Initiate Filters dynamic rows and evaltype.
		this.filterDynamicRows();
		this.operations = new Operations(this.$form, this.override.data.operations);
		// This will be used for link on edit button.
		window.lldoverrides.operations = this.operations; // TODO VM: do I need "this.operations", or I can save directly to window.lldoverrides?
	}

	OverrideEditForm.prototype.updateExpression = function() {
		var filters = [];

		jQuery('#overrides_filters .macro').each(function(index, macroInput) {
			macroInput = jQuery(macroInput);
			macroInput.val(macroInput.val().toUpperCase());

			filters.push({
				id: macroInput.data('formulaid'),
				type: macroInput.val()
			});
		});

		jQuery('#overrides_expression').html(getConditionFormula(filters, +jQuery('#overrides_evaltype').val()));
	};

	OverrideEditForm.prototype.filterDynamicRows = function() {
		var that = this;

		jQuery('#overrides_filters')
			.dynamicRows({
				template: '#override-filters-row',
				dataCallback: function(data) { // TODO VM: why do I need this?
					data.formulaId = num2letter(data.rowNum);

					return data;
				}
			})
			.bind('tableupdate.dynamicRows', function(event, options) {
				jQuery('#overrideRow').toggle(jQuery(options.row, jQuery(this)).length > 1);

				if (jQuery('#overrides_evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
					that.updateExpression();
				}
			})
			.on('change', '.macro', function() {
				if (jQuery('#overrides_evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
					that.updateExpression();
				}
			})
			.ready(function() {
				jQuery('#overrideRow').toggle(jQuery('.form_row', jQuery('#overrides_filters')).length > 1);
			});

		jQuery('#overrides_evaltype').change(function() {
			var show_formula = (jQuery(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

			jQuery('#overrides_expression').toggle(!show_formula);
			jQuery('#overrides_formula').toggle(show_formula);
			if (!show_formula) {
				that.updateExpression();
			}
		});

		jQuery('#overrides_evaltype').trigger('change');
	};

	// TODO VM: where this function was used?
	/**
	 * @param {string} msg                 Error message.
	 * @param {Node|jQuery} trigger_elmnt  An element that the focus will be returned to.
	 */
	OverrideEditForm.prototype.errorDialog = function(msg, trigger_elmnt) {
		overlayDialogue({
			'title': lldoverrides.msg.error,
			'content': jQuery('<span>').html(msg),
			'buttons': [{
				title: lldoverrides.msg.ok,
				class: 'btn-alt',
				focused: true,
				action: function() {}
			}]
		}, trigger_elmnt);
	};

	/**
	 * This method is bound via popup button attribute. It posts serialized version of current form to be validated.
	 * Note that we do not bother posting dynamic fields, since they are not validated at this point.
	 *
	 * @param {Overlay} overlay
	 */
	OverrideEditForm.prototype.validate = function(overlay) {
		var url = new Curl(this.$form.attr('action'));
		url.setArgument('validate', 1);

		this.$form.trimValues(['#override_name']); // TODO VM: check, what should be here
		this.$form.parent().find('.msg-bad, .msg-good').remove();

//		var curr_pairs = this.stepPairsData();

		overlay.setLoading();
		overlay.xhr = jQuery.ajax({
			url: url.getUrl(),
			data: this.$form.serialize(),
			dataType: 'json',
			type: 'post'
		})
		.always(function() {
			overlay.unsetLoading();
		})
		.done(function(ret) {
			if (typeof ret.errors !== 'undefined') {
				return jQuery(ret.errors).insertBefore(this.$form);
			}

			if (!lldoverrides.overrides.data[ret.params.no]) {
				lldoverrides.overrides.sort_index.push(ret.params.no);
				lldoverrides.overrides.data[ret.params.no] = this.override;
			}

//			ret.params.pairs = curr_pairs;
			lldoverrides.overrides.data[ret.params.no].update(ret.params);
			lldoverrides.overrides.renderData();

			overlayDialogueDestroy(overlay.dialogueid);
		}.bind(this));
	};

	function Operations($form, operations) {
		var that = this;
		this.data = {};
		this.new_id = 0;
		this.sort_index = []; // TODO VM: no sort index needed for operations

		operations.forEach(function(operation, no) {
			this.data[no + 1] = new Operation(operation, no + 1);
			this.sort_index.push(no + 1);
		}.bind(this));

		this.$container = jQuery('.lld-overrides-operations-table', $form);
		this.$container.find('.element-table-add').on('click', this.openNew.bind(this));

		this.$container.on('dynamic_rows.beforerender', function(e, dynamic_rows) {
//			e.view_data.no = (dynamic_rows.data_index + 1); // TODO VM: it should be possible here, instead as parameter in operation.
			e.view_data.condition = that.conditionHtml(e.view_data);
			e.view_data.actions = '<actions here>';

		});

		this.operations_dynamic_rows = new DynamicRows(this.$container, {
			add_before: this.$container.find('.element-table-add').closest('tr')[0],
			template: lldoverrides.operations_row_template
		});

		if (!lldoverrides.templated) {
//			this.$container.sortable(sortableOpts());
//			this.$container.sortable('option', 'update', this.onSortOrderChange.bind(this));
			this.$container.on('dynamic_rows.afterremove', function(e, dynamic_rows) {
				delete this.data[e.data_index];
//				this.onSortOrderChange();
			}.bind(this));

//			dynamicRowsBindSortableDisable(this.$container);
			dynamicRowsBindNewRow(this.$container);
		}
		else {
			this.$container.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {
				e.new_node.setAttribute('data-index', e.data_index);
			});
		}

		this.renderData();
	};

	Operations.prototype.operationobjectName = function(operationobject) {
		var operationobject_name = '';
		if (operationobject === '0') { // TODO VM: constant?
			operationobject_name = window.lldoverrides.msg.item_prototype;
		}
		else if (operationobject === '1') { // TODO VM: constant?
			operationobject_name = window.lldoverrides.msg.trigger_prototype;
		}
		else if (operationobject === '2') { // TODO VM: constant?
			operationobject_name = window.lldoverrides.msg.graph_prototype;
		}
		else if (operationobject === '3') { // TODO VM: constant?
			operationobject_name = window.lldoverrides.msg.host_prototype;
		}

		return operationobject_name;
	};

	Operations.prototype.operatorName = function(operator) {
		var operator_name = '';
		if (operator === '0') { // TODO VM: constant?
			operator_name = window.lldoverrides.msg.equals;
		}
		else if (operator === '1') { // TODO VM: constant?
			operator_name = window.lldoverrides.msg.does_not_equal;
		}
		else if (operator === '2') { // TODO VM: constant?
			operator_name = window.lldoverrides.msg.contains;
		}
		else if (operator === '3') { // TODO VM: constant?
			operator_name = window.lldoverrides.msg.does_not_contain;
		}
		else if (operator === '8') { // TODO VM: constant?
			operator_name = window.lldoverrides.msg.matches;
		}
		else if (operator === '9') { // TODO VM: constant?
			operator_name = window.lldoverrides.msg.does_not_match;
		}

		return operator_name;
	};

	Operations.prototype.conditionHtml = function(operation) {
		return this.operationobjectName(operation.operationobject)
				+ ' ' + this.operatorName(operation.operator)
				+ ' ' + operation.value; // TODO VM: check if jsencoding is necessary
		// TODO VM: make value "italic"
	};

//	/**
//	 * This method maintains property for iterating overrides in the order that rows have in DOM at the moment,
//	 * also updates visual counter in DOM for override rows.
//	 */
//	Operations.prototype.onSortOrderChange = function() {
//		var order = [];
//		this.$container.find('[data-index]').each(function(index) {
//			this.querySelector('[data-row-num]').innerText = (index + 1) + ':';
//			order.push(this.attributes.getNamedItem('data-index').value);
//		});
//		this.sort_index = order;
//	};

//	/**
//	 * Used to validate override names with server, on PopUp form validate event.
//	 *
//	 * @return {array}  Array of strings.
//	 */
//	Operations.prototype.getOverrideNames = function() {
//		var names = [];

//		for (var no in this.data) {
//			names.push(this.data[no].data.name);
//		}

//		return names;
//	};

	/**
	 * This method hydrates the parsed html PopUp form with data from specific override.
	 *
	 * @param {int} no  Override index.
	 */
	Operations.prototype.onOperationOverlayReadyCb = function(no) {
		var operation_ref = this.data[no] ? this.data[no] : this.new_operation;
		this.edit_form = new OverrideEditForm(jQuery('#lldoverride_form'), operation_ref);
	};

	/**
	 * Creates new override id and opens form for it.
	 */
	Operations.prototype.openNew = function() {
		this.new_id -= 1;

		this.new_operation = new Operation({no: this.new_id});
		this.new_operation.open(this.new_id, this.$container.find('.element-table-add'));
	};

	/**
	 * Renders overrides in DOM.
	 */
	Operations.prototype.renderData = function() {
		this.sort_index.forEach(function(data_index) {
			this.operations_dynamic_rows.addRow(this.data[data_index].data, data_index);
		}.bind(this));

//		this.onSortOrderChange();
	}

	/**
	 * Opens popup for a override.
	 *
	 * @param {integer} no
	 */
	Operations.prototype.open = function(no) {
		this.data[no]
			.open(no, this.$container.find('[data-index="' + no + '"] a'));
	};

	function Operation(data, no) {
		// TODO VM: do I need default here?
		var defaults = {

		};
		this.data = jQuery.extend(true, defaults, data); // TODO VM: why it is other way around in httptest??
		this.data.no = no; // TODO VM: this should be possible in dynamic_rows.beforerender instead
	}

	/**
	 * Merges old data with new data.
	 */
	Operation.prototype.update = function(data) {
		jQuery.extend(this.data, data);
	};

	/**
	 * Opens override popup - edit or create form.
	 * Note: a callback this.onStepOverlayReadyCb is called from within popup form once it is parsed.
	 *
	 * @param {int}  step     Override index.
	 * @param {Node} refocus  A node to set focus to, when popup is closed.
	 */
	Operation.prototype.open = function(no, refocus) {
		// TODO VM: maybe parameters should be limited to only ones used by operationobject, but it will be yet another place, where such case would be defined.
		return PopUp('popup.lldoperation', {
			no:                 no,
			templated:          lldoverrides.templated,
			operationobject:    this.data.operationobject,
			operator:           this.data.operator,
			value:              this.data.value,
			opstatus:           this.data.opstatus,
			opperiod:           this.data.opperiod,
			ophistory:          this.data.ophistory,
			optrends:           this.data.optrends,
			opseverity:         this.data.opseverity,
			optag:              this.data.optag,
			optemplate:         this.data.optemplate,
			opinventory:        this.data.opinventory,
//			overrides_names:    lldoverrides.overrides.getOverrideNames() // TODO VM: same operation should not be added twice? (is this checked in API?)
		}, null, refocus);
	};
</script>
