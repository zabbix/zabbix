<script type="text/javascript">

	function save_previous_form_state(form) {
		var	userElement = form.find('#userid');

		if (typeof userElement.data('multiSelect') !== 'undefined') {
			owner = userElement.multiSelect('getData');
			owner = owner[0];
		}
		form.data('data', {"name": form.find('#name').val(), "owner": owner || {}});
	};

	jQuery(document).ready(function() {
		var	edit_form = jQuery('form[name="dashboard_form"]'),
			dashboard = jQuery('.dashbrd-grid-widget-container');

		edit_form.data(
			'data',
			{"name": edit_form.find('#name').val(), "owner": edit_form.find('#userid').data().defaultOwner}
		);

		edit_form.submit(function (event) {
			var	form = jQuery(this);

			form.trimValues(['#name']);

			var	form_data = form.serializeJSON();

			// cancel original event to prevent form submitting
			event.preventDefault();

			save_previous_form_state(form);

			dashboard.dashboardGrid(
				"setDashboardData", {"name": form_data['name'], "userid": form_data['userid'] || 0}
			);
			jQuery('div.article .header-title .cell:first h1').text(form.data('data').name);
			jQuery('#dashboard-direct-link').text(form.data('data').name);
		});
	});
</script>
