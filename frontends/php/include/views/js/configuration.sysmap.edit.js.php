<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
<tr id="user_group_shares_#{usrgrpid}">
	<td>
		<input name="userGroups[#{usrgrpid}][usrgrpid]" type="hidden" value="#{usrgrpid}" />
		<span>#{name}</span>
	</td>
	<td>
		<ul class="radio-segmented">
			<li>
				<input type="radio" id="user_group_#{usrgrpid}_permission_<?= PERM_READ ?>" name="userGroups[#{usrgrpid}][permission]" value="<?= PERM_READ ?>">
				<label for="user_group_#{usrgrpid}_permission_<?= PERM_READ ?>"><?= _('Read-only') ?></label>
			</li><li>
				<input type="radio" id="user_group_#{usrgrpid}_permission_<?= PERM_READ_WRITE ?>" name="userGroups[#{usrgrpid}][permission]" value="<?= PERM_READ_WRITE ?>">
				<label for="user_group_#{usrgrpid}_permission_<?= PERM_READ_WRITE ?>"><?= _('Read-write') ?></label>
			</li>
		</ul>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeUserGroupShares('#{usrgrpid}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/x-jquery-tmpl" id="user_row_tpl">
<tr id="user_shares_#{id}">
	<td>
		<input name="users[#{id}][userid]" type="hidden" value="#{id}" />
		<span>#{name}</span>
	</td>
	<td>
		<ul class="radio-segmented">
			<li>
				<input type="radio" id="user_#{id}_permission_<?= PERM_READ ?>" name="users[#{id}][permission]" value="<?= PERM_READ ?>">
				<label for="user_#{id}_permission_<?= PERM_READ ?>"><?= _('Read-only') ?></label>
			</li><li>
				<input type="radio" id="user_#{id}_permission_<?= PERM_READ_WRITE ?>" name="users[#{id}][permission]" value="<?= PERM_READ_WRITE ?>">
				<label for="user_#{id}_permission_<?= PERM_READ_WRITE ?>"><?= _('Read-write') ?></label>
			</li>
		</ul>
	</td>
	<td class="<?= ZBX_STYLE_NOWRAP ?>">
		<button type="button" class="<?= ZBX_STYLE_BTN_LINK ?>" name="remove" onclick="removeUserShares('#{id}');"><?= _('Remove') ?></button>
	</td>
</tr>
</script>

<script type="text/javascript">
	function toggleAdvancedLabels(toggle) {
		var inputs = ['label_type_hostgroup', 'label_type_host', 'label_type_trigger', 'label_type_map', 'label_type_image'];

		jQuery.each(inputs, function() {
			jQuery('#' + this).parentsUntil('ul').toggle(toggle);
		});

		jQuery('#label_type').parentsUntil('ul').toggle(!toggle);
	}

	function toggleCustomLabel(e) {
		jQuery(e.target).parent().find('textarea').toggle(e.target.options[e.target.selectedIndex].value.toString() == '<?php echo MAP_LABEL_TYPE_CUSTOM; ?>');
	}

	jQuery(document).ready(function() {
		jQuery('#label_format').click(function() {
			toggleAdvancedLabels(jQuery('#label_format:checked').length != 0);
		});

		var inputs = ['label_type_hostgroup', 'label_type_host', 'label_type_trigger', 'label_type_map', 'label_type_image'];

		jQuery.each(inputs, function() {
			jQuery('#' + this).change(toggleCustomLabel);
		});

		toggleAdvancedLabels(jQuery('#label_format:checked').length != 0);

		// clone button
		jQuery('#clone').click(function() {
			jQuery('#sysmapid, #delete, #clone').remove();
			jQuery('#update')
				.text(<?= CJs::encodeJson(_('Add')) ?>)
				.attr({id: 'add', name: 'add'});
			jQuery('#name').focus();
		});
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
				if (jQuery('input[name=private]:checked').val() == <?= SYSMAP_PRIVATE ?>) {
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
		var row = jQuery('#user_group_shares_' + usrgrpid);
		var rowParent = row.parent();

		row.remove();
	}

	function removeUserShares(userid) {
		var row = jQuery('#user_shares_' + userid);
		var rowParent = row.parent();

		row.remove();
	}
</script>
