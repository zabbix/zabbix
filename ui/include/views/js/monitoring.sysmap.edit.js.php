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

<script type="text/x-jquery-tmpl" id="url-tpl">
	<?= (new CRow([
			(new CTextBox('urls[#{id}][name]'))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CTextBox('urls[#{id}][url]'))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CSelect('urls[#{id}][elementtype]'))
				->addOptions(CSelect::createOptionsFromArray(sysmap_element_types())),
			(new CCol(
				(new CButton(null, _('Remove')))
					->onClick('$("#url-row-#{id}").remove();')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->addClass(ZBX_STYLE_NOWRAP)
		]))->setId('url-row-#{id}')
	?>
</script>

<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
	<?= (new CRow([
			new CCol([
				(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
				(new CSpan('#{name}'))
			]),
			new CCol(
				(new CTag('ul', false, [
					new CTag('li', false, [
						(new CInput('radio', 'userGroups[#{usrgrpid}][permission]', PERM_READ))
							->setId('user_group_#{usrgrpid}_permission_'.PERM_READ),
						(new CTag('label', false, _('Read-only')))
							->setAttribute('for', 'user_group_#{usrgrpid}_permission_'.PERM_READ)
					]),
					new CTag('li', false, [
						(new CInput('radio', 'userGroups[#{usrgrpid}][permission]', PERM_READ_WRITE))
							->setId('user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE),
						(new CTag('label', false, _('Read-write')))
							->setAttribute('for', 'user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE)
					])
				]))->addClass(CRadioButtonList::ZBX_STYLE_CLASS)
			),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('removeUserGroupShares("#{usrgrpid}");')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('user_group_shares_#{usrgrpid}')
			->toString()
	?>
</script>

<script type="text/x-jquery-tmpl" id="user_row_tpl">
	<?= (new CRow([
			new CCol([
				(new CTextBox('users[#{id}][userid]', '#{id}'))->setAttribute('type', 'hidden'),
				(new CSpan('#{name}'))
			]),
			new CCol(
				(new CTag('ul', false, [
					new CTag('li', false, [
						(new CInput('radio', 'users[#{id}][permission]', PERM_READ))
							->setId('user_#{id}_permission_'.PERM_READ),
						(new CTag('label', false, _('Read-only')))
							->setAttribute('for', 'user_#{id}_permission_'.PERM_READ)
					]),
					new CTag('li', false, [
						(new CInput('radio', 'users[#{id}][permission]', PERM_READ_WRITE))
							->setId('user_#{id}_permission_'.PERM_READ_WRITE),
						(new CTag('label', false, _('Read-write')))
							->setAttribute('for', 'user_#{id}_permission_'.PERM_READ_WRITE)
					])
				]))->addClass(CRadioButtonList::ZBX_STYLE_CLASS)
			),
			(new CCol(
				(new CButton('remove', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->onClick('removeUserShares("#{id}");')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->setId('user_shares_#{id}')
			->toString()
	?>
</script>

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

		$('#clone, #full_clone').click(function() {
			var form = $(this).attr('id');

			$('#form').val(form);

			if (form === 'clone') {
				$('#sysmapid').remove();
			}

			$('#delete, #clone, #full_clone, #inaccessible_user').remove();

			$('#update')
				.text(<?= json_encode(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});

			$('#tab_sysmap_tab').trigger('click');
			$('#multiselect_userid_wrapper').show();

			$('#userid').multiSelect('addData', [{
				'id': $('#current_user_userid').val(),
				'name': $('#current_user_fullname').val()
			}]);

			$('#name').focus();
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
