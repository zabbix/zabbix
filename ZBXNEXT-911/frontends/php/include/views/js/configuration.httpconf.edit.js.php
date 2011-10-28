<script type="text/javascript">
	function removeHttpStep(stepid) {
		remove('steps_' + stepid);
		remove('steps_' + stepid + '_httpstepid');
		remove('steps_' + stepid + '_httptestid');
		remove('steps_' + stepid + '_name');
		remove('steps_' + stepid + '_no');
		remove('steps_' + stepid + '_url');
		remove('steps_' + stepid + '_timeout');
		remove('steps_' + stepid + '_posts');
		remove('steps_' + stepid + '_required');
		remove('steps_' + stepid + '_status_codes');
	}

	function remove(id) {
		obj = document.getElementById(id);
		if (!empty(obj)) {
			obj.parentNode.removeChild(obj);
		}
	}
</script>
