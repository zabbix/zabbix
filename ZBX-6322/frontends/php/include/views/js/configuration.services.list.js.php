<script type="text/javascript">
	jQuery(function() {
		/**
		 * Handles the service configuration pop up menus.
		 */
		jQuery('.tableinfo').on('click', '.service-conf-menu', function(event) {
			var menuData = jQuery(this).data('menu');

			var menu = [];
			menu.push(createMenuHeader(menuData.name));
			menu.push(createMenuItem(t('Add service'), 'services.php?form=1&parentid=' + menuData.serviceid + '&parentname=' + menuData.name));

			if (menuData.serviceid) {
				menu.push(createMenuItem(t('Edit service'), 'services.php?form=1&serviceid=' + menuData.serviceid));
			}

			// don't display the delete link for services with hard dependencies
			if (menuData.deletable) {
				menu.push(createMenuItem(t('Delete service'), function() {
					if (confirm(t('Delete the selected service?'))) {
						window.location.href = new Curl('services.php?delete=1&serviceid=' + menuData.serviceid).getUrl();
					}
				}));
			}

			// render the menu
			show_popup_menu(event, menu, 180);

			return false;
		});
	});
</script>
