var popup_request_data;
var close_popup;

function get_popup_request_data()
{
	return window.document.popup_request_data;
}

function get_close_popup()
{
	return window.document.close_popup;
}

function open_popup(module_name, width, height, initial_filter, close_popup, hide_clear_button, popup_request_data)
{
	// set the variables that the popup will pull from
	window.document.popup_request_data = popup_request_data;
	window.document.close_popup = close_popup;

	// launch the popup
	URL = 'popup.php?'
		+ 'module=' + module_name
		+ '&action=Popup';
	
	if(initial_filter != '')
	{
		URL += '&query=true' + initial_filter;
	}
	
	if(hide_clear_button)
	{
		URL += '&hide_clear_button=true';
	}
	
	windowName = 'popup_window';
	
	windowFeatures = 'width=' + width
		+ ',height=' + height
		+ ',resizable=1,scrollbars=1';

	win = window.open(URL, windowName, windowFeatures);

	if(window.focus)
	{
		// put the focus on the popup if the browser supports the focus() method
		win.focus();
	}

	return win;
}

/**
 * The reply data must be a JSON array structured with the following information:
 *  1) form name to populate
 *  2) associative array of input names to values for populating the form
 */
function set_return(popup_reply_data)
{
	var form_name = popup_reply_data.form_name;
	var name_to_value_array = popup_reply_data.name_to_value_array;
	
	for (var the_key in name_to_value_array)
	{
		if(the_key == 'toJSON')
		{
			/* just ignore */
		}
		else
		{
			var displayValue=name_to_value_array[the_key];
			displayValue=displayValue.replace('&#039;',"'");  //restore escaped single quote.
			displayValue=displayValue.replace( '&amp;',"&");  //restore escaped &.
			displayValue=displayValue.replace( '&gt;',">");  //restore escaped >.
			displayValue=displayValue.replace( '&lt;',"<");  //restore escaped <.
			displayValue=displayValue.replace( '&quot; ',"\"");  //restore escaped ".
			
			window.document.forms[form_name].elements[the_key].value = displayValue;
		}
	}
}

function set_return_and_save(popup_reply_data)
{
	var form_name = popup_reply_data.form_name;
	var name_to_value_array = popup_reply_data.name_to_value_array;
	
	
	for (var the_key in name_to_value_array)
	{
		if(the_key == 'toJSON')
		{
			/* just ignore */
		}
		else
		{
			window.document.forms[form_name].elements[the_key].value = name_to_value_array[the_key];
		}
	}
	
	window.document.forms[form_name].return_module.value = window.document.forms[form_name].module.value;
	window.document.forms[form_name].return_action.value = 'DetailView';
	window.document.forms[form_name].return_id.value = window.document.forms[form_name].record.value;
	window.document.forms[form_name].action.value = 'Save';
	window.document.forms[form_name].submit();
}

function zzz()
{
	window.opener.document.forms['item'].hostid.value='ZZZ';
	window.close();
}

function send_back(module, id)
{
	var associated_row_data = associated_javascript_data[id];
	eval("var request_data = " + window.document.forms['popup_query_form'].request_data.value);
	var passthru_data = Object();
	if(typeof(request_data.passthru_data) != 'undefined')
	{
		passthru_data = request_data.passthru_data;
	}
	var form_name = request_data.form_name;
	var field_to_name_array = request_data.field_to_name_array;
	var call_back_function = eval("window.opener." + request_data.call_back_function);
	var array_contents = Array();


	// constructs the array of values associated to the bean that the user clicked
	for(var the_key in field_to_name_array)
	{
		if(the_key != 'toJSON')
		{
			var the_name = field_to_name_array[the_key];
			var the_value = '';

			if(module != '' && id != '')
			{
				the_value = associated_row_data[the_key.toUpperCase()];
			}
			
			array_contents.push('"' + the_name + '":"' + the_value + '"');
		}
	}
	
	eval("var name_to_value_array = {" + array_contents.join(",") + "}");

	var result_data = {"form_name":form_name,"name_to_value_array":name_to_value_array,"passthru_data":passthru_data};
	var close_popup = window.opener.get_close_popup();
	
	call_back_function(result_data);

	if(close_popup)
	{
		window.close();
	}
}
