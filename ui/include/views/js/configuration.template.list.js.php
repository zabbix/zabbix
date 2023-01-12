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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(); ?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		$('#filter-tags')
			.dynamicRows({template: '#filter-tag-row-tmpl'})
			.on('afteradd.dynamicRows', function() {
				var rows = this.querySelectorAll('.form_row');
				new CTagFilterItem(rows[rows.length - 1]);
			});

		// Init existing fields once loaded.
		document.querySelectorAll('#filter-tags .form_row').forEach(row => {
			new CTagFilterItem(row);
		});
	});
</script>

<script>
	const view = new class {

		init({csrf_tokens}) {
			this.csrf_tokens = csrf_tokens;

			this._initActionButtons();
		}

		_initActionButtons() {
			document.addEventListener('click', (e) => {
				let prevent_event = false;

				if (e.target.classList.contains('js-massdelete-template')) {
					prevent_event = !this.massDeleteTemplate(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-massdeleteclear-template')) {
					prevent_event = !this.massDeleteClearTemplate(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-import-template')) {
					this.openTemplateImportPopup(e.target);
				}

				if (prevent_event) {
					e.preventDefault();
					e.stopPropagation();
					return false;
				}
			});
		}

		massDeleteTemplate(target, templateids) {
			const confirmation = templateids.length > 1
				? <?= json_encode(_('Delete selected templates?')) ?>
				: <?= json_encode(_('Delete selected template?')) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['template.massdelete'], false
			);

			return true;
		}

		massDeleteClearTemplate(target, templateids) {
			const confirmation = templateids.length > 1
				? <?= json_encode(
						_('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
				) ?>
				: <?= json_encode(
						_('Delete and clear selected template? (Warning: all linked hosts will be cleared!)')
				) ?>;

			if (!window.confirm(confirmation)) {
				return false;
			}

			create_var(target.closest('form'), '<?= CController::CSRF_TOKEN_NAME ?>',
				this.csrf_tokens['template.massdeleteclear'], false
			);

			return true;
		}

		openTemplateImportPopup() {
			return PopUp("popup.import", {
				rules_preset: "template",
				'<?= CController::CSRF_TOKEN_NAME ?>': this.csrf_tokens['popup.import']
			},
				{dialogue_class: "modal-popup-generic"}
			);
		}
	};
</script>
