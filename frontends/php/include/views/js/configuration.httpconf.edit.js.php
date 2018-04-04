<script type="text/x-jquery-tmpl" id="scenarioPairRow">
	<?= (new CRow([
			(new CCol([
				(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON),
				new CInput('hidden', 'pairs[#{pair.id}][isNew]', '#{pair.isNew}'),
				new CInput('hidden', 'pairs[#{pair.id}][id]', '#{pair.id}'),
				(new CInput('hidden', 'pairs[#{pair.id}][type]', '#{pair.type}'))->setId('pair_type_#{pair.id}'),
			]))
				->addClass('pair-drag-control')
				->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CTextBox('pairs[#{pair.id}][name]', '#{pair.name}'))
				->setAttribute('data-type', 'name')
				->setAttribute('placeholder', _('name'))
				->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
			'&rArr;',
			(new CTextBox('pairs[#{pair.id}][value]', '#{pair.value}'))
				->setAttribute('data-type', 'value')
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_TAG_WIDTH),
			(new CCol(
				(new CButton('removePair_#{pair.id}', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('remove')
					->setAttribute('data-pairid', '#{pair.id}')
			))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass('pair-control')
		]))
			->setId('pairRow_#{pair.id}')
			->addClass('pairRow')
			->addClass('sortable')
			->setAttribute('data-pairid', '#{pair.id}')
			->toString()
	?>
</script>

