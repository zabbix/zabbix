<script>
	function add_var_to_opener_obj(obj, name, value) {
		var new_variable = window.opener.document.createElement('input');
		new_variable.type = 'hidden';
		new_variable.name = name;
		new_variable.value = value;
		obj.appendChild(new_variable);
	}

	function add_httpstep(formname, name, timeout, url, posts, required, status_codes) {
		var form = window.opener.document.forms[formname];
		if (!form) {
			close_window();
			return false;
		}

		add_var_to_opener_obj(form, 'new_httpstep[name]', name);
		add_var_to_opener_obj(form, 'new_httpstep[timeout]', timeout);
		add_var_to_opener_obj(form, 'new_httpstep[url]', url);
		add_var_to_opener_obj(form, 'new_httpstep[posts]', posts);
		add_var_to_opener_obj(form, 'new_httpstep[required]', required);
		add_var_to_opener_obj(form, 'new_httpstep[status_codes]', status_codes);

		form.submit();
		close_window();
		return true;
	}

	function update_httpstep(formname, list_name, stepid, name, timeout, url, posts, required, status_codes) {
		var form = window.opener.document.forms[formname];
		if (!form) {
			close_window();
			return false;
		}

		add_var_to_opener_obj(form, list_name + '[' + stepid + '][name]', name);
		add_var_to_opener_obj(form, list_name + '[' + stepid + '][timeout]', timeout);
		add_var_to_opener_obj(form, list_name + '[' + stepid + '][url]', url);
		add_var_to_opener_obj(form, list_name + '[' + stepid + '][posts]', posts);
		add_var_to_opener_obj(form, list_name + '[' + stepid + '][required]', required);
		add_var_to_opener_obj(form, list_name + '[' + stepid + '][status_codes]', status_codes);

		form.submit();
		close_window();
		return true;
	}
</script>
