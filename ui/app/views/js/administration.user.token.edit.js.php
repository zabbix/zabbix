<?php declare(strict_types = 0);
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
	$(() => {
		const $expires_row = $('#expires-at-row');
		const $expires_at = $expires_row.find('#expires_at');
		const $form = $(document.forms['token']);

		$form.on('submit', () => $form.trimValues(['#name', '#description']));

		$('#expires_state')
			.on('change', ({target: {checked}}) => {
				$expires_row.toggle(checked);
				$expires_at.prop('disabled', !checked);
			})
			.trigger('change');

		$('#regenerate').on('click', ({target}) => {
			if (confirm($(target).data('confirmation'))) {
				$form.append($('<input>', {type: 'hidden', name: 'regenerate', value: '1'}));
				$form.find('#action_dst').val('user.token.view');
				$form.submit();
			}
		});
	});
</script>
