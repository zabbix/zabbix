// JavaScript Document

function check_target(e)
{
	var targets = document.getElementsByName('expr_target');
	for (var i = 0; i < targets.length; ++i) {
		targets[i].checked = targets[i] == e;
	}
}

function delete_expression(expr_id)
{
	document.getElementsByName('remove_expression')[0].value = expr_id;
}

function copy_expression(id)
{
	var expr_temp = document.getElementsByName('expr_temp')[0];
	if (expr_temp.value.length > 0 && !confirm('Do you replace the conditional expression?')) return;

	var src = document.getElementById(id);
	if (typeof src.textContent != 'undefined') expr_temp.value = src.textContent;
	else expr_temp.value = src.innerText;
}

function call_menu(ev)
{
	show_popup_menu(ev,
					[
						['Insert macro',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						['TRIGGER.VALUE=0', 'javascript: set_macro(0);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE=1', 'javascript: set_macro(1);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE=2', 'javascript: set_macro(2);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE#0', 'javascript: set_macro(10);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE#1', 'javascript: set_macro(11);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE#2', 'javascript: set_macro(12);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
					],150);
	return false;
}

function set_macro(v)
{
	var expr_temp = document.getElementsByName('expr_temp')[0];
	if (expr_temp.value.length > 0 && !confirm('Do you replace the conditional expression?')) return;

	var sign = '=';
	if (v >= 10) {
		v %= 10;
		sign = '#';
	}

	expr_temp.value = '{TRIGGER.VALUE}' + sign + v;
}
