<script type="text/javascript">
	jQuery(document).ready(function() {

		jQuery(document.body).addClass('setupBG');

		jQuery(':submit').button();

		jQuery('#agree').change(function() {
			if (this.checked) {
				jQuery('next_1').button('enable');
			}
			else {
				jQuery('#next_1').button('disable');
			}
		})
	});
</script>
