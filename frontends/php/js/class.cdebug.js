/*!
 * This file is part of Zabbix software.
 *
 * Copyright 2000-2011, Zabbix SIA
 * Licensed under the GPL Version 2 license.
 * http://www.zabbix.com/licence.php
 */

// Author: Aly
var CDebug = Class.create({
className:		null,			// debuging class name
debug_status:	0,				// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info:		'',				// debug string

// ---------- DEBUG ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------
initialize: function(className){
	this.className = className;
},

debug: function(str, id){
	if(this.debug_status){
		str = this.className+'. '+str;

		if(typeof(id) != 'undefined') str+= ' :'+id;

//		if(this.debug_prev == str) return true;

		this.debug_info += str+'\n';
		if(this.debug_status == 2){
			SDI(str);
		}

		this.debug_prev = str;
	}
},

notify: function(){
},

info: function(msg){
	msg = msg || 'Info.'
	alert(msg);
},

error: function(msg){
	msg = msg || 'Error.'
	throw(msg);
}
});
