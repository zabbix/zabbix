// JavaScript Document

var chkbx_range_ext = {
startbox: 			null,			// start checkbox obj
startbox_name: 		null,			// start checkbox name
chkboxes:			new Array(),	// ckbx list

init: function(){
	var chk_bx = document.getElementsByTagName('input');
	for(var i=0; i < chk_bx.length; i++){
		if((typeof(chk_bx[i]) != 'undefined') && (chk_bx[i].type.toLowerCase() == 'checkbox')){
			this.implement(chk_bx[i]);
		}
	}
},

implement: function(obj){
	var obj_name = obj.name.split('[')[0];

	if(typeof(this.chkboxes[obj_name]) == 'undefined') this.chkboxes[obj_name] = new Array();
	this.chkboxes[obj_name].push(obj);

	addListener(obj, 'click', this.check.bindAsEventListener(this), false);
},

check: function(e){
	var e = e || window.event;
	if(!e.ctrlKey) return true;
	var obj = eventTarget(e);

	if((typeof(obj) != 'undefined') && (obj.type.toLowerCase() == 'checkbox')){
		var obj_name = obj.name.split('[')[0];

		if(!is_null(this.startbox) && (this.startbox_name == obj_name) && (obj.name != this.startbox.name)){
			var chkbx_list = this.chkboxes[obj_name];
			var flag = false;
			
			for(var i=0; i < chkbx_list.length; i++){
				if(typeof(chkbx_list[i]) !='undefined'){
//alert(obj.name+' == '+chkbx_list[i].name);
					if(flag){
						chkbx_list[i].checked = this.startbox.checked;
					}
					
					if(obj.name == chkbx_list[i].name) break;
					if(this.startbox.name == chkbx_list[i].name) flag = true;
				}
			}
			if(flag){
				this.startbox = null;
				this.startbox_name = null;
				return true;
			}
			else{
				for(var i=chkbx_list.length-1; i >= 0; i--){
					if(typeof(chkbx_list[i]) !='undefined'){
//alert(obj.name+' == '+chkbx_list[i].name);			
						if(flag){
							chkbx_list[i].checked = this.startbox.checked;
						}
						
						if(obj.name == chkbx_list[i].name){
							this.startbox = null;
							this.startbox_name = null;
							return true;
						}
						if(this.startbox.name == chkbx_list[i].name) flag = true;
					}
				}	
			}

		}
		else{
			if(!is_null(this.startbox)) this.startbox.checked = !this.startbox.checked;
			
			this.startbox = obj;
			this.startbox_name = obj_name;
		}
	}
}
}