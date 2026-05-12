<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	$(function() {
		<?php if (isset($page['scripts']) && in_array('flickerfreescreen.js', $page['scripts'])): ?>
			window.flickerfreeScreen.responsiveness = <?php echo SCREEN_REFRESH_RESPONSIVENESS * 1000; ?>;
		<?php endif ?>

		// the chkbxRange.init() method must be called after the inserted post scripts and initializing cookies
		cookie.init();
		// Timeout is added to reliably restore checkbox states, when using Back and Forward navigation
		// in different browsers.
		setTimeout(() => chkbxRange.init());
	});

	/**
	 * Toggles filter state and updates title and icons accordingly.
	 *
	 * @param {string} 					idx					User profile index
	 * @param {string} 					value				Value
	 * @param {Array} 					idx2				An array of IDs
	 * @param {int} 					profile_type		Profile type
	 * @param {AbortController|null} 	abort_controller
	 *
	 * @return {Promise<any>}
	 */
	function updateUserProfile(idx, value, idx2, profile_type = PROFILE_TYPE_INT, abort_controller = null) {
		const value_fields = {
			[PROFILE_TYPE_INT]: 'value_int',
			[PROFILE_TYPE_STR]: 'value_str'
		};

		const url = new URL('zabbix.php', location.href);
		url.searchParams.set('action', 'profile.update');
		url.searchParams.set('output', 'ajax');

		const body = new URLSearchParams();
		body.set('idx', idx);
		body.set(value_fields[profile_type], value);

		for (const idx2_value of idx2) {
			body.append('idx2[]', idx2_value);
		}

		body.set(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('profile')) ?>);

		return fetch(url.toString(), {method: 'POST', body, signal: abort_controller?.signal});
	}

	/**
	 * Add object to the list of favorites.
	 */
	function add2favorites(object, objectid) {
		sendAjaxData('zabbix.php?action=favorite.create', {
			data: {
				object: object,
				objectid: objectid,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('favorite')) ?>
			}
		});
	}

	/**
	 * Remove object from the list of favorites. Remove all favorites if objectid==0.
	 */
	function rm4favorites(object, objectid) {
		sendAjaxData('zabbix.php?action=favorite.delete', {
			data: {
				object: object,
				objectid: objectid,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('favorite')) ?>
			}
		});
	}
</script>
