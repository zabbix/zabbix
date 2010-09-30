var CUrlList = function(entryid, data){

	this.entryid = entryid;
	this.entryTpl = new Template($(entryid).cloneNode(true).wrap('div').innerHTML);

	this.urlid = 0;

	var div = document.createElement('div');
	div.innerHTML = '<input class="button" type="button" value="Add" />';
	var addButton = div.firstChild;
	$(addButton).observe('click', this.add.bindAsEventListener(this));


	for(var i in data){

//		$('url['+this.urlid+'][type]').selectedIndex = data[i].type;
	}

	this.add();
	$(entryid).insert({'after' : addButton});

};

CUrlList.prototype.add = function(){
	this.urlid++;
	var emptyEntry = this.entryTpl.evaluate({'urlid' : this.urlid});
	$(this.entryid).insert({'before' : emptyEntry});

	$('urlEntry_'+this.urlid).style.display = '';
};

function cloneRow(elementid, count){
	if(typeof(cloneRow.count) == 'undefined'){
		cloneRow.count = count;
	}
	cloneRow.count++;

	var tpl = new Template($(elementid).cloneNode(true).wrap('div').innerHTML);

	var emptyEntry = tpl.evaluate({'id' : cloneRow.count});

	var newEntry = $(elementid).insert({'before' : emptyEntry}).previousSibling;

	$(newEntry).descendants().each(function(e){e.removeAttribute('disabled');});
	newEntry.setAttribute('id', 'entry_'+cloneRow.count);
	newEntry.style.display = '';
}
