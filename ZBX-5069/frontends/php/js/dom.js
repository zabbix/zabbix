// JavaScript Document
// DOM Classes
// Author: Aly

var CNode = Class.create({
node:		null,			// main node (ul)
initialize: function(nodeName){
	this.node = document.createElement(nodeName);
	return this.node;
},

addItem: function(item){

	if(is_object(item)) this.node.appendChild(item);
	else if(is_string(item)) this.node.appendChild(documect.createTextNode(item));
	else return true;
},

setClass: function(className){
	className = className || '';

	this.node.className = className;
}
});


var CList = Class.create(CNode,{
items:		new Array(),	// items list
initialize: function($super, className){
	className = className || '';

	$super('ul');
	this.setClass(this.classNames);

	Object.extend(this.node, this);
},

addItem: function($super, item, className){
	className = className || '';

	if(!is_object(item, CListItem)){
		item = new CListItem(item, className).node;
	}

	$super(item);
	this.items.push(item);
}
});

var CListItem = Class.create(CNode,{
items:		new Array(),	// items list
initialize: function($super, item, className){
	className = className || '';
	item = item || null;

	$super('li');
	this.setClass(className);
	this.addItem(item);
},

addItem: function($super, item){
	$super(item);
	this.items.push(item);
}
});
