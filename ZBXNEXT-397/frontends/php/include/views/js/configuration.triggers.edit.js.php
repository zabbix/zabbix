<script type="text/javascript">
	var selectedSeverity = <?php echo $this->data['priority']; ?>;

	jQuery(document).ready(function() {
		jQuery('#severity_label_<?php echo $this->data['priority']; ?>').css('background', '#<?php echo $this->data['config']['severity_color_'.$this->data['priority']]; ?>');
	});

	function focusSeverity(priority) {
		jQuery('#severity_label_0 span').removeClass('not_classified');
		jQuery('#severity_label_1 span').removeClass('information');
		jQuery('#severity_label_2 span').removeClass('warning');
		jQuery('#severity_label_3 span').removeClass('average');
		jQuery('#severity_label_4 span').removeClass('high');
		jQuery('#severity_label_5 span').removeClass('disaster');
		jQuery('#severity_label_0, #severity_label_1, #severity_label_2, #severity_label_3, #severity_label_4, #severity_label_5').css('background', '');

		jQuery('#severity_label_' + priority + ' span').addClass(getSeverityName(priority));
		selectedSeverity = priority;
	}

	function mouseOverSeverity(priority) {
		jQuery('#severity_label_' + priority +' span').addClass(getSeverityName(priority));
	}

	function mouseOutSeverity(priority) {
		if (selectedSeverity != priority) {
			jQuery('#severity_label_' + priority +' span').removeClass(getSeverityName(priority));
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
	});
</script>
