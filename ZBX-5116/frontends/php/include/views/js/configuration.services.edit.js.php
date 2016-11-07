<script type="text/javascript">
	jQuery(function() {
		jQuery('#showsla').change(function() {
			jQuery('#goodsla').prop('disabled', !this.checked);
		});

		jQuery('#algorithm').change(function() {
			var statusDisabled = (jQuery(this).val() == <?= SERVICE_ALGORITHM_NONE ?>);
			jQuery('#showsla, #trigger, #btn1, #goodsla').prop('disabled', statusDisabled);

			if (!statusDisabled) {
				jQuery('#showsla').change();
			}
		}).change();
	});

	function add_child_service(name, serviceid, trigger) {
		if (jQuery('#children_' + serviceid + '_serviceid').attr('id') == null) {
			var tr = document.createElement('tr');
			tr.setAttribute('id', 'children_' + serviceid);

			// column "name"
			var td = document.createElement('td');
			var inputServiceId = document.createElement('input');
			inputServiceId.setAttribute('type', 'hidden');
			inputServiceId.setAttribute('value', serviceid);
			inputServiceId.setAttribute('name', 'children[' + serviceid + '][serviceid]');
			inputServiceId.setAttribute('id', 'children_' + serviceid + '_serviceid');

			var inputName = document.createElement('input');
			inputName.setAttribute('type', 'hidden');
			inputName.setAttribute('value', name);
			inputName.setAttribute('name', 'children[' + serviceid + '][name]');
			inputName.setAttribute('id', 'children_' + serviceid + '_name');

			var inputTrigger = document.createElement('input');
			inputTrigger.setAttribute('type', 'hidden');
			inputTrigger.setAttribute('value', trigger);
			inputTrigger.setAttribute('name', 'children[' + serviceid + '][trigger]');
			inputTrigger.setAttribute('id', 'children_' + serviceid + '_trigger');

			var url = document.createElement('a');
			url.setAttribute('href', 'services.php?form=1&serviceid=' + serviceid);
			url.setAttribute('target', '_blank');
			url.appendChild(document.createTextNode(name));

			td.appendChild(inputServiceId);
			td.appendChild(inputName);
			td.appendChild(inputTrigger);
			td.appendChild(url);
			tr.appendChild(td);

			// column "soft"
			var td = document.createElement('td');
			var softCheckbox = document.createElement('input');
			softCheckbox.setAttribute('type', 'checkbox');
			softCheckbox.setAttribute('value', '1');
			softCheckbox.setAttribute('name', 'children[' + serviceid + '][soft]');
			softCheckbox.setAttribute('id', 'children_' + serviceid + '_soft');
			softCheckbox.setAttribute('class', '<?= ZBX_STYLE_CHECKBOX_RADIO ?>');

			var softCheckboxLabel = document.createElement('label');
			softCheckboxLabel.setAttribute('for', 'children_' + serviceid + '_soft');
			softCheckboxLabel.appendChild(document.createElement('span'));

			td.appendChild(softCheckbox);
			td.appendChild(softCheckboxLabel);
			tr.appendChild(td);

			// column "trigger"
			var td = document.createElement('td');
			td.appendChild(document.createTextNode(trigger));
			tr.appendChild(td);

			// column "action"
			var td = document.createElement('td');
			td.setAttribute('class', '<?= ZBX_STYLE_NOWRAP ?>');
			var inputRemove = document.createElement('button');
			inputRemove.setAttribute('class', '<?= ZBX_STYLE_BTN_LINK ?>');
			inputRemove.setAttribute('onclick', 'javascript: removeDependentChild(\'' + serviceid + '\');');
			inputRemove.appendChild(document.createTextNode(<?= CJs::encodeJson(_('Remove')) ?>));

			td.appendChild(inputRemove);
			tr.appendChild(td);
			document.getElementById('service_children').firstChild.appendChild(tr);
		}
	}

	function removeDependentChild(serviceid) {
		removeObjectById('children_' + serviceid);
		removeObjectById('children_' + serviceid + '_name');
		removeObjectById('children_' + serviceid + '_serviceid');
		removeObjectById('children_' + serviceid + '_trigger');
	}

	function removeTime(id) {
		var parent = jQuery('#times_' + id).parent();

		removeObjectById('times_' + id);
		removeObjectById('times_' + id + '_type');
		removeObjectById('times_' + id + '_from');
		removeObjectById('times_' + id + '_to');
		removeObjectById('times_' + id + '_note');
	}
</script>
