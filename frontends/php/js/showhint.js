// JavaScript Document
function GetPos(obj)
{
	var left = obj.offsetLeft;
	var top  = obj.offsetTop;;
	while (obj = obj.offsetParent)
	{
		left	+= obj.offsetLeft
		top	+= obj.offsetTop
	}
	return [left,top];
}

var hint_box = null;

function hide_hint()
{
	if(!hint_box) return;

	hint_box.style.visibility="hidden"
	hint_box.style.left	= "-" + ((hint_box.style.width) ? hint_box.style.width : 100) + "px";
}

function show_hint(obj, e, hint_text)
{
	show_hint_ext(obj, e, hint_text, "", "");
}

function show_hint_ext(obj, e, hint_text, width, class_name)
{
	if(!hint_box) return;

	var cursor = get_cursor_position(e);
	
	if(class_name != "")
	{
		hint_text = "<span class=" + class_name + ">" + hint_text + "</"+"span>";
	}

	hint_box.innerHTML = hint_text;
	hint_box.style.width = width;

	var pos = GetPos(obj);

	hint_box.x	= pos[0];
	hint_box.y	= pos[1];

	hint_box.style.left	= cursor.x + 10 + "px";
	//hint_box.style.left	= hint_box.x + obj.offsetWidth + 10 + "px";
	hint_box.style.top	= hint_box.y + obj.offsetHeight + "px";

	hint_box.style.visibility = "visible";
	obj.onmouseout	= hide_hint;
}

function update_hint(obj, e)
{
	if(!hint_box) return;

	var cursor = get_cursor_position(e);

	var pos = GetPos(obj);

	hint_box.style.left     = cursor.x + 10 + "px";
	hint_box.style.top      = hint_box.y + obj.offsetHeight + "px";
}

function create_hint_box()
{
	if(hint_box) return;

	hint_box = document.createElement("div");
	hint_box.setAttribute("id", "hint_box");
	document.body.appendChild(hint_box);

	hide_hint();
}

if (window.addEventListener)
{
	window.addEventListener("load", create_hint_box, false);
}
else if (window.attachEvent)
{
	window.attachEvent("onload", create_hint_box);
}
else if (document.getElementById)
{
	window.onload	= create_hint_box;
}
//-->
