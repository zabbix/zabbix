<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	jQuery(document).ready(function() {
		// type change
		jQuery('#type')
			.change(function() {
				var type = jQuery('input[name=type]:checked').val(),
					command_ipmi = jQuery('#commandipmi'),
					command = jQuery('#command');

				if (type == <?= ZBX_SCRIPT_TYPE_IPMI ?>) {
					if (command.val() !== '') {
						command_ipmi.val(command.val());
						command.val('');
					}

					jQuery('#execute_on').add(command).closest('li').hide();
					command_ipmi.closest('li').show();
				}
				else {
					if (command_ipmi.val() !== '') {
						command.val(command_ipmi.val());
						command_ipmi.val('');
					}

					command_ipmi.closest('li').hide();
					jQuery('#execute_on').add(command).closest('li').show();
				}
			})
			.change();

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#scriptid, #delete, #clone').remove();
			jQuery('#update').text(<?= json_encode(_('Add')) ?>);
			jQuery('#update')
				.val('script.create')
				.attr({id: 'add'});
			jQuery('#name').focus();
		});

		// confirmation text input
		jQuery('#confirmation').keyup(function() {
			jQuery('#testConfirmation').prop('disabled', (this.value == ''));
		}).keyup();

		// enable confirmation checkbox
		jQuery('#enable_confirmation')
			.change(function() {
				if (this.checked) {
					jQuery('#confirmation')
						.prop('disabled', false)
						.keyup();
				}
				else {
					jQuery('#confirmation, #testConfirmation').prop('disabled', true);
				}
			})
			.change();

		// test confirmation button
		jQuery('#testConfirmation').click(function() {
			executeScript(null, null, jQuery('#confirmation').val(), this);
		});

		// host group selection
		jQuery('#hgstype-select')
			.change(function() {
				if (jQuery('#hgstype-select').val() == 1) {
					jQuery('#hostGroupSelection').show();
				}
				else {
					jQuery('#hostGroupSelection').hide();
				}
			})
			.change();

		// trim spaces on sumbit
		jQuery('#scriptForm').submit(function() {
			jQuery(this).trimValues(['#name', '#command', '#description']);
		});
	});
</script>
