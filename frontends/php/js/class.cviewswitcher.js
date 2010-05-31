/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

var CViewSwitcher = Class.create();

CViewSwitcher.prototype = {
  mainObj : null,
  shownIds : new Array(),
  depIds : {},
  lastValue : null,

  initialize : function(objId, objAction, confData) {
    
    this.depIds = confData;
    this.mainObj = document.getElementById(objId);

    if(!is_array(objAction)) objAction = new Array(objAction);
    
    for(var i = 0; i < objAction.length; i++)
      addListener(this.mainObj, objAction[i], this.rebuildView.bindAsEventListener(this));

    this.rebuildView();
  },
  
  rebuildView : function () {
    var myValue = this.objValue();

    if(myValue == this.lastValue) return;

    for(var i  = 0; i < this.shownIds.length; i++) {
      this.shownIds[i].style.display = 'none';
      this.shownIds[i].setAttribute('disabled', 'disabled');
    }

    this.shownIds = null;
    this.shownIds = new Array();

    if(this.depIds[myValue]) {
      for(var i in this.depIds[myValue]) {
        var elm = document.getElementById(this.depIds[myValue][i]);

        if(!elm) continue;

        if(elm.removeAttribute) elm.removeAttribute('disabled');
        elm.style.display = 'inline';
        this.shownIds.push(elm);
      }
    }
    
    this.lastValue = myValue;
  },
  
  objValue : function () {
    var aValue;
    
    if(this.mainObj.tagName) {
      switch(this.mainObj.tagName.toString().toLowerCase()) {
        case 'select':
          aValue = this.mainObj.options[this.mainObj.selectedIndex].value;
        break;
        case 'input':
          //TO DO should be added support of checkboxes, radio and etc
          aValue = this.mainObj.value;
        break;
        case 'textarea':
          aValue = this.mainObj.value;
        break;
        default:
          aValue = this.mainObj.valueOf();
      }
    }else
      aValue = this.mainObj.valueOf();

    return aValue;
  }
}
