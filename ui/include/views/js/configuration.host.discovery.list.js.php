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
	jQuery(function($) {
		// Disable the status filter when using the state filter.
		$('#filter_state').on('change', (event, save_value = true) => {
			var $state_obj = $(this).find('[name="filter_state"]:checked'),
				$status_obj = $('#filter_status [name="filter_status"]:checked'),
				$status_buttons = $('#filter_status [name="filter_status"]');

			if ($state_obj.val() == -1) {
				// Restore the last remembered status filter value.
				if ($status_buttons.prop('disabled') && typeof $status_buttons.data('last-value') !== 'undefined') {
					for (var i = 0; i < $status_buttons.length; i++) {
						if ($($status_buttons[i]).val() == $status_buttons.data('last-value')) {
							$($status_buttons[i]).prop('checked', true);
						}
						else {
							$($status_buttons[i]).prop('checked', false);
						}
					}
				}

				$status_buttons.prop('disabled', false);
			}
			else {
				// Remember the last status filter value.
				if (!$status_buttons.prop('disabled') && save_value) {
					$status_buttons.data('last-value', $status_obj.val());
				}

				for (var i = 0; i < $status_buttons.length; i++) {
					if ($($status_buttons[i]).val() == <?= ITEM_STATUS_ACTIVE ?>) {
						$($status_buttons[i]).prop('checked', true);
					}
					else {
						$($status_buttons[i]).prop('checked', false);
					}
				}

				$status_buttons.prop('disabled', true);
			}
		})
		.trigger('change', false);
	});
</script>
