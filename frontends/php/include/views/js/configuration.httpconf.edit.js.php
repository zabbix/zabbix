<script type="text/x-jquery-tmpl" id="scenario-step-row-templated">
	<?= (new CRow([
			'',
			(new CSpan('1:'))->addClass('rowNum'),
			(new CLink('#{name}', 'javascript:httpconf.steps.open(#{httpstepid});')),
			'#{timeout}',
			(new CSpan('#{url_short}'))->setTitle('#{url}'),
			'#{required}',
			'#{status_codes}',
			''
		]))
			->setAttribute('data-step-id', '#{httpstepid}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="scenario-step-row">
	<?= (new CRow([
			(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSpan('1:'))->addClass('rowNum'),
			(new CLink('#{name}', 'javascript:httpconf.steps.open(#{httpstepid});')),
			'#{timeout}',
			(new CSpan('#{url_short}'))->setTitle('#{url}'),
			'#{required}',
			'#{status_codes}',
			(new CCol((new CButton(null, _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setAttribute('data-step-id', '#{httpstepid}')
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
				->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
			'&rArr;',
			(new CTextBox(null, '#{value}'))
				->setAttribute('placeholder', _('value'))
				->setAttribute('data-type', 'value')
				->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
			(new CCol(
				(new CButton(null, _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->addClass('sortable')
			->toString()
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		window.httpconf = {
			templated:                           <?= $data['templated'] ? 1 : 0 ?>,
			ZBX_STYLE_DRAG_ICON:                 <?= zbx_jsvalue(ZBX_STYLE_DRAG_ICON) ?>,
			msg: {
				data_not_encoded:           <?= CJs::encodeJson(_('Data is not properly encoded.')); ?>,
				name_filed_length_exceeded: <?= CJs::encodeJson(_('Name of the form field should not exceed 255 characters.')); ?>,
				value_without_name:         <?= CJs::encodeJson(_('Values without names are not allowed in form fields.')); ?>,
				failed_to_parse_url:        <?= CJs::encodeJson(_('Failed to parse URL.')); ?>,
				ok:                         <?= CJs::encodeJson(_('Ok')); ?>,
				error:                      <?= CJs::encodeJson(_('Error')); ?>,
				url_not_encoded_properly:   <?= CJs::encodeJson(_('URL is not properly encoded.')); ?>,
				cannot_convert_into_raw:    <?= CJs::encodeJson(_('Cannot convert POST data from raw data format to form field data format.')); ?>
			}
		};

		window.httpconf.scenario = new Scenario(
			$('#scenarioTab'), <?= zbx_jsvalue($this->data['agentVisibility'], true) ?>);
		window.httpconf.steps = new Steps($('#stepTab'), <?= CJs::encodeJson(array_values($data['steps'])) ?>);
		window.httpconf.authentication = new Authentication($('#authenticationTab'));

		window.httpconf.$form = $('#httpForm').on('submit', function() {
			var hidden_form = this.querySelector('div#hidden-form');

			hidden_form && hidden_form.remove();
			hidden_form = document.createElement('div');
			hidden_form.id = 'hidden-form';
			hidden_form.className = 'hidden';

			hidden_form.append(httpconf.scenario.toFragment());
			hidden_form.append(httpconf.steps.toFragment());

			this.append(hidden_form);
		});
	});

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
			var http_fields_disabled = (e.target.value == <?= HTTPTEST_AUTH_NONE ?>);
			this.$user.prop('disabled', http_fields_disabled).closest('li').toggle(!http_fields_disabled);
			this.$password.prop('disabled', http_fields_disabled).closest('li').toggle(!http_fields_disabled);
		}.bind(this));
		this.$type_select.trigger('change');
	}

	/**
	 * Represents scenario tab in web layout.
	 *
	 * @param {jQuery} $tab
	 * @param {object} switcher_conf  CViewSwitcher configuration.
	 */
	function Scenario($tab, switcher_conf) {
		new CViewSwitcher('agent', 'change', switcher_conf);
		this.pairs = {
			'variables': null,
			'headers': null
		};

		jQuery('.httpconf-dynamic-row', $tab).each(function(index, table) {
			var $table = jQuery(table),
				type = $table.data('type');

			this.pairs[type] = $table
				.dynamicRows({
					keep_min_rows: 1,
					template: '#scenario-pair-row'
				})
				.sortable({
					items: 'tbody tr.sortable',
					axis: 'y',
					cursor: 'move',
					containment: 'parent',
					handle: 'div.' + httpconf.ZBX_STYLE_DRAG_ICON,
					tolerance: 'pointer',
					opacity: 0.6,
					start: function(e, ui) {
						ui.placeholder.height(ui.item.height());
					}
				})
				.on('tableupdate.dynamicRows', function(e, data) {
					data.dynamicRows.$element.sortable('option','disabled', data.dynamicRows.length < 2);
				})
				.data('dynamicRows');

			$table.sortable('option','disabled', $table.data('dynamicRows').length < 2);
		}.bind(this));
	}

	/**
	 * The parts of form that are easier to maintain in functional objects are transformed into hidden input fields.
	 *
	 * @return {DocumentFragment}
	 */
	Scenario.prototype.toFragment = function() {
		var frag = new DocumentFragment(),
			iter = 0;

		this.pairs.headers.eachRow(function(i, node) {
			var name = node.querySelector('[data-type="name"]').value,
				value = node.querySelector('[data-type="value"]').value,
				prefix = 'pairs[' + (iter ++) + ']';

			frag.append(hiddenInput('type',  'headers', prefix));
			frag.append(hiddenInput('name',  name,      prefix));
			frag.append(hiddenInput('value', value,     prefix));
		});

		this.pairs.variables.eachRow(function(i, node) {
			var name = node.querySelector('[data-type="name"]').value,
				value = node.querySelector('[data-type="value"]').value,
				prefix = 'pairs[' + (iter ++) + ']';

			frag.append(hiddenInput('type',  'variables', prefix));
			frag.append(hiddenInput('name',  name,        prefix));
			frag.append(hiddenInput('value', value,       prefix));
		});

		return frag;
	};

	/**
	 * Represents steps tab in web layout.
	 *
	 * @param {jQuery} $tab
	 * @param {array} steps  Initial step objects data array.
	 */
	function Steps($tab, steps) {
		this.new_stepid = 0;
		this.steps = {};
		this.steps_sort_order = [];

		var that = this;
		steps.forEach(function(step) {
			that.steps[step.httpstepid] = new Step(step);
		});

		this.$container = jQuery('.httpconf-steps-dynamic-row', $tab);
		this.$container.dynamicRows({
			template: httpconf.templated ? '#scenario-step-row-templated' : '#scenario-step-row',
			dataCallback(data) {
				return jQuery.extend({
					url_short: midEllipsis(data.url, 65)
				}, data);
			}
		});

		if (!httpconf.templated) {
			this.$container.sortable({
				items: 'tbody tr.sortable',
				axis: 'y',
				cursor: 'move',
				containment: 'parent',
				handle: 'div.' + httpconf.ZBX_STYLE_DRAG_ICON,
				tolerance: 'pointer',
				update: this.onSortOrderChange.bind(this),
				opacity: 0.6,
				start: function(e, ui) {
					ui.placeholder.height(ui.item.height());
				}
			});
		}

		this.dynamicRows = this.$container.data('dynamicRows');

		this.dynamicRows.setData(steps);

		if (!httpconf.templated) {
			this.$container.each(function(index, el) {
				$el = jQuery(el);
				$el.sortable('option','disabled', $el.data('dynamicRows').length < 2);
			});

			this.$container.on('beforeadd.dynamicRows', function(e) {
				if (!e.originalEvent) {
					// If event is not triggered by click, but invoked programmatically.
					return;
				}
				e.preventDefault();
				that.openNew();
			});

			this.$container.on('tableupdate.dynamicRows', function(e, data) {
				data.dynamicRows.$element.sortable('option','disabled', data.dynamicRows.length < 2);
				that.onSortOrderChange();
			});
		}

		this.onSortOrderChange();
	}

	/**
	 * A helper method for truncating string in middle.
	 *
	 * @param {string} str  String to be shortened into mid-elliptic.
	 * @param {int} max     Max length of resulting string.
	 */
	function midEllipsis(str, max) {
		if (str.length <= max) {
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
	 * The parts of form that are easier to maintain in functional objects are transformed into hidden input fields.
	 *
	 * @return {DocumentFragment}
	 */
	Steps.prototype.toFragment = function() {
		var frag = new DocumentFragment(),
			iter_step = 0;

		this.steps_sort_order.forEach(function(id) {
			var iter_pair = 0,
				step = this.steps[id],
				prefix_step = 'steps[' + (iter_step ++) + ']';

			frag.append(hiddenInput('follow_redirects', step.data.follow_redirects, prefix_step));
			frag.append(hiddenInput('httpstepid',       step.data.httpstepid,       prefix_step));
			frag.append(hiddenInput('name',             step.data.name,             prefix_step));
			frag.append(hiddenInput('post_type',        step.data.post_type,        prefix_step));
			frag.append(hiddenInput('required',         step.data.required,         prefix_step));
			frag.append(hiddenInput('retrieve_mode',    step.data.retrieve_mode,    prefix_step));
			frag.append(hiddenInput('status_codes',     step.data.status_codes,     prefix_step));
			frag.append(hiddenInput('timeout',          step.data.timeout,          prefix_step));
			frag.append(hiddenInput('url',              step.data.url,              prefix_step));

			if (step.data.retrieve_mode != <?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>) {
				if (step.data.post_type != <?= ZBX_POSTTYPE_FORM ?>) {
					frag.append(hiddenInput('posts', step.data.posts, prefix_step));
				}
				else {
					step.data.pairs.post_fields.forEach(function(pair) {
						var prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
						frag.append(hiddenInput('type',  'post_fields', prefix_pair));
						frag.append(hiddenInput('name',  pair.name,     prefix_pair));
						frag.append(hiddenInput('value', pair.value,    prefix_pair));
					});
				}
			}

			step.data.pairs.query_fields.forEach(function(pair) {
				var prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
				frag.append(hiddenInput('type',  'query_fields', prefix_pair));
				frag.append(hiddenInput('name',  pair.name,      prefix_pair));
				frag.append(hiddenInput('value', pair.value,     prefix_pair));
			});

			step.data.pairs.variables.forEach(function(pair) {
				var prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
				frag.append(hiddenInput('type',  'variables', prefix_pair));
				frag.append(hiddenInput('name',  pair.name,   prefix_pair));
				frag.append(hiddenInput('value', pair.value,  prefix_pair));
			});
			step.data.pairs.headers.forEach(function(pair) {
				var prefix_pair = prefix_step + '[pairs][' + (iter_pair ++) + ']';
				frag.append(hiddenInput('type',  'headers',  prefix_pair));
				frag.append(hiddenInput('name',  pair.name,  prefix_pair));
				frag.append(hiddenInput('value', pair.value, prefix_pair));
			});

		}.bind(this));

		return frag;
	};

	/**
	 * This method maintains property for iterating steps in order, and updates visual counter in DOM.
	 */
	Steps.prototype.onSortOrderChange = function() {
		var order = [];
		this.$container.find('[data-step-id]').each(function(index) {
			this.querySelector('.rowNum').innerText = (index + 1) + ':';
			order.push(this.attributes.getNamedItem('data-step-id').value);
		});
		this.steps_sort_order = order;
	};

	/**
	 * Updates step data with Steps object.
	 *
	 * @param {object} step  Step data, that holds accurate httpstepid filed.
	 */
	Steps.prototype.updateStep = function(step) {
		jQuery.extend(this.steps[step.httpstepid].data, step);
		this.dynamicRows.setData([]);
		this.steps_sort_order.forEach(function(httpstepid) {
			this.dynamicRows.addRow(this.steps[httpstepid].data);
		}.bind(this));
	};

	/**
	 * Adds or updates newly created step data with Steps object.
	 *
	 * @param {object} step  Step data, that holds accurate httpstepid filed.
	 */
	Steps.prototype.addStep = function(step) {
		if (this.steps_sort_order.indexOf(step.httpstepid) == -1) {
			this.steps_sort_order.push(step.httpstepid);
		}
		this.updateStep(step);
	};

	/**
	 * Used to validate step names with server, on PopUp form validate event.
	 *
	 * @return {array}  Array of strings.
	 */
	Steps.prototype.getStepNames = function() {
		var names = [];

		for (var httpstepid in this.steps) {
			names.push(this.steps[httpstepid].data.name);
		}

		return names;
	};

	/**
	 * This method hydrates the parsed html PopUp form with data from specific step.
	 *
	 * @param {integer} httpstepid
	 */
	Steps.prototype.onStepOverlayReadyCb = function(httpstepid) {
		this.edit_form = new StepEditForm(jQuery('#http_step'), this.steps[httpstepid]);
	};

	/**
	 * Creates new step id and opens form for it.
	 */
	Steps.prototype.openNew = function() {
		this.new_stepid -= 1;
		this.steps[this.new_stepid] = new Step({httpstepid: this.new_stepid});
		this.open(this.new_stepid);
	};

	/**
	 * Opens popup for a step.
	 *
	 * @param {integer} httpstepid
	 */
	Steps.prototype.open = function(httpstepid) {
		var $refocus = (httpstepid != this.new_stepid)
			? this.$container.find('[data-step-id="' + httpstepid + '"] a')
			: this.$container.find('.element-table-add');

		this.steps[httpstepid].open($refocus);
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
	 * Opens step popup edit or create form.
	 * Note: a callback this.onStepOverlayReadyCb is called from within popup form once it is parsed.
	 *
	 * @param {Node} refocus
	 */
	Step.prototype.open = function(refocus) {
		return PopUp('popup.httpstep', {
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
			follow_redirects: this.data.follow_redirect,
			steps_names:      httpconf.steps.getStepNames()
		}, null, refocus);
	};

	/**
	 * @param {jQuery} $form
	 * @param {Step} step_ref  Reference to step instance from Steps object.
	 */
	function StepEditForm($form, step_ref) {
		this.$form = $form;
		this.step = step_ref;

		var $pairs = jQuery('.httpconf-dynamic-row', $form);

		$pairs.sortable({
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'div.' + httpconf.ZBX_STYLE_DRAG_ICON,
			tolerance: 'pointer',
			opacity: 0.6,
			start: function(e, ui) {
				ui.placeholder.height(ui.item.height());
			}
		});

		this.pairs = {
			query_fields: null,
			post_fields: null,
			variables: null,
			headers: null
		};

		$pairs.each(function(index, node) {
			var $node = jQuery(node),
				type = $node.data('type');

			this.pairs[type] = $node.dynamicRows({
				keep_min_rows: 1,
				template: '#scenario-pair-row'
			})
			.data('dynamicRows')
			.setData(this.step.data.pairs[type]);
		}.bind(this));

		$pairs.each(function(index, el) {
			$el = jQuery(el);
			$el.sortable('option','disabled', $el.data('dynamicRows').length < 2);
		});

		$pairs.on('tableupdate.dynamicRows', function(e, data) {
			data.dynamicRows.$element.sortable('option','disabled', data.dynamicRows.length < 2);
		});

		this.$checkbox_retrieve_mode = jQuery('#retrieve_mode', $form);
		this.$input_required_string = jQuery('#required', $form);
		this.$textarea_raw_post = jQuery('#posts', $form);
		this.$radio_post_type = jQuery('#post_type input', $form);

		this.$radio_post_type.val = function(value) {
			if (typeof value === 'undefined') {
				return this.filter(':checked').val();
			}
			this.filter('[value="' + value + '"]').get(0).checked = true;
		};

		this.$radio_post_type.on('change', this.onPostTypeChange.bind(this));

		this.togglePostTypeForm(this.$radio_post_type.val() == <?= ZBX_POSTTYPE_RAW ?>);
		this.$checkbox_retrieve_mode.on('change', this.onRetrieveModeChange.bind(this)).trigger('change');
		this.$input_url = jQuery('#url', $form);
	}

	/**
	 * Retrieve mode changed event handler.
	 */
	StepEditForm.prototype.onRetrieveModeChange = function() {
		var disable = this.$checkbox_retrieve_mode.prop('checked');

		this.$input_required_string.prop('disabled', disable);
		this.$textarea_raw_post.prop('disabled', disable);
		this.$radio_post_type.prop('disabled', disable);
		this.pairs.post_fields.$element.sortable('option', 'disabled', disable);
		this.pairs.post_fields.$element.toggleClass('disabled', disable);
		this.pairs.post_fields.$element.find('input').prop('disabled', disable);
		this.pairs.post_fields.disabled(disable);
	};

	/**
	 * Post type changed event handler.
	 */
	StepEditForm.prototype.onPostTypeChange = function(e) {
		var is_raw = (this.$radio_post_type.val() == <?= ZBX_POSTTYPE_RAW ?>);

		try {
			this.setPostTypeRaw(!is_raw);
		}
		catch (err) {
			this.$radio_post_type.val(is_raw ? <?= ZBX_POSTTYPE_FORM ?> : <?= ZBX_POSTTYPE_RAW ?>);
			this.errorDialog(httpconf.msg.cannot_convert_into_raw + '<br><br>' + err, e.target);
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

		// We add one by one instead of using setData, because we append to preexisting dynamic pairs.
		url.pairs.forEach(function(pair) {
			this.pairs.query_fields.addRow(pair);
		}.bind(this));

		this.$input_url.val(url.url);
	};

	/**
	 * @param {string} msg                 Error message.
	 * @param {Node|jQuery} trigger_elmnt  An element that the focus will be retuned to.
	 */
	StepEditForm.prototype.errorDialog = function(msg, trigger_elmnt) {
		overlayDialogue({
			'title': httpconf.msg.error,
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
	 * This method builds query string from pairs given.
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
				non_printable_chars = (/%[01]/.match(fields[0]) || /%[01]/.match(fields[1]));

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
	 * This method switches between textarea and dynamic field layouts.
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
			// This way sortable order is preserved.
			this.pairs.post_fields.eachRow(function(i, node) {
				var name = node.querySelector('[data-type="name"]').value,
					value = node.querySelector('[data-type="value"]').value;

				if (name || value) {
					pairs.push({name: name, value: value});
				}
			});
			this.$textarea_raw_post.val(this.parsePostPairsToRaw(pairs));
		}

		this.togglePostTypeForm(!set_raw);
	};

	/**
	 * Current state is always rendered form httpconf.steps object. This method collects data from form fields
	 * and writes it in httpconf.steps object. Note that sort order is read from DOM.
	 */
	StepEditForm.prototype.formToData = function() {
		for (var type in this.pairs) {
			this.step.data.pairs[type] = [];
			this.pairs[type].eachRow(function(index, row) {
				var name = row.querySelector('[data-type="name"]').value,
					value = row.querySelector('[data-type="value"]').value;
				if (name || value) {
					this.push({name: name, value: value});
				}
			}.bind(this.step.data.pairs[type]));
		}
	};

	/**
	 * This method is bound via popup button attribute. It posts serialized version of current form to be validated.
	 * Note that we do not bother posting dynamic fields, since they are not validated at this point.
	 */
	StepEditForm.prototype.validate = function() {
		var url = new Curl(this.$form.attr('action')),
			dialogueid = this.$form.closest('[data-dialogueid]').attr('data-dialogueid');

		this.$form.trimValues(['#step_name', '#url', '#timeout', '#required', '#status_codes']);

		url.setArgument('validate', 1);
		this.$form.parent().find('.msg-bad, .msg-good').remove();

		return jQuery.ajax({
			url: url.getUrl(),
			data: this.$form.serialize(),
			dataType: 'json',
			type: 'post'
		})
		.done(function(ret) {
			if (typeof ret.errors !== 'undefined') {
				return jQuery(ret.errors).insertBefore(this.$form);
			}

			this.formToData();
			if (ret.params.httpstepid < 0) {
				httpconf.steps.addStep(ret.params);
			}
			else {
				httpconf.steps.updateStep(ret.params);
			}

			overlayDialogueDestroy(dialogueid);
		}.bind(this));
	};

</script>
