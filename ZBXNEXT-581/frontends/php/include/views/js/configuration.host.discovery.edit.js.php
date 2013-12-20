<?php
include dirname(__FILE__).'/common.item.edit.js.php';
?>
<script type="text/x-jquery-tmpl" id="condition-row">
	<tr class="form_row">
		<td>
			<input class="input text macro" type="text" id="conditions_#{rowNum}_macro"
				name="conditions[#{rowNum}][macro]" size="30" maxlength="64" placeholder="{#MACRO}">
		</td>
		<td>
			<span><?php echo _('matches') ?></span>
		</td>
		<td>
			<input class="input text" type="text" id="conditions_#{rowNum}_value" name="conditions[#{rowNum}][value]"
				size="40" maxlength="255" placeholder="<?php echo _('regular expression') ?>">
		</td>
		<td>
			<input class="input link_menu element-table-remove" type="button" id="conditions_#{rowNum}_remove"
				name="conditions_#{rowNum}_remove" value="<?php echo _('Remove'); ?>">
		</td>
	</tr>
</script>
<script type="text/javascript">
	(function($){
		$(function() {
			$('#conditions')
				.elementTable({
					template: '#condition-row'
				})
				.bind('tableupdate.elementTable', function(event, options) {
					$('#conditionRow').toggleClass('hidden', ($(options.row, $(this)).length <= 1));
				});

			$('#evaltype').change(function() {
				var custom = ($(this).val() == <?php echo ACTION_EVAL_TYPE_EXPRESSION ?>);
				$('#expression').toggleClass('hidden', custom);
				$('#formula').toggleClass('hidden', !custom);
			});
		});
	})(jQuery);
</script>
