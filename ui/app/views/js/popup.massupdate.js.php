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

// Initialized massupdate form tabs.
$('#tabs').tabs();

$('#tabs').on('tabsactivate', (event, ui) => {
	$('#tabs').resize();
});

// Host groups.
<?php if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN): ?>
(() => {
	const groups_elem = document.querySelector('#groups-div');

	if (groups_elem === null) {
		return false;
	}

	const obj = groups_elem.tagName === 'SPAN' ? groups_elem.originalObject : groups_elem;
	const $groups_ms = $(groups_elem).find('#groups_');

	$groups_ms.on('change', (e) => {
		$groups_ms.multiSelect('setDisabledEntries',
			[... document.querySelectorAll('[name^="groups["]')].map((input) => input.value)
		);
	});

	[... obj.querySelectorAll('input[name=mass_update_groups]')].map((elem) => {
		elem.addEventListener('change', (e) => {
			const action_value = e.currentTarget.value;

			$groups_ms.multiSelect('modify', {
				'addNew': (action_value == <?= ZBX_ACTION_ADD ?> || action_value == <?= ZBX_ACTION_REPLACE ?>)
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
			$('.macro-input-group', $(obj.querySelector('#tbl_macros'))).macroValue();
			$('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>', $(obj.querySelector('#tbl_macros'))).textareaFlexible();
			obj.querySelector('#macro_add').scrollIntoView({block: 'nearest'});
		});

	$(obj.querySelector('#tbl_macros'))
		.find('.macro-input-group')
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

	const obj = template_visible.tagName === 'SPAN' ? template_visible.originalObject : template_visible;
	const mass_action_tpls = obj.querySelector('#mass_action_tpls');

	if (mass_action_tpls === null) {
		return false;
	}

	const $template_ms = $(template_visible).find('#templates_, #linked_templates_');

	$template_ms.on('change', (e) => {
		$template_ms.multiSelect('setDisabledEntries',
			[... template_visible.querySelectorAll('[name^="templates["], [name^="linked_templates["]')]
				.map((input) => input.value)
		);
	});

	mass_action_tpls.addEventListener('change', (e) => {
		const action = obj.querySelector('input[name="mass_action_tpls"]:checked').value;

		obj.querySelector('#mass_clear_tpls').disabled = (action === '<?= ZBX_ACTION_ADD ?>');
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

// Value maps.
(() => {
	const valuemap = document.querySelector('#valuemap-div');

	if (!valuemap) {
		return false;
	}

	let obj = valuemap;
	if (valuemap.tagName === 'SPAN') {
		obj = valuemap.originalObject;
	}

	obj.querySelectorAll('[name=valuemap_massupdate]').forEach((elem) => elem.addEventListener('click',
		(event) => toggleVisible(obj, event.currentTarget.value)
	));
	obj.querySelectorAll('.element-table-addfrom').forEach(elm => elm.addEventListener('click',
		(event) => openAddfromPopup(event.target)
	));

	$('#valuemap-rename-table').dynamicRows({
		template: '#valuemap-rename-row-tmpl',
		row: '.form_row',
		rows: [{from: '', to: ''}]
	});

	let overlay = overlays_stack.end();

	$(overlay.$dialogue||document).on('remove', () => {
		$(document).off('add.popup', processAddfromPopup);
	});
	$(document).on('add.popup', processAddfromPopup);

	function processAddfromPopup(ev, data) {
		let value = data.values[0];

		if (data.parentId === null) {
			new AddValueMap({
				name: value.name,
				mappings: value.mappings
			});
		}
	}

	function openAddfromPopup(element) {
		let disable_names = [];
		let valuemap_table = element.closest('table');

		valuemap_table.querySelectorAll('[name$="[name]"]').forEach((element) => disable_names.push(element.value));
		PopUp('popup.generic', {
			srctbl: 'valuemaps',
			srcfld1: 'valuemapid',
			disable_names: disable_names,
			editable: true
		}, {dialogue_class: 'modal-popup-generic', trigger_element: element});
	}

	function toggleVisible(obj, data_type) {
		obj.querySelectorAll('[data-type]').forEach((elm) => {
			elm.style.display = (elm.getAttribute('data-type').split(',').indexOf(data_type) != -1) ? '' : 'none';
		});
		$(window).resize();
	}

	toggleVisible(obj, obj.querySelector('[name=valuemap_massupdate]:checked').value);
})();

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
	let macros_removeall_warning = (form.querySelector('#visible_macros:checked')
		&& form.querySelector('[name="mass_update_macros"][value="<?= ZBX_ACTION_REMOVE_ALL ?>"]:checked')
		&& (form.querySelector('#macros_remove_all').checked === false)
	);
	let valuemaps_removeall_warning = (form.querySelector('#visible_valuemaps:checked')
		&& form.querySelector('[name="valuemap_massupdate"][value="<?= ZBX_ACTION_REMOVE_ALL ?>"]:checked')
		&& (form.querySelector('#valuemap_remove_all').checked === false)
	);
	let warning_message = '';

	if (macros_removeall_warning) {
		warning_message = <?= json_encode(_('Please confirm that you want to remove all macros.')) ?>;
	}
	else if (valuemaps_removeall_warning) {
		warning_message = <?= json_encode(_('Please confirm that you want to remove all value mappings.')) ?>;
	}

	if (warning_message !== '') {
		overlayDialogue({
			'title': <?= json_encode(_('Warning')) ?>,
			'type': 'popup',
			'class': 'position-middle',
			'content': $('<span>').text(warning_message),
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

	if (form.querySelector('#visible_valuemaps:checked')) {
		$(form).trimValues(['[name^="valuemap_rename["]']);
	}

	if (form.querySelector('#visible_tags:checked')) {
		$(form).trimValues(['[name^="tags"][name$="[tag]"]', '[name^="tags"][name$="[value]"]']);
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

	const url = new Curl('zabbix.php', false);
	url.setArgument('action', action);
	url.setArgument('output', 'ajax');

	fetch(url.getUrl(), {
		method: 'post',
		headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
		body: $(form).serialize()
	})
	.then((response) => response.json())
	.then((response) => {
		if ('script_inline' in response) {
			$('head').append(response.script_inline);
		}

		if ('errors' in response) {
			overlay.unsetLoading();
			$(response.errors).insertBefore(form);
		}
		else {
			postMessageOk(response.title);

			if ('messages' in response) {
				postMessageDetails('success', response.messages);
			}

			overlayDialogueDestroy(overlay.dialogueid);
			location.href = location_url;
		}
	});
}
