<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

// Initialized massupdate form tabs.
$('#tabs').tabs();

$('#tabs').on('tabsactivate', (event, ui) => {
	$('#tabs').resize();
});

// Host groups.
<?php if (CWebUser:: getType() == USER_TYPE_SUPER_ADMIN): ?>
(() => {
	const groups_elem = document.querySelector('#groups-div');
	if (!groups_elem) {
		return false;
	}

	let obj = groups_elem
	if (groups_elem.tagName === 'SPAN') {
		obj = groups_elem.originalObject;
	}

	[...obj.querySelectorAll('input[name=mass_update_groups]')].map((elem) => {
		elem.addEventListener('change', (event) => {
			$('#groups_').multiSelect('modify', {
				'addNew': (event.currentTarget.value == <?= ZBX_ACTION_ADD ?> || event.currentTarget.value == <?= ZBX_ACTION_REPLACE ?>)
			});
		})
	});
})();
<?php endif ?>

// Macros.
(() => {
	const macros_elem = document.querySelector('#macros-div');
	if (!macros_elem) {
		return false;
	}

	let obj = macros_elem
	if (macros_elem.tagName === 'SPAN') {
		obj = macros_elem.originalObject;
	}

	$(obj.querySelector('#tbl_macros')).dynamicRows({template: '#macro-row-tmpl'});
	$(obj.querySelector('#tbl_macros'))
		.on('afteradd.dynamicRows', () => {
			$('.input-group', $(obj.querySelector('#tbl_macros'))).macroValue();
			$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', $(obj.querySelector('#tbl_macros'))).textareaFlexible();
			obj.querySelector('#macro_add').scrollIntoView({block: 'nearest'});
		});

	$(obj.querySelector('#tbl_macros'))
		.find('.input-group')
		.macroValue();

	$(obj.querySelector('#tbl_macros'))
		.on('change keydown', '.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>.macro', function(event) {
			if (event.type === 'change' || event.which === 13) {
				$(this)
					.val($(this).val().replace(/([^:]+)/, (value) => value.toUpperCase('$1')))
					.textareaFlexible();
			}
		});

	$(obj.querySelector('#tbl_macros'))
		.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
		.textareaFlexible();

	$(obj.querySelector('#tbl_macros'))
		.on('resize', '.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', () => {
			$(window).resize();
		});
})();

// Tags.
(() => {
	const tags_elem = document.querySelector('#tags-div');
	if (!tags_elem) {
		return false;
	}

	let obj = tags_elem
	if (tags_elem.tagName === 'SPAN') {
		obj = tags_elem.originalObject;
	}

	$(obj.querySelector('#tags-table')).dynamicRows({template: '#tag-row-tmpl'});
	$(obj.querySelector('#tags-table'))
		.on('click', 'button.element-table-add', () => {
			$('#tags-table .<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>').textareaFlexible();
		})
		.on('resize', '.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', () => {
			$(window).resize();
		});
})();

// Linked templates.
(() => {
	const template_visible = document.querySelector('#linked-templates-div');
	if (!template_visible) {
		return false;
	}

	let obj = template_visible
	if (template_visible.tagName === 'SPAN') {
		obj = template_visible.originalObject;
	}

	const mass_action_tpls = obj.querySelector('#mass_action_tpls');

	if (!mass_action_tpls) {
		return false;
	}

	mass_action_tpls.addEventListener('change', (ev) => {
		const action = obj.querySelector('input[name="mass_action_tpls"]:checked').value;

		obj.querySelector('#mass_clear_tpls').disabled = action === '<?= ZBX_ACTION_ADD ?>';
	});

	mass_action_tpls.dispatchEvent(new CustomEvent('change', {}));
})();

// Inventory mode.
(() => {
	const inventory = document.querySelector('#inventoryFormList');
	if (!inventory) {
		return false;
	}

	let obj = inventory;
	if (inventory.tagName === 'SPAN') {
		obj = inventory.originalObject;
	}

	const cb = (event) => {
		const value = event.currentTarget.value;

		$('.formrow-inventory').toggle(value !== '<?php echo HOST_INVENTORY_DISABLED; ?>');

		// Update popup size.
		$('#tabs').resize();
	};

	[...obj.querySelectorAll('[name=inventory_mode]')].map((elem) => elem.addEventListener('change', cb));

	document
		.querySelector('#visible_inventory_mode')
		.addEventListener('click',
			() => cb({
				currentTarget: {
					value: (!document.querySelector('#visible_inventory_mode:checked'))
						? '<?php echo HOST_INVENTORY_DISABLED; ?>'
						: document
							.querySelector('[name=inventory_mode]:checked')
							.value
				}
			})
		);

	obj
		.querySelector('[name=inventory_mode]')
		.dispatchEvent(new CustomEvent('change', {}));
})();

