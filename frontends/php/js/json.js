// JavaScript Document
function callJSON(){
//	json.clean();
	json.createScript('dashboard.php?output=json');
	json.buildAll();
	json.addAll();
}

var json = {
scripts: 		new Array(),	// array of existing scripts id's
nextId:			1,				// id of next script tag

head:			'',				// DOM Head obj

initialize: function(){
	this.head = document.getElementsByTagName("head").item(0);
},

callBack: function(){
	if(this.callBack.arguments.length > 0)
		alert(this.callBack.arguments[0])
	else 
		alert('callBack!');
},

onetime: function(url){
	var onetimeid;
	onetimeid = this.createScript(url);
	this.buildScript(onetimeid);
	this.addScript(onetimeid);
},

createScript: function(url){
	this.scripts[this.nextId] = {
		'id':	this.nextId,
		'fullurl': url+'&jsscriptid='+this.nextId,
		'noCacheIE': '&noCacheIE=' + (new Date()).getTime(),
		'scriptId': 'JscriptId' + this.nextId,
		'status': 1
	};
//SDI('create:' + this.nextId);
	this.nextId++;
return (this.nextId-1);
},

buildScript: function(id){
	if(isset(id)){
		if(isset(this.scripts[id]) && !empty(this.scripts[id]) && (this.scripts[id].status == 1)){
			var scriptObj = document.createElement("script");
		
			// Add script object attributes
			scriptObj.setAttribute("type", "text/javascript");
			scriptObj.setAttribute("charset", "utf-8");
		
			scriptObj.setAttribute("src", this.scripts[id].fullurl+this.scripts[id].noCacheIE);
		
			scriptObj.setAttribute("id", this.scripts[id].scriptId);
			
			this.scripts[id].scriptObj = scriptObj;
			this.scripts[id].status = 2;
		}
	}
},

buildAll: function() {
	for(var i=1; i < this.nextId; i++){
		this.buildScript(i);
	}
},

addScript: function(id){
	if(isset(id)){
		if(isset(this.scripts[id]) && !empty(this.scripts[id]) && (this.scripts[id].status == 2)){
			this.head.appendChild(this.scripts[id].scriptObj);
			this.scripts[id].status = 3;
		}
	}	
},

addAll: function(){
	for(var i=1; i < this.nextId; i++){
		this.addScript(i);
	}
},

removeScript: function(id){
	if(isset(id)){
		if(isset(this.scripts[id]) && !empty(this.scripts[id]) && (this.scripts[id].status == 3)){
//SDI('remove:'+this.scripts[id].scriptId);
		    this.head.removeChild(this.scripts[id].scriptObj);  
			this.scripts[id] = null;			
		}
	}	
},

clean: function(){
	for(var i=1; i < this.nextId; i++){
		this.removeScript(i);
	}
	this.scripts = new Array();
	this.nextId = 1;
}
}

json.initialize();