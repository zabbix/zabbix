// JavaScript Document

var cookie ={
time : '',
path : '/',

create : function(name,value,time) {
	if((typeof(time) != 'undefined')) {
		var date = new Date();
		date.setTime(date.getTime()+time);
		var expires = "; expires="+date.toGMTString();
		document.cookie = name+"="+value+expires+'; path='+this.path;
		return;
	} else if(this.time != '') {
		var date = new Date();
		date.setTime(date.getTime()+this.time);
		var expires = "; expires="+date.toGMTString();
		document.cookie = name+"="+value+expires+'; path='+this.path;
		return;
	} else {
		document.cookie = name+"="+value;
	}
},

read : function(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
},

erase : function(name) {
	this.create(name,"",-1);
}

}