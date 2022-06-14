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
	$(document).ready(function() {
		let $menu_path = $('#menu-path'),
			$user_group = $('#user-group'),
			$host_access = $('#host-access'),
			$enable_confirmation = $('#enable-confirmation'),
			$confirmation = $('#confirmation'),
			$publickey = $('#publickey'),
			$privatekey = $('#privatekey'),
			$password = $('#password'),
			$passphrase = $('#passphrase');

		// Scope change.
		$('#scope')
			.change(function() {
				let scope = $('input[name=scope]:checked').val();

				if (scope == <?= ZBX_SCRIPT_SCOPE_ACTION ?>) {
					$menu_path
						.add($user_group)
						.add($host_access)
						.add($enable_confirmation)
						.add($confirmation)
						.closest('li')
						.hide();
				}
				else {
					$menu_path
						.add($user_group)
						.add($host_access)
						.add($enable_confirmation)
						.add($confirmation)
						.closest('li')
						.show();
				}
			})
			.change();

		// Type change.
		$('#type')
			.change(function() {
				let type = $('input[name=type]:checked').val(),
					$execute_on = $('#execute-on'),
					$authtype = $('#authtype'),
					$username = $('#username'),
					$port = $('#port'),
					$command_ipmi = $('#commandipmi'),
					$command = $('#command'),
					$parameters = $('#row-webhook-parameters'),
					$script = $('#script'),
					$timeout = $('#timeout');

				switch (type) {
					case '<?= ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ?>':
						if ($command_ipmi.val() !== '') {
							$command.val($command_ipmi.val());
							$command_ipmi.val('');
						}

						$command_ipmi
							.add($parameters)
							.add($script)
							.add($timeout)
							.add($authtype)
							.add($username)
							.add($password)
							.add($publickey)
							.add($privatekey)
							.add($passphrase)
							.add($port)
							.closest('li')
							.hide();

						$execute_on
							.add($command)
							.closest('li')
							.show();
						break;

					case '<?= ZBX_SCRIPT_TYPE_IPMI ?>':
						if ($command.val() !== '') {
							$command_ipmi.val($command.val());
							$command.val('');
						}

						$execute_on
							.add($command)
							.add($parameters)
							.add($script)
							.add($timeout)
							.add($authtype)
							.add($username)
							.add($password)
							.add($publickey)
							.add($privatekey)
							.add($passphrase)
							.add($port)
							.closest('li')
							.hide();

						$command_ipmi
							.closest('li')
							.show();
						break;

					case '<?= ZBX_SCRIPT_TYPE_SSH ?>':
						if ($command_ipmi.val() !== '') {
							$command.val($command_ipmi.val());
							$command_ipmi.val('');
						}

						$execute_on
							.add($command_ipmi)
							.add($parameters)
							.add($script)
							.add($timeout)
							.closest('li')
							.hide();

							if ($authtype.val() == <?= ITEM_AUTHTYPE_PASSWORD ?>) {
								$publickey
									.add($privatekey)
									.add($passphrase)
									.closest('li')
									.hide();

								$command
									.add($authtype)
									.add($username)
									.add($password)
									.add($port)
									.closest('li')
									.show();
							}
							else {
								$password
									.closest('li')
									.hide();

								$command
									.add($authtype)
									.add($username)
									.add($publickey)
									.add($privatekey)
									.add($passphrase)
									.add($port)
									.closest('li')
									.show();
							}
						break;

					case '<?= ZBX_SCRIPT_TYPE_TELNET ?>':
						if ($command_ipmi.val() !== '') {
							$command.val($command_ipmi.val());
							$command_ipmi.val('');
						}

						$execute_on
							.add($command_ipmi)
							.add($parameters)
							.add($script)
							.add($timeout)
							.add($authtype)
							.add($publickey)
							.add($privatekey)
							.add($passphrase)
							.closest('li')
							.hide();

						$command
							.add($username)
							.add($password)
							.add($port)
							.closest('li')
							.show();
						break;

					case '<?= ZBX_SCRIPT_TYPE_WEBHOOK ?>':
						$execute_on
							.add($command)
							.add($command_ipmi)
							.add($authtype)
							.add($username)
							.add($password)
							.add($publickey)
							.add($privatekey)
							.add($passphrase)
							.add($port)
							.closest('li')
							.hide();

						$parameters
							.add($script)
							.add($timeout)
							.closest('li')
							.show();
						break;
				}
			})
			.change();

		// Authtype change.
		$('#authtype')
			.change(function() {
				let type = $('input[name=type]:checked').val();

				if (type == <?= ZBX_SCRIPT_TYPE_SSH ?>) {
					if ($(this).val() == <?= ITEM_AUTHTYPE_PASSWORD ?>) {
						$publickey
							.add($privatekey)
							.add($passphrase)
							.closest('li')
							.hide();

						$password
							.closest('li')
							.show();
					}
					else {
						$password
							.closest('li')
							.hide();

						$publickey
							.add($privatekey)
							.add($passphrase)
							.closest('li')
							.show();
					}
				}
			})
			.change();

		// clone button
		$('#clone').click(function() {
			$('#scriptid, #delete, #clone').remove();
			$('#update').text(<?= json_encode(_('Add')) ?>);
			$('input[name=scope]').prop('disabled', false);
			$('#update')
				.val('script.create')
				.attr({id: 'add'});
			$('#name').focus();
		});

		// confirmation text input
		$('#confirmation')
			.keyup(function() {
				$('#test-confirmation').prop('disabled', (this.value == ''));
			})
			.keyup();

		// enable confirmation checkbox
		$('#enable-confirmation')
			.change(function() {
				if (this.checked) {
					$('#confirmation')
						.prop('disabled', false)
						.keyup();
				}
				else {
					$('#confirmation, #test-confirmation').prop('disabled', true);
				}
			})
			.change();

		// test confirmation button
		$('#test-confirmation').click(function() {
			executeScript(null, $('#confirmation').val(), this);
		});

		// host group selection
		$('#hgstype-select')
			.change(function() {
				if ($('#hgstype-select').val() == 1) {
					$('#host-group-selection').show();
				}
				else {
					$('#host-group-selection').hide();
				}
			})
			.change();

		// Trim spaces on sumbit.
		$('#script-form').submit(function() {
			$(this).trimValues(['#name', '#command', '#commandipmi', '#description', 'input[name^="parameters"]',
				'input[name="script"]', '#username', '#publickey', '#privatekey', '#menu-path', '#port'
			]);
		});

		$('#parameters-table').dynamicRows({ template: '#parameters-row' });
	});
</script>
