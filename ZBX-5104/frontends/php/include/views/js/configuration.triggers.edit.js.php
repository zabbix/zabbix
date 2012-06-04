<script type="text/javascript">
	jQuery(function($) {
		$('#severity_0, #severity_1, #severity_2, #severity_3, #severity_4, #severity_5').change(function() {
			var label = $('#severity_label_' + $(this).val());

			// remove classes from all labels
			$('div.control-severity label').each(function(i, obj) {
				obj = $(obj);
				obj.removeClass(obj.data('severityStyle'));
			});

			label.addClass(label.data('severityStyle'));
		});

		$('#severity_label_0, #severity_label_1, #severity_label_2, #severity_label_3, #severity_label_4, #severity_label_5').mouseenter(function() {
			var obj = $(this);

			$('#severity_label_' + obj.data('severity')).addClass(obj.data('severityStyle'));
		});

		$('#severity_label_0, #severity_label_1, #severity_label_2, #severity_label_3, #severity_label_4, #severity_label_5').mouseleave(function() {
			var obj = $(this);

			if (!$('#' + obj.attr('for')).prop('checked')) {
				$('#severity_label_' + obj.data('severity')).removeClass(obj.data('severityStyle'));
			}
		});

		// click on selected severity on form load
		$('input[name="priority"]:checked').change();


		// create jQuery buttonset object when VisibilityBox is switched on
		jQuery('#visible_priority').click(function() {
			if (!jQuery('#priority_div').hasClass('ui-buttonset')) {
				jQuery('#priority_div').buttonset();
			}
		});
	});


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

</script>