// Encryption.
(() => {
	const encryption = document.querySelector('#encryption_div');
	if (!encryption) {
		return false;
	}

	let obj = encryption;
	if (encryption.tagName === 'SPAN') {
		obj = encryption.originalObject;
	}

	[...obj.querySelectorAll('#tls_connect, #tls_in_psk, #tls_in_cert')].map(
		(elem) => elem.addEventListener('change', (event) => {
			// If certificate is selected or checked.
			if (obj.querySelector('input[name=tls_connect]:checked').value == <?= HOST_ENCRYPTION_CERTIFICATE ?>
					|| obj.querySelector('#tls_in_cert').checked) {
				obj
					.querySelector('#tls_issuer')
					.closest('li')
					.style
					.display = '';
				obj
					.querySelector('#tls_subject')
					.closest('li')
					.style
					.display = '';
			}
			else {
				obj
					.querySelector('#tls_issuer')
					.closest('li')
					.style
					.display = 'none';
				obj
					.querySelector('#tls_subject')
					.closest('li')
					.style
					.display = 'none';
			}

			// If PSK is selected or checked.
			if (obj.querySelector('input[name=tls_connect]:checked').value == <?= HOST_ENCRYPTION_PSK ?>
					|| obj.querySelector('#tls_in_psk').checked) {
				obj
					.querySelector('#tls_psk')
					.closest('li')
					.style
					.display = '';
				obj
					.querySelector('#tls_psk_identity')
					.closest('li')
					.style
					.display = '';
			}
			else {
				obj
					.querySelector('#tls_psk')
					.closest('li')
					.style
					.display = 'none';
				obj
					.querySelector('#tls_psk_identity')
					.closest('li')
					.style
					.display = 'none';
			}
		})
	);

	// Refresh field visibility on document load.
	const tls_accept = document.querySelector('#tls_accept');
	if (tls_accept) {
		if ((tls_accept.value & <?= HOST_ENCRYPTION_NONE ?>) == <?= HOST_ENCRYPTION_NONE ?>) {
			obj.querySelector('#tls_in_none').checked = true;
		}
		if ((tls_accept.value & <?= HOST_ENCRYPTION_PSK ?>) == <?= HOST_ENCRYPTION_PSK ?>) {
			obj.querySelector('#tls_in_psk').checked = true;
		}
		if ((tls_accept.value & <?= HOST_ENCRYPTION_CERTIFICATE ?>) == <?= HOST_ENCRYPTION_CERTIFICATE ?>) {
			obj.querySelector('#tls_in_cert').checked = true;
		}
	}

	obj
		.querySelector('#tls_connect')
		.dispatchEvent(new CustomEvent('change', {}));
})();

function visibility_status_changeds(value, obj_id, replace_to) {
	const obj = document.getElementById(obj_id);
	if (obj === null) {
		throw `Cannot find objects with name [${obj_id}]`;
	}

	if (replace_to && replace_to != '') {
		if (obj.originalObject) {
			const old_obj = obj.originalObject;
			old_obj.originalObject = obj;
			obj.parentNode.replaceChild(old_obj, obj);
		}
		else if (!value) {
			const new_obj = document.createElement('span');
			new_obj.setAttribute('name', obj.name);
			new_obj.setAttribute('id', obj.id);

			new_obj.innerHTML = replace_to;
			new_obj.originalObject = obj;
			obj.parentNode.replaceChild(new_obj, obj);
		}
		else {
			throw 'Missing originalObject for restoring';
		}
	}
	else {
		obj.style.visibility = value ? 'visible' : 'hidden';
	}
}

if (!CR && !GK) {
	$("textarea[maxlength]").bind("paste contextmenu change keydown keypress keyup", function() {
		var elem = $(this);
		if (elem.val().length > elem.attr("maxlength")) {
			elem.val(elem.val().substr(0, elem.attr("maxlength")));
		}
	});
}

function submitPopup(overlay) {
	const form = document.querySelector('#massupdate-form');
	const action = form.querySelector('#action').value;
	const location_url = form.querySelector('#location_url').value;

	// Check "remove all" checkbox in macro tab.
	const macro_tab_visible = form.querySelector('#visible_macros');
	if (macro_tab_visible && macro_tab_visible.checked) {
		const is_checked = form.querySelector('#macros_remove_all').checked;
		const is_remove_block =
				form.querySelector('[name=mass_update_macros]:checked').value == <?= ZBX_ACTION_REMOVE_ALL ?>;
		if (is_remove_block && !is_checked) {
			overlayDialogue({
				'title': <?= json_encode(_('Warning')) ?>,
				'type': 'popup',
				'class': 'modal-popup modal-popup-medium',
				'content': $('<span>').text(<?= json_encode(_('Please confirm that you want to remove all macros.')) ?>),
				'buttons': [
					{
						'title': <?= json_encode(_('Ok')) ?>,
						'focused': true,
						'action': () => {}
					}
				]
			}, overlay);

			overlay.unsetLoading();
			return false;
		}
	}

	if (action == 'popup.massupdate.host') {
		// Depending on checkboxes, create a value for hidden field 'tls_accept'.
		let tls_accept = 0x00;

		if (form.querySelector('#tls_in_none') && form.querySelector('#tls_in_none').checked) {
			tls_accept |= <?= HOST_ENCRYPTION_NONE ?>;
		}
		if (form.querySelector('#tls_in_psk') && form.querySelector('#tls_in_psk').checked) {
			tls_accept |= <?= HOST_ENCRYPTION_PSK ?>;
		}
		if (form.querySelector('#tls_in_cert') && form.querySelector('#tls_in_cert').checked) {
			tls_accept |= <?= HOST_ENCRYPTION_CERTIFICATE ?>;
		}

		form.querySelector('#tls_accept').value = tls_accept;
	}

	// Remove error message.
	overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

	url = new Curl('zabbix.php', false),
	url.setArgument('action', action);
	url.setArgument('output', 'ajax');

	fetch(url.getUrl(), {
		method: 'post',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
		},
		body: $(form).serialize()
	})
	.then((response) => response.json())
	.then((response) => {
		if ('errors' in response) {
			overlay.unsetLoading();
			$(response.errors).insertBefore(form);
		}
		else {
			postMessageOk(response['title']);
			if ('messages' in response) {
				postMessageDetails('success', response.messages);
			}
			overlayDialogueDestroy(overlay.dialogueid);
			location.href = location_url;
		}
	});
}
