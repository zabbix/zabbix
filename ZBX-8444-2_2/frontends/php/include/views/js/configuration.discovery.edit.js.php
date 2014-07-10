<script type="text/x-jquery-tmpl" id="dcheckRowTPL">
	<tr id="dcheckRow_#{dcheckid}">
		<td id="dcheckCell_#{dcheckid}">
			<span class="bold">#{name}</span>
		</td>
		<td>
			<input type="button" class="input link_menu" name="edit" value="<?php echo CHtml::encode(_('Edit')); ?>"
				onclick="javascript: showNewCheckForm(null, null, '#{dcheckid}');" />
		</td>
		<td>
			<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>"
				onclick="javascript: removeDCheckRow('#{dcheckid}');" />
		</td>
	</tr>
</script>
<script type="text/x-jquery-tmpl" id="uniqRowTPL">
	<div id="uniqueness_criteria_row_#{dcheckid}">
		<input type="radio" id="uniqueness_criteria_#{dcheckid}" name="uniqueness_criteria" class="input radio"
			value="#{dcheckid}" />
		<label for="uniqueness_criteria_#{dcheckid}">#{name}</label>
	</div>
</script>
<script type="text/x-jquery-tmpl" id="newDCheckTPL">
	<div id="new_check_form">
		<div class="objectgroup inlineblock border_dotted ui-corner-all">
			<table class="formElementTable">
				<tbody>
				<tr>
					<td><label for="type"><?php echo _('Check type'); ?></label></td>
					<td><select id="type" name="type" class="input select"></select></td>
				</tr>
				<tr id="newCheckPortsRow" class="hidden">
					<td><label for="ports"><?php echo _('Port range'); ?></label></td>
					<td>
						<input type="text" id="ports" name="ports" value="" class="input text" size="16" maxlength="255">
					</td>
				</tr>
				<tr id="newCheckCommunityRow" class="hidden">
					<td><label for="snmp_community"><?php echo _('SNMP community'); ?></label></td>
					<td><input type="text" id="snmp_community" name="snmp_community" value="" class="input text"
							size="20" maxlength="255"></td>
				</tr>
				<tr id="newCheckKeyRow" class="hidden">
					<td><label for="key_"><?php echo _('SNMP Key'); ?></label></td>
					<td>
						<input type="text" id="key_" name="key_" value="" class="input text" size="20" maxlength="255">
					</td>
				</tr>
				<tr id="newCheckContextRow" class="hidden">
					<td><label for="snmpv3_contextname"><?php echo _('Context name'); ?></label></td>
					<td>
						<input type="text" id="snmpv3_contextname" name="snmpv3_contextname" value="" class="input text" size="20" maxlength="255">
					</td>
				</tr>
				<tr id="newCheckSecNameRow" class="hidden">
					<td><label for="snmpv3_securityname"><?php echo _('Security name'); ?></label></td>
					<td><input type="text" id="snmpv3_securityname" name="snmpv3_securityname" value=""
							class="input text" size="20" maxlength="64"></td>
				</tr>
				<tr id="newCheckSecLevRow" class="hidden">
					<td><label for="snmpv3_securitylevel"><?php echo _('Security level'); ?></label></td>
					<td>
						<select id="snmpv3_securitylevel" name="snmpv3_securitylevel" class="input select" size="1">
							<option value="0"><?php echo 'noAuthNoPriv'; ?> </option>
							<option value="1"><?php echo 'authNoPriv'; ?> </option>
							<option value="2"><?php echo 'authPriv'; ?> </option>
						</select>
					</td>
				</tr>
				<tr id="newCheckAuthProtocolRow" class="hidden">
					<td><label for="snmpv3_authprotocol"><?php echo _('Authentication protocol'); ?></label></td>
					<td>
						<div class="jqueryinputset">
							<input name="snmpv3_authprotocol" id="snmpv3_authprotocol_0" type="radio" checked="checked"
								value="<?php echo ITEM_AUTHPROTOCOL_MD5; ?>" />
							<input name="snmpv3_authprotocol" id="snmpv3_authprotocol_1" type="radio"
								value="<?php echo ITEM_AUTHPROTOCOL_SHA; ?>" />
							<label for="snmpv3_authprotocol_0"><?php echo _('MD5'); ?></label><label for="snmpv3_authprotocol_1"><?php echo _('SHA'); ?></label>
						</div>
					</td>
				</tr>
				<tr id="newCheckAuthPassRow" class="hidden">
					<td><label for="snmpv3_authpassphrase"><?php echo _('Authentication passphrase'); ?></label></td>
					<td><input type="text" id="snmpv3_authpassphrase" name="snmpv3_authpassphrase" value=""
							class="input text" size="20" maxlength="64"></td>
				</tr>
				<tr id="newCheckPrivProtocolRow" class="hidden">
					<td><label for="snmpv3_authprotocol"><?php echo _('Authentication protocol'); ?></label></td>
					<td>
						<div class="jqueryinputset">
							<input name="snmpv3_privprotocol" id="snmpv3_privprotocol_0" type="radio" checked="checked"
								value="<?php echo ITEM_PRIVPROTOCOL_DES; ?>" />
							<input name="snmpv3_privprotocol" id="snmpv3_privprotocol_1" type="radio"
								value="<?php echo ITEM_PRIVPROTOCOL_AES; ?>" />
							<label for="snmpv3_privprotocol_0"><?php echo _('DES'); ?></label><label for="snmpv3_privprotocol_1"><?php echo _('AES'); ?></label>
						</div>
					</td>
				</tr>
				<tr id="newCheckPrivPassRow" class="hidden">
					<td><label for="snmpv3_privpassphrase"><?php echo _('Privacy passphrase'); ?></label></td>
					<td><input type="text" id="snmpv3_privpassphrase" name="snmpv3_privpassphrase" value=""
							class="input text" size="20" maxlength="64"></td>
				</tr>
				</tbody>
			</table>
			<table class="formElementTable">
				<tr>
					<td>
						<input type="button" id="add_new_dcheck" name="add_new_dcheck" class="input button link_menu"
							value="<?php echo CHtml::encode(_('Add')); ?>" />
					</td>
					<td>
						<input type="button" id="cancel_new_dcheck" name="cancel_new_dcheck" class="input button link_menu"
							value="<?php echo CHtml::encode(_('Cancel')); ?>" />
					</td>
				</tr>
			</table>
		</div>
	</div>
