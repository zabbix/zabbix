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

<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#description')
			.on('input keydown paste', function() {
				$('#event_name').attr('placeholder', $(this).val());
			})
			.trigger('input');

		// Refresh field visibility on document load.
		changeRecoveryMode();
		changeCorrelationMode();

		$('input[name=recovery_mode]').change(function() {
			changeRecoveryMode();
		});

		$('input[name=correlation_mode]').change(function() {
			changeCorrelationMode();
		});

		function changeRecoveryMode() {
			var	recovery_mode = $('input[name=recovery_mode]:checked').val();

			$('#expression_row').find('label').text(
				(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					? <?= json_encode(_('Problem expression')) ?>
					: <?= json_encode(_('Expression')) ?>
			);
			$('.recovery_expression_constructor_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>);
			$('#correlation_mode_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>
				);

			changeCorrelationMode();
		}

		function changeCorrelationMode() {
			var	recovery_mode = $('input[name=recovery_mode]:checked').val(),
				correlation_mode = $('input[name=correlation_mode]:checked').val();

			$('#correlation_tag_row')
				.toggle((recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					&& correlation_mode == <?= ZBX_TRIGGER_CORRELATION_TAG ?>
				);
		}

		let triggers_initialized = false;
		$('#tabs').on('tabscreate tabsactivate', function(event, ui) {
			const panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

			if (panel.attr('id') === 'triggersTab') {
				if (triggers_initialized) {
					return;
				}

				$('#triggersTab')
					.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
					.textareaFlexible();

				triggers_initialized = true;
			}
		});
	});

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		if (!isset('object', list)) {
			return false;
		}

		if (list.object == 'deptrigger') {
			for (var i = 0; i < list.values.length; i++) {
				create_var('triggersForm', 'new_dependency[' + i + ']', list.values[i].triggerid, false);
			}

			// return to the same form after it has been submitted
			jQuery('#action').val(<?= json_encode(getRequest('action')) ?>);

			create_var('triggersForm', 'add_dependency', 1, true);
		}
	}

	function removeDependency(triggerid) {
		jQuery('#dependency_' + triggerid).remove();
		jQuery('#dependencies_' + triggerid).remove();
	}
</script>
