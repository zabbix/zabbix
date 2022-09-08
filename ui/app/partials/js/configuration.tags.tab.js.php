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
 * @var CPartial $this
 * @var array    $data
 */
?>

<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= renderTagTableRow('#{rowNum}', '', '', ZBX_TAG_MANUAL, [
		'add_post_js' => false,
		'with_automatic' => array_key_exists('with_automatic', $data) && $data['with_automatic']
	]) ?>
</script>

<script type="text/javascript">
	jQuery(function() {
		const on_tab_create_activate = (event, ui) => {
			const $panel = event.type === 'tabscreate' ? ui.panel : ui.newPanel;

			if ($panel.attr('id') === '<?= $data['tags_tab_id'] ?>') {
				$('#<?= $data['tabs_id'] ?>').off('tabscreate.tags-tab tabsactivate.tags-tab', on_tab_create_activate);

				const $table = $panel.find('.tags-table');

				$table
					.dynamicRows({template: '#tag-row-tmpl'})
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
		};

		$('#<?= $data['tabs_id'] ?>').on('tabscreate.tags-tab tabsactivate.tags-tab', on_tab_create_activate);
	});
</script>
