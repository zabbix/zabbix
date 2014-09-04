<script type="text/javascript">
	jQuery(function($) {
		chkbxRange.pageGoName = "itemids";

		/**
		 * Make so that all visible table rows would alternate colors
		 */
		var rebuildRowColoring = function() {
			var a = 1;
			$('.tableinfo tr:visible').each(function() {
				a = a ? 0 : 1;

				if (a) {
					$(this).addClass('even_row');
				}
				else {
					$(this).removeClass('even_row');
				}
			});
		};

		var initialize = function() {
			// if at least one toggle group is opened
			if ($('.app-list-toggle.icon-minus-9x9').length) {
				$('.app-list-toggle-all').addClass('icon-minus-9x9').data('openState', 1);
			}

			rebuildRowColoring();
		};

		initialize();

		// click event for main toggle (+-) button
		$('.app-list-toggle-all').click(function() {
			// this is for Opera browser with large tables, which renders table layout while showing/hiding rows
			$('.tableinfo').fadeTo(0, 0);

			var openState = $(this).data('openState'),
				appIdList = [];

			// switch between + and - icon
			$(this).toggleClass('icon-minus-9x9');

			if (openState) {
				$('.app-list-toggle.icon-minus-9x9').each(function() {
					$(this).toggleClass('icon-minus-9x9');
					$(this).data('openState', 0);

					var appId = $(this).attr('data-app-id');
					$('tr[parent_app_id=' + appId + ']').hide();

					appIdList.push(appId);
				});
			}
			else {
				$('.app-list-toggle').not('.icon-minus-9x9').each(function() {
					$(this).toggleClass('icon-minus-9x9');
					$(this).data('openState', 1);

					var appId = $(this).attr('data-app-id');
					$('tr[parent_app_id=' + appId + ']').show();

					appIdList.push(appId);
				});
			}

			// change and store new state
			openState = openState ? 0 : 1;
			$(this).data('openState', openState);

			rebuildRowColoring();

			// this is for Opera browser with large tables, which renders table layout while showing/hiding rows
			$('.tableinfo').fadeTo(0, 1);

			// store toggle state in DB
			var url = new Curl('latest.php?output=ajax');
			url.addSID();
			$.post(url.getUrl(), {
				favobj: 'toggle',
				toggle_ids: appIdList,
				toggle_open_state: openState
			});
		});

		// click event for every toggle (+-) button
		$('.app-list-toggle').click(function() {
			var appId = $(this).attr('data-app-id'),
				openState = $(this).data('openState');

			// hide/show all corresponding toggle sub rows
			$('tr[parent_app_id=' + appId + ']')[(openState ? 'hide' : 'show')]();

			// switch between + and - icon
			$(this).toggleClass('icon-minus-9x9');

			// change and store new state
			openState = openState ? 0 : 1;
			$(this).data('openState', openState);

			// if at least one toggle is opened, make main toggle as -
			if (openState) {
				$('.app-list-toggle-all').addClass('icon-minus-9x9').data('openState', 1);
			}
			// if all toggles are closed, make main toggle as +
			else if (!$('.app-list-toggle.icon-minus-9x9').length) {
				$('.app-list-toggle-all').removeClass('icon-minus-9x9').data('openState', 0);
			}

			rebuildRowColoring();

			// store toggle state in DB
			var url = new Curl('latest.php?output=ajax');
			url.addSID();
			$.post(url.getUrl(), {
				favobj: 'toggle',
				toggle_ids: appId,
				toggle_open_state: openState
			});
		});

		$('#filter_set, #filter_rst').click(function() {
			chkbxRange.clearSelectedOnFilterChange();
		});
	});
</script>
