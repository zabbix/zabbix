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

<script type="text/x-jquery-tmpl" id="filter-inventory-row">
	<?= (new CRow([
			new CComboBox('filter_inventory[#{rowNum}][field]', null, null, $data['filter']['inventories']),
			(new CTextBox('filter_inventory[#{rowNum}][value]'))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('filter_inventory[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= (new CRow([
			(new CTextBox('filter_tags[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('filter_tags[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
				->addValue(_('Contains'), TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
				->setModern(true),
			(new CTextBox('filter_tags[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
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
		$(function() {
			$('#filter-inventory').dynamicRows({template: '#filter-inventory-row'});
			$('#filter-tags').dynamicRows({template: '#filter-tag-row-tmpl'});
		});

		$('#filter_show').change(function() {
			var	filter_show = jQuery('input[name=filter_show]:checked').val();

			$('#filter_age').closest('li').toggle(filter_show == <?= TRIGGERS_OPTION_RECENT_PROBLEM ?>
				|| filter_show == <?= TRIGGERS_OPTION_IN_PROBLEM ?>);
		});

		$('#filter_show').trigger('change');

		$('#filter_compact_view').change(function() {
			if ($(this).is(':checked')) {
				$('#filter_show_timeline, #filter_details').prop('disabled', true);
				$('input[name=filter_show_opdata]').prop('disabled', true);
				$('#filter_highlight_row').prop('disabled', false);
			}
			else {
				$('#filter_show_timeline, #filter_details').prop('disabled', false);
				$('input[name=filter_show_opdata]').prop('disabled', false);
				$('#filter_highlight_row').prop('disabled', true);
			}
		});

		$('#filter_show_tags').change(function() {
			var disabled = $(this).find('[value = "<?= PROBLEMS_SHOW_TAGS_NONE ?>"]').is(':checked');
			$('#filter_tag_priority').prop('disabled', disabled);
			$('#filter_tag_name_format input').prop('disabled', disabled);
		});

		$(document).on({
			mouseenter: function() {
				if ($(this)[0].scrollWidth > $(this)[0].offsetWidth) {
					$(this).attr({title: $(this).text()});
				}
			},
			mouseleave: function() {
				if ($(this).is('[title]')) {
					$(this).removeAttr('title');
				}
			}
		}, 'table.<?= ZBX_STYLE_COMPACT_VIEW ?> a.<?= ZBX_STYLE_LINK_ACTION ?>');

		$.subscribe('acknowledge.create', function(event, response) {
			// Clear all selected checkboxes in Monitoring->Problems.
			if (chkbxRange.prefix === 'problem') {
				chkbxRange.checkObjectAll(chkbxRange.pageGoName, false);
				chkbxRange.clearSelectedOnFilterChange();
			}

			window.flickerfreeScreen.refresh('problem');

			clearMessages();
			addMessage(makeMessageBox('good', [], response.message, true, false));
		});

		$(document).on('submit', '#problem_form', function(e) {
			e.preventDefault();

			var eventids = $('[id^="eventids_"]:checked', $(this)).map(function() {
					return $(this).val();
				}).get();

			acknowledgePopUp({eventids: eventids}, this);
		});
	});
</script>
