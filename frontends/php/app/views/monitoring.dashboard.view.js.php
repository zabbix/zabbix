<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Turn on edit dashboard
		$('#dashbrd-edit').click(function() {
			var btn_conf = $('<button>')
				.addClass('<?= ZBX_STYLE_BTN_ALT ?>')
				.attr('id','dashbrd-config')
				.attr('type','button')
				.append(
					$('<span>').addClass('<?= ZBX_STYLE_PLUS_ICON ?>') // TODO VM: replace by cog icon
				)
				.click(dashbrd_config);

			var btn_add = $('<button>')
				.addClass('<?= ZBX_STYLE_BTN_ALT ?>')
				.attr('id','dashbrd-add-widget')
				.attr('type','button')
				.append(
					$('<span>').addClass('<?= ZBX_STYLE_PLUS_ICON ?>'),
					'<?= _('Add widget') ?>'
				)
				.click(dashbrd_add_widget);

			var btn_save = $('<button>')
				.attr('id','dashbrd-save')
				.attr('type','button')
				.append('<?= _('Save changes') ?>')
				.click(dashbrd_save_changes);

			var btn_cancel = $('<a>')
				.attr('id','dashbrd-cancel')
				.attr('href', '#') // TODO VM: (?) needed for style, but adds # at URL, when clicked. Probably better to create new class with same styles
				.append('<?= _('Cancel') ?>')
				.click(dashbrd_cancel);

			var btn_edit_disabled = $('<button>')
				.attr('disabled','disabled')
				.attr('type','button')
				.append('<?= _('Edit dashboard') ?>');

			$(this).closest('li').hide();
			$(this).closest('ul').before(
				$('<span>')
					.addClass('<?= ZBX_STYLE_DASHBRD_EDIT ?>')
					.append($('<ul>')
						.append($('<li>').append(btn_conf))
						.append($('<li>').append(btn_add))
						.append($('<li>').append(btn_save))
						.append($('<li>').append(btn_cancel))
						.append($('<li>'))
						.append($('<li>').append(btn_edit_disabled))
					)
			);

			// Update buttons on existing widgets to edit mode
			$('.dashbrd-grid-widget-container').dashboardGrid('setModeEditDashboard');
		});

		// Change dashboard settings
		var dashbrd_config = function() {
			// Update buttons on existing widgets to view mode
			$('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
		};

		// Save changes and cancel editing dashboard
		var dashbrd_save_changes = function() {
			// Update buttons on existing widgets to view mode
			$('.dashbrd-grid-widget-container').dashboardGrid('saveDashboardChanges');
			// dashboardButtonsSetView() will be called in case of success of ajax in function 'saveDashboardChanges'
		};

		// Cancel editing dashboard
		var dashbrd_cancel = function(e) {
			e.preventDefault(); // To prevent going by href link

			// Update buttons on existing widgets to view mode
			$('.dashbrd-grid-widget-container').dashboardGrid('cancelEditDashboard');

			var form = $(this).closest('form');
			$('.dashbrd-edit', form).remove();
			$('#dashbrd-edit', form).closest('li').show();
		};

		// Add new widget
		var dashbrd_add_widget = function() {
			// TODO VM: implement adding widget functionality
		};
	});

	// will be called by setModeViewDashboard() method in dashboard.grid.js
	function dashboardButtonsSetView() {
		var $form = jQuery('.article .header-title form');
		jQuery('.dashbrd-edit', $form).remove();
		jQuery('#dashbrd-edit', $form).closest('li').show();
	}

	function dashbaordAddMessages(messages) {
		var $message_div = jQuery('<div>').attr('id','dashbrd-messages');
		$message_div.append(messages);
		jQuery('.article').prepend($message_div);
	}

	function dashboardRemoveMessages() {
		jQuery('#dashbrd-messages').remove();
	}

	// Function is in global scope, because it should be accessable by html onchange() attribute
	function updateWidgetConfigDialogue() {
		jQuery('.dashbrd-grid-widget-container').dashboardGrid('updateWidgetConfigDialogue');
	}
</script>
