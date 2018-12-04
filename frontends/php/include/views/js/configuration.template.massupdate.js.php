<script type="text/x-jquery-tmpl" id="tag-row">
	<?= renderTagTableRow('tags', '#{rowNum}') ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		<?php if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN): ?>
			$('input[name=mass_update_groups]').on('change', function() {
				$('#groups_').multiSelect('setOption', 'addNew',
					(this.value == '<?php echo ZBX_MASSUPDATE_ACTION_ADD ?>'
						|| this.value == '<?php echo ZBX_MASSUPDATE_ACTION_REPLACE ?>')
				);
			});
		<?php endif ?>

		$('#tbl-tags').dynamicRows({
			template: '#tag-row'
		});

		$('#mass_replace_tpls').on('change', function() {
			$('#mass_clear_tpls').prop('disabled', !this.checked);
		}).change();
	});
</script>
