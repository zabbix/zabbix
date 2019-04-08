<script type="text/javascript">
	jQuery(function($) {
		function changeTargetType(data) {
			var $multiselect = $('<div>', {
					id: 'copy_targetids',
					class: 'multiselect',
					css: {
						width: '<?= ZBX_TEXTAREA_MEDIUM_WIDTH ?>px'
					},
					'aria-required': true
				}),
				helper_options = {
					id: 'copy_targetids',
					name: 'copy_targetids[]',
					data: data.length ? data : [],
					objectOptions: {
						editable: true
					},
					popup: {
						parameters: {
							dstfrm: '<?= $form->getName() ?>',
							dstfld1: 'copy_targetids',
							writeonly: 1,
							multiselect: 1
						}
					}
				};

			switch ($('#copy_type').find('input[name=copy_type]:checked').val()) {
				case '<?= COPY_TYPE_TO_HOST_GROUP ?>':
					helper_options.objectName = 'hostGroup';
					helper_options.popup.parameters.srctbl = 'host_groups';
					helper_options.popup.parameters.srcfld1 = 'groupid';
					break;
				case '<?= COPY_TYPE_TO_HOST ?>':
					helper_options.objectName = 'hosts';
					helper_options.popup.parameters.srctbl = 'hosts';
					helper_options.popup.parameters.srcfld1 = 'hostid';
					break;
				case '<?= COPY_TYPE_TO_TEMPLATE ?>':
					helper_options.objectName = 'templates';
					helper_options.popup.parameters.srctbl = 'templates';
					helper_options.popup.parameters.srcfld1 = 'hostid';
					helper_options.popup.parameters.srcfld2 = 'host';
					break;
			}

			$('#copy_targets').html($multiselect);

			$multiselect.multiSelectHelper(helper_options);
		}

		$('#copy_type').on('change', changeTargetType);

		changeTargetType(<?= CJs::encodeJson($data['copy_targetids']) ?>);
	});
</script>
