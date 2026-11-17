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
	jQuery(function($) {
		const url_tpl = new Template($('#url-tpl').html());
		const $add_url_btn = $('#add-url');
		const $add_url_row = $add_url_btn.closest('tr');
		let url_rowid = $('[id^="url-row-"]').length;

		$add_url_btn.on('click', (e) => {
			$add_url_row.before(url_tpl.evaluate({id: url_rowid}));
			url_rowid++;
		});

		var inputs = '#label_type_hostgroup, #label_type_host, #label_type_trigger, #label_type_map, #label_type_image';

		$('#label_format').click(function() {
			var toggle = $('#label_format').is(':checked');

			$(inputs).each(function() {
				$(this).parentsUntil('ul')
					.toggle(toggle)
					.next().toggle($(this).val() == <?= MAP_LABEL_TYPE_CUSTOM ?> && toggle);
			});

			$('#label_type').parentsUntil('ul').toggle(!toggle);
		});

		$(inputs).change(function() {
			$(this).parentsUntil('ul').next().toggle($(this).val() == <?= MAP_LABEL_TYPE_CUSTOM ?>);
		});

		$('#label_format').triggerHandler('click');
	});

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var i,
			value,
			tpl,
			container;

		for (i = 0; i < list.values.length; i++) {
			if (empty(list.values[i])) {
				continue;
			}

			value = list.values[i];
			if (typeof value.permission === 'undefined') {
				if (jQuery('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
					value.permission = <?= PERM_READ ?>;
				}
				else {
					value.permission = <?= PERM_READ_WRITE ?>;
				}
			}

			switch (list.object) {
				case 'usrgrpid':
					if (jQuery('#user_group_shares_' + value.usrgrpid).length) {
						continue;
					}

					tpl = new Template(jQuery('#user_group_row_tpl').html());

					container = jQuery('#user_group_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_group_' + value.usrgrpid + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;

				case 'userid':
					if (jQuery('#user_shares_' + value.id).length) {
						continue;
					}

					tpl = new Template(jQuery('#user_row_tpl').html());

					container = jQuery('#user_list_footer');
					container.before(tpl.evaluate(value));

					jQuery('#user_' + value.id + '_permission_' + value.permission + '')
						.prop('checked', true);
					break;
			}
		}
	}

	function removeUserGroupShares(usrgrpid) {
		jQuery('#user_group_shares_' + usrgrpid).remove();
	}

	function removeUserShares(userid) {
		jQuery('#user_shares_' + userid).remove();
	}
</script>
