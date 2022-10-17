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
 * @var array $data
 */
?>

<script type="text/x-jquery-tmpl" id="scenario-step-row-templated">
	<?= (new CRow([
			'',
			(new CSpan('1:'))->setAttribute('data-row-num', ''),
			(new CLink('#{name}', 'javascript:httpconf.steps.open(#{no});')),
			'#{timeout}',
			(new CSpan('#{url_short}'))->setHint('#{url}', '', true, 'word-break: break-all;')
				->setAttribute('data-hintbox', '#{enabled_hint}'),
			'#{required}',
			'#{status_codes}',
			''
		]))->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="scenario-step-row">
	<?= (new CRow([
			(new CCol((new CDiv())
				->addClass(ZBX_STYLE_DRAG_ICON)
				->addStyle('top: 0px;')
			))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan('1:'))->setAttribute('data-row-num', ''),
			(new CLink('#{name}', 'javascript:httpconf.steps.open(#{no});')),
			'#{timeout}',
			(new CSpan('#{url_short}'))->setHint('#{url}', '', true, 'word-break: break-all;')
				->setAttribute('data-hintbox', '#{enabled_hint}'),
			'#{required}',
			'#{status_codes}',
			(new CCol((new CButton(null, _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="scenario-pair-row">
	<?= (new CRow([
			(new CCol([
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
			]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CTextBox(null, '#{name}'))
				->setAttribute('placeholder', _('name'))
				->setAttribute('data-type', 'name')
				->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH),
			'&rArr;',
			(new CTextBox(null, '#{value}'))
				->setAttribute('placeholder', _('value'))
				->setAttribute('data-type', 'value')
				->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH),
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('sortable')
			->toString()
	?>
</script>

<script>
	const view = {
		form_name: null,

		init({form_name}) {
			this.form_name = form_name;
		},

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		refresh() {
			const url = new Curl('', false);
			const form = document.getElementsByName(this.form_name)[0].cloneNode(true);

			form.append(httpconf.scenario.toFragment());
			form.append(httpconf.steps.toFragment());

			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php', false);
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};

	jQuery(function($) {
		window.httpconf = {
			templated:                           <?= $data['templated'] ? 1 : 0 ?>,
			ZBX_STYLE_DRAG_ICON:                 <?= zbx_jsvalue(ZBX_STYLE_DRAG_ICON) ?>,
			ZBX_STYLE_TD_DRAG_ICON:              <?= zbx_jsvalue(ZBX_STYLE_TD_DRAG_ICON) ?>,
			ZBX_STYLE_DISABLED:                  <?= zbx_jsvalue(ZBX_STYLE_DISABLED) ?>,
			HTTPTEST_AUTH_NONE:                  <?= HTTPTEST_AUTH_NONE ?>,
			ZBX_POSTTYPE_FORM:                   <?= ZBX_POSTTYPE_FORM ?>,
			HTTPTEST_STEP_RETRIEVE_MODE_HEADERS: <?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>,
			ZBX_POSTTYPE_RAW:                    <?= ZBX_POSTTYPE_RAW ?>,
			msg: {
				data_not_encoded:           <?= json_encode(_('Data is not properly encoded.')) ?>,
				name_filed_length_exceeded: <?= json_encode(_('Name of the form field should not exceed 255 characters.')) ?>,
				value_without_name:         <?= json_encode(_('Values without names are not allowed in form fields.')) ?>,
				failed_to_parse_url:        <?= json_encode(_('Failed to parse URL.')) ?>,
				ok:                         <?= json_encode(_('Ok')) ?>,
				error:                      <?= json_encode(_('Error')) ?>,
				url_not_encoded_properly:   <?= json_encode(_('URL is not properly encoded.')) ?>,
				cannot_convert_post_data:   <?= json_encode(_('Cannot convert POST data:')) ?>
			}
		};

		window.httpconf.step_row_template = new Template(jQuery(httpconf.templated
			? '#scenario-step-row-templated'
			: '#scenario-step-row'
		).html());

		window.httpconf.pair_row_template = new Template(jQuery('#scenario-pair-row').html());
		window.httpconf.scenario = new Scenario($('#scenarioTab'), <?= json_encode($this->data['scenario_tab_data']) ?>);
		window.httpconf.steps = new Steps($('#stepTab'), <?= json_encode(array_values($data['steps'])) ?>);
		window.httpconf.authentication = new Authentication($('#authenticationTab'));

		window.httpconf.$form = $('#http-form').on('submit', function(e) {
			var hidden_form = this.querySelector('#hidden-form');

			hidden_form && hidden_form.remove();
			hidden_form = document.createElement('div');
			hidden_form.id = 'hidden-form';

			hidden_form.appendChild(httpconf.scenario.toFragment());
			hidden_form.appendChild(httpconf.steps.toFragment());

			this.appendChild(hidden_form);
		});
	});

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
			handle: 'div.' + httpconf.ZBX_STYLE_DRAG_ICON,
			tolerance: 'pointer',
			opacity: 0.6,
			start: function(e, ui) {
				ui.placeholder.height(ui.item.height());
			}
		};
	}

	/**
	 * A helper method for truncating string in middle.
	 *
	 * @param {string} str  String to be shortened into mid-elliptic.
	 * @param {int}    max  Max length of resulting string (inclusive).
	 */
	function midEllipsis(str, max) {
		if (str.length < max) {
			return str;
		}

		var sep = '...',
			max = max - sep.length,
			len = max / 2,
			pt1 = str.slice(0, Math.floor(len)),
			pt2 = str.slice(- Math.ceil(len));

		return pt1 + sep + pt2;
	}

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
				dynamic_rows.$element.find('.' + httpconf.ZBX_STYLE_DRAG_ICON)
					.addClass(httpconf.ZBX_STYLE_DISABLED);
			}
			else {
				dynamic_rows.$element.sortable('option', 'disabled', false);
				dynamic_rows.$element.find('.' + httpconf.ZBX_STYLE_DRAG_ICON)
					.removeClass(httpconf.ZBX_STYLE_DISABLED);
			}
		});
	}

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
		var evt = this.dispatch('beforerender', {view_data: data});
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
	 * Represents authentication tab in web layout.
	 *
	 * @param {jQuery} $tab
	 */
	function Authentication($tab) {
		this.$type_select = jQuery('#authentication', $tab);
		this.$user = jQuery('#http_user', $tab);
		this.$password = jQuery('#http_password', $tab);

		this.$type_select.on('change', function(e) {
			var http_fields_disabled = (e.target.value == httpconf.HTTPTEST_AUTH_NONE);
			this.$user.prop('disabled', http_fields_disabled).closest('li').toggle(!http_fields_disabled);
			this.$password.prop('disabled', http_fields_disabled).closest('li').toggle(!http_fields_disabled);
		}.bind(this));
		this.$type_select.trigger('change');
	}

	/**
	 * Represents scenario tab in web layout.
	 *
	 * @param {jQuery} $tab
	 * @param {object} config
	 */
	function Scenario($tab, config) {
		new CViewSwitcher('agent', 'change', config.agent_visibility);
		this.pairs = {
			'variables': null,
			'headers': null
		};

		jQuery('.httpconf-dynamic-row', $tab).each(function(index, table) {

			var $table = jQuery(table),
				type = $table.data('type');

			$table.sortable(sortableOpts());

			dynamicRowsBindSortableDisable($table);
			dynamicRowsBindRemoveDisable($table);
			dynamicRowsBindNewRow($table);

			$table.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {

				if (type === 'variables') {
					e.new_node.querySelector('.' + httpconf.ZBX_STYLE_DRAG_ICON).remove();
				}

				if (type === 'variables' || type === 'headers') {
					e.new_node.querySelector('[data-type="value"]').setAttribute('maxlength', 2000);
				}
			});

			this.pairs[type] = new DynamicRows($table, {
				add_before: $table.find('.element-table-add').closest('tr')[0],
				template: httpconf.pair_row_template,
				ensure_min_rows: 1
			}, config.pairs[type]);

			$table.find('.element-table-add')
				.on('click', this.pairs[type].addRow.bind(this.pairs[type], {}, null));
		}.bind(this));
	}

	/**
	 * The parts of form that are easier to maintain in functional objects are transformed into hidden input fields.
	 *
	 * @return {DocumentFragment}
	 */
	Scenario.prototype.toFragment = function() {
		var frag = document.createDocumentFragment(),
			iter = 0,
			prefix;

		eachPair.call(this.pairs.headers, function(pair) {
			prefix = 'pairs[' + (iter ++) + ']';
			frag.appendChild(hiddenInput('type', 'headers', prefix));
			frag.appendChild(hiddenInput('name', pair.name, prefix));
			frag.appendChild(hiddenInput('value', pair.value, prefix));
		});

		eachPair.call(this.pairs.variables, function(pair) {
			prefix = 'pairs[' + (iter ++) + ']';
			frag.appendChild(hiddenInput('type', 'variables', prefix));
			frag.appendChild(hiddenInput('name', pair.name, prefix));
			frag.appendChild(hiddenInput('value', pair.value, prefix));
		});

		return frag;
	};

	/**
	 * Represents steps tab in web layout.
	 *
	 * @param {jQuery} $tab
	 * @param {array}  steps  Initial step objects data array.
	 */
	function Steps($tab, steps) {
		this.data = {};
		this.new_stepid = 0;
		this.sort_index = [];

		steps.forEach(function(step, no) {
			this.data[no + 1] = new Step(step);
			this.sort_index.push(no + 1);
		}.bind(this));

		this.$container = jQuery('.httpconf-steps-dynamic-row', $tab);
		this.$container.find('.element-table-add').on('click', this.openNew.bind(this));

		this.$container.on('dynamic_rows.beforerender', function(e, dynamic_rows) {
			e.view_data.url_short = midEllipsis(e.view_data.url, 65);
			e.view_data.enabled_hint = (e.view_data.url.length < 65) ? 0 : 1;
		});

		this.steps_dynamic_rows = new DynamicRows(this.$container, {
			add_before: this.$container.find('.element-table-add').closest('tr')[0],
			template: httpconf.step_row_template
		});

		if (!httpconf.templated) {
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
	Steps.prototype.toFragment = function() {
		var frag = document.createDocumentFragment(),
			iter_step = 0;

		this.sort_index.forEach(function(id) {
			var iter_pair = 0,
				step = this.data[id],
				prefix_step = 'steps[' + (iter_step ++) + ']',
				prefix_pair;

			frag.appendChild(hiddenInput('follow_redirects', step.data.follow_redirects, prefix_step));
			frag.appendChild(hiddenInput('httpstepid',       step.data.httpstepid,       prefix_step));
			frag.appendChild(hiddenInput('name',             step.data.name,             prefix_step));
			frag.appendChild(hiddenInput('post_type',        step.data.post_type,        prefix_step));
			frag.appendChild(hiddenInput('required',         step.data.required,         prefix_step));
			frag.appendChild(hiddenInput('retrieve_mode',    step.data.retrieve_mode,    prefix_step));
			frag.appendChild(hiddenInput('status_codes',     step.data.status_codes,     prefix_step));
			frag.appendChild(hiddenInput('timeout',          step.data.timeout,          prefix_step));
			frag.appendChild(hiddenInput('url',              step.data.url,              prefix_step));

			if (step.data.retrieve_mode != httpconf.HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
				if (step.data.post_type != httpconf.ZBX_POSTTYPE_FORM) {
					frag.appendChild(hiddenInput('posts', step.data.posts, prefix_step));
				}
				else {
					step.data.pairs.post_fields.forEach(function(pair) {
						prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
						frag.appendChild(hiddenInput('type',  'post_fields', prefix_pair));
						frag.appendChild(hiddenInput('name',  pair.name,     prefix_pair));
						frag.appendChild(hiddenInput('value', pair.value,    prefix_pair));
					});
				}
			}

			step.data.pairs.query_fields.forEach(function(pair) {
				prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
				frag.appendChild(hiddenInput('type',  'query_fields', prefix_pair));
				frag.appendChild(hiddenInput('name',  pair.name,      prefix_pair));
				frag.appendChild(hiddenInput('value', pair.value,     prefix_pair));
			});

			step.data.pairs.variables.forEach(function(pair) {
				prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
				frag.appendChild(hiddenInput('type',  'variables', prefix_pair));
				frag.appendChild(hiddenInput('name',  pair.name,   prefix_pair));
				frag.appendChild(hiddenInput('value', pair.value,  prefix_pair));
			});

			step.data.pairs.headers.forEach(function(pair) {
				prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
				frag.appendChild(hiddenInput('type',  'headers',  prefix_pair));
				frag.appendChild(hiddenInput('name',  pair.name,  prefix_pair));
				frag.appendChild(hiddenInput('value', pair.value, prefix_pair));
			});

		}.bind(this));

		return frag;
	};

	/**
	 * This method maintains property for iterating steps in the order that rows have in DOM at the moment,
	 * also updates visual counter in DOM for step rows.
	 */
	Steps.prototype.onSortOrderChange = function() {
		var order = [];
		this.$container.find('[data-index]').each(function(index) {
			this.querySelector('[data-row-num]').innerText = (index + 1) + ':';
			order.push(this.attributes.getNamedItem('data-index').value);
		});
		this.sort_index = order;
	};

	/**
	 * Used to validate step names with server, on PopUp form validate event.
	 *
	 * @return {array}  Array of strings.
	 */
	Steps.prototype.getStepNames = function() {
		var names = [];

		for (var no in this.data) {
			names.push(this.data[no].data.name);
		}

		return names;
	};

	/**
	 * This method hydrates the parsed html PopUp form with data from specific step.
	 *
	 * @param {int} no  Step index.
	 */
	Steps.prototype.onStepOverlayReadyCb = function(no) {
		var step_ref = this.data[no] ? this.data[no] : this.new_step;
		this.edit_form = new StepEditForm(jQuery('#http_step'), step_ref);
	};

	/**
	 * Creates new step id and opens form for it.
	 */
	Steps.prototype.openNew = function() {
		this.new_stepid -= 1;

		this.new_step = new Step({httpstepid: 0, no: this.new_stepid});
		this.new_step.open(this.new_stepid, this.$container.find('.element-table-add'));
	};

	/**
	 * Renders steps in DOM.
	 */
	Steps.prototype.renderData = function() {
		this.sort_index.forEach(function(data_index) {
			this.steps_dynamic_rows.addRow(this.data[data_index].data, data_index);
		}.bind(this));

		this.onSortOrderChange();
	}

	/**
	 * Opens popup for a step.
	 *
	 * @param {integer} no
	 */
	Steps.prototype.open = function(no) {
		this.data[no]
			.open(no, this.$container.find('[data-index="' + no + '"] a'));
	};

	/**
	 * This object represents a step of web scenario.
	 *
	 * @param {object} data  Optional step initial data.
	 */
	function Step(data) {
		var defaults = {
			pairs: {
				query_fields: [],
				post_fields: [],
				variables: [],
				headers: []
			}
		};
		this.data = jQuery.extend(true, data, defaults);
	}

	/**
	 * Merges old data with new data.
	 */
	Step.prototype.update = function(data) {
		jQuery.extend(this.data, data);
	};

	/**
	 * Opens step popup - edit or create form.
	 * Note: a callback this.onStepOverlayReadyCb is called from within popup form once it is parsed.
	 *
	 * @param {int}  no               Step index.
	 * @param {Node} trigger_element  A node to set focus to, when popup is closed.
	 */
	Step.prototype.open = function(no, trigger_element) {
		return PopUp('popup.httpstep', {
			no:               no,
			httpstepid:       this.data.httpstepid,
			templated:        httpconf.templated,
			name:             this.data.name,
			url:              this.data.url,
			posts:            this.data.posts,
			post_type:        this.data.post_type,
			timeout:          this.data.timeout,
			required:         this.data.required,
			status_codes:     this.data.status_codes,
			old_name:         this.data.name,
			retrieve_mode:    this.data.retrieve_mode,
			follow_redirects: this.data.follow_redirects,
			steps_names:      httpconf.steps.getStepNames()
		}, {dialogue_class: 'modal-popup-generic', trigger_element});
	};

	/**
	 * Represents popup form.
	 *
	 * @param {jQuery} $form
	 * @param {Step} step_ref  Reference to step instance from Steps object.
	 */
	function StepEditForm($form, step_ref) {
		this.$form = $form
		this.step = step_ref;

		var $pairs = jQuery('.httpconf-dynamic-row', $form);
		$pairs.sortable(sortableOpts());

		dynamicRowsBindSortableDisable($pairs);
		dynamicRowsBindRemoveDisable($pairs);
		dynamicRowsBindNewRow($pairs);

		this.pairs = {
			query_fields: null,
			post_fields: null,
			variables: null,
			headers: null
		};

		$pairs.each(function(index, node) {
			var $node = jQuery(node),
				type = $node.data('type'),
				data = this.step.data.pairs[type];

			if (type === 'variables') {
				$node.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {
					e.new_node.querySelector('.' + httpconf.ZBX_STYLE_DRAG_ICON).remove();
				});
			}

			if (type === 'variables' || type === 'headers' || type === 'post_fields') {
				$node.on('dynamic_rows.beforeadd', function(e, dynamic_rows) {
					e.new_node.querySelector('[data-type="value"]').setAttribute('maxlength', 2000);
				});
			}

			var dynamic_rows = new DynamicRows($node, {
					add_before: $node.find('.element-table-add').closest('tr')[0],
					template: httpconf.pair_row_template,
					ensure_min_rows: 1
				}, data);

			$node.find('.element-table-add').on('click', dynamic_rows.addRow.bind(dynamic_rows, {}, null));

			this.pairs[type] = dynamic_rows;
		}.bind(this));


		this.$radio_retrieve_mode = jQuery('#retrieve_mode input', $form);
		this.$textarea_raw_post = jQuery('#posts', $form);
		this.$radio_post_type = jQuery('#post_type input', $form);

		this.$radio_post_type.on('change', this.onPostTypeChange.bind(this));

		this.togglePostTypeForm(radioVal.call(this.$radio_post_type) == httpconf.ZBX_POSTTYPE_RAW);
		this.$radio_retrieve_mode.on('change', this.onRetrieveModeChange.bind(this)).trigger('change');
		this.$input_url = jQuery('#url', $form);
	}

	/**
	 * Retrieve mode changed event handler.
	 */
	StepEditForm.prototype.onRetrieveModeChange = function() {
		var disable = (radioVal.call(this.$radio_retrieve_mode) == httpconf.HTTPTEST_STEP_RETRIEVE_MODE_HEADERS);

		this.$textarea_raw_post.prop('disabled', disable);
		this.$radio_post_type.prop('disabled', disable);

		dynamicRowsToggleDisable.call(this.pairs.post_fields, disable);
	};

	/**
	 * Post type changed event handler.
	 */
	StepEditForm.prototype.onPostTypeChange = function(e) {
		var is_raw = (radioVal.call(this.$radio_post_type) == httpconf.ZBX_POSTTYPE_RAW);

		try {
			this.setPostTypeRaw(!is_raw);
		}
		catch (err) {
			radioVal.call(this.$radio_post_type, is_raw ? httpconf.ZBX_POSTTYPE_FORM : httpconf.ZBX_POSTTYPE_RAW);
			this.errorDialog(httpconf.msg.cannot_convert_post_data + '<br><br>' + err, e.target);
		}
	};

	/**
	 * Appends to query fields dynamic rows based on url field.
	 */
	StepEditForm.prototype.parseUrl = function() {
		var url = parseUrlString(this.$input_url.val());

		if (url === false) {
			var html_msg = httpconf.msg.failed_to_parse_url + '<br><br>' + httpconf.msg.url_not_encoded_properly;

			return this.errorDialog(html_msg, this.$input_url);
		}
		this.$input_url.val(url.url);

		// Here we exhaust query parameters to fill any empty pair inputs first.
		eachPair.call(this.pairs.query_fields, function(pair, data_index) {
			if (!pair.value && !pair.name) {
				var pair = url.pairs.shift();
				pair && this.addRow(pair, data_index);
			}
		}.bind(this.pairs.query_fields), true);

		// Appends remaining query parameters, if any.
		url.pairs.forEach(function(new_pair) {
			this.pairs.query_fields.addRow(new_pair);
		}.bind(this));
	};

	/**
	 * @param {string} msg                 Error message.
	 * @param {Node|jQuery} trigger_elmnt  An element that the focus will be returned to.
	 */
	StepEditForm.prototype.errorDialog = function(msg, trigger_elmnt) {
		overlayDialogue({
			'title': httpconf.msg.error,
			'class': 'modal-popup position-middle',
			'content': jQuery('<span>').html(msg),
			'buttons': [{
				title: httpconf.msg.ok,
				class: 'btn-alt',
				focused: true,
				action: function() {}
			}]
		}, trigger_elmnt);
	};

	/**
	 * This method builds query string from given pairs.
	 *
	 * @throws
	 *
	 * @param {array} pairs  Array of pair objects. Pair is an object with two keys - name and value.
	 *
	 * @return {string}
	 */
	StepEditForm.prototype.parsePostPairsToRaw = function(pairs) {
		var fields = [];

		pairs.forEach(function(pair) {
			var parts = [];
			if (pair.name === '') {
				throw httpconf.msg.value_without_name;
			}
			parts.push(encodeURIComponent(pair.name.replace(/'/g,'%27').replace(/"/g,'%22')));
			if (pair.value !== '') {
				parts.push(encodeURIComponent(pair.value.replace(/'/g,'%27').replace(/"/g,'%22')));
			}
			fields.push(parts.join('='));
		});

		return fields.join('&');
	};

	/**
	 * This method parses query string into pairs.
	 *
	 * @throws
	 *
	 * @param {string} raw_txt  Query string that will be parsed into pairs.
	 *
	 * @return {array}  Array of pair objects. Pair is an object with two keys - name and value.
	 */
	StepEditForm.prototype.parsePostRawToPairs = function(raw_txt) {
		var pairs = [];

		if (!raw_txt) {
			return pairs;
		}

		raw_txt.split('&').forEach(function(pair) {
			var fields = pair.split('=');

			if (fields[0] === '') {
				throw httpconf.msg.value_without_name;
			}

			if (fields[0].length > 255) {
				throw httpconf.msg.name_filed_length_exceeded;
			}

			if (fields.length == 1) {
				fields.push('');
			}

			var malformed = (fields.length > 2),
				non_printable_chars = (fields[0].match(/%[01]/) || fields[1].match(/%[01]/));

			if (malformed || non_printable_chars) {
				throw httpconf.msg.data_not_encoded;
			}

			pairs.push({
				name: decodeURIComponent(fields[0].replace(/\+/g, ' ')),
				value: decodeURIComponent(fields[1].replace(/\+/g, ' '))
			});
		});

		return pairs;
	};

	/**
	 * This method switches view between textarea and dynamic field layouts.
	 *
	 * @param {bool} set_raw
	 */
	StepEditForm.prototype.togglePostTypeForm = function(set_raw) {
		this.$textarea_raw_post.closest('#post-raw-row').css('display', set_raw ? 'table-row' : 'none');
		this.pairs.post_fields.$element.closest('#post-fields-row').css('display', set_raw ? 'none' : 'table-row');
	};

	/**
	 * This method tries to parse and populate textarea contents into dynamic fields
	 * or populates dynamic fields into text. On success it updates layout.
	 *
	 * @throws
	 *
	 * @param {bool} set_raw
	 */
	StepEditForm.prototype.setPostTypeRaw = function(set_raw) {
		if (set_raw) {
			var pairs = this.parsePostRawToPairs(this.$textarea_raw_post.val());
			this.pairs.post_fields.setData(pairs);
		}
		else {
			var pairs = [];
			eachPair.call(this.pairs.post_fields, function(pair) {
				pairs.push(pair);
			});
			this.$textarea_raw_post.val(this.parsePostPairsToRaw(pairs));
		}

		this.togglePostTypeForm(!set_raw);
	};

	/**
	 * Current state is always rendered form httpconf.steps object. This method collects data from form fields
	 * and writes it in httpconf.steps object. Note that sort order is read from DOM.
	 */
	StepEditForm.prototype.stepPairsData = function() {
		var curr_pairs = {};

		for (var type in this.pairs) {
			curr_pairs[type] = [];

			eachPair.call(this.pairs[type], function(pair) {
				curr_pairs[type].push(pair);
			});
		}

		return curr_pairs;
	};

	/**
	 * This method is bound via popup button attribute. It posts serialized version of current form to be validated.
	 * Note that we do not bother posting dynamic fields, since they are not validated at this point.
	 *
	 * @param {Overlay} overlay
	 */
	StepEditForm.prototype.validate = function(overlay) {
		var url = new Curl(this.$form.attr('action'));

		this.$form.trimValues(['#step_name', '#url', '#timeout', '#required', '#status_codes']);

		url.setArgument('validate', 1);
		this.$form.parent().find('.msg-bad, .msg-good').remove();

		var curr_pairs = this.stepPairsData();

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

			if (!httpconf.steps.data[ret.params.no]) {
				httpconf.steps.sort_index.push(ret.params.no);
				httpconf.steps.data[ret.params.no] = this.step;
			}

			ret.params.pairs = curr_pairs;
			httpconf.steps.data[ret.params.no].update(ret.params);
			httpconf.steps.renderData();

			overlayDialogueDestroy(overlay.dialogueid);
		}.bind(this));
	};
</script>
