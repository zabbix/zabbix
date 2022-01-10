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
 * @var CView $this
 */
?>

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
					helper_options.object_name = 'hostGroup';
					helper_options.popup.parameters.srctbl = 'host_groups';
					helper_options.popup.parameters.srcfld1 = 'groupid';
					break;
				case '<?= COPY_TYPE_TO_HOST ?>':
					helper_options.object_name = 'hosts';
					helper_options.popup.parameters.srctbl = 'hosts';
					helper_options.popup.parameters.srcfld1 = 'hostid';
					break;
				case '<?= COPY_TYPE_TO_TEMPLATE ?>':
					helper_options.object_name = 'templates';
					helper_options.popup.parameters.srctbl = 'templates';
					helper_options.popup.parameters.srcfld1 = 'hostid';
					helper_options.popup.parameters.srcfld2 = 'host';
					break;
			}

			$('#copy_targets').html($multiselect);

			$multiselect.multiSelectHelper(helper_options);
		}

		$('#copy_type').on('change', changeTargetType);

		changeTargetType(<?= json_encode($data['copy_targetids']) ?>);
	});
</script>