</script>
<script type="text/javascript">
	var ZBX_SVC = {
		ssh: <?php echo SVC_SSH; ?>,
		ldap: <?php echo SVC_LDAP; ?>,
		smtp: <?php echo SVC_SMTP; ?>,
		ftp: <?php echo SVC_FTP; ?>,
		http: <?php echo SVC_HTTP; ?>,
		pop: <?php echo SVC_POP; ?>,
		nntp: <?php echo SVC_NNTP; ?>,
		imap: <?php echo SVC_IMAP; ?>,
		tcp: <?php echo SVC_TCP; ?>,
		agent: <?php echo SVC_AGENT; ?>,
		snmpv1: <?php echo SVC_SNMPv1; ?>,
		snmpv2: <?php echo SVC_SNMPv2c; ?>,
		snmpv3: <?php echo SVC_SNMPv3; ?>,
		icmp: <?php echo SVC_ICMPPING; ?>,
		https: <?php echo SVC_HTTPS; ?>,
		telnet: <?php echo SVC_TELNET; ?>
	};

	var ZBX_CHECKLIST = {};

	function discoveryCheckDefaultPort(service) {
		var defPorts = {};
		defPorts[ZBX_SVC.ssh] = '22';
		defPorts[ZBX_SVC.ldap] = '389';
		defPorts[ZBX_SVC.smtp] = '25';
		defPorts[ZBX_SVC.ftp] = '21';
		defPorts[ZBX_SVC.http] = '80';
		defPorts[ZBX_SVC.pop] = '110';
		defPorts[ZBX_SVC.nntp] = '119';
		defPorts[ZBX_SVC.imap] = '143';
		defPorts[ZBX_SVC.tcp] = '0';
		defPorts[ZBX_SVC.icmp] = '0';
		defPorts[ZBX_SVC.agent] = '10050';
		defPorts[ZBX_SVC.snmpv1] = '161';
		defPorts[ZBX_SVC.snmpv2] = '161';
		defPorts[ZBX_SVC.snmpv3] = '161';
		defPorts[ZBX_SVC.https] = '443';
		defPorts[ZBX_SVC.telnet] = '23';

		service = service.toString();

		return isset(service, defPorts) ? defPorts[service] : 0;
	}

	function discoveryCheckTypeToString(svcPort) {
		var defPorts = {};
		defPorts[ZBX_SVC.ftp] = <?php echo CJs::encodeJson(_('FTP')); ?>;
		defPorts[ZBX_SVC.http] = <?php echo CJs::encodeJson(_('HTTP')); ?>;
		defPorts[ZBX_SVC.https] = <?php echo CJs::encodeJson(_('HTTPS')); ?>;
		defPorts[ZBX_SVC.icmp] = <?php echo CJs::encodeJson(_('ICMP ping')); ?>;
		defPorts[ZBX_SVC.imap] = <?php echo CJs::encodeJson(_('IMAP')); ?>;
		defPorts[ZBX_SVC.tcp] = <?php echo CJs::encodeJson(_('TCP')); ?>;
		defPorts[ZBX_SVC.ldap] = <?php echo CJs::encodeJson(_('LDAP')); ?>;
		defPorts[ZBX_SVC.nntp] = <?php echo CJs::encodeJson(_('NNTP')); ?>;
		defPorts[ZBX_SVC.pop] = <?php echo CJs::encodeJson(_('POP')); ?>;
		defPorts[ZBX_SVC.snmpv1] = <?php echo CJs::encodeJson(_('SNMPv1 agent')); ?>;
		defPorts[ZBX_SVC.snmpv2] = <?php echo CJs::encodeJson(_('SNMPv2 agent')); ?>;
		defPorts[ZBX_SVC.snmpv3] = <?php echo CJs::encodeJson(_('SNMPv3 agent')); ?>;
		defPorts[ZBX_SVC.smtp] = <?php echo CJs::encodeJson(_('SMTP')); ?>;
		defPorts[ZBX_SVC.ssh] = <?php echo CJs::encodeJson(_('SSH')); ?>;
		defPorts[ZBX_SVC.telnet] = <?php echo CJs::encodeJson(_('Telnet')); ?>;
		defPorts[ZBX_SVC.agent] = <?php echo CJs::encodeJson(_('Zabbix agent')); ?>;

		if (typeof svcPort === 'undefined') {
			return defPorts;
		}

		svcPort = parseInt(svcPort, 10);

		return isset(svcPort, defPorts) ? defPorts[svcPort] : <?php echo CJs::encodeJson(_('Unknown')); ?>;
	}

	function toggleInputs(id, state) {
		jQuery('#' + id).toggle(state);

		if (state) {
			jQuery('#' + id + ' :input').prop('disabled', false);
		}
		else {
			jQuery('#' + id + ' :input').prop('disabled', true);
		}
	}

	function addPopupValues(list) {
		// templates
		var dcheckRowTpl = new Template(jQuery('#dcheckRowTPL').html()),
			uniqRowTpl = new Template(jQuery('#uniqRowTPL').html());

		for (var i = 0; i < list.length; i++) {
			if (empty(list[i])) {
				continue;
			}

			var value = list[i];

			if (typeof value.dcheckid === 'undefined') {
				value.dcheckid = getUniqueId();
			}

			// add
			if (typeof ZBX_CHECKLIST[value.dcheckid] === 'undefined') {
				ZBX_CHECKLIST[value.dcheckid] = value;

				jQuery('#dcheckListFooter').before(dcheckRowTpl.evaluate(value));

				for (var fieldName in value) {
					if (typeof value[fieldName] === 'string') {
						var input = jQuery('<input>', {
							name: 'dchecks[' + value.dcheckid + '][' + fieldName + ']',
							type: 'hidden',
							value: value[fieldName]
						});

						jQuery('#dcheckCell_' + value.dcheckid).append(input);
					}
				}
			}

			// update
			else {
				ZBX_CHECKLIST[value.dcheckid] = value;

				var ignoreNames = ['druleid', 'dcheckid', 'name', 'ports', 'type', 'uniq'];

				// clean values
				jQuery('#dcheckCell_' + value.dcheckid + ' input').each(function(i, item) {
					var itemObj = jQuery(item);

					var name = itemObj.attr('name').replace('dchecks[' + value.dcheckid + '][', '');
					name = name.substring(0, name.length - 1);

					if (jQuery.inArray(name, ignoreNames) == -1) {
						itemObj.remove();
					}
				});

				// set values
				for (var fieldName in value) {
					if (typeof value[fieldName] === 'string') {
						var obj = jQuery('input[name="dchecks[' + value.dcheckid + '][' + fieldName + ']"]');

						if (obj.length) {
							obj.val(value[fieldName]);
						}
						else {
							var input = jQuery('<input>', {
								name: 'dchecks[' + value.dcheckid + '][' + fieldName + ']',
								type: 'hidden',
								value: value[fieldName]
							});

							jQuery('#dcheckCell_' + value.dcheckid).append(input);
						}
					}
				}

				// update check name
				jQuery('#dcheckCell_' + value.dcheckid + ' .bold').text(value['name']);
			}

			// update device uniqueness criteria
			var availableDeviceTypes = [ZBX_SVC.agent, ZBX_SVC.snmpv1, ZBX_SVC.snmpv2, ZBX_SVC.snmpv3],
				uniquenessCriteria = jQuery('#uniqueness_criteria_row_' + value.dcheckid);

			if (jQuery.inArray(parseInt(value.type, 10), availableDeviceTypes) > -1) {
				if (uniquenessCriteria.length) {
					jQuery('label[for=uniqueness_criteria_' + value.dcheckid + ']').text(value['name']);
				}
				else {
					jQuery('#uniqList').append(uniqRowTpl.evaluate(value));
				}
			}
			else {
				if (uniquenessCriteria.length) {
					uniquenessCriteria.remove();

					selectUniquenessCriteriaDefault();
				}
			}
		}
	}

	function removeDCheckRow(dcheckid) {
		jQuery('#dcheckRow_' + dcheckid).remove();

		delete(ZBX_CHECKLIST[dcheckid]);

		// remove uniqueness criteria
		var obj = jQuery('#uniqueness_criteria_' + dcheckid);

		if (obj.length) {
			if (obj.attr('checked') == 'checked') {
				selectUniquenessCriteriaDefault();
			}

			jQuery('#uniqueness_criteria_row_' + dcheckid).remove();
		}
	}

	function showNewCheckForm(e, dcheckType, dcheckId) {
		var isUpdate = (typeof dcheckId !== 'undefined');

		// remove existing form
		jQuery('#new_check_form').remove();

		if (jQuery('#new_check_form').length == 0) {
			var tpl = new Template(jQuery('#newDCheckTPL').html());

			jQuery('#dcheckList').after(tpl.evaluate());

			// display fields dependent from type
			jQuery('#type').change(function() {
				updateNewDCheckType(dcheckId);
			});

			// display addition snmpv3 security level fields dependent from snmpv3 security level
			jQuery('#snmpv3_securitylevel').change(updateNewDCheckSNMPType);

			// button "add"
			jQuery('#add_new_dcheck').click(function() {
				saveNewDCheckForm(dcheckId);
			});

			// rename button to "update"
			if (isUpdate) {
				jQuery('#add_new_dcheck').val(<?php echo CJs::encodeJson(_('Update')); ?>);
			}

			// button "remove" form
			jQuery('#cancel_new_dcheck').click(function() {
				jQuery('#new_check_form').remove();
			});

			// port name sorting
			var svcPorts = discoveryCheckTypeToString(),
				portNameSvcValue = {},
				portNameOrder = [];

			for (var key in svcPorts) {
				portNameOrder.push(svcPorts[key]);
				portNameSvcValue[svcPorts[key]] = key;
			}

			portNameOrder.sort();

			for (var i = 0; i < portNameOrder.length; i++) {
				var portName = portNameOrder[i];

				jQuery('#type').append(jQuery('<option>', {
					value: portNameSvcValue[portName],
					text: portName
				}));
			}
		}

		// restore form values
		if (isUpdate) {
			jQuery('#dcheckCell_' + dcheckId + ' input').each(function(i, item) {
				var itemObj = jQuery(item);

				var name = itemObj.attr('name').replace('dchecks[' + dcheckId + '][', '');
				name = name.substring(0, name.length - 1);

				// ignore "name" value because it is virtual
				if (name !== 'name') {
					jQuery('#' + name).val(itemObj.val());

					// set radio button value
					var radioObj = jQuery('input[name=' + name + ']');

					if (radioObj.attr('type') == 'radio') {
						radioObj.removeAttr('checked');

						jQuery('#' + name + '_' + itemObj.val()).attr('checked', 'checked');
					}
				}
			});
		}

		updateNewDCheckType(dcheckId);
	}

	function updateNewDCheckType(dcheckId) {
		var dcheckType = parseInt(jQuery('#type').val(), 10);

		var keyRowTypes = {};
		keyRowTypes[ZBX_SVC.agent] = true;
		keyRowTypes[ZBX_SVC.snmpv1] = true;
		keyRowTypes[ZBX_SVC.snmpv2] = true;
		keyRowTypes[ZBX_SVC.snmpv3] = true;

		var comRowTypes = {};
		comRowTypes[ZBX_SVC.snmpv1] = true;
		comRowTypes[ZBX_SVC.snmpv2] = true;

		var secNameRowTypes = {};
		secNameRowTypes[ZBX_SVC.snmpv3] = true;

		toggleInputs('newCheckPortsRow', (ZBX_SVC.icmp != dcheckType));
		toggleInputs('newCheckKeyRow', isset(dcheckType, keyRowTypes));

		if (isset(dcheckType, keyRowTypes)) {
			var caption = (dcheckType == ZBX_SVC.agent)
				? <?php echo CJs::encodeJson(_('Key')); ?>
				: <?php echo CJs::encodeJson(_('SNMP OID')); ?>;

			jQuery('#newCheckKeyRow label').text(caption);
		}

		toggleInputs('newCheckCommunityRow', isset(dcheckType, comRowTypes));
		toggleInputs('newCheckSecNameRow', isset(dcheckType, secNameRowTypes));
		toggleInputs('newCheckSecLevRow', isset(dcheckType, secNameRowTypes));
		toggleInputs('newCheckContextRow', isset(dcheckType, secNameRowTypes));

		// get old type
		var oldType = jQuery('#type').data('oldType');

		jQuery('#type').data('oldType', dcheckType);

		// type is changed
		if (ZBX_SVC.icmp != dcheckType && typeof oldType !== 'undefined' && dcheckType != oldType) {
			// reset values
			var snmpTypes = [ZBX_SVC.snmpv1, ZBX_SVC.snmpv2, ZBX_SVC.snmpv3],
				ignoreNames = ['druleid', 'name', 'ports', 'type'];

			if (jQuery.inArray(dcheckType, snmpTypes) !== -1 && jQuery.inArray(oldType, snmpTypes) !== -1) {
				// ignore value reset when changing type from snmp's
			}
			else {
				jQuery('#new_check_form input[type="text"]').each(function(i, item) {
					var itemObj = jQuery(item);

					if (jQuery.inArray(itemObj.attr('id'), ignoreNames) < 0) {
						itemObj.val('');
					}
				});

				// reset port to default
				jQuery('#ports').val(discoveryCheckDefaultPort(dcheckType));
			}
		}

		// set default port
		if (jQuery('#ports').val() == '') {
			jQuery('#ports').val(discoveryCheckDefaultPort(dcheckType));
		}

		updateNewDCheckSNMPType();
	}

	function updateNewDCheckSNMPType() {
		var dcheckType = parseInt(jQuery('#type').val(), 10),
			dcheckSecLevType = parseInt(jQuery('#snmpv3_securitylevel').val(), 10);

		var secNameRowTypes = {};
		secNameRowTypes[ZBX_SVC.snmpv3] = true;

		var showAuthProtocol = (isset(dcheckType, secNameRowTypes)
			&& (dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV; ?>
				|| dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV; ?>));
		var showAuthPass = (isset(dcheckType, secNameRowTypes)
			&& (dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV; ?>
				|| dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV; ?>));
		var showPrivProtocol = (isset(dcheckType, secNameRowTypes)
			&& dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV; ?>);
		var showPrivPass = (isset(dcheckType, secNameRowTypes)
			&& dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV; ?>);

		toggleInputs('newCheckAuthProtocolRow', showAuthProtocol);
		toggleInputs('newCheckAuthPassRow', showAuthPass);
		toggleInputs('newCheckPrivProtocolRow', showPrivProtocol);
		toggleInputs('newCheckPrivPassRow', showPrivPass);

		if (showAuthProtocol) {
			jQuery('#newCheckAuthProtocolRow .jqueryinputset').buttonset();
		}
		if (showPrivProtocol) {
			jQuery('#newCheckPrivProtocolRow .jqueryinputset').buttonset();
		}
	}

	function saveNewDCheckForm(dcheckId) {
		var dCheck = jQuery('#new_check_form :input:enabled').serializeJSON();

		// get check id
		dCheck.dcheckid = (typeof dcheckId === 'undefined') ? getUniqueId() : dcheckId;

		// check for duplicates
		for (var zbxDcheckId in ZBX_CHECKLIST) {
			if (typeof dcheckId === 'undefined' || (typeof dcheckId !== 'undefined') && dcheckId != zbxDcheckId) {
				if ((typeof dCheck['key_'] === 'undefined' || ZBX_CHECKLIST[zbxDcheckId]['key_'] === dCheck['key_'])
						&& (typeof dCheck['type'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['type'] === dCheck['type'])
						&& (typeof dCheck['ports'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['ports'] === dCheck['ports'])
						&& (typeof dCheck['snmp_community'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmp_community'] === dCheck['snmp_community'])
						&& (typeof dCheck['snmpv3_authprotocol'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_authprotocol'] === dCheck['snmpv3_authprotocol'])
						&& (typeof dCheck['snmpv3_authpassphrase'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_authpassphrase'] === dCheck['snmpv3_authpassphrase'])
						&& (typeof dCheck['snmpv3_privprotocol'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_privprotocol'] === dCheck['snmpv3_privprotocol'])
						&& (typeof dCheck['snmpv3_privpassphrase'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_privpassphrase'] === dCheck['snmpv3_privpassphrase'])
						&& (typeof dCheck['snmpv3_securitylevel'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_securitylevel'] === dCheck['snmpv3_securitylevel'])
						&& (typeof dCheck['snmpv3_securityname'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_securityname'] === dCheck['snmpv3_securityname'])
						&& (typeof dCheck['snmpv3_contextname'] === 'undefined'
							|| ZBX_CHECKLIST[zbxDcheckId]['snmpv3_contextname'] === dCheck['snmpv3_contextname'])) {
					showModalWindow(
						t(<?php echo CJs::encodeJson(_('Discovery check error')); ?>),
						<?php echo CJs::encodeJson(_('Check already exists.')); ?>,
						[{text: t(<?php echo CJs::encodeJson(_('Close')); ?>), click: function() {
							jQuery(this).dialog('destroy');
						}}]
					);

					return null;
				}
			}
		}

		// validate
		var validationErrors = [],
			ajaxChecks = {
				ajaxaction: 'validate',
				ajaxdata: []
			};

		switch (parseInt(dCheck.type, 10)) {
			case ZBX_SVC.agent:
				ajaxChecks.ajaxdata.push({
					field: 'itemKey',
					value: dCheck.key_
				});
				break;
			case ZBX_SVC.snmpv1:
			case ZBX_SVC.snmpv2:
				if (dCheck.snmp_community == '') {
					validationErrors.push(<?php echo CJs::encodeJson(_('Incorrect SNMP community.')); ?>);
				}
			case ZBX_SVC.snmpv3:
				if (dCheck.key_ == '') {
					validationErrors.push(<?php echo CJs::encodeJson(_('Incorrect SNMP OID.')); ?>);
				}
				break;
		}

		if (dCheck.type != ZBX_SVC.icmp) {
			ajaxChecks.ajaxdata.push({
				field: 'port',
				value: dCheck.ports
			});
		}

		var jqxhr;

		if (ajaxChecks.ajaxdata.length > 0) {
			jQuery('#add_new_dcheck').prop('disabled', true);

			var url = new Curl();
			jqxhr = jQuery.ajax({
				url: url.getPath() + '?output=ajax&sid=' + url.getArgument('sid'),
				data: ajaxChecks,
				dataType: 'json',
				success: function(result) {
					if (!result.result) {
						jQuery.each(result.errors, function(i, val) {
							validationErrors.push(val.error);
						});
					}
				},
				error: function() {
					showModalWindow(
						t(<?php echo CJs::encodeJson(_('Discovery check error')); ?>),
						<?php echo CJs::encodeJson(_('Cannot validate discovery check: invalid request or connection to Zabbix server failed.')); ?>,
						[{text: t(<?php echo CJs::encodeJson(_('Close')); ?>), click: function() {
							jQuery(this).dialog('destroy');
						}}]
					);

					jQuery('#add_new_dcheck').prop('disabled', false);
				}
			});
		}

		jQuery.when(jqxhr).done(function() {
			jQuery('#add_new_dcheck').prop('disabled', false);

			if (validationErrors.length) {
				showModalWindow(
					t(<?php echo CJs::encodeJson(_('Discovery check error')); ?>),
					validationErrors.join('\n'),
					[{text: t(<?php echo CJs::encodeJson(_('Close')); ?>), click: function() {
						jQuery(this).dialog('destroy');
					}}]
				);
			}
			else {
				dCheck.name = jQuery('#type :selected').text();

				if (typeof dCheck.ports !== 'undefined' && dCheck.ports != discoveryCheckDefaultPort(dCheck.type)) {
					dCheck.name += ' (' + dCheck.ports + ')';
				}
				if (dCheck.key_) {
					dCheck.name += ' "' + dCheck.key_ + '"';
				}

				addPopupValues([dCheck]);

				jQuery('#new_check_form').remove();
			}
		});
	}

	function selectUniquenessCriteriaDefault() {
		jQuery('#uniqueness_criteria_ip').attr('checked', 'checked');
	}

	jQuery(document).ready(function() {
		addPopupValues(<?php echo zbx_jsvalue(array_values($this->data['drule']['dchecks'])); ?>);

		jQuery("input:radio[name='uniqueness_criteria'][value=<?php echo zbx_jsvalue($this->data['drule']['uniqueness_criteria']); ?>]").attr('checked', 'checked');

		jQuery('#newCheck').click(showNewCheckForm);
		jQuery('#clone').click(function() {
			jQuery('#druleid, #delete, #clone').remove();
			jQuery('#cancel').addClass('ui-corner-left');
			jQuery('#form').val('clone');
			jQuery('#name').focus();
		});
	});

	(function($) {
		$.fn.serializeJSON = function() {
			var json = {};

			jQuery.map($(this).serializeArray(), function(n, i) {
				json[n['name']] = n['value'];
			});

			return json;
		};
	})(jQuery);
</script>
