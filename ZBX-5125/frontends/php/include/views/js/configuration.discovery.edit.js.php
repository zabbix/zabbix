<script type="text/x-jquery-tmpl" id="dcheckRowTPL">
	<tr id="dcheckRow_#{dcheckid}">
		<td id="dcheckCell_#{dcheckid}">
			<span class="bold"> #{name} </span>
		</td>
		<td>
			<input type="button" class="input link_menu" name="remove" value="<?php echo CHtml::encode(_('Remove')); ?>" onclick="javascript: removeDCheckRow(#{dcheckid});" />
		</td>
	</tr>
</script>
<script type="text/x-jquery-tmpl" id="uniqRowTPL">
	<div id="uniqueness_criteria_row_#{dcheckid}">
		<input type="radio" id="uniqueness_criteria_#{dcheckid}" name="uniqueness_criteria" value="#{dcheckid}" class="input radio">
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
					<td><input type="text" id="ports" name="ports" value="" class="input text" size="16" maxlength="255"></td>
				</tr>
				<tr id="newCheckCommunityRow" class="hidden">
					<td><label for="snmp_community"><?php echo _('SNMP community'); ?></label></td>
					<td><input type="text" id="snmp_community" name="snmp_community" value="" class="input text"
							size="20" maxlength="255"></td>
				</tr>
				<tr id="newCheckKeyRow" class="hidden">
					<td><label for="key_"><?php echo _('SNMP Key'); ?></label></td>
					<td><input type="text" id="key_" name="key_" value="" class="input text" size="20" maxlength="255"></td>
				</tr>
				<tr id="newCheckSecNameRow" class="hidden">
					<td><label for="snmpv3_securityname"><?php echo _('SNMPv3 security name'); ?></label></td>
					<td><input type="text" id="snmpv3_securityname" name="snmpv3_securityname" value=""
							class="input text" size="20" maxlength="64"></td>
				</tr>
				<tr id="newCheckSecLevRow" class="hidden">
					<td><label for="snmpv3_securitylevel"><?php echo _('SNMPv3 security level'); ?></label></td>
					<td>
						<select id="snmpv3_securitylevel" name="snmpv3_securitylevel" class="input select" size="1">
							<option value="0"><?php echo 'noAuthNoPriv'; ?> </option>
							<option value="1"><?php echo 'authNoPriv'; ?> </option>
							<option value="2"><?php echo 'authPriv'; ?> </option>
						</select>
					</td>
				</tr>
				<tr id="newCheckAuthPassRow" class="hidden">
					<td><label for="snmpv3_authpassphrase"><?php echo _('SNMPv3 auth passphrase'); ?></label></td>
					<td><input type="text" id="snmpv3_authpassphrase" name="snmpv3_authpassphrase" value=""
							class="input text" size="20" maxlength="64"></td>
				</tr>
				<tr id="newCheckPrivPassRow" class="hidden">
					<td><label for="snmpv3_privpassphrase"><?php echo _('SNMPv3 priv passphrase'); ?></label></td>
					<td><input type="text" id="snmpv3_privpassphrase" name="snmpv3_privpassphrase" value=""
							class="input text" size="20" maxlength="64"></td>
				</tr>
				</tbody>
			</table>
			<input type="button" id="add_new_dcheck" name="add_new_dcheck" value="<?php echo CHtml::encode(_('Add')); ?>"
				class="input button link_menu">
			&nbsp;&nbsp;
			<input type="button" id="cancel_new_dcheck" name="cancel_new_dcheck" value="<?php echo CHtml::encode(_('Cancel')); ?>"
				class="input button link_menu">
		</div>
	</div>
