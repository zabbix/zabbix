<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

<script type="text/x-jquery-tmpl" id="tag-row-tmpl">
	<?= renderTagTableRow('#{rowNum}', '', '', ['add_post_js' => false]) ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		<?php if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN): ?>
			$('input[name=mass_update_groups]').on('change', function() {
				$('#groups_').multiSelect('modify', {
					'addNew': ($(this).val() == <?= ZBX_ACTION_ADD ?> || $(this).val() == <?= ZBX_ACTION_REPLACE ?>)
				});
			});
		<?php endif ?>

		$('#tags-table')
			.dynamicRows({template: '#tag-row-tmpl'})
			.on('click', 'button.element-table-add', function() {
				$('#tags-table .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
			});

		var mass_action_tpls = $('#mass_action_tpls'),
			mass_clear_tpls = $('#mass_clear_tpls');

		mass_action_tpls.on('change', function() {
			var action = mass_action_tpls.find('input[name="mass_action_tpls"]:checked').val();
			mass_clear_tpls.prop('disabled', action === '<?= ZBX_ACTION_ADD ?>');
		}).trigger('change');

		$('#inventory_mode').on('change', function() {
			$('.formrow-inventory').toggle($(this).val() !== '<?php echo HOST_INVENTORY_DISABLED; ?>');
		}).trigger('change');

		$('#tls_connect, #tls_in_psk, #tls_in_cert').on('change', function() {
			// If certificate is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| $('#tls_in_cert').is(':checked')) {
				$('#tls_issuer, #tls_subject').closest('tr').show();
			}
			else {
				$('#tls_issuer, #tls_subject').closest('tr').hide();
			}

			// If PSK is selected or checked.
			if ($('input[name=tls_connect]:checked').val() == <?= HOST_ENCRYPTION_PSK ?>
					|| $('#tls_in_psk').is(':checked')) {
				$('#tls_psk, #tls_psk_identity').closest('tr').show();
			}
			else {
				$('#tls_psk, #tls_psk_identity').closest('tr').hide();
			}
		});

		// Refresh field visibility on document load.
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			$('#tls_in_none').prop('checked', true);
		}
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			$('#tls_in_psk').prop('checked', true);
		}
		if (($('#tls_accept').val() & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			$('#tls_in_cert').prop('checked', true);
		}

		$('input[name=tls_connect]').trigger('change');

		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		$('#hostForm').on('submit', function() {
			var tls_accept = 0x00;

			if ($('#tls_in_none').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
			}
			if ($('#tls_in_psk').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
			}
			if ($('#tls_in_cert').is(':checked')) {
				tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
			}

			$('#tls_accept').val(tls_accept);
		});
	});
</script>
