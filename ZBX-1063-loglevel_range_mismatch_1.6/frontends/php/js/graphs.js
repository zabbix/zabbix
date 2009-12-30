// JavaScript Document
var graphs = {
graphtype : 0,
	
submit : function(obj){
	if(obj.name == 'graphtype'){
		if(((obj.selectedIndex > 1) && (this.graphtype < 2)) || ((obj.selectedIndex < 2) && (this.graphtype > 1))){
			var refr = document.getElementsByName('form_refresh');
			refr[0].value = 0;
		} 
	}
	document.getElementsByName('frm_graph')[0].submit();
}
}