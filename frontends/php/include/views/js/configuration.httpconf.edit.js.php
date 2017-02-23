<script type="text/x-jquery-tmpl" id="scenarioPairRow">
<tr class="pairRow sortable" id="pairRow_#{pair.id}" data-pairid="#{pair.id}">
	<td class="pair-drag-control <?= ZBX_STYLE_TD_DRAG_ICON ?>">
		<div class="<?= ZBX_STYLE_DRAG_ICON ?>"></div>
		<input type="hidden" name="pairs[#{pair.id}][isNew]" value="#{pair.isNew}">
		<input type="hidden" name="pairs[#{pair.id}][id]" value="#{pair.id}">
		<input type="hidden" id="pair_type_#{pair.id}" name="pairs[#{pair.id}][type]" value="#{pair.type}">
	</td>

	<td>
		<input type="text" id="pair_name_#{pair.id}" name="pairs[#{pair.id}][name]" data-type="name" value="#{pair.name}" maxlength="255" style="width: <?= ZBX_TEXTAREA_TAG_WIDTH ?>px" placeholder="<?= _('name') ?>">
	</td>

	<td> = </td>

	<td>
		<input type="text" id="pair_value_#{pair.id}" name="pairs[#{pair.id}][value]" data-type="value" value="#{pair.value}" style="width: <?= ZBX_TEXTAREA_TAG_WIDTH ?>px" placeholder="<?= _('value') ?>">
	</td>

	<td class="<?= ZBX_STYLE_NOWRAP ?> pair-control">
		<button class="<?= ZBX_STYLE_BTN_LINK ?> remove" type="button" id="removePair_#{pair.id}" data-pairid="#{pair.id}"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/javascript">
	var pairManager = (function() {
		'use strict';

		var rowTemplate = new Template(jQuery('#scenarioPairRow').html()),
			allPairs = {};

		function updatePairControls(pair) {
			var parent = jQuery('#pairRow_' + pair.id);
			if (pair.isNew === true && pair.name === '' && pair.value === '') {
				parent.find("button").attr('disabled','disabled');
			}
			else {
				parent.find("button").removeAttr('disabled');
			}
		}

		function renderPairRow(pair) {
			var parent,
				domId = getDomTargetIdForRowInsert(pair.type);

			jQuery(domId).before(rowTemplate.evaluate({'pair': pair}));
			parent = jQuery('#pairRow_' + pair.id);
			parent.find("input[data-type]").on('change', function() {
				var	target = jQuery(this),
					parent = target.parents('.pairRow'),
					id = parent.data('pairid'),
					pair = allPairs[id],
					value = target.val().trim();

				target.val(value);
				pair[target.data('type')] = value;
				updatePairControls(pair);
				allPairs[id] = pair;
			});
		}

		function getDomTargetIdForRowInsert(type) {
			return '#' + type.toLowerCase().trim() + '_footer';
		}

		function addPair(pair) {
			if ('true' === pair.isNew) {
				pair.isNew = true;
			}
			allPairs[pair.id] = pair;
			return pair;
		}

		function createNewPair(typeName) {
			var newPair = {
				isNew: true,
				type: typeName,
				name: '',
				value: ''
			};

			newPair.id = 1;
			while (allPairs[newPair.id] !== void(0)) {
				newPair.id++;
			}

			return addPair(newPair);
		}

		function refreshContainers() {
			jQuery('.pair-container').each(function() {
				jQuery(this).sortable( {
					disabled: (jQuery(this).find('tr.sortable').length < 2)
				} );

				var rows = jQuery(this).find('.pairRow').length;
				if (0 === rows) {
					renderPairRow(createNewPair(this.id));
				}

				if (1 >= rows) {
					updatePairControls(allPairs[jQuery(this).find('.pairRow').data('pairid')]);
				}
				else {
					jQuery(this).find('button').removeAttr('disabled');
				}
			});
		}

		function moveRowToAnotherTypeTable(pairId, type) {
			jQuery('#pair_type_' + pairId).val(type);
			jQuery('#pairRow_' + pairId).insertBefore(getDomTargetIdForRowInsert(type));

			refreshContainers();
		}

		return {
			add: function(pairs) {
				for (var i = 0; i < pairs.length; i++) {
					renderPairRow(addPair(pairs[i]));
				}

				refreshContainers();
			},

			addNew: function(type) {
				renderPairRow(createNewPair(type));
				refreshContainers();
			},

			remove: function(pairId) {
				delete allPairs[pairId];
				refreshContainers();
			},

			setType: function(pairId, type) {
				if (allPairs[pairId].type !== type) {
					moveRowToAnotherTypeTable(pairId, type);
					allPairs[pairId].type = type;
				}
			},

			refresh: function() {
				refreshContainers();
			}
		};
	}());

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#httpFormList').on('click', 'button.remove', function() {
			var pairId = jQuery(this).data('pairid');
			jQuery('#pairRow_' + pairId).remove();
			pairManager.remove(pairId);
		});

		jQuery('.pair-container').sortable({
			disabled: (jQuery(this).find('tr.sortable').length < 2),
			items: 'tr.sortable',
			axis: 'y',
			cursor: 'move',
			containment: 'parent',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			helper: function(e, ui) {
				/*ui.children().each(function() {
					var td = $(this);

					td.width(td.width());
				});*/

				return ui;
			},
			start: function(e, ui) {
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		jQuery('.pairs-control-add').on('click', function() {
			pairManager.addNew(jQuery(this).data('type'));
		});
	});

	function removeStep(obj) {
		var step = obj.getAttribute('remove_step'),
			table = jQuery('#httpStepTable');

		jQuery('#steps_' + step).remove();

		jQuery('input[id^=steps_' + step + '_]').each( function(item) {
			item.remove();
		});

		if (table.find('tr.sortable').length <= 1) {
			table.sortable('disable');
		}

		recalculateSortOrder();
	}

	function recalculateSortOrder() {
		var i = 0;

		jQuery('#httpStepTable tr.sortable .rowNum').each(function() {
			var step = (i == 0) ? '0' : i;

			// rewrite ids to temp
			jQuery('#remove_' + step).attr('id', 'tmp_remove_' + step);
			jQuery('#name_' + step).attr('id', 'tmp_name_' + step);
			jQuery('#steps_' + step).attr('id', 'tmp_steps_' + step);
			jQuery('#current_step_' + step).attr('id', 'tmp_current_step_' + step);

			jQuery('input[id^=steps_' + step + '_]').each( function() {
				var	input = jQuery(this),
					id = input.attr('id').replace(/^steps_[0-9]+_/, 'tmp_steps_' + step + '_');

				input.attr('id', id);
			});

			// set order number
			jQuery(this)
				.attr('new_step', i)
				.text((i + 1) + ':');
			i++;
		});

		// rewrite ids in new order
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

			// set new step order position
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
				// force not to change table width
				stepTable.width(stepTableWidth);
			},
			helper: function(e, ui) {
				ui.children().each(function(i) {
					var td = $(this);

					td.width(stepTableColumnWidths[i]);
				});

				// when dragging element on safari, it jumps out of the table
				if (SF) {
					// move back draggable element to proper position
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				stepTableColumns.each(function(i) {
					$(this).width(stepTableColumnWidths[i]);
				});

				return ui;
			},
			start: function(e, ui) {
				// fix placeholder not to change height while object is being dragged
				$(ui.placeholder).height($(ui.helper).height());
			}
		});

		// http step add pop up
		<?php if (!$this->data['templated']) : ?>
			$('#add_step').click(function() {
				var form = $(this).parents('form');

				// append existing step names
				var stepNames = '';
				form.find('input[name^=steps]').filter('input[name*=name]:not([name*=pairs])').each(function(i, stepName) {
					stepNames += '&steps_names[]=' + encodeURIComponent($(stepName).val());
				});

				return PopUp('popup_httpstep.php?dstfrm=httpForm' + stepNames);
			});
		<?php endif ?>

		// http step edit pop up
		<?php foreach ($this->data['steps'] as $i => $step): ?>
			$('#name_<?= $i ?>').click(function() {
				// append existing step names
				var stepNames = '';
				var form = $(this).parents('form');
				form.find('input[name^=steps]').filter('input[name*=name]:not([name*=pairs])').each(function(i, stepName) {
					stepNames += '&steps_names[]=' + encodeURIComponent($(stepName).val());
				});

				return PopUp('popup_httpstep.php?dstfrm=httpForm&templated=<?= $this->data['templated'] ?>'
					+ '&list_name=steps&stepid=' + jQuery(this).attr('name_step')
					+ '<?= url_param($step['name'], false, 'name') ?>'
					+ '<?= url_param($step['url'], false, 'url') ?>'
					+ '<?= url_param($step['posts'], false, 'posts') ?>'
					+ '<?= url_param($step['post_type'], false, 'post_type') ?>'
					+ '<?= url_param(array_key_exists('pairs', $step)?$step['pairs']:[], false, 'pairs') ?>'
					+ '<?= url_param($step['timeout'], false, 'timeout') ?>'
					+ '<?= url_param($step['required'], false, 'required') ?>'
					+ '<?= url_param($step['status_codes'], false, 'status_codes') ?>'
					+ '<?= url_param($step['name'], false, 'old_name') ?>'
					+ '<?= url_param($step['retrieve_mode'], false, 'retrieve_mode') ?>'
					+ '<?= url_param($step['follow_redirects'], false, 'follow_redirects') ?>'
					+ stepNames);
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
</script>
