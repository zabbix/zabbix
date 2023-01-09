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

				if (e.target.classList.contains('js-massenable-scheduledreport')) {
					prevent_event = !this.massEnableScheduledreport(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdisable-scheduledreport')) {
					prevent_event = !this.massDisableScheduledreport(
						e.target, Object.keys(chkbxRange.getSelectedIds())
					);
				}
				else if (e.target.classList.contains('js-massdelete-scheduledreport')) {
					prevent_event = !this.massDeleteScheduledreport(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}

				if (prevent_event) {
					e.preventDefault();
					e.stopPropagation();
					return false;
				}
			});
		}

		massEnableScheduledreport(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Enable selected scheduled reports?')) ?>
				: <?= json_encode(_('Enable selected scheduled report?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['scheduledreport.enable'], false
			);

			return true;
		}

		massDisableScheduledreport(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Disable selected scheduled reports?')) ?>
				: <?= json_encode(_('Disable selected scheduled report?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['scheduledreport.disable'], false
			);

			return true;
		}

		massDeleteScheduledreport(target, druleids) {
			const confirmation = druleids.length > 1
				? <?= json_encode(_('Delete selected scheduled reports?')) ?>
				: <?= json_encode(_('Delete selected scheduled report?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['scheduledreport.delete'], false
			);

			return true;
		}
	};
</script>
