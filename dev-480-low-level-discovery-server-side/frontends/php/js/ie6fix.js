var ie6pngfix = {
root:				false,
applyPositioning:	false,
shim:				'images/general/x.gif',	// Path to a transparent GIF image

run: function(el) {
	this.root = el;
	this.fnLoadPngs();
},
		
fnLoadPngs: function(){
	if(this.root){
		this.root = document.getElementById(this.root);
	}
	else{
		this.root = document;
	}

	for (var i = this.root.all.length - 1, obj = null; (obj = this.root.all[i]); i--) {
		if(obj.currentStyle.backgroundImage.match(/\.png/i) !== null)  this.bg_fnFixPng(obj);
		if(obj.tagName=='IMG' && obj.src.match(/\.png$/i) !== null) this.el_fnFixPng(obj);

// apply position to 'active' elements
		if(this.applyPositioning && (obj.tagName=='A' || obj.tagName=='INPUT') && obj.style.position === ''){
			obj.style.position = 'relative';
		}
	}
},

bg_fnFixPng: function(obj) {
	var mode = 'scale';
	var bg	= obj.currentStyle.backgroundImage;
	var src = bg.substring(5,bg.length-2);
	if (obj.currentStyle.backgroundRepeat == 'no-repeat') {
		mode = 'crop';
	}
	obj.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + src + "', sizingMethod='" + mode + "')";
	obj.style.backgroundImage = 'url('+this.shim+')';
},

el_fnFixPng: function(img) {
	var src = img.src;
	img.style.width = img.width + "px";
	img.style.height = img.height + "px";
	img.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + src + "', sizingMethod='scale')";
	img.src = this.shim;
}
};


/************************************************************************************/
/*										IE 6 FIXES 									*/
/************************************************************************************/

function hidePopupDiv(iFrameID){
	if(!IE6) return;

	if(!is_null($(iFrameID))){
		$(iFrameID).hide();
		$(iFrameID).remove();
	}
}

function showPopupDiv(divID,iFrameID){
	if(!IE6) return;

	var iFrame = $(iFrameID);
	var divPopup = $(divID);

	if(is_null(iFrame)){
		var iFrame = document.createElement('iframe');
		document.body.appendChild(iFrame);

//Match IFrame position with divPopup
		iFrame.setAttribute('id',iFrameID);
		iFrame.style.position='absolute';
	}

	if(divPopup.style.display == 'none'){
		iFrame.style.display = 'none';
		return;
	}

//Increase default zIndex of div by 1, so that DIV appears before IFrame
	divPopup.style.zIndex=divPopup.style.zIndex+1;
	//iFrame.style.zIndex = 1;

	var divCumOff = $(divID).cumulativeOffset();
	iFrame.style.display = 'block';
	iFrame.style.left = divCumOff.left + 'px';
	iFrame.style.top = divCumOff.top + 'px';
	iFrame.style.width = divPopup.offsetWidth + 'px';
	iFrame.style.height = divPopup.offsetHeight + 'px';
}