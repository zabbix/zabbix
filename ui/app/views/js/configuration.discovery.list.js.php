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

<script>
	const view = new class {

		init({csrf_tokens}) {
			this.csrf_tokens = csrf_tokens;

			this._initActionButtons();
		}

		_initActionButtons() {
			document.addEventListener('click', (e) => {
				let prevent_event = false;

				if (e.target.classList.contains('js-massenable-discovery')) {
					prevent_event = !this.massEnableDiscovery(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdisable-discovery')) {
					prevent_event = !this.massDisableDiscovery(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdelete-discovery')) {
					prevent_event = !this.massDeleteDiscovery(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}

				if (prevent_event) {
					e.preventDefault();
					e.stopPropagation();
					return false;
				}
			});
		}

		massEnableDiscovery(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Enable selected discovery rules?')) ?>
				: <?= json_encode(_('Enable selected discovery rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['discovery.enable'], false
			);

			return true;
		}

		massDisableDiscovery(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Disable selected discovery rules?')) ?>
				: <?= json_encode(_('Disable selected discovery rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['discovery.disable'], false
			);

			return true;
		}

		massDeleteDiscovery(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Delete selected discovery rules?')) ?>
				: <?= json_encode(_('Delete selected discovery rule?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['discovery.delete'], false
			);

			return true;
		}
	};
</script>
