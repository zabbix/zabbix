// JavaScript Document
var blink = {
	blinkobjs: new Array(),
	
	init: function(){
		this.blinkobjs = document.getElementsByName("blink");
		if(this.blinkobjs.length > 0) this.view();
	},
	hide: function(){
		for(var id=0; id<this.blinkobjs.length; id++){
			this.blinkobjs[id].style.visibility = 'hidden';
		}
		setTimeout('blink.view()',500);
	},
	view: function(){
		for(var id=0; id<this.blinkobjs.length; id++){
			this.blinkobjs[id].style.visibility = 'visible'
		}
		setTimeout('blink.hide()',750);
	}
}