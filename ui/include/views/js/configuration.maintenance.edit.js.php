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

<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= (new CRow([
		(new CTextBox('tags[#{rowNum}][tag]'))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CRadioButtonList('tags[#{rowNum}][operator]', MAINTENANCE_TAG_OPERATOR_LIKE))
			->addValue(_('Contains'), MAINTENANCE_TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), MAINTENANCE_TAG_OPERATOR_EQUAL)
			->setModern(true),
		(new CTextBox('tags[#{rowNum}][value]'))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('tags[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	]))
		->addClass('form_row')
		->toString()
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#maintenance_type').change(function() {
			var maintenance_type = $('input[name=maintenance_type]:checked', $(this)).val();
			if (maintenance_type == <?= MAINTENANCE_TYPE_NODATA ?>) {
				$('#tags input, #tags button').prop('disabled', true);
				$('#tags input[name$="[tag]"], #tags input[name$="[value]"]').removeAttr('placeholder');
			}
			else {
				$('#tags input, #tags button').prop('disabled', false);
				$('#tags input[name$="[tag]"]').attr('placeholder', <?= json_encode(_('tag')) ?>);
				$('#tags input[name$="[value]"]').attr('placeholder', <?= json_encode(_('value')) ?>);
			}
		});

		$('#tags').dynamicRows({template: '#tag-row-tmpl'});

		// Maintenance periods.
		$('#maintenance_periods').on('click', '[data-action]', function() {
			var button = $(this),
				rows = $('#maintenance_periods table > tbody > tr');

			switch (button.data('action')) {
				case 'remove':
					button.closest('tr').remove();
					break;

				case 'edit':
					var row = button.closest('tr');
					var parameters = {
						update: 1,
						index: row.find('[type="hidden"]:first').attr('name').match(/\[(\d+)\]/)[1]
					};

					row.find('input[type="hidden"]').each(function() {
						var $input = $(this),
							name = $input.attr('name').match(/\[([^\]]+)]$/);

						if (name) {
							parameters[name[1]] = $input.val();
						}
					});

					PopUp('popup.maintenance.period', parameters, {
						dialogue_class: 'modal-popup-medium',
						trigger_element: this
					});
					break;

				case 'add':
					var index = 0;

					rows.each(function(_, row) {
						index = Math.max(index,
							parseInt($(this).find('[type="hidden"]:first').attr('name').match(/\[(\d+)\]/)[1])
						);
					});

					PopUp('popup.maintenance.period', {index: index + 1}, {
						dialogue_class: 'modal-popup-medium',
						trigger_element: this
					});
					break;
			}
		});
	});
</script>
