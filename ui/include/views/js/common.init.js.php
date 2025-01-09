<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
		chkbxRange.init();
	});

	/**
	 * Toggles filter state and updates title and icons accordingly.
	 *
	 * @param {string} 	idx					User profile index
	 * @param {string} 	value				Value
	 * @param {object} 	idx2				An array of IDs
	 * @param {int} 	profile_type		Profile type
	 */
	function updateUserProfile(idx, value, idx2, profile_type = PROFILE_TYPE_INT) {
		const value_fields = {
			[PROFILE_TYPE_INT]: 'value_int',
			[PROFILE_TYPE_STR]: 'value_str'
		};

		return sendAjaxData('zabbix.php?action=profile.update', {
			data: {
				idx: idx,
				[value_fields[profile_type]]: value,
				idx2: idx2,
				[CSRF_TOKEN_NAME]: <?= json_encode(CCsrfTokenHelper::get('profile')) ?>
			}
		});
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
