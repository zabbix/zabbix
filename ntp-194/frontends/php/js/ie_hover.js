// JavaScript Document

function attach_iehover(elem){
	var mouseover = function() {
		elem.style.textDecoration = 'underline';
		elem.style.backgroundColor = '#FDFDFF';
	}
	addListener(elem, 'mouseover', mouseover);


	var mouseout = function() {
		elem.style.textDecoration = '';
		elem.style.backgroundColor = '#ECECFF';
	}
	addListener(elem, 'mouseout', mouseout);
}