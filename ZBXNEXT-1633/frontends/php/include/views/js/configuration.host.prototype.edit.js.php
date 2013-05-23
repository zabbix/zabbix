<script type="text/x-jquery-tmpl" id="groupPrototypeRow">
	<tr class="form_row">
		<td>
			<input class="input text" name="group_prototypes[#{i}][name]" type="text" size="30" value="#{name}"
				placeholder="{#MACRO}">
		</td>
		<td>
			<input type="button" class="link_menu group-prototype-remove" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" />
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

		jQuery('#tbl_group_prototypes').on('click', 'input.group-prototype-remove', function() {
			jQuery(this).closest('.form_row').remove();
		});
	});
</script>
