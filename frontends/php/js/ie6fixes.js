// JavaScript Document

function hidePopupDiv(iFrameID){
	if(!IE6) return;
	$(iFrameID).hide();
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
//	iFrame.style.zIndex = 1;


	iFrame.style.display = 'block';
	iFrame.style.left = divPopup.offsetLeft + 'px';
	iFrame.style.top = divPopup.offsetTop + 'px';
	iFrame.style.width = divPopup.offsetWidth + 'px';
	iFrame.style.height = divPopup.offsetHeight + 'px';
}