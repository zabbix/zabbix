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
	function removeMedia(index) {
		// table row
		jQuery('#medias_' + index).remove();
		// hidden variables
		jQuery('#medias_' + index + '_mediaid').remove();
		jQuery('#medias_' + index + '_mediatype').remove();
		jQuery('#medias_' + index + '_mediatypeid').remove();
		jQuery('#medias_' + index + '_period').remove();
		jQuery('#medias_' + index + '_sendto').remove();
		removeVarsBySelector(null, 'input[id^="medias_' + index + '_sendto_"]');
		jQuery('#medias_' + index + '_severity').remove();
		jQuery('#medias_' + index + '_active').remove();
		jQuery('#medias_' + index + '_name').remove();
	}

	function autologoutHandler() {
		var	$autologout_visible = jQuery('#autologout_visible'),
			disabled = !$autologout_visible.prop('checked'),
			$autologout = jQuery('#autologout'),
			$hidden = $autologout.prev('input[type=hidden][name="' + $autologout.prop('name') + '"]');

		$autologout.prop('disabled', disabled);

		if (!disabled) {
			$hidden.remove();
		}
		else if (!$hidden.length) {
			jQuery('<input>', {'type': 'hidden', 'name': $autologout.prop('name')})
				.val('0')
				.insertBefore($autologout);
		}
	}

	jQuery(function($) {
		var $autologin_cbx = $('#autologin'),
			$autologout_cbx = $('#autologout_visible');

		$autologin_cbx.on('click', function() {
			if (this.checked) {
				$autologout_cbx.prop('checked', false);
			}
			autologoutHandler();
		});

		$autologout_cbx.on('click', function() {
			if (this.checked) {
				$autologin_cbx.prop('checked', false).change();
			}
			autologoutHandler();
		});
	});

	jQuery(document).ready(function($) {
		autologoutHandler();
	});
</script>
