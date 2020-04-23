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
	jQuery(function() {
		// disable the status filter when using the state filter
		jQuery('#filter_state').change(function(event, saveValue) {
			var stateObj = jQuery(this),
				statusObj = jQuery('#filter_status'),
				saveValue = (saveValue === undefined) ? true : saveValue;

			if (stateObj.val() == -1) {
				// restore the last remembered status filter value
				if (statusObj.prop('disabled') && statusObj.data('last-value') !== undefined) {
					statusObj.val(statusObj.data('last-value'));
				}
				statusObj.prop('disabled', false);
			}
			else {
				// remember the last status filter value
				if (!statusObj.prop('disabled') && saveValue) {
					statusObj.data('last-value', statusObj.val());
				}
				statusObj.prop('disabled', true).val(<?php echo ITEM_STATUS_ACTIVE ?>);
			}
		})
		.trigger('change', false);
	});
</script>