</script>
<script type="text/javascript">
	var ZBX_SVC = {
		'ssh': <?php echo SVC_SSH; ?>,
		'ldap': <?php echo SVC_LDAP; ?>,
		'smtp': <?php echo SVC_SMTP; ?>,
		'ftp': <?php echo SVC_FTP; ?>,
		'http': <?php echo SVC_HTTP; ?>,
		'pop': <?php echo SVC_POP; ?>,
		'nntp': <?php echo SVC_NNTP; ?>,
		'imap': <?php echo SVC_IMAP; ?>,
		'tcp': <?php echo SVC_TCP; ?>,
		'agent': <?php echo SVC_AGENT; ?>,
		'snmpv1': <?php echo SVC_SNMPv1; ?>,
		'snmpv2': <?php echo SVC_SNMPv2c; ?>,
		'snmpv3': <?php echo SVC_SNMPv3; ?>,
		'icmp': <?php echo SVC_ICMPPING; ?>,
		'https': <?php echo SVC_HTTPS; ?>,
		'telnet': <?php echo SVC_TELNET; ?>
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

		if (typeof(svcPort) == 'undefined') {
			return defPorts;
		}
		svcPort = parseInt(svcPort, 10);
		return isset(svcPort, defPorts) ? defPorts[svcPort] : <?php echo CJs::encodeJson(_('Unknown')); ?>;
	}

	function toggleInputs(id, state) {
		jQuery('#'+id).toggle(state);
		if (state) {
			jQuery('#'+id+' :input').prop('disabled', false);
		}
		else {
			jQuery('#'+id+' :input').prop('disabled', true);
		}
	}

	function addPopupValues(list) {
		var uniqTypeList = Array(ZBX_SVC.agent, ZBX_SVC.snmpv1, ZBX_SVC.snmpv2, ZBX_SVC.snmpv3);
		var dcheckRowTpl = new Template(jQuery('#dcheckRowTPL').html());
		var uniqRowTpl = new Template(jQuery('#uniqRowTPL').html());

		for (var i = 0; i < list.length; i++) {
			if (empty(list[i])) {
				continue;
			}
			var value = list[i];
			ZBX_CHECKLIST[value.dcheckid] = value;

			jQuery('#dcheckListFooter').before(dcheckRowTpl.evaluate(value));
			for (var fieldname in value) {
				jQuery('#dcheckCell_'+value.dcheckid).append('<input name="dchecks['+value.dcheckid+']['+fieldname+']" type="hidden" value="'+value[fieldname]+'" />');
			}
			if (jQuery.inArray(parseInt(value.type, 10), uniqTypeList) !== -1) {
				jQuery('#uniqList').append(uniqRowTpl.evaluate(value));
			}
		}
	}

	function removeDCheckRow(dcheckid) {
		jQuery('#dcheckRow_'+dcheckid).remove();
		if (jQuery('#uniqueness_criteria_'+dcheckid).is(':checked')) {
			jQuery('#uniqueness_criteria_1').attr('checked', 'checked');
		}
		jQuery('#uniqueness_criteria_row_'+dcheckid).remove();

		delete(ZBX_CHECKLIST[dcheckid]);
	}

	function showNewCheckForm(e, dcheckType) {
		if (jQuery('#new_check_form').length == 0) {
			var tpl = new Template(jQuery('#newDCheckTPL').html());

			jQuery('#dcheckList').after(tpl.evaluate());
			jQuery('#type').change(updateNewDCheckType);
			jQuery('#snmpv3_securitylevel').change(updateNewDCheckSNMPType);
			jQuery('#add_new_dcheck').click(saveNewDCheckForm);
			jQuery('#cancel_new_dcheck').click(function() {
				jQuery('#new_check_form').remove();
			});

			// port name sorting
			var svcPorts = discoveryCheckTypeToString();
			var portNameSvcValue = {};
			var portNameOrder = [];
			for (var key in svcPorts) {
				portNameOrder.push(svcPorts[key]);
				portNameSvcValue[svcPorts[key]] = key;
			}
			portNameOrder.sort();

			for (var i = 0; i < portNameOrder.length; i++) {
				var portName = portNameOrder[i];
				jQuery('#type').append(jQuery('<option>').attr({'value': portNameSvcValue[portName]}).text(portName));
			}
		}
		updateNewDCheckType(e);
	}

	function updateNewDCheckType(e) {
		var dcheckType = parseInt(jQuery('#type').val(), 10);
		var keyRowTypes = {};
		keyRowTypes[ZBX_SVC.agent] = true;
		keyRowTypes[ZBX_SVC.snmpv1] = true;
		keyRowTypes[ZBX_SVC.snmpv2] = true;
		keyRowTypes[ZBX_SVC.snmpv3] = true;

		var ComRowTypes = {};
		ComRowTypes[ZBX_SVC.snmpv1] = true;
		ComRowTypes[ZBX_SVC.snmpv2] = true;

		var SecNameRowTypes = {};
		SecNameRowTypes[ZBX_SVC.snmpv3] = true;

		toggleInputs('newCheckPortsRow', (ZBX_SVC.icmp != dcheckType));
		toggleInputs('newCheckKeyRow', isset(dcheckType, keyRowTypes));

		if (isset(dcheckType, keyRowTypes)) {
			var caption = (dcheckType == ZBX_SVC.agent) ? <?php echo CJs::encodeJson(_('Key')); ?> : <?php echo CJs::encodeJson(_('SNMP OID')); ?>;
			jQuery('#newCheckKeyRow label').text(caption);
		}
		toggleInputs('newCheckCommunityRow', isset(dcheckType, ComRowTypes));
		toggleInputs('newCheckSecNameRow', isset(dcheckType, SecNameRowTypes));
		toggleInputs('newCheckSecLevRow', isset(dcheckType, SecNameRowTypes));

		if (ZBX_SVC.icmp != dcheckType) {
			jQuery('#ports').val(discoveryCheckDefaultPort(dcheckType));
		}
		updateNewDCheckSNMPType(e);
	}

	function updateNewDCheckSNMPType(e) {
		var dcheckType = parseInt(jQuery('#type').val(), 10);
		var dcheckSecLevType = parseInt(jQuery('#snmpv3_securitylevel').val(), 10);

		var SecNameRowTypes = {};
		SecNameRowTypes[ZBX_SVC.snmpv3] = true;

		var showAuthPass = (isset(dcheckType, SecNameRowTypes)
			&& (dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV; ?>
				|| dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV; ?>));
		var showPrivPass = (isset(dcheckType, SecNameRowTypes)
			&& dcheckSecLevType == <?php echo ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV; ?>);

		toggleInputs('newCheckAuthPassRow', showAuthPass);
		toggleInputs('newCheckPrivPassRow', showPrivPass);
	}

	function saveNewDCheckForm(e) {
		var dCheck = jQuery('#new_check_form :input:enabled').serializeJSON();
		dCheck.dcheckid = jQuery('#dcheckList tr[id^=dcheckRow_]').length;
		while (jQuery('#uniqueness_criteria_'+dCheck.dcheckid).length || jQuery('#dcheckRow_'+dCheck.dcheckid).length) {
			dCheck.dcheckid++;
		}

		for (var dcheckid in ZBX_CHECKLIST) {
			if ((typeof dCheck['key_'] == 'undefined' || ZBX_CHECKLIST[dcheckid]['key_'] === dCheck['key_'])
					&& (typeof dCheck['type'] == 'undefined' || ZBX_CHECKLIST[dcheckid]['type'] === dCheck['type'])
					&& (typeof dCheck['ports'] == 'undefined' || ZBX_CHECKLIST[dcheckid]['ports'] === dCheck['ports'])
					&& (typeof dCheck['snmp_community'] == 'undefined'
						|| ZBX_CHECKLIST[dcheckid]['snmp_community'] === dCheck['snmp_community'])
					&& (typeof dCheck['snmpv3_authpassphrase'] == 'undefined'
						|| ZBX_CHECKLIST[dcheckid]['snmpv3_authpassphrase'] === dCheck['snmpv3_authpassphrase'])
					&& (typeof dCheck['snmpv3_privpassphrase'] == 'undefined'
						|| ZBX_CHECKLIST[dcheckid]['snmpv3_privpassphrase'] === dCheck['snmpv3_privpassphrase'])
					&& (typeof dCheck['snmpv3_securitylevel'] == 'undefined'
						|| ZBX_CHECKLIST[dcheckid]['snmpv3_securitylevel'] === dCheck['snmpv3_securitylevel'])
					&& (typeof dCheck['snmpv3_securityname'] == 'undefined'
						|| ZBX_CHECKLIST[dcheckid]['snmpv3_securityname'] === dCheck['snmpv3_securityname'])) {
				alert(<?php echo CJs::encodeJson(_('Check already exists.')); ?>);
				return null;
			}
		}

		var ajaxChecks = {
			ajaxaction: 'validate',
			ajaxdata: []
		};
		var validationErrors = [];

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

		function ajaxValidation() {
			if (ajaxChecks.ajaxdata.length) {
				jQuery('#add_new_dcheck').prop('disabled', true);
				var url = new Curl();

				return jQuery.ajax({
					url: url.getPath()+'?output=ajax&sid='+url.getArgument('sid'),
					data: ajaxChecks,
					success: function(result) {
						if (!result.result) {
							jQuery.each(result.errors, function(i, val) {
								validationErrors.push(val.error);
							});
						}
					},
					error: function() {
						alert('AJAX request error');
						jQuery('#add_new_dcheck').prop('disabled', false);
					},
					dataType: 'json'
				});
			}
			else {
				return true;
			}
		}

		jQuery.when(ajaxValidation()).done(function() {
			if (validationErrors.length) {
				alert(validationErrors.join('\n'));
				jQuery('#add_new_dcheck').prop('disabled', false);
			}
			else {
				dCheck.name = jQuery('#type :selected').text();
				if (typeof dCheck.ports != 'undefined' && dCheck.ports != discoveryCheckDefaultPort(dCheck.type)) {
					dCheck.name += ' ('+dCheck.ports+')';
				}
				if (dCheck.key_) {
					dCheck.name += ' "'+dCheck.key_+'"';
				}
				addPopupValues([dCheck]);
				jQuery('#new_check_form').remove();
			}
		});
	}

	jQuery(document).ready(function() {
		addPopupValues(<?php echo zbx_jsvalue(array_values($this->data['drule']['dchecks'])); ?>);
		jQuery("input:radio[name='uniqueness_criteria'][value=<?php echo zbx_jsvalue($this->data['drule']['uniqueness_criteria']); ?>]").attr('checked', 'checked');

		setTimeout(function(){ jQuery('#name').focus() }, 10);

		jQuery('#newCheck').click(showNewCheckForm);
		jQuery('#clone').click(function() {
			jQuery('#druleid, #delete, #clone').remove();
			jQuery('#cancel').addClass('ui-corner-left');
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
