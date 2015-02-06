<script type="text/x-jquery-tmpl" id="mappingRow">

	<tr>
		<td>
			<input class="input" type="text" name="mappings[#{mappingNum}][value]" value="#{value}" size="20" maxlength="64"
					title="<?php echo CHtml::encode(_('Only whole numbers')); ?>" pattern="-?[0-9]+"
					placeholder="<<?php echo CHtml::encode(_('whole number')); ?>>">
		</td>
		<td>&rArr;</td>
		<td>
			<input class="input" type="text" name="mappings[#{mappingNum}][newvalue]" value="#{newvalue}" size="30" maxlength="64">
		</td>
		<td>
			<input class="input link_menu removeMapping" type="button" value="<?php echo CHtml::encode(_('Remove')); ?>">
		</td>
	</tr>

</script>


<script type="text/javascript">
	var mappingsManager = (function() {

		var tpl = new Template(jQuery('#mappingRow').html()),
			mappingsCount = 0,
			nextMappingNum = 0;

		function renderMappingRow(mapping) {
			mapping.mappingNum = nextMappingNum++;
			jQuery(tpl.evaluate(mapping)).insertBefore('#mappingsTable tr:last-child');

			if (mapping.mappingid !== void(0)) {
				jQuery('#mappingsTable tr:last-child').prev().find('td').first()
						.append('<input type="hidden" name="mappings[' + mapping.mappingNum + '][mappingid]" value="' + mapping.mappingid + '">');
			}

			mappingsCount++;
			toggleSaveButton();
		}

		function toggleSaveButton() {
			if (mappingsCount === 0) {
				jQuery('#save').prop('disabled', true).button('refresh');
			}
			else if (mappingsCount === 1) {
				jQuery('#save').prop('disabled', false).button('refresh');
			}
		}

		return {
			addNew: function() {
				renderMappingRow({});
				toggleSaveButton();
			},

			addExisting: function(mappings) {
				for (var i = 0, ln = mappings.length; i < ln; i++) {
					renderMappingRow(mappings[i]);
				}
			},

			remove: function() {
				jQuery(this).closest('tr').remove();

				mappingsCount--;
				toggleSaveButton();
			}
		}
	}());

	jQuery(document).ready(function() {
		'use strict';

		jQuery('#addMapping').click(mappingsManager.addNew);
		jQuery('#mappingsTable tbody').on('click', 'input.removeMapping', mappingsManager.remove);
	});

</script>
