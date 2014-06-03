<script>
	function add_var_to_opener_obj(obj, name, value) {
		var new_variable = window.opener.document.createElement('input');
		new_variable.type = 'hidden';
		new_variable.name = name;
		new_variable.value = value;
		obj.appendChild(new_variable);
	}

	function add_httpstep(formname, httpstep) {
		var form = window.opener.document.forms[formname];
		if (!form) {
			close_window();
			return false;
		}

		add_var_to_opener_obj(form, 'new_httpstep[name]', httpstep.name);
		add_var_to_opener_obj(form, 'new_httpstep[timeout]', httpstep.timeout);
		add_var_to_opener_obj(form, 'new_httpstep[url]', httpstep.url);
		add_var_to_opener_obj(form, 'new_httpstep[posts]', httpstep.posts);
		add_var_to_opener_obj(form, 'new_httpstep[variables]', httpstep.variables);
		add_var_to_opener_obj(form, 'new_httpstep[required]', httpstep.required);
		add_var_to_opener_obj(form, 'new_httpstep[status_codes]', httpstep.status_codes);
		add_var_to_opener_obj(form, 'new_httpstep[headers]', httpstep.headers);
		add_var_to_opener_obj(form, 'new_httpstep[follow_redirects]', httpstep.follow_redirects);
		add_var_to_opener_obj(form, 'new_httpstep[retrieve_mode]', httpstep.retrieve_mode);

		form.submit();
		close_window();
		return true;
	}

	function update_httpstep(formname, list_name, httpstep) {
		var form = window.opener.document.forms[formname];
		if (!form) {
			close_window();
			return false;
		}

		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][name]', httpstep.name);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][timeout]', httpstep.timeout);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][url]', httpstep.url);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][posts]', httpstep.posts);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][variables]', httpstep.variables);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][required]', httpstep.required);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][status_codes]', httpstep.status_codes);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][headers]', httpstep.headers);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][follow_redirects]', httpstep.follow_redirects);
		add_var_to_opener_obj(form, list_name + '[' + httpstep.stepid + '][retrieve_mode]', httpstep.retrieve_mode);

		form.submit();
		close_window();
		return true;
	}

	jQuery(function() {
		jQuery('#retrieve_mode')
			.on('change', function() {
				jQuery('#required, #posts').attr('disabled', this.checked);
			})
			.trigger('change');
	});
</script>
