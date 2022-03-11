<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			(new CCol((new CLink('#{name}', 'javascript:lldoverrides.overrides.open(#{no});')))),
			'#{stop_verbose}',
			(new CCol((new CButton(null, _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->setEnabled(false)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="lldoverride-row">
	<?= (new CRow([
			(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))
				->addClass(ZBX_STYLE_TD_DRAG_ICON)
				->setWidth('15'),
			(new CCol((new CSpan('1:'))->setAttribute('data-row-num', '')))
				->setWidth('15'),
			(new CCol((new CLink('#{name}', 'javascript:lldoverrides.overrides.open(#{no});'))))
				->setWidth('350'),
			(new CCol('#{stop_verbose}'))
				->setWidth('100'),
			(new CCol((new CButton(null, _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
			))
				->addClass(ZBX_STYLE_NOWRAP)
				->setWidth('50')
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
			(new CTextBox('overrides_filters[#{rowNum}][macro]', '', false,
					DB::getFieldLength('lld_override_condition', 'macro')))
				->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
				->addClass(ZBX_STYLE_UPPERCASE)
				->addClass('macro')
				->setAttribute('placeholder', '{#MACRO}')
				->setAttribute('data-formulaid', '#{formulaId}'),
			(new CSelect('overrides_filters[#{rowNum}][operator]'))
				->setValue(CONDITION_OPERATOR_REGEXP)
				->addClass('js-operator')
				->addOptions(CSelect::createOptionsFromArray([
					CONDITION_OPERATOR_REGEXP => _('matches'),
					CONDITION_OPERATOR_NOT_REGEXP => _('does not match'),
					CONDITION_OPERATOR_EXISTS => _('exists'),
					CONDITION_OPERATOR_NOT_EXISTS => _('does not exist')
				])),
			(new CDiv(
				(new CTextBox('overrides_filters[#{rowNum}][value]', '', false,
					DB::getFieldLength('lld_override_condition', 'value')))
						->addClass('js-value')
						->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
						->setAttribute('placeholder', _('regular expression'))
			))->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH),
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
			['#{condition_object} #{condition_operator} ', italic('#{value}')],
			(new CCol(
				(new CButton(null, _('View')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-open')
					->onClick('lldoverrides.operations.open(#{no});')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="lldoverride-operation-row">
	<?= (new CRow([
			['#{condition_object} #{condition_operator} ', italic('#{value}')],
			(new CHorList([
				(new CButton(null, _('Edit')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-open')
					->onClick('lldoverrides.operations.open(#{no});'),
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			]))->addClass(ZBX_STYLE_NOWRAP)
		]))->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="lldoverride-custom-intervals-row">
	<?= (new CRow([
			(new CRadioButtonList('opperiod[delay_flex][#{rowNum}][type]', 0))
				->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
				->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
				->setModern(true),
			[
				(new CTextBox('opperiod[delay_flex][#{rowNum}][delay]'))
					->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT),
				(new CTextBox('opperiod[delay_flex][#{rowNum}][schedule]'))
					->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
					->setAttribute('style', 'display: none;')
			],
			(new CTextBox('opperiod[delay_flex][#{rowNum}][period]'))
				->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL),
			(new CButton('opperiod[delay_flex][#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/x-jquery-tmpl" id="lldoverride-tag-row">
	<?= renderTagTableRow('#{rowNum}', '', '', ['field_name' => 'optag', 'add_post_js' => false]) ?>
</script>
<script type="text/javascript">
	jQuery(function($) {
		window.lldoverrides = {
			templated:                           <?= $data['limited'] ? 1 : 0 ?>,
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
				does_not_match:             <?= json_encode(_('does not match')) ?>
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

		window.lldoverrides.overrides = new Overrides($('#overridesTab'),
			<?= json_encode(array_values($data['overrides'])) ?>
		);
		window.lldoverrides.actions = ['opstatus', 'opdiscover', 'opperiod', 'ophistory', 'optrends', 'opseverity',
			'optag', 'optemplate', 'opinventory'
		];

		window.lldoverrides.$form = $('form[name="itemForm"]').on('submit', function(e) {
			var hidden_form = this.querySelector('#hidden-form');

			hidden_form && hidden_form.remove();
			hidden_form = document.createElement('div');
			hidden_form.id = 'hidden-form';

			hidden_form.appendChild(lldoverrides.overrides.toFragment());

			this.appendChild(hidden_form);
		});
	});

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

	/**
	 * Writes data index as new nodes attribute. Bind remove event.
	 */
	function dynamicRowsBindNewRow($el) {
		$el.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {
			e.new_node.setAttribute('data-index', e.data_index);
			e.new_node.querySelector('.element-table-remove')
				.addEventListener('click', dynamic_rows.removeRow.bind(dynamic_rows, e.data_index));

			// Because IE does not have NodeList.prototype.forEach method.
			Array.prototype.forEach.call(e.new_node.querySelectorAll('input'), function(input_node) {
				input_node.addEventListener('keyup', function(e) {
					$el.trigger('dynamic_rows.updated', dynamic_rows);
				});
			});
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

	/**
	 * A helper method for creating hidden input nodes.
	 *
	 * @param {string} name
	 * @param {string} value
	 * @param {string} prefix
	 *
	 * @return {object}  Return input element node.
	 */
	function hiddenInput(name, value, prefix) {
		var input = window.document.createElement('input');

		input.type = 'hidden';
		input.value = value;
		input.name = prefix ? prefix + '[' + name + ']' : name;

		return input;
	}

	/**
	 * @param {object} $element
	 * @param {object} options
	 * @param {array}  data
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
	 * @return {object}  Returns a jQuery.Event object.
	 */
	DynamicRows.prototype.dispatch = function(evt_key, evt_data) {
		var evt = jQuery.Event('dynamic_rows.' + evt_key, evt_data);

		this.$element.trigger(evt, [this]);

		return evt;
	}

	/**
	 * Adds a row before the given row.
	 *
	 * @param {object} data        Data to be passed into template.
	 * @param {number} data_index  Optional index, if data with given index exists, then in place update will happen.
	 *                             In case of update all events dispatched as if add new was performed.
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
			this.length++;
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
	* @return {object}  Returns the DynamicRows object.
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
	 * @return {object}
	 */
	DynamicRows.prototype.createRowNode = function(data) {
		var evt = this.dispatch('beforerender', {view_data: data});
		var html_str = this.options.template.evaluate(evt.view_data);

		return jQuery(html_str).get(0);
	};


	/**
	 * Removes data at given index. Method is to be used by plugin internally, does not dispatch events.
	 *
	 * @param {number} data_index
	 *
	 * @return {object}  Object that just got removed.
	 */
	DynamicRows.prototype.unlinkIndex = function(data_index) {
		this.data[data_index].node.remove();
		var ref = this.data[data_index];

		delete this.data[data_index];
		this.length--;

		return ref;
	}

	/**
	 * Removes the given row.
	 *
	 * @param {number} data_index
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
	 * @param {object} $tab
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
	 * @return {object}  Returns DocumentFragment object.
	 */
	Overrides.prototype.toFragment = function() {
		var frag = document.createDocumentFragment(),
			iter_step = 0;

		this.sort_index.forEach(function(id) {
			var override = this.data[id],
				prefix_override = 'overrides[' + (iter_step++) + ']',
				prefix_filter = prefix_override + '[filter]',
				iter_filters = 0,
				iter_operations = 0;

			frag.appendChild(hiddenInput('step', iter_step, prefix_override));
			frag.appendChild(hiddenInput('name', override.data.name, prefix_override));
			frag.appendChild(hiddenInput('stop', override.data.stop, prefix_override));

			if (override.data.overrides_filters.length > 0) {
				frag.appendChild(hiddenInput('evaltype', override.data.overrides_evaltype, prefix_filter));

				if (override.data.overrides_evaltype == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
					frag.appendChild(hiddenInput('formula', override.data.overrides_formula, prefix_filter));
				}

				override.data.overrides_filters.forEach(function(override_filter) {
					var prefix = prefix_filter + '[conditions][' + (iter_filters++) + ']';
					frag.appendChild(hiddenInput('formulaid', override_filter.formulaid, prefix));
					frag.appendChild(hiddenInput('macro', override_filter.macro, prefix));
					frag.appendChild(hiddenInput('value', override_filter.value, prefix));
					frag.appendChild(hiddenInput('operator', override_filter.operator, prefix));
				});
			}

			override.data.operations.forEach(function(operation) {
				var prefix = prefix_override + '[operations][' + (iter_operations++) + ']';
				frag.appendChild(hiddenInput('operationobject', operation.operationobject, prefix));
				frag.appendChild(hiddenInput('operator', operation.operator, prefix));
				frag.appendChild(hiddenInput('value', operation.value, prefix));

				if ('opstatus' in operation) {
					frag.appendChild(hiddenInput('status', operation.opstatus.status, prefix + '[opstatus]'));
				}
				if ('opdiscover' in operation) {
					frag.appendChild(hiddenInput('discover', operation.opdiscover.discover, prefix + '[opdiscover]'));
				}
				if ('opperiod' in operation) {
					frag.appendChild(hiddenInput('delay', operation.opperiod.delay, prefix + '[opperiod]'));
				}
				if ('ophistory' in operation) {
					frag.appendChild(hiddenInput('history', operation.ophistory.history, prefix + '[ophistory]'));
				}
				if ('optrends' in operation) {
					frag.appendChild(hiddenInput('trends', operation.optrends.trends, prefix + '[optrends]'));
				}
				if ('opseverity' in operation) {
					frag.appendChild(hiddenInput('severity', operation.opseverity.severity, prefix + '[opseverity]'));
				}
				if ('optag' in operation) {
					var iter_tags = 0;

					operation.optag.forEach(function(tag) {
						var prefix_tag = prefix + '[optag][' + (iter_tags++) + ']';
						frag.appendChild(hiddenInput('tag', tag.tag, prefix_tag));

						if (('value' in tag) && 'value' !== '') {
							frag.appendChild(hiddenInput('value', tag.value, prefix_tag));
						}
					});
				}
				if ('optemplate' in operation) {
					var iter_templates = 0;

					operation.optemplate.forEach(function(template) {
						var prefix_template = prefix + '[optemplate][' + (iter_templates++) + ']';
						frag.appendChild(hiddenInput('templateid', template.templateid, prefix_template));
					});
				}
				if ('opinventory' in operation) {
					frag.appendChild(hiddenInput('inventory_mode', operation.opinventory.inventory_mode,
						prefix + '[opinventory]'
					));
				}
			});
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
	 * @param {number} no  Override index.
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
	 * Opens popup for an override.
	 *
	 * @param {number} no
	 */
	Overrides.prototype.open = function(no) {
		this.data[no].open(no, this.$container.find('[data-index="' + no + '"] a'));
	};

	/**
	 * This object represents an override of web scenario.
	 *
	 * @param {object} data  Optional override initial data.
	 */
	function Override(data) {
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
		this.data = jQuery.extend(true, defaults, data);

		this.data.no = this.data.step;
		this.data.overrides_evaltype = this.data.filter.evaltype;
		this.data.overrides_formula = this.data.filter.formula;
		this.data.overrides_filters = this.data.filter.conditions;
		delete this.data.filter;

		/*
		 * Used to add proper letter, when creating new dynamic row for filter. If no filters are configured,
		 * one empty row is created by View.
		 */
		this.filter_counter = (this.data.overrides_filters.length > 0) ? this.data.overrides_filters.length : 1;
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
	 * @param {number} step             Override index.
	 * @param {Node}   trigger_element  A node to set focus to, when popup is closed.
	 */
	Override.prototype.open = function(no, trigger_element) {
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
		}, {dialogue_class: 'modal-popup-generic', trigger_element});
	};

	/**
	 * Represents popup form.
	 *
	 * @param {object} $form
	 * @param {object} override_ref  Reference to override instance from Overrides object.
	 */
	function OverrideEditForm($form, override_ref) {
		this.$form = $form;
		this.override = override_ref;

		// Initiate Filters dynamic rows and evaltype.
		this.filterDynamicRows();
		this.operations = new Operations(this.$form, this.override.data.operations);
		// This will be used for link on edit button.
		window.lldoverrides.operations = this.operations;
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

		jQuery('#overrides_expression').html(getConditionFormula(filters, +jQuery('#overrides-evaltype').val()));
	};

	OverrideEditForm.prototype.filterDynamicRows = function() {
		var that = this;

		jQuery('#overrides_filters')
			.dynamicRows({
				template: '#override-filters-row',
				counter: this.override.filter_counter,
				dataCallback: function(data) {
					data.formulaId = num2letter(data.rowNum);
					that.override.filter_counter++;

					return data;
				}
			})
			.bind('tableupdate.dynamicRows', function(event, options) {
				jQuery('#overrideRow').toggle(jQuery(options.row, jQuery(this)).length > 1);

				if (jQuery('#overrides-evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
					that.updateExpression();
				}
			})
			.on('change', '.macro', function() {
				if (jQuery('#overrides-evaltype').val() != <?= CONDITION_EVAL_TYPE_EXPRESSION ?>) {
					that.updateExpression();
				}
			})
			.on('afteradd.dynamicRows', (event) => {
				[...event.currentTarget.querySelectorAll('.js-operator')]
					.pop()
					.addEventListener('change', view.toggleConditionValue);
			})
			.ready(function() {
				jQuery('#overrideRow').toggle(jQuery('.form_row', jQuery('#overrides_filters')).length > 1);
				overlays_stack.end().centerDialog();
			});

		jQuery('#overrides-evaltype').change(function() {
			var show_formula = (jQuery(this).val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>);

			jQuery('#overrides_expression').toggle(!show_formula);
			jQuery('#overrides_formula').toggle(show_formula);
			if (!show_formula) {
				that.updateExpression();
			}

			overlays_stack.end().centerDialog();
		});

		jQuery('#overrides-evaltype').trigger('change');

		[...document.getElementById('overrides_filters').querySelectorAll('.js-operator')].map((elem) => {
			elem.addEventListener('change', view.toggleConditionValue);
		});
	};

	/**
	 * This method is bound via popup button attribute. It posts serialized version of current form to be validated.
	 * Note that we do not bother posting dynamic fields, since they are not validated at this point.
	 *
	 * @param {object} overlay
	 */
	OverrideEditForm.prototype.validate = function(overlay) {
		var url = new Curl(this.$form.attr('action'));
		url.setArgument('validate', 1);

		this.$form.trimValues(['input[type="text"]']);
		this.$form.parent().find('.msg-bad, .msg-good').remove();

		var form_data = this.$form.serializeJSON();
		if (Object.keys(form_data.overrides_filters).length <= 1) {
			delete form_data.overrides_formula;
			delete form_data.overrides_evaltype;
		}

		overlay.setLoading();
		overlay.xhr = jQuery.ajax({
			url: url.getUrl(),
			data: form_data,
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

			this.operations.sort_index.forEach(function(data_index) {
				ret.params.operations.push(this.operations.data[data_index].data);
			}.bind(this));

			lldoverrides.overrides.data[ret.params.no].update(ret.params);
			lldoverrides.overrides.renderData();

			overlayDialogueDestroy(overlay.dialogueid);
		}.bind(this));
	};

	function Operations($form, operations) {
		var that = this;
		this.data = {};
		this.new_id = 0;
		this.sort_index = [];

		operations.forEach(function(operation, no) {
			this.data[no + 1] = new Operation(operation, no + 1);
			this.sort_index.push(no + 1);
		}.bind(this));

		this.$container = jQuery('.lld-overrides-operations-table', $form);
		this.$container.find('.element-table-add').on('click', this.openNew.bind(this));

		this.$container.on('dynamic_rows.beforerender', function(e, dynamic_rows) {
			e.view_data.condition_object = that.operationobjectName(e.view_data.operationobject);
			e.view_data.condition_operator = that.operatorName(e.view_data.operator);
		});

		this.operations_dynamic_rows = new DynamicRows(this.$container, {
			add_before: this.$container.find('.element-table-add').closest('tr')[0],
			template: lldoverrides.operations_row_template
		});

		if (!lldoverrides.templated) {
			this.$container.on('dynamic_rows.afterremove', function(e, dynamic_rows) {
				delete this.data[e.data_index];

				var index = this.sort_index.indexOf(e.data_index);
				if (index > -1) {
					this.sort_index.splice(index, 1);
				}
			}.bind(this));

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
		if (operationobject === '<?= OPERATION_OBJECT_ITEM_PROTOTYPE ?>') {
			operationobject_name = window.lldoverrides.msg.item_prototype;
		}
		else if (operationobject === '<?= OPERATION_OBJECT_TRIGGER_PROTOTYPE ?>') {
			operationobject_name = window.lldoverrides.msg.trigger_prototype;
		}
		else if (operationobject === '<?= OPERATION_OBJECT_GRAPH_PROTOTYPE ?>') {
			operationobject_name = window.lldoverrides.msg.graph_prototype;
		}
		else if (operationobject === '<?= OPERATION_OBJECT_HOST_PROTOTYPE ?>') {
			operationobject_name = window.lldoverrides.msg.host_prototype;
		}

		return operationobject_name;
	};

	Operations.prototype.operatorName = function(operator) {
		var operator_name = '';
		if (operator === '<?= CONDITION_OPERATOR_EQUAL ?>') {
			operator_name = window.lldoverrides.msg.equals;
		}
		else if (operator === '<?= CONDITION_OPERATOR_NOT_EQUAL ?>') {
			operator_name = window.lldoverrides.msg.does_not_equal;
		}
		else if (operator === '<?= CONDITION_OPERATOR_LIKE ?>') {
			operator_name = window.lldoverrides.msg.contains;
		}
		else if (operator === '<?= CONDITION_OPERATOR_NOT_LIKE ?>') {
			operator_name = window.lldoverrides.msg.does_not_contain;
		}
		else if (operator === '<?= CONDITION_OPERATOR_REGEXP ?>') {
			operator_name = window.lldoverrides.msg.matches;
		}
		else if (operator === '<?= CONDITION_OPERATOR_NOT_REGEXP ?>') {
			operator_name = window.lldoverrides.msg.does_not_match;
		}

		return operator_name;
	};

	/**
	 * This method hydrates the parsed html PopUp form with data from specific override.
	 *
	 * @param {number} no  Override index.
	 */
	Operations.prototype.onOperationOverlayReadyCb = function(no) {
		var operation_ref = this.data[no] ? this.data[no] : this.new_operation;
		this.edit_form = new OperationEditForm(jQuery('#lldoperation_form'), operation_ref);
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
	}

	/**
	 * Opens popup for a override.
	 *
	 * @param {number} no
	 */
	Operations.prototype.open = function(no) {
		this.data[no].open(no, this.$container.find('[data-index="' + no + '"] .element-table-open'));
	};

	function Operation(data, no) {
		this.data = data;
		this.data.no = no;
	}

	/**
	 * Replaces data with new one.
	 */
	Operation.prototype.update = function(data) {
		this.data = data;
	};

	/**
	 * Opens override popup - edit or create form.
	 * Note: a callback this.onStepOverlayReadyCb is called from within popup form once it is parsed.
	 *
	 * @param {number} step             Override index.
	 * @param {Node}   trigger_element  A node to set focus to, when popup is closed.
	 */
	Operation.prototype.open = function(no, trigger_element) {
		var parameters = {
			no:                 no,
			templated:          lldoverrides.templated,
			operationobject:    this.data.operationobject,
			operator:           this.data.operator,
			value:              this.data.value
		};

		window.lldoverrides.actions.forEach(function(action) {
			if (action in this.data) {
				parameters[action] = this.data[action];
			}
		}.bind(this));

		return PopUp('popup.lldoperation', parameters, {dialogue_class: 'modal-popup-generic', trigger_element});
	};

	/**
	 * Represents popup form.
	 *
	 * @param {object} $form
	 * @param {object} operation_ref  Reference to override instance from Overrides object.
	 */
	function OperationEditForm($form, operation_ref) {
		this.$form = $form;
		this.operation = operation_ref;

		var that = this,
			$custom_intervals = jQuery('#lld_overrides_custom_intervals', this.$form);

		$custom_intervals.on('click', 'input[type="radio"]', function() {
			var rowNum = jQuery(this).attr('id').split('_')[3];

			if (jQuery(this).val() == <?= ITEM_DELAY_FLEXIBLE; ?>) {
				jQuery('#opperiod_delay_flex_' + rowNum + '_schedule', $custom_intervals).hide();
				jQuery('#opperiod_delay_flex_' + rowNum + '_delay', $custom_intervals).show();
				jQuery('#opperiod_delay_flex_' + rowNum + '_period', $custom_intervals).show();
			}
			else {
				jQuery('#opperiod_delay_flex_' + rowNum + '_delay', $custom_intervals).hide();
				jQuery('#opperiod_delay_flex_' + rowNum + '_period', $custom_intervals).hide();
				jQuery('#opperiod_delay_flex_' + rowNum + '_schedule', $custom_intervals).show();
			}
		});

		$custom_intervals.dynamicRows({
			template: '#lldoverride-custom-intervals-row'
		});

		jQuery('#ophistory_history_mode', this.$form)
			.change(function() {
				if (jQuery('[name="ophistory[history_mode]"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
					jQuery('#ophistory_history', that.$form).prop('disabled', true).hide();
				}
				else {
					jQuery('#ophistory_history', that.$form).prop('disabled', false).show();
				}
			})
			.trigger('change');

		jQuery('#optrends_trends_mode', this.$form)
			.change(function() {
				if (jQuery('[name="optrends[trends_mode]"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
					jQuery('#optrends_trends', that.$form).prop('disabled', true).hide();
				}
				else {
					jQuery('#optrends_trends', that.$form).prop('disabled', false).show();
				}
			})
			.trigger('change');

		jQuery('#tags-table .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', this.$form).textareaFlexible();
		jQuery('#tags-table', this.$form)
			.dynamicRows({template: '#lldoverride-tag-row'})
			.on('click', 'button.element-table-add', function() {
				jQuery('#tags-table .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', this.$form).textareaFlexible();
			});

		// Override actions available per override object.
		var available_actions = {
			'<?= OPERATION_OBJECT_ITEM_PROTOTYPE ?>': ['opstatus', 'opdiscover', 'opperiod', 'ophistory', 'optrends',
														'optag'],
			'<?= OPERATION_OBJECT_TRIGGER_PROTOTYPE ?>': ['opstatus', 'opdiscover', 'opseverity', 'optag'],
			'<?= OPERATION_OBJECT_GRAPH_PROTOTYPE ?>': ['opdiscover'],
			'<?= OPERATION_OBJECT_HOST_PROTOTYPE ?>': ['opstatus', 'opdiscover', 'optemplate', 'optag', 'opinventory']
		};

		jQuery('#operationobject', this.$form)
			.change(function() {
				window.lldoverrides.actions.forEach(function(action) {
					if (available_actions[this.value].indexOf(action) !== -1) {
						that.showActionRow(action + '_row');
					}
					else {
						that.hideActionRow(action + '_row');
					}
				}.bind(this));
			});
	};

	OperationEditForm.prototype.initHideActionRows = function() {
		jQuery('#operationobject', this.$form).trigger('change');
	};

	OperationEditForm.prototype.showActionRow = function(row_id) {
		var obj = document.getElementById(row_id);
		if (is_null(obj)) {
			throw 'Cannot find action row with id [' + row_id + ']';
		}

		// Show it only if it was previously hidden.
		if (obj.originalObject) {
			obj.parentNode.replaceChild(obj.originalObject, obj);
		}
	};

	OperationEditForm.prototype.hideActionRow = function(row_id) {
		var obj = document.getElementById(row_id);
		if (is_null(obj)) {
			throw 'Cannot find action row with id [' + row_id +']';
		}

		// Hide it only if it was previously visible.
		if (!('originalObject' in obj)) {
			try {
				var new_obj = document.createElement('li');
				new_obj.setAttribute('id', obj.id);
			}
			catch(e) {
				throw 'Cannot create new element';
			}

			new_obj.originalObject = obj;
			obj.parentNode.replaceChild(new_obj, obj);
		}
	};

	/**
	 * This method is bound via popup button attribute. It posts serialized version of current form to be validated.
	 * Note that we do not bother posting dynamic fields, since they are not validated at this point.
	 *
	 * @param {object} overlay
	 */
	OperationEditForm.prototype.validate = function(overlay) {
		var url = new Curl(this.$form.attr('action'));
		url.setArgument('validate', 1);

		this.$form.trimValues(['input[type="text"]', 'textarea']);
		this.$form.parent().find('.msg-bad, .msg-good').remove();

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

			if (!lldoverrides.operations.data[ret.params.no]) {
				lldoverrides.operations.sort_index.push(ret.params.no);
				lldoverrides.operations.data[ret.params.no] = this.operation;
			}

			lldoverrides.operations.data[ret.params.no].update(ret.params);
			lldoverrides.operations.renderData();

			overlayDialogueDestroy(overlay.dialogueid);
		}.bind(this));
	};
</script>
