<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= (new CRow([
			(new CTextBox('filter_tags[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CSelect('filter_tags[#{rowNum}][operator]'))
				->addOptions(CSelect::createOptionsFromArray([
					TAG_OPERATOR_EXISTS => _('Exists'),
					TAG_OPERATOR_EQUAL => _('Equals'),
					TAG_OPERATOR_LIKE => _('Contains'),
					TAG_OPERATOR_NOT_EXISTS => _('Does not exist'),
					TAG_OPERATOR_NOT_EQUAL => _('Does not equal'),
					TAG_OPERATOR_NOT_LIKE => _('Does not contain')
				]))
				->setValue(TAG_OPERATOR_LIKE)
				->setFocusableElementId('filter-tags-#{rowNum}-operator-select')
				->setId('filter_tags_#{rowNum}_operator'),
			(new CTextBox('filter_tags[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setId('filter_tags_#{rowNum}_value'),
			(new CCol(
				(new CButton('filter_tags[#{rowNum}][remove]', _('Remove')))
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
		$('#filter-tags')
			.dynamicRows({template: '#filter-tag-row-tmpl'})
			.on('afteradd.dynamicRows', function() {
				// Hide tag value field if operator is "Exists" or "Does not exist". Show tag value field otherwise.
				$(this)
					.find('z-select')
					.on('change', function() {
						var num = this.id.match(/filter_tags_(\d+)_operator/);

						if (num !== null) {
							$('#filter_tags_' + num[1] + '_value').toggle($(this).val() != <?= TAG_OPERATOR_EXISTS ?>
									&& $(this).val() != <?= TAG_OPERATOR_NOT_EXISTS ?>
							);
						}
					});
			});

		// Hide tag value field if operator is "Exists" or "Does not exist". Show tag value field otherwise.
		$('#filter-tags z-select')
			.on('change', function() {
				var num = this.id.match(/filter_tags_(\d+)_operator/);

				if (num !== null) {
					$('#filter_tags_' + num[1] + '_value').toggle($(this).val() != <?= TAG_OPERATOR_EXISTS ?>
							&& $(this).val() != <?= TAG_OPERATOR_NOT_EXISTS ?>
					);
				}
			})
			.trigger('change');

		$('#filter_state')
			.on('change', function() {
				$('input[name=filter_status]').prop('disabled', $('input[name=filter_state]:checked').val() != -1);
			})
			.trigger('change');
	});
</script>
