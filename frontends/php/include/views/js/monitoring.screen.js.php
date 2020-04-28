<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

<script type="text/javascript">
	var monitoringScreen = {
		refreshOnAcknowledgeCreateSubscribed: false,
		refreshOnAcknowledgeCreateHandler: function(response, overlay) {
			var element;

			if (overlays_stack.length) {
				var overlay_end = overlays_stack.end();

				element = overlay_end.element;

				// Hide hintbox to allow refreshing of the screen.
				if (overlay_end.type === 'hintbox') {
					hintBox.hideHint(element, true);
				}
			}
			else {
				// Find element which does not get replaced on screen refresh.
				element = overlay.trigger_parents.filter('.screenitem');
			}

			if (element) {
				element = (element instanceof jQuery) ? element[0] : element;
				for (var id in flickerfreeScreen.screens) {
					if (flickerfreeScreen.screens.hasOwnProperty(id)) {
						var flickerfreescreen = $("#flickerfreescreen_" + id)[0];
						if ($.contains(flickerfreescreen, element) || $.contains(element, flickerfreescreen)) {
							clearMessages();
							addMessage(makeMessageBox("good", response.message, null, true));
							flickerfreeScreen.refresh(id);
						}
					}
				}
			}
		},
		refreshOnAcknowledgeCreate: function() {
			if (!this.refreshOnAcknowledgeCreateSubscribed) {
				$.subscribe('acknowledge.create',
					(event, response, overlay) => this.refreshOnAcknowledgeCreateHandler(response, overlay)
				);

				this.refreshOnAcknowledgeCreateSubscribed = true;
			}
		}
	};
</script>
