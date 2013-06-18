<script type="text/javascript">
	jQuery(document).ready(function() {
		// handle map menus
		jQuery('.map-container').on('click', '.menu-map', function(event) {
			var menuData = jQuery(this).data('menu'),
				menu = [],
				linkMenu = [];

			// host menu
			if (+menuData.elementType === <?php echo SYSMAP_ELEMENT_TYPE_HOST; ?>) {
				if (menuData.scripts.length) {
					menu.push(createMenuHeader(<?php echo CJs::encodeJson(_('Scripts')); ?>));
					jQuery.each(menuData.scripts, function(i, script) {
						menu.push(createMenuItem(script.name, function () {
							executeScript(menuData.elementId, script.scriptid, script.confirmation);
							return false;
						}));
					});
				}

				if (menuData.isMonitored) {
					linkMenu.push(createMenuItem(<?php echo CJs::encodeJson(_('Status of triggers')); ?>,
						'tr_status.php?hostid=' + menuData.elementId + '&show_severity=<?php echo $this->data['severity_min']; ?>&filter_set=1'));

					if (menuData.hasScreens) {
						linkMenu.push(createMenuItem(<?php echo CJs::encodeJson(_('Host screens')); ?>,
							'host_screen.php?hostid=' + menuData.elementId));
					}
				}
			}
			// map menu
			else if (+menuData.elementType === <?php echo SYSMAP_ELEMENT_TYPE_MAP; ?>) {
				linkMenu.push(createMenuItem(<?php echo CJs::encodeJson(_('Submap')); ?>,
					'maps.php?sysmapid=' + menuData.elementId + '&severity_min=<?php echo $this->data['severity_min']; ?>'));
			}
			// trigger menu
			else if (+menuData.elementType === <?php echo SYSMAP_ELEMENT_TYPE_TRIGGER; ?>) {
				linkMenu.push(createMenuItem(<?php echo CJs::encodeJson(_('Latest events')); ?>,
					'events.php?source=0&triggerid=' + menuData.elementId + '&nav_time=<?php echo time() - SEC_PER_WEEK; ?>'));
			}
			// host group menu
			else if (+menuData.elementType === <?php echo SYSMAP_ELEMENT_TYPE_HOST_GROUP; ?>) {
				linkMenu.push(createMenuItem(<?php echo CJs::encodeJson(_('Status of triggers')); ?>,
					'tr_status.php?hostid=0&groupid=' + menuData.elementId + '&show_severity=<?php echo $this->data['severity_min']; ?>&filter_set=1'));
			}

			// link section
			if (linkMenu.length) {
				menu.push(createMenuHeader(<?php echo CJs::encodeJson(_('Go to')); ?>));
				menu = menu.concat(linkMenu);
			}

			// URL menu
			if (menuData.urls.length) {
				menu.push(createMenuHeader(<?php echo CJs::encodeJson(_('URLs')); ?>));
				jQuery.each(menuData.urls, function(i, url) {
					menu.push(createMenuItem(url.name, url.url, 'nosid'));
				});
			}

			// render the menu
			show_popup_menu(event, menu, 180);

			return false;
		});
	});
</script>
