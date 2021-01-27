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

<script type="text/javascript">
	$(document).ready(function() {
		// type change
		$('#type')
			.change(function() {
				let type = $('input[name=type]:checked').val(),
					$execute_on = $('#execute_on');
					$command_ipmi = $('#commandipmi'),
					$command = $('#command');
					$parameters = $('#row_webhook_parameters'),
					$script = $('#script'),
					$timeout = $('#timeout');

				if (type == <?= ZBX_SCRIPT_TYPE_IPMI ?>) {
					if ($command.val() !== '') {
						$command_ipmi.val($command.val());
						$command.val('');
					}

					$execute_on
						.add($command)
						.add($parameters)
						.add($script)
						.add($timeout)
						.closest('li')
						.hide();

					$command_ipmi
						.closest('li')
						.show();
				}
				else if (type == <?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>) {
					if ($command_ipmi.val() !== '') {
						$command.val($command_ipmi.val());
						$command_ipmi.val('');
					}

					$command_ipmi
						.add($parameters)
						.add($script)
						.add($timeout)
						.closest('li')
						.hide();

					$execute_on
						.add($command)
						.closest('li')
						.show();
				}
				else if (type == <?= ZBX_SCRIPT_TYPE_WEBHOOK ?>) {
					$execute_on
						.add($command)
						.add($command_ipmi)
						.closest('li')
						.hide();

					$parameters
						.add($script)
						.add($timeout)
						.closest('li')
						.show();
				}
			})
			.change();

		// clone button
		$('#clone').click(function() {
			$('#scriptid, #delete, #clone').remove();
			$('#update').text(<?= json_encode(_('Add')) ?>);
			$('#update')
				.val('script.create')
				.attr({id: 'add'});
			$('#name').focus();
		});

		// confirmation text input
		$('#confirmation')
			.keyup(function() {
				$('#testConfirmation').prop('disabled', (this.value == ''));
			})
			.keyup();

		// enable confirmation checkbox
		$('#enable_confirmation')
			.change(function() {
				if (this.checked) {
					$('#confirmation')
						.prop('disabled', false)
						.keyup();
				}
				else {
					$('#confirmation, #testConfirmation').prop('disabled', true);
				}
			})
			.change();

		// test confirmation button
		$('#testConfirmation').click(function() {
			executeScript(null, $('#confirmation').val(), this);
		});

		// host group selection
		$('#hgstype-select')
			.change(function() {
				if ($('#hgstype-select').val() == 1) {
					$('#hostGroupSelection').show();
				}
				else {
					$('#hostGroupSelection').hide();
				}
			})
			.change();

		// trim spaces on sumbit
		$('#scriptForm').submit(function() {
			$(this).trimValues(['#name', '#command', '#commandipmi', '#description', 'input[name^="parameters"]',
				'input[name="script"]'
			]);
		});

		$('#parameters_table').dynamicRows({ template: '#parameters_row' });
	});
</script>
