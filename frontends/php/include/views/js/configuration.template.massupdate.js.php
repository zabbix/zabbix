<script type="text/x-jquery-tmpl" id="tag-row">
	<?= renderTagTableRow('#{rowNum}') ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		<?php if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN): ?>
			$('input[name=mass_update_groups]').on('change', function() {
				$('#groups_').multiSelect('setOption', 'addNew',
					(this.value == <?= ZBX_MASSUPDATE_ACTION_ADD ?>
						|| this.value == <?= ZBX_MASSUPDATE_ACTION_REPLACE ?>)
				);
			});
		<?php endif ?>

		$('#tags-table').dynamicRows({
			template: '#tag-row'
		});

		$('#mass_replace_tpls').on('change', function() {
			$('#mass_clear_tpls').prop('disabled', !this.checked);
		}).trigger('change');
	});
</script>
