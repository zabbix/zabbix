<script type="text/x-jquery-tmpl" id="groupPrototypeRow">
	<tr class="form_row">
		<td>
			<input class="input text" name="new_group_prototypes[#{i}][name]" type="text" size="30" value="#{name}"
				placeholder="{#MACRO}">
		</td>
		<td>
			<input type="button" class="link_menu group-prototype-remove" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" />
		</td>
	</tr>
</script>

<script type="text/javascript">
	jQuery(function() {
		'use strict';

		jQuery('#group_prototype_add')
			.data('group-prototype-count', jQuery('#tbl_group_prototypes').find('.group-prototype-remove').length)
			.click(function() {
				var e = jQuery(this);

				var rowTemplate = new Template(jQuery('#groupPrototypeRow').html());
				jQuery('#row_new_group_prototype').before(rowTemplate.evaluate({
					i: e.data('group-prototype-count')
				}));

				e.data('group-prototype-count', e.data('group-prototype-count') + 1);
			});

		jQuery('#tbl_group_prototypes').on('click', 'input.group-prototype-remove', function() {
			jQuery(this).closest('.form_row').remove();
		});
	});
</script>
