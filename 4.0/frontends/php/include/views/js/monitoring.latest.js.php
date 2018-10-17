<script type="text/javascript">
	jQuery(function($) {

		var initialize = function() {
			var open_state_all = '0';

			$('.app-list-toggle').each(function() {
				var open_state = $(this).data('open-state');

				$('span', this)
					.addClass(open_state == '0' ? '<?= ZBX_STYLE_ARROW_RIGHT ?>' : '<?= ZBX_STYLE_ARROW_DOWN ?>');

				if (open_state == '0') {
					var	hostid = $(this).attr('data-host-id');
					if (hostid) {
						$('tr[parent_host_id=' + hostid + ']').hide();
					}
					else {
						$('tr[parent_app_id=' + $(this).attr('data-app-id') + ']').hide();
					}
				}
				else {
					open_state_all = '1';
				}
			});

			$('.app-list-toggle-all').data('open-state', open_state_all);
			$('.app-list-toggle-all span')
				.addClass(open_state_all == '0' ? '<?= ZBX_STYLE_ARROW_RIGHT ?>' : '<?= ZBX_STYLE_ARROW_DOWN ?>');
		};

		initialize();

		// click event for main toggle (+-) button
		$('.app-list-toggle-all').click(function() {
			// this is for Opera browser with large tables, which renders table layout while showing/hiding rows
			$(this).closest('table').fadeTo(0, 0);

			var open_state = ($(this).data('open-state') == '0') ? '1' : '0',
				del_class = (open_state == '0') ? '<?= ZBX_STYLE_ARROW_DOWN ?>' : '<?= ZBX_STYLE_ARROW_RIGHT ?>',
				add_class = (open_state == '0') ? '<?= ZBX_STYLE_ARROW_RIGHT ?>' : '<?= ZBX_STYLE_ARROW_DOWN ?>',
				applicationids = [],
				hostids = [];

			// change and store new state
			$(this).data('open-state', open_state);

			$('span', this)
				.removeClass(del_class)
				.addClass(add_class);

			$('.app-list-toggle').each(function() {
				if ($(this).data('open-state') != open_state) {
					$(this).data('open-state', open_state);
					$('span', this)
						.removeClass(del_class)
						.addClass(add_class);

					var hostid = $(this).attr('data-host-id');
					if (hostid) {
						$('tr[parent_host_id=' + hostid + ']').toggle(open_state == '1');
						hostids.push(hostid);
					}
					else {
						var applicationid = $(this).attr('data-app-id');
						$('tr[parent_app_id=' + applicationid + ']').toggle(open_state == '1');
						applicationids.push(applicationid);
					}
				}
			});

			// this is for Opera browser with large tables, which renders table layout while showing/hiding rows
			$(this).closest('table').fadeTo(0, 1);

			if (!empty(hostids)) {
				updateUserProfile('web.latest.toggle_other', open_state, hostids);
			}
			if (!empty(applicationids)) {
				updateUserProfile('web.latest.toggle', open_state, applicationids);
			}
		});

		// click event for every toggle (+-) button
		$('.app-list-toggle').click(function() {
			var open_state = ($(this).data('open-state') == '0') ? '1' : '0',
				del_class = (open_state == '0') ? '<?= ZBX_STYLE_ARROW_DOWN ?>' : '<?= ZBX_STYLE_ARROW_RIGHT ?>',
				add_class = (open_state == '0') ? '<?= ZBX_STYLE_ARROW_RIGHT ?>' : '<?= ZBX_STYLE_ARROW_DOWN ?>',
				open_state_all = '0';

			// change and store new state
			$(this).data('open-state', open_state);

			$('span', this)
				.removeClass(del_class)
				.addClass(add_class);

			if (open_state == '0') {
				$('.app-list-toggle').each(function() {
					if ($(this).data('open-state') != '0') {
						open_state_all = '1';
					}
				});
			}
			else {
				open_state_all = '1';
			}

			if ($('.app-list-toggle-all').data('open-state') != open_state_all) {
				$('.app-list-toggle-all').data('open-state', open_state_all);
				$('.app-list-toggle-all span')
					.removeClass(del_class)
					.addClass(add_class);
			}

			var hostid = $(this).attr('data-host-id');
			if (hostid) {
				$('tr[parent_host_id=' + hostid + ']').toggle(open_state == '1');
				updateUserProfile('web.latest.toggle_other', open_state, [hostid]);
			}
			else {
				var applicationid = $(this).attr('data-app-id');
				$('tr[parent_app_id=' + applicationid + ']').toggle(open_state == '1');
				updateUserProfile('web.latest.toggle', open_state, [applicationid]);
			}
		});
	});
</script>
