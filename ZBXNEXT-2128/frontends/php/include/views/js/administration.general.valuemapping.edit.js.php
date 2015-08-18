<script type="text/x-jquery-tmpl" id="mappingRow">
	<tr>
		<td>
			<input type="text" name="mappings[#{mappingNum}][value]" value="#{value}" style="width: <?= ZBX_TEXTAREA_SMALL_WIDTH ?>px" maxlength="64">
		</td>
		<td>&rArr;</td>
		<td>
			<input type="text" name="mappings[#{mappingNum}][newvalue]" value="#{newvalue}" style="width: <?= ZBX_TEXTAREA_SMALL_WIDTH ?>px" maxlength="64">
		</td>
		<td>
			<button class="<?= ZBX_STYLE_BTN_LINK ?> removeMapping" type="button"><?= _('Remove') ?></button>
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
				jQuery('#mappingsTable tr:last-child')
					.prev()
					.find('td')
					.first()
					.append('<input type="hidden" name="mappings[' + mapping.mappingNum + '][mappingid]" value="' + mapping.mappingid + '">');
			}

			mappingsCount++;
			toggleSaveButton();
		}

		function toggleSaveButton() {
			if (mappingsCount === 0) {
				jQuery('#save').button('disable');
			}
			else if (mappingsCount === 1) {
				jQuery('#save').button('enable');
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
		};
	}());

	jQuery(document).ready(function() {
		jQuery('#save').button();
		jQuery('#addMapping').click(mappingsManager.addNew);
		jQuery('#mappingsTable tbody').on('click', '.removeMapping', mappingsManager.remove);
	});
</script>
