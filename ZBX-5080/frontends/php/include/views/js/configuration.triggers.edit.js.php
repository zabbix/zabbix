<script type="text/javascript">
	var selectedSeverity = <?php echo $this->data['priority']; ?>;

	function focusSeverity(priority) {
		jQuery('#severity_label_0').removeClass('not_classified');
		jQuery('#severity_label_1').removeClass('information');
		jQuery('#severity_label_2').removeClass('warning');
		jQuery('#severity_label_3').removeClass('average');
		jQuery('#severity_label_4').removeClass('high');
		jQuery('#severity_label_5').removeClass('disaster');
		jQuery('.trigger-severity').css('background', '');

		jQuery('#severity_label_' + priority + '').addClass(getSeverityName(priority));
		selectedSeverity = priority;
	}

	function mouseOverSeverity(priority) {
		jQuery('#severity_label_' + priority).addClass(getSeverityName(priority));
	}

	function mouseOutSeverity(priority) {
		if (selectedSeverity != priority) {
			jQuery('#severity_label_' + priority).removeClass(getSeverityName(priority));
		}
	}

	function getSeverityName(priority) {
		if (priority == 0) {
			return 'not_classified';
		}
		else if (priority == 1) {
			return 'information';
		}
		else if (priority == 2) {
			return 'warning';
		}
		else if (priority == 3) {
			return 'average';
		}
		else if (priority == 4) {
			return 'high';
		}
		else {
			return 'disaster';
		}
	}

	function addPopupValues(list) {
		if (!isset('object', list)) {
			return false;
		}
		if (list.object == 'deptrigger') {
			for (var i = 0; i < list.values.length; i++) {
				create_var('triggersForm', 'new_dependency[' + i + ']', list.values[i].triggerid, false);
			}
			create_var('triggersForm', 'add_dependency', 1, true);
		}
	}

	function removeDependency(triggerid) {
		jQuery('#dependency_' + triggerid).remove();
		jQuery('#dependencies_' + triggerid).remove();
	}

	// create jQuery buttonset object when VisibilityBox is switched on
	jQuery(document).ready(function() {
		jQuery('#visible_priority').click(function() {
			if (!jQuery('#priority_div').hasClass('ui-buttonset')) {
				jQuery('#priority_div').buttonset();
			}
		});

		var severity = jQuery('input[name="priority"]:checked').val();
		focusSeverity(severity);
	});
</script>
