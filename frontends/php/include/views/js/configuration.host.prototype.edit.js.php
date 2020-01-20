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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


?>
<script type="text/x-jquery-tmpl" id="groupPrototypeRow">
	<tr class="form_row">
		<td>
			<input name="group_prototypes[#{i}][name]" type="text" value="#{name}" style="width: <?= ZBX_TEXTAREA_STANDARD_WIDTH ?>px" placeholder="{#MACRO}" maxlength="255" />
		</td>
		<td class="<?= ZBX_STYLE_NOWRAP ?>">
			<button class="<?= ZBX_STYLE_BTN_LINK ?> group-prototype-remove" type="button" name="remove"><?= _('Remove') ?></button>
			<input type="hidden" name="group_prototypes[#{i}][group_prototypeid]" value="#{group_prototypeid}" />
		</td>
	</tr>
</script>

<script type="text/javascript">
	function addGroupPrototypeRow(groupPrototype) {
		var addButton = jQuery('#group_prototype_add');

		var rowTemplate = new Template(jQuery('#groupPrototypeRow').html());
		groupPrototype.i = addButton.data('group-prototype-count');
		jQuery('#row_new_group_prototype').before(rowTemplate.evaluate(groupPrototype));

		addButton.data('group-prototype-count', addButton.data('group-prototype-count') + 1);
	}

	jQuery(function() {
		jQuery('#group_prototype_add')
			.data('group-prototype-count', jQuery('#tbl_group_prototypes').find('.group-prototype-remove').length)
			.click(function() {
				addGroupPrototypeRow({})
			});

		jQuery('#tbl_group_prototypes').on('click', '.group-prototype-remove', function() {
			jQuery(this).closest('.form_row').remove();
		});

		<?php if (!$hostPrototype['groupPrototypes']): ?>
			addGroupPrototypeRow({'name': '', 'group_prototypeid': ''});
		<?php endif ?>
		<?php foreach ($hostPrototype['groupPrototypes'] as $i => $groupPrototype): ?>
			addGroupPrototypeRow(<?= CJs::encodeJson([
				'name' => $groupPrototype['name'],
				'group_prototypeid' => isset($groupPrototype['group_prototypeid']) ? $groupPrototype['group_prototypeid'] : null
			]) ?>);
		<?php endforeach ?>

		<?php if ($hostPrototype['templateid']): ?>
			jQuery('#tbl_group_prototypes').find('input').prop('readonly', true);
			jQuery('#tbl_group_prototypes').find('button').prop('disabled', true);
		<?php endif ?>
	});
</script>
