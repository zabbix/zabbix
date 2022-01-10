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

include dirname(__FILE__).'/common.item.edit.js.php';
include dirname(__FILE__).'/item.preprocessing.js.php';
include dirname(__FILE__).'/editabletable.js.php';
include dirname(__FILE__).'/itemtest.js.php';
?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		function typeChangeHandler() {
			// Selected item type.
			var type = parseInt($('#type').val(), 10),
				asterisk = '<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>';

			$('#keyButton').prop('disabled',
				type != <?= ITEM_TYPE_ZABBIX ?>
					&& type != <?= ITEM_TYPE_ZABBIX_ACTIVE ?>
					&& type != <?= ITEM_TYPE_SIMPLE ?>
					&& type != <?= ITEM_TYPE_INTERNAL ?>
					&& type != <?= ITEM_TYPE_DB_MONITOR ?>
					&& type != <?= ITEM_TYPE_SNMPTRAP ?>
					&& type != <?= ITEM_TYPE_JMX ?>
					&& type != <?= ITEM_TYPE_IPMI ?>
			)

			if (type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>) {
				$('label[for=username]').addClass(asterisk);
				$('input[name=username]').attr('aria-required', 'true');
			}
			else {
				$('label[for=username]').removeClass(asterisk);
				$('input[name=username]').removeAttr('aria-required');
			}
		}

		// Field switchers.
		new CViewSwitcher('value_type', 'change', item_form.field_switches.for_value_type);

		$('#type')
			.change(typeChangeHandler)
			.trigger('change');

		// Whenever non-numeric type is changed back to numeric type, set the default value in "trends" field.
		$('#value_type')
			.change(function() {
				var new_value = $(this).val(),
					old_value = $(this).data('old-value'),
					trends = $('#trends');

				if ((old_value == <?= ITEM_VALUE_TYPE_STR ?> || old_value == <?= ITEM_VALUE_TYPE_LOG ?>
						|| old_value == <?= ITEM_VALUE_TYPE_TEXT ?>)
						&& (new_value == <?= ITEM_VALUE_TYPE_FLOAT ?>
						|| new_value == <?= ITEM_VALUE_TYPE_UINT64 ?>)) {
					if (trends.val() == 0) {
						trends.val('<?= $data['trends_default'] ?>');
					}

					$('#trends_mode_1').prop('checked', true);
				}

				$('#trends_mode').trigger('change');
				$(this).data('old-value', new_value);
			})
			.data('old-value', $('#value_type').val());

		$('#history_mode')
			.change(function() {
				if ($('[name="history_mode"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
					$('#history').prop('disabled', true).hide();
				}
				else {
					$('#history').prop('disabled', false).show();
				}
			})
			.trigger('change');

		$('#trends_mode')
			.change(function() {
				if ($('[name="trends_mode"][value=' + <?= ITEM_STORAGE_OFF ?> + ']').is(':checked')) {
					$('#trends').prop('disabled', true).hide();
				}
				else {
					$('#trends').prop('disabled', false).show();
				}
			})
			.trigger('change');
	});
</script>
