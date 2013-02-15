<script type="text/javascript">
	jQuery(function($) {

		/**
		 * Make so that all visible table rows would alternate colors
		 */
		var rebuildRowColoring = function() {
			var a = 1;
			$('.tableinfo tr:visible').each(function(){
				a = a ? 0 : 1;
				if (a) {
					$(this).addClass('even_row');
				}
				else {
					$(this).removeClass('even_row');
				}
			});
		}

		var initialize = function() {
			// if at least one toggle group is opened
			if ($('.app-list-toggle.icon-minus-9x9').length) {
				$('.app-list-toggle-all').addClass('icon-minus-9x9').data('open_state', 1);
			}

			rebuildRowColoring();
		}

		initialize();

		// click event for main toggle (+-) button
		$('.app-list-toggle-all').click(function(){
			var openState = $(this).data('open_state'),
				appIdList = [];

			// switch between + and - icon
			$(this).toggleClass('icon-minus-9x9');

			if (openState) {
				$('.app-list-toggle.icon-minus-9x9').each(function(){
					appIdList.push($(this).data('app_id'));
					// click on all opened toggle buttons
					$(this).trigger('click', true);
				});
			}
			else {
				$('.app-list-toggle').not('.icon-minus-9x9').each(function(){
					appIdList.push($(this).data('app_id'));
					// click on all closed toggle buttons
					$(this).trigger('click', true);
				});
			}

			// change and store new state
			openState = openState ? 0 : 1;
			$(this).data('open_state', openState);

			rebuildRowColoring();

			// store toggle state in DB
			var url = new Curl('latest.php?output=ajax');
			url.addSID();
			$.post(url.getUrl(), { favobj: 'toggle', toggle_ids: appIdList, toggle_open_state: openState });
		});

		// click event for every toggle (+-) button
		$('.app-list-toggle').click(function(e, fromTrigger){
			var appId = $(this).data('app_id'),
				openState = $(this).data('open_state');

			// hide/show all corresponding toggle sub rows
			$('tr[parent_app_id=' + appId + ']').toggle();
			// switch between + and - icon
			$(this).toggleClass('icon-minus-9x9');

			// change and store new state
			openState = openState ? 0 : 1;
			$(this).data('open_state', openState);

			// only if clicked directly
			if (typeof fromTrigger === 'undefined') {
				// if at least one toggle is opened, make main toggle as -
				if (openState) {
					$('.app-list-toggle-all').addClass('icon-minus-9x9').data('open_state', 1);
				}
				// if all toggles are closed, make main toggle as +
				else if (!$('.app-list-toggle.icon-minus-9x9').length) {
					$('.app-list-toggle-all').removeClass('icon-minus-9x9').data('open_state', 0);
				}

				rebuildRowColoring();

				// store toggle state in DB
				var url = new Curl('latest.php?output=ajax');
				url.addSID();
				$.post(url.getUrl(), { favobj: 'toggle', toggle_ids: appId, toggle_open_state: openState });
			}
		});
	});
</script>