<script type="text/javascript">
	var pairManager = (function() {
		'use strict';

		var rowTemplate = new Template(jQuery('#scenarioPairRow').html()),
			allPairs = {};

		/**
		 * Creates HTML for pair, inserts it in page.
		 *
		 * @param {string}	formid	Id of current form HTML form element.
		 * @param {object}	pair	Object with pair data.
		 */
		function renderPairRow(formid, pair) {
			var parent,
				target = jQuery(getDomTargetIdForRowInsert(pair.type), jQuery('#'+formid)),
				pair_row = jQuery(rowTemplate.evaluate({'pair': pair}));

			if (!target.parents('.pair-container').hasClass('pair-container-sortable')) {
				pair_row.find('.<?= ZBX_STYLE_DRAG_ICON ?>').remove();
			}

			target.before(pair_row);
			parent = jQuery('#pairRow_' + pair.id);
			parent.find('input[data-type]').on('change', function() {
				var	target = jQuery(this),
					parent = target.parents('.pairRow'),
					id = parent.data('pairid'),
					pair = allPairs[id];

				pair[target.data('type')] = target.val();
				allPairs[id] = pair;
			});
		}

		/**
		 * Prepares ID for block, at the end of which new pair will be added.
		 *
		 * @param {string}	type	Type of pair to be added.
		 *
		 * @return {string}
		 */
		function getDomTargetIdForRowInsert(type) {
			return '#' + type.toLowerCase().trim() + '_footer';
		}

		/**
		 * Add pair in allPairs array.
		 *
		 * @param {object}	pair	Object with pair data. Should contain id.
		 *
		 * @return {object}
		 */
		function addPair(pair) {
			if (pair.isNew === 'true') {
				pair.isNew = true;
			}
			allPairs[pair.id] = pair;
			return pair;
		}

		/**
		 * Creates new pair object from provided data.
		 *
		 * @param {object}	options		Object with pair options that are different from default. Should contain type.
		 *
		 * @return {object}
		 */
		function createNewPair(options) {
			var newPair = jQuery.extend({
				formid: '',
				isNew: true,
				type: '',
				name: '',
				value: ''
			}, options);

			newPair.id = 1;
			while (allPairs[newPair.id] !== void(0)) {
				newPair.id++;
			}

			return addPair(newPair);
		}

		/**
		 * Makes sortable handler inactive, if nothing to sort.
		 */
		function refreshContainers() {
			jQuery('.pair-container-sortable').each(function() {
				jQuery(this).sortable({
					disabled: (jQuery(this).find('tr.sortable').length < 2)
				});
			});
		}

		return {
			/**
			 * Add pair when form is beeing created.
			 *
			 * @param {string}	formid	Id of current form HTML form element.
			 * @param {object}	pairs	Object with pair objects.
			 */
			add: function(formid, pairs) {
				for (var i = 0; i < pairs.length; i++) {
					pairs[i]['formid'] = formid;
					renderPairRow(formid, addPair(pairs[i]));
				}

				jQuery('.pair-container', jQuery('#'+formid)).each(function() {
					var rows = jQuery(this).find('.pairRow').length;
					if (rows === 0) {
						renderPairRow(formid, createNewPair({formid: formid, type: this.id}));
					}
				});

				refreshContainers();
			},

			/**
			 * Add empty pair at the block of pair's type.
			 *
			 * @param {string}	formid		Id of current form HTML form element.
			 * @param {object}	options		Object with new pair options, that are different from default.
			 *								Should contain type.
			 */
			addNew: function(formid, options) {
				options.formid = formid;
				renderPairRow(formid, createNewPair(options));
				refreshContainers();
			},

			/**
			 * Delete pair with given ID from allPairs.
			 *
			 * @param {number}	pairId		Id of pair, that should be removed.
			 */
			remove: function(pairId) {
				delete allPairs[pairId];
				refreshContainers();
			},

			/**
			 * Add listeners to HTML elements in web scenario and web step forms.
			 *
			 * @param {string}	formid		Id of current form HTML form element.
			 */
			initControls: function(formid) {
				var $form = jQuery('#'+formid);
				$form.on('click', 'button.remove', function() {
					var pairId = jQuery(this).data('pairid');
					jQuery('#pairRow_' + pairId).remove();
					pairManager.remove(pairId);
				});

				jQuery('.pair-container-sortable', $form).sortable({
					disabled: (jQuery(this).find('tr.sortable').length < 2),
					items: 'tr.sortable',
					axis: 'y',
					cursor: 'move',
					containment: 'parent',
					handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
					tolerance: 'pointer',
					opacity: 0.6,
					helper: function(e, ui) {
						return ui;
					},
					start: function(e, ui) {
						$(ui.placeholder).height($(ui.helper).height());
					}
				});

				jQuery('.pairs-control-add', $form).on('click', function() {
					pairManager.addNew(formid, {type:jQuery(this).data('type')});
				});

				jQuery('#retrieve_mode', $form)
					.on('change', function() {
						jQuery('#post_fields', $form).toggleClass('disabled',this.checked);
						jQuery('#required, #posts, #post_fields input[type="text"], #post_fields .btn-link,' +
								'#post_type input', $form)
							.attr('disabled', this.checked);

						if (this.checked === false) {
							pairManager.refresh();
						}
					})
					.trigger('change');
			},

			/**
			 * Makes sortable handler inactive, if nothing to sort.
			 */
			refresh: function() {
				refreshContainers();
			},

			/**
			 * Removes all new pairs with empty name and value (of given type withing given form).
			 *
			 * @param {string}	formid	Id of current form HTML element.
			 * @param {string}	type	Type of pairs that should be cleaned.
			 */
			cleanup: function(formid, type) {
				var pairs = this.getPairsByType(formid, type);

				for (var p = 0; p < pairs.length; p++) {
					if (pairs[p].isNew === true && pairs[p].name === '' && pairs[p].value === '') {
						jQuery('#pairRow_' + pairs[p].id).remove();
						delete allPairs[pairs[p].id];
					}
				}
			},

			/**
			 * Finds all pairs of given type within given form.
			 *
			 * @param {string}	formid	Id of current form HTML element.
			 * @param {string}	type	Type of pairs that should be found.
			 */
			getPairsByType: function(formid, type) {
				var	pairs = [],
					existingPairs = Object.keys(allPairs);

				for (var p = 0; p < existingPairs.length; p++) {
					if (allPairs[existingPairs[p]].type === type && allPairs[existingPairs[p]].formid === formid) {
						pairs.push(allPairs[existingPairs[p]]);
					}
				}

				return pairs;
			},

			/**
			 * Removes all pairs and their fields of given type in given form.
			 *
			 * @param {string}	formid	Id of current form HTML element.
			 * @param {string}	type	String with type of pair, or '', if all pairs for the form should be removed.
			 */
			removeAll: function(formid, type) {
				var pairs = Object.keys(allPairs);

				for (var p = 0; p < pairs.length; p++) {
					if (allPairs[pairs[p]].formid === formid
							&& (type === '' || allPairs[pairs[p]].type === type)) {
						jQuery('#pairRow_' + pairs[p]).remove();
						delete allPairs[pairs[p]];
					}
				}
			}
		};
	}());

	/**
	 * Removes step, when Remove button in step is clicked.
	 *
	 * @param {obj}	obj	Step remove button object.
	 */
	function removeStep(obj) {
		var step = obj.getAttribute('remove_step'),
			table = jQuery('#httpStepTable');

		jQuery('#steps_' + step).remove();

		jQuery('input[id^=steps_' + step + '_]').each( function() {
			this.remove();
		});

		if (table.find('tr.sortable').length <= 1) {
			table.sortable('disable');
		}

		recalculateSortOrder();
	}

	/**
	 * Changes ID's of steps in table (data in row and all hidden fields with step data),
	 * after one of existing steps is deleted.
	 */
	function recalculateSortOrder() {
		var i = 0;

		jQuery('#httpStepTable tr.sortable .rowNum').each(function() {
			var step = (i == 0) ? '0' : i;

			// Rewrite ids to temp.
			jQuery('#remove_' + step).attr('id', 'tmp_remove_' + step);
			jQuery('#name_' + step).attr('id', 'tmp_name_' + step);
			jQuery('#steps_' + step).attr('id', 'tmp_steps_' + step);
			jQuery('#current_step_' + step).attr('id', 'tmp_current_step_' + step);

			jQuery('input[id^=steps_' + step + '_]').each( function() {
				var input = jQuery(this),
					id = input.attr('id').replace(/^steps_[0-9]+_/, 'tmp_steps_' + step + '_');

				input.attr('id', id);
			});

			// Set order number.
			jQuery(this)
				.attr('new_step', i)
				.text((i + 1) + ':');
			i++;
		});

		// Rewrite ids in new order.
		for (var n = 0; n < i; n++) {
			var currStep = jQuery('#tmp_current_step_' + n),
				newStep = currStep.attr('new_step');

			jQuery('#tmp_remove_' + n).attr('id', 'remove_' + newStep);
			jQuery('#tmp_name_' + n).attr('id', 'name_' + newStep);
			jQuery('#tmp_steps_' + n).attr('id', 'steps_' + newStep);
			jQuery('#remove_' + newStep).attr('remove_step', newStep);
			jQuery('#name_' + newStep).attr('name_step', newStep);

			jQuery('input[id^=tmp_steps_' + n + '_]').each( function() {
				var	input = jQuery(this),
					id = input.attr('id').replace(/^tmp_steps_[0-9]+_/, 'steps_' + newStep + '_'),
					name = input.attr('name').replace(/^steps\[[0-9]+\]/, 'steps[' + newStep + ']');

				input.attr('id', id);
				input.attr('name', name);
			});

			jQuery('#steps_' + newStep + '_no').val(parseInt(newStep) + 1);

			// Set new step order position.
			currStep.attr('id', 'current_step_' + newStep);
		}
	}

	jQuery(function($) {
		var stepTable = $('#httpStepTable'),
			stepTableWidth = stepTable.width(),
			stepTableColumns = $('#httpStepTable .header td'),
			stepTableColumnWidths = [];

		stepTableColumns.each(function() {
			stepTableColumnWidths[stepTableColumnWidths.length] = $(this).width();
		});

		stepTable.sortable({
			disabled: (stepTable.find('tr.sortable').length < 2),
			items: 'tbody tr.sortable',
			axis: 'y',
			cursor: 'move',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: recalculateSortOrder,
			create: function () {
				// Force not to change table width.
				stepTable.width(stepTableWidth);
			},
			helper: function(e, ui) {
				ui.children().each(function(i) {
					var td = $(this);

					td.width(stepTableColumnWidths[i]);
				});

				// When dragging element on safari, it jumps out of the table.
				if (SF) {
					// Move back draggable element to proper position.
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				stepTableColumns.each(function(i) {
					$(this).width(stepTableColumnWidths[i]);
				});

				return ui;
			},
			start: function(e, ui) {
				// Fix placeholder not to change height while object is being dragged.
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		// Http step add pop up.
		<?php if (!$this->data['templated']) : ?>
			$('#add_step').click(function(event) {
				var form = $(this).parents('form');

				// Append existing step names.
				var step_names = [];
				form.find('input[name^=steps]').filter('input[name*=name]:not([name*=pairs])').each(function(i, step) {
					step_names.push($(step).val());
				});

				var popup_options = {dstfrm: 'httpForm'};
				if (step_names.length > 0) {
					popup_options['steps_names'] = step_names;
				}

				return PopUp('popup.httpstep', popup_options, null, event.target);
			});
		<?php endif ?>

		// Http step edit pop up.
		<?php foreach ($this->data['steps'] as $i => $step): ?>
			$('#name_<?= $i ?>').click(function(event) {
				// Append existing step names.
				var step_names = [];
				var form = $(this).parents('form');
				form.find('input[name^=steps]').filter('input[name*=name]:not([name*=pairs])').each(function(i, step) {
					step_names.push($(step).val());
				});

				var popup_options = <?= CJs::encodeJson([
					'dstfrm' => 'httpForm',
					'templated' => $this->data['templated'] ? 1 : 0,
					'list_name' => 'steps',
					'name' => $step['name'],
					'url' => $step['url'],
					'posts' => $step['posts'],
					'pairs' => (array_key_exists('pairs', $step)) ? $step['pairs'] : [],
					'post_type' => $step['post_type'],
					'timeout' => $step['timeout'],
					'required' => $step['required'],
					'status_codes' => $step['status_codes'],
					'old_name' => $step['name'],
					'retrieve_mode' => $step['retrieve_mode'],
					'follow_redirects' => $step['follow_redirects']
				]) ?>

				if (step_names.length > 0) {
					popup_options['steps_names'] = step_names;
				}

				return PopUp('popup.httpstep',jQuery.extend(popup_options,{
					stepid: jQuery(this).attr('name_step')
				}), null, event.target);
			});
		<?php endforeach ?>

		$('#authentication').on('change', function() {
			var httpFieldsDisabled = ($(this).val() == <?= HTTPTEST_AUTH_NONE ?>);

			$('#http_user')
				.attr('disabled', httpFieldsDisabled)
				.closest('li').toggle(!httpFieldsDisabled);
			$('#http_password')
				.attr('disabled', httpFieldsDisabled)
				.closest('li').toggle(!httpFieldsDisabled);
		});

		<?php if (isset($this->data['agentVisibility']) && $this->data['agentVisibility']): ?>
			new CViewSwitcher('agent', 'change', <?= zbx_jsvalue($this->data['agentVisibility'], true) ?>);
		<?php endif ?>

		$('#agent').trigger('change');
		$('#authentication').trigger('change');
	});

	/**
	 * Inital post type selection for "Post type" field.
	 *
	 * @param {string}	formid	Id of current form HTML element.
	 * @param {int}		type	Value for "Post type" field.
	 */
	function setPostType(formid, type) {
		var $form = jQuery('#'+formid);
		if (type == <?= ZBX_POSTTYPE_FORM ?>) {
			jQuery('#post_fields_row', $form).css('display', 'table-row');
			jQuery('#post_raw_row', $form).css('display', 'none');
		}
		else {
			jQuery('#post_fields_row', $form).css('display', 'none');
			jQuery('#post_raw_row', $form).css('display', 'table-row');
		}

		jQuery('input[name="post_type"][value="' + type + '"]', $form).prop('checked', true);
	}

	/**
	 * Converts "Form data" pairs to "Raw data" text and vice versa for post values.
	 *
	 * @param {string}	formid	Id of current form HTML element.
	 * @param {int}		type	New value for "Post type" field.
	 */
	function switchToPostType(formid, type) {
		if (type == <?= ZBX_POSTTYPE_FORM ?>) {
			var	posts = jQuery('#posts', jQuery('#'+formid)).val(),
				fields,
				parts,
				pair,
				pairs = [];

			if (posts !== '') {
				fields = posts.split('&');

				try {
					for (var i = 0; i < fields.length; i++) {
						parts = fields[i].split('=');
						if (parts.length === 1) {
							parts.push('');
						}

						pair = {};
						try {
							if (parts.length > 2) {
								throw null;
							}

							if (/%[01]/.match(parts[0]) || /%[01]/.match(parts[1]) ) {
								// Non-printable characters in data.
								throw null;
							}

							pair.name = decodeURIComponent(parts[0].replace(/\+/g, ' '));
							pair.value = decodeURIComponent(parts[1].replace(/\+/g, ' '));
						}
						catch(e) {
							throw <?= CJs::encodeJson(_('Data is not properly encoded.')); ?>;
						}

						if (pair.name === '') {
							throw <?= CJs::encodeJson(_('Values without names are not allowed in form fields.')); ?>;
						}

						if (pair.name.length > 255) {
							throw <?= CJs::encodeJson(_('Name of the form field should not exceed 255 characters.')); ?>;
						}

						pairs.push(pair);
					}
				}
				catch(e) {
					jQuery('input[name="post_type"][value="<?= ZBX_POSTTYPE_RAW ?>"]', jQuery('#'+formid))
						.prop('checked', true);

					overlayDialogue({
						'title': <?= CJs::encodeJson(_('Error')); ?>,
						'content': jQuery('<span>').html(<?=
							CJs::encodeJson(
								_('Cannot convert POST data from raw data format to form field data format.').'<br><br>'
							); ?> + e),
						'buttons': [
							{
								title: <?= CJs::encodeJson(_('Ok')); ?>,
								class: 'btn-alt',
								focused: true,
								action: function() {}
							}
						]
					});

					return false;
				}
			}

			pairManager.removeAll(formid, 'post_fields');
			for (var i = 0; i < pairs.length; i++) {
				pairManager.addNew(formid, {
					type: 'post_fields',
					name: pairs[i].name,
					value: pairs[i].value
				});
			}
			pairManager.refresh();
		}
		else {
			var fields = [],
				parts,
				pairs = pairManager.getPairsByType(formid, 'post_fields');

			for (var p = 0; p < pairs.length; p++) {
				parts = [];
				if (pairs[p].name !== '') {
					parts.push(encodeURIComponent(pairs[p].name.replace(/'/g,'%27').replace(/"/g,'%22')));
				}
				if (pairs[p].value !== '') {
					parts.push(encodeURIComponent(pairs[p].value.replace(/'/g,'%27').replace(/"/g,'%22')));
				}
				if (parts.length > 0) {
					fields.push(parts.join('='));
				}
			}

			jQuery('#posts').val(fields.join('&'));
		}

		setPostType(formid, type);
	}

	/**
	 * Parse action for URL field. Parses "URL" field string to "Query fields" pairs.
	 *
	 * @param {string}	formid	Id of current form HTML element.
	 */
	function parseUrl(formid) {
		var target = jQuery('#url', jQuery('#'+formid)),
			url = parseUrlString(target.val());

		if (typeof url === 'object') {
			if (url.pairs.length > 0) {
				pairManager.cleanup(formid, 'query_fields');
				jQuery.each(url.pairs, function(i, pair) {
					pair.type = 'query_fields';
					pairManager.addNew(formid, pair);
				})
			}

			target.val(url.url);
		}
		else {
			overlayDialogue({
				'title': <?= CJs::encodeJson(_('Error')); ?>,
				'content': jQuery('<span>').html(<?=
					CJs::encodeJson(_('Failed to parse URL.').'<br><br>'._('URL is not properly encoded.'));
				?>),
				'buttons': [
					{
						title: <?= CJs::encodeJson(_('Ok')); ?>,
						class: 'btn-alt',
						focused: true,
						action: function() {}
					}
				]
			});

			return false;
		}
	}

	/**
	 * Adds new step to web scenario form with data from popup.
	 *
	 * @param {string}	formname	Web monitoring scenario form name.
	 * @param {obj}		httpstep	Object with web scenario step values.
	 */
	function add_httpstep(formname, httpstep) {
		var form = window.document.forms[formname];
		if (!form) {
			return false;
		}

		add_var_to_opener_obj(form, 'new_httpstep[name]', httpstep.name);
		add_var_to_opener_obj(form, 'new_httpstep[timeout]', httpstep.timeout);
		add_var_to_opener_obj(form, 'new_httpstep[url]', httpstep.url);
		add_var_to_opener_obj(form, 'new_httpstep[posts]', httpstep.posts);
		add_var_to_opener_obj(form, 'new_httpstep[post_type]', httpstep.post_type);
		add_var_to_opener_obj(form, 'new_httpstep[required]', httpstep.required);
		add_var_to_opener_obj(form, 'new_httpstep[status_codes]', httpstep.status_codes);
		add_var_to_opener_obj(form, 'new_httpstep[follow_redirects]', httpstep.follow_redirects);
		add_var_to_opener_obj(form, 'new_httpstep[retrieve_mode]', httpstep.retrieve_mode);

		addPairsToOpenerObject(form, 'new_httpstep', httpstep.pairs);

		form.submit();
		return true;
	}

	/**
	 * Updates existing step in web scenario form with data from popup.
	 *
	 * @param {string}	formname	Web monitoring scenario form name.
	 * @param {string}	list_name	List name.
	 * @param {obj}		httpstep	Object with web scenario step values.
	 */
	function update_httpstep(formname, list_name, httpstep) {
		var prefix,
			form = window.document.forms[formname];

		if (!form) {
			return false;
		}

		prefix = list_name + '[' + httpstep.stepid + ']';

		add_var_to_opener_obj(form, prefix + '[name]', httpstep.name);
		add_var_to_opener_obj(form, prefix + '[timeout]', httpstep.timeout);
		add_var_to_opener_obj(form, prefix + '[url]', httpstep.url);
		add_var_to_opener_obj(form, prefix + '[posts]', httpstep.posts);
		add_var_to_opener_obj(form, prefix + '[post_type]', httpstep.post_type);
		add_var_to_opener_obj(form, prefix + '[required]', httpstep.required);
		add_var_to_opener_obj(form, prefix + '[status_codes]', httpstep.status_codes);
		add_var_to_opener_obj(form, prefix + '[follow_redirects]', httpstep.follow_redirects);
		add_var_to_opener_obj(form, prefix + '[retrieve_mode]', httpstep.retrieve_mode);

		addPairsToOpenerObject(form, prefix, httpstep.pairs);
		form.submit();
		return true;
	}

	/**
	 * Adds step value as hidden field to web scenario form.
	 *
	 * @param {obj}		obj		Web monitoring scenario form name.
	 * @param {string}	name	Field or single pair name.
	 * @param {mixed}	value	Value that will be added for field with given name.
	 */
	function add_var_to_opener_obj(obj, name, value) {
		var input = window.document.createElement('input');

		input.value = value;
		input.type = 'hidden';
		input.name = name;
		obj.appendChild(input);
	}

	/**
	 * Prepares names for web scenario step pairs to be added to web scenario form.
	 * Removes all previous steps, before adding new.
	 *
	 * @param {obj}		obj			Web monitoring scenario form name.
	 * @param {string}	name		Field name.
	 * @param {obj}		stepPairs	Object with web scenario step values.
	 */
	function addPairsToOpenerObject(obj, name, stepPairs) {
		var prefix,
			keys,
			pairs,
			inputs;

		name += '[pairs]';
		inputs = jQuery(window.document).find('input[name^="' + name + '"]');
		for (var i = 0; i < inputs.length; i++) {
			inputs[i].remove();
		}

		pairs = Object.keys(stepPairs);
		for (var i = 0; i < pairs.length; i++) {
			if (!/[0-9]+/.match(pairs[i])) {
				continue;
			}

			var pair = stepPairs[pairs[i]];
			prefix = name + '[' + pair.id + ']';

			// Empty values are ignored.
			if (typeof pair.name === 'undefined'
					|| (typeof pair.isNew !== 'undefined' && pair.name === '' && pair.value === '')) {
				continue;
			}

			keys = Object.keys(pair);
			for (var p = 0; p < keys.length; p++) {
				add_var_to_opener_obj(obj, prefix + '[' + keys[p] + ']', pair[keys[p]]);
			}
		}
	}
</script>
