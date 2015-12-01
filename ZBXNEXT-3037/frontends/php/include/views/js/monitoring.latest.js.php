<script type="text/javascript">
	jQuery(function($) {

		var initialize = function() {
			var open_state_all = '0';

			$('.app-list-toggle').each(function() {
				if ($(this).data('open-state') == '0') {
					$('span', this).addClass('<?= ZBX_STYLE_ARROW_RIGHT ?>');

					var applicationid = $(this).attr('data-app-id');
					$('tr[parent_app_id=' + applicationid + ']').hide();
				}
				else {
					$('span', this).addClass('<?= ZBX_STYLE_ARROW_DOWN ?>');

					open_state_all = '1';
				}
			});

			$('.app-list-toggle-all').data('open-state', open_state_all);

			// if at least one toggle group is opened
			if (open_state_all == '0') {
				$('.app-list-toggle-all span').addClass('<?= ZBX_STYLE_ARROW_RIGHT ?>');
			}
			else {
				$('.app-list-toggle-all span').addClass('<?= ZBX_STYLE_ARROW_DOWN ?>');
			}
		};

		initialize();

		// click event for main toggle (+-) button
		$('.app-list-toggle-all').click(function() {
			// this is for Opera browser with large tables, which renders table layout while showing/hiding rows
			$(this).closest('table').fadeTo(0, 0);

			var open_state = $(this).data('open-state'),
				applicationids = [];

			if (open_state == '0') {
				$(this).data('open-state', '1');
				$('span', this)
					.removeClass('<?= ZBX_STYLE_ARROW_RIGHT ?>')
					.addClass('<?= ZBX_STYLE_ARROW_DOWN ?>');

				$('.app-list-toggle').each(function() {
					if ($(this).data('open-state') == '0') {
						$(this).data('open-state', '1');
						$('span', this)
							.removeClass('<?= ZBX_STYLE_ARROW_RIGHT ?>')
							.addClass('<?= ZBX_STYLE_ARROW_DOWN ?>');

						var applicationid = $(this).attr('data-app-id');
						$('tr[parent_app_id=' + applicationid + ']').show();

						applicationids.push(applicationid);
					}
				});
			}
			else {
				$(this).data('open-state', '0');
				$('span', this)
					.removeClass('<?= ZBX_STYLE_ARROW_DOWN ?>')
					.addClass('<?= ZBX_STYLE_ARROW_RIGHT ?>');

				$('.app-list-toggle').each(function() {
					if ($(this).data('open-state') != '0') {
						$(this).data('open-state', '0');
						$('span', this)
							.removeClass('<?= ZBX_STYLE_ARROW_DOWN ?>')
							.addClass('<?= ZBX_STYLE_ARROW_RIGHT ?>');

						var applicationid = $(this).attr('data-app-id');
						$('tr[parent_app_id=' + applicationid + ']').hide();

						applicationids.push(applicationid);
					}
				});
			}

			// change and store new state
			open_state = (open_state == '0') ? '1' : '0';

			// this is for Opera browser with large tables, which renders table layout while showing/hiding rows
			$(this).closest('table').fadeTo(0, 1);

			// store toggle state in DB
			var url = new Curl('latest.php?output=ajax');
			url.addSID();
			$.post(url.getUrl(), {
				favobj: 'toggle',
				toggle_ids: applicationids,
				toggle_open_state: open_state
			});
		});

		// click event for every toggle (+-) button
		$('.app-list-toggle').click(function() {
			var applicationid = $(this).attr('data-app-id'),
				open_state = $(this).data('open-state');

			// change and store new state
			open_state = (open_state == '0') ? '1' : '0';
			$(this).data('open-state', open_state);

			if (open_state == '0') {
				$('span', this)
					.removeClass('<?= ZBX_STYLE_ARROW_DOWN ?>')
					.addClass('<?= ZBX_STYLE_ARROW_RIGHT ?>');

				$('tr[parent_app_id=' + applicationid + ']').hide();

				var open_state_all = '0';

				$('.app-list-toggle').each(function() {
					if ($(this).data('open-state') != '0') {
						open_state_all = '1';
					}
				});

				if (open_state_all == '0') {
					$('.app-list-toggle-all').data('open-state', '0');
					$('.app-list-toggle-all span')
						.removeClass('<?= ZBX_STYLE_ARROW_DOWN ?>')
						.addClass('<?= ZBX_STYLE_ARROW_RIGHT ?>');
				}
			}
			else {
				$(this).data('open-state', '1');
				$('span', this)
					.removeClass('<?= ZBX_STYLE_ARROW_RIGHT ?>')
					.addClass('<?= ZBX_STYLE_ARROW_DOWN ?>');

				$('tr[parent_app_id=' + applicationid + ']').show();

				if ($('.app-list-toggle-all').data('open-state') == '0') {
					$('.app-list-toggle-all').data('open-state', '1');
					$('.app-list-toggle-all span')
						.removeClass('<?= ZBX_STYLE_ARROW_RIGHT ?>')
						.addClass('<?= ZBX_STYLE_ARROW_DOWN ?>')
				}
			}

			// store toggle state in DB
			var url = new Curl('latest.php?output=ajax');
			url.addSID();
			$.post(url.getUrl(), {
				favobj: 'toggle',
				toggle_ids: [applicationid],
				toggle_open_state: open_state
			});
		});

		$('#filter_set, #filter_rst').click(function() {
			chkbxRange.clearSelectedOnFilterChange();
		});
	});
</script>
