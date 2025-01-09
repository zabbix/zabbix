<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CPartial $this
 * @var array    $data
 */
?>

<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= renderTagTableRow('#{rowNum}', ['tag' => '', 'value' => ''], [
		'add_post_js' => false,
		'with_automatic' => array_key_exists('with_automatic', $data) && $data['with_automatic']
	]) ?>
</script>

<script type="text/javascript">
	jQuery(function() {
		const tabsEventHandler = (event, ui) => {
			const $panel = event.type === 'tabscreate' ? ui.panel : ui.newPanel;

			if ($panel.is('#<?= $data['tags_tab_id'] ?>')) {
				$('#<?= $data['tabs_id'] ?>').off('tabscreate.tags-tab tabsactivate.tags-tab', tabsEventHandler);
				bindTagsTableEvents($panel);
			}
		};
		const bindTagsTableEvents = ($panel) => {
			const $table = $panel.find('.tags-table');

			$table
				.dynamicRows({template: '#tag-row-tmpl', allow_empty: true})
				.on('afteradd.dynamicRows', () => {
					$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', $table).textareaFlexible();
				})
				.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
				.textareaFlexible();
			$table.on('click', '.element-table-disable', (e) => {
				const type_input = e.target.closest('.form_row').querySelector('input[name$="[type]"]');

				type_input.value &= ~<?= ZBX_PROPERTY_OWN ?>;
			});
		}
		const tags_tab = $('#<?= $data['tags_tab_id'] ?>[aria-hidden="false"]');

		if (tags_tab.length) {
			bindTagsTableEvents(tags_tab);
		}
		else {
			$('#<?= $data['tabs_id'] ?>').on('tabscreate.tags-tab tabsactivate.tags-tab', tabsEventHandler);
		}
	});
</script>
