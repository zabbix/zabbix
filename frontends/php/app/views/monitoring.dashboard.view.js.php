<script type="text/x-jquery-tmpl" id="user_group_row_tpl">
<?= (new CRow([
	new CCol([
		(new CTextBox('userGroups[#{usrgrpid}][usrgrpid]', '#{usrgrpid}'))->setAttribute('type', 'hidden'),
		'#{name}'
	]),
	new CCol(
		(new CRadioButtonList('userGroups[#{usrgrpid}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_group_#{usrgrpid}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_group_#{usrgrpid}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('removeUserGroupShares("#{usrgrpid}");')
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
		'#{name}',
	]),
	new CCol(
		(new CRadioButtonList('users[#{id}][permission]', PERM_READ))
			->addValue(_('Read-only'), PERM_READ, 'user_#{id}_permission_'.PERM_READ)
			->addValue(_('Read-write'), PERM_READ_WRITE, 'user_#{id}_permission_'.PERM_READ_WRITE)
			->setModern(true)
	),
	(new CCol(
		(new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('removeUserShares("#{id}");')
	))->addClass(ZBX_STYLE_NOWRAP)
]))
	->setId('user_shares_#{id}')
	->toString()
?>
</script>

<script type="text/javascript">
	function dashboardAddMessages(messages) {
		var $message_div = jQuery('<div>').attr('id','dashbrd-messages');
		$message_div.append(messages);
		jQuery('.article').prepend($message_div);
	}

	function dashboardRemoveMessages() {
		jQuery('#dashbrd-messages').remove();
	}

	jQuery(document).ready(function() {
		var	form = jQuery('form[name="dashboard_sharing_form"]');

		// overwrite submit action to AJAX call
		form.submit(function(event) {
			var	me = this;

			jQuery.ajax({
				data: jQuery(me).serialize(), // get the form data
				type: jQuery(me).attr('method'),
				url: jQuery(me).attr('action'),
				success: function (response) {
					dashboardRemoveMessages();
					if (typeof response === 'object') {
						if ('errors' in response && response.errors.length > 0) {
							dashboardAddMessages(response.errors.join());
						}
					}
					else if (typeof response === 'string' && response.indexOf('Access denied') !== -1) {
						alert('<?= _('You need permission to perform this action!') ?>');
					}
				},
				error: function (response) {
					alert('<?= _('Something went wrong. Please try again later!') ?>')
				}
			});
			event.preventDefault(); // cancel original event to prevent form submitting
		});
	});

	// fill the form with actual data
	jQuery.fn.fillForm = function(data) {
		if (typeof data.private !== 'undefined') {
			addPopupValues({'object': 'private', 'values': [data.private] });
		}

		if (typeof data.users !== 'undefined') {
			removeUserShares();
			addPopupValues({'object': 'userid', 'values': data.users });
		}

		if (typeof data.userGroups !== 'undefined') {
			removeUserGroupShares();
			addPopupValues({'object': 'usrgrpid', 'values': data.userGroups });
		}
	};

	/**
	 * @see init.js add.popup event
	 */
	function addPopupValues(list) {
		var	i,
			tpl,
			container;

		for (i = 0; i < list.values.length; i++) {
			var	value = list.values[i];

			if (empty(value)) {
				continue;
			}

			if (typeof value.permission === 'undefined') {
				if (jQuery('input[name=private]:checked').val() == <?= PRIVATE_SHARING ?>) {
					value.permission = <?= PERM_READ ?>;
				}
				else {
					value.permission = <?= PERM_READ_WRITE ?>;
				}
			}

			switch (list.object) {
				case 'private':
					jQuery('input[name=private][value=' + value + ']').prop('checked', 'checked');
					break;

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
		if (typeof usrgrpid === 'undefined') {
			jQuery("[id^='user_group_shares']").remove();
		}
		else {
			jQuery('#user_group_shares_' + usrgrpid).remove();
		}
	}

	function removeUserShares(userid) {
		if (typeof userid === 'undefined') {
			jQuery("[id^='user_shares']").remove();
		}
		else {
			jQuery('#user_shares_' + userid).remove();
		}
	}
</script>
