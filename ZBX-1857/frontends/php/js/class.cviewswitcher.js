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
  inAction : false,
  mainObj : null,
  curObj : null,
  changedFields : {},
  depIds : {},
  lastValue : null,

  initialize : function(objId, objAction, confData) {

    this.inAction = false;
    this.depIds = confData;
    this.mainObj = document.getElementById(objId);

    if(!is_array(objAction)) objAction = new Array(objAction);

    var me = this; // required for next action;
    this.mainObj.rebuildView = function () { me.rebuildView(); };

    for(var i = 0; i < objAction.length; i++) {
      addListener(this.mainObj, objAction[i], this.rebuildView.bindAsEventListener(this));
    }

    this.hideAllObjs();
    this.rebuildView();
  },

  rebuildView : function () {
    if(this.inAction === true) return;
    this.inAction = true;

    var myValue = this.objValue(this.mainObj);

    if(myValue == this.lastValue) {
      this.inAction = false;
      return;
    }

    if(this.lastValue && this.depIds[this.lastValue]) {
      //alert('HIDING\n'+this.mainObj.id+'\n'+this.lastValue+': '+this.depIds[this.lastValue]);
      for(var i in this.depIds[this.lastValue]) {
        if(typeof(this.depIds[this.lastValue][i]) != 'string' && this.depIds[this.lastValue][i] != 'object' && !this.depIds[this.lastValue][i].id) {
          if(typeof(this.depIds[this.lastValue][i]) != 'function') alert(this.depIds[this.lastValue][i]+': '+typeof(this.depIds[this.lastValue][i]));
          continue;
        }
        //alert('HIDING\n'+this.mainObj.id+'\n'+this.lastValue+': '+this.depIds[this.lastValue][i]);
        //if(this.depIds[this.lastValue][i] && !this.depIds[this.lastValue][i].id) this.depIds[this.lastValue][i] = {id: this.depIds[this.lastValue][i], value:''};

        //var elm = document.getElementById(this.depIds[this.lastValue][i].id);
        var elm = document.getElementById(this.depIds[this.lastValue][i].id ? this.depIds[this.lastValue][i].id : this.depIds[this.lastValue][i]);
        if(!elm) {
//          alert(this.depIds[this.lastValue][i]);
          continue;
        }

        this.curObj = this.depIds[this.lastValue][i];
        this.hideObj(elm);
        this.depIds[this.lastValue][i] = this.curObj;
        this.curObj = null;
      }
    }

    //this.shownIds = null;
    //this.shownIds = new Array();

    if(myValue && this.depIds[myValue]) {
      //alert('SHOWING\n'+this.mainObj.id+'\n'+myValue+': '+this.depIds[myValue]);
      for(var i in this.depIds[myValue]) {
        if(typeof(this.depIds[myValue][i]) != 'string' && this.depIds[myValue][i] != 'object' && !this.depIds[myValue][i].id) {
          if(typeof(this.depIds[myValue][i]) != 'function') alert(this.depIds[myValue][i]+': '+typeof(this.depIds[myValue][i]));
          continue;
        }
        //alert('SHOWING\n'+this.mainObj.id+'\n'+myValue+': '+this.depIds[myValue][i]);
        //if(this.depIds[myValue][i] && !this.depIds[myValue][i].id) this.depIds[myValue][i] = {id: this.depIds[myValue][i], value:''};

        //var elm = document.getElementById(this.depIds[myValue][i].id);
        var elm = document.getElementById(this.depIds[myValue][i].id ? this.depIds[myValue][i].id : this.depIds[myValue][i]);
        if(!elm) {
//          alert(this.depIds[myValue][i]);
          continue;
        }

        this.curObj = this.depIds[myValue][i];
        this.showObj(elm);
        this.depIds[myValue][i] = this.curObj;
        this.curObj = null;
      }
    }

    this.lastValue = myValue;
    this.inAction = false;
  },

  objValue : function (obj) {
    var aValue;

    if(obj && obj.tagName && !obj.disabled) {
      switch(obj.tagName.toString().toLowerCase()) {
        case 'select':
          aValue = obj.options[obj.selectedIndex].value;
        break;
        case 'input':
          //TO DO should be added support of checkboxes, radio and etc
          inpType = obj.getAttribute('type') || obj.type;
          if(inpType) {
            switch(inpType.toLowerCase()) {
              case 'checkbox':
                aValue = obj.checked ? obj.value : null;
              break;
              default:
                aValue = obj.value;
            }
          }else {
            aValue = null;
          }
        break;
        case 'textarea':
          aValue = obj.value;
        break;
        default:
          aValue = null; //obj.valueOf();
      }
    }else if(obj.disabled) {
      aValue = null;
    }else
      aValue = null; //obj.valueOf();

    return aValue;
  },

  setObjValue : function (obj, objVal) {
    //SDI(objVal);
    if(obj && obj.tagName && !obj.disabled) {
      switch(obj.tagName.toString().toLowerCase()) {
        case 'select':
          for(var o in obj.options) {
            if(obj.options[o].value == objVal) {
              obj.selectedIndex = o;
              break;
            }
          }
        break;
        case 'input':
          //TO DO should be added support of checkboxes, radio and etc
          inpType = obj.getAttribute('type') || obj.type;
          if(inpType) {
            switch(inpType.toLowerCase()) {
              case 'checkbox':
              case 'radio':
                obj.checked = obj.value == objVal;
              break;
              default:
                obj.value = objVal;
            }
          }
        break;
        case 'textarea':
          obj.value = objVal;
        break;
      }
    }
  },

  objDisplay : function (obj) {
    //if(obj.className == 'form_odd_even_hide') obj.className = 'form_odd_even';
    //obj.style.visibility = 'visible';
    if(obj.tagName) {
      switch(obj.tagName.toString().toLowerCase()) {
        case 'th':
        case 'td':
          obj.style.display = 'table-cell';
        break;
        case 'tr':
          obj.style.display = 'table-row';
        break;
        default:
          obj.style.display = 'inline';
      }
    }
  },

  disableObj : function (obj, disable) {
    disable = disable ? true : false;
    if(obj.tagName) {
      switch(obj.tagName.toString().toLowerCase()) {
        case 'button':
        case 'input':
        case 'optgroup':
        case 'option':
        case 'select':
        case 'textarea':
          obj.disabled = disable;
          if(obj.rebuildView) obj.rebuildView();
        break;
      }
    }
  },

  hideObj : function (obj) {
    obj.style.display = 'none';
    //obj.style.visibility = 'collapse';
    //if(obj.className == 'form_odd_even') obj.className = 'form_odd_even_hide';
    if(this.curObj && this.curObj.id) {
      var objVal = this.objValue(obj),
          elmVal;

      if(typeof(this.curObj.value) != 'undefined') {
        var elm = document.getElementById(this.curObj.value);
        if(elm) elmVal = this.objValue(elm);
      }

      if(typeof(elmVal) != 'undefined') this.setObjValue(elm, objVal);

      if(typeof(elmVal) != 'undefined' || typeof(this.curObj.defaultValue) != 'undefined' && objVal === this.curObj.defaultValue) {
        if(typeof(elmVal) != 'undefined') this.setObjValue(obj, elmVal);
        else this.setObjValue(obj, null);
      }
    }
      //SDI(this.curObj.id+': '+this.curObj.value);
//    }

    this.disableObj(obj, true);
  },

  showObj : function (obj) {
    this.disableObj(obj, false);
    if(this.curObj && this.curObj.id) {
      var objVal = this.objValue(obj),
          elmVal;

      if(typeof(this.curObj.value) != 'undefined') {
        var elm = document.getElementById(this.curObj.value);
        if(elm) elmVal = this.objValue(elm);
      }

      if(typeof(this.curObj.defaultValue) != 'undefined' && this.changedFields[this.curObj.id] === false && objVal === '' && (typeof(elmVal) == 'undefined' || elmVal === ''))
        this.setObjValue(obj, this.curObj.defaultValue);
      else if(typeof(elmVal) != 'undefined')
        this.setObjValue(obj, elmVal);

      if(typeof(elmVal) != 'undefined') this.setObjValue(elm, objVal);
    }
    this.objDisplay(obj);
  },

  hideAllObjs : function () {

    for(var i in this.depIds) {
      for(var a in this.depIds[i]) {
        if(typeof(this.depIds[i][a]) != 'string' && typeof(this.depIds[i][a]) != 'object' && !this.depIds[i][a].id) continue;
        // if(this.depIds[i][a] && !this.depIds[i][a].id) this.depIds[i][a] = {id: this.depIds[i][a], value:''};

        // var elm = document.getElementById(this.depIds[i][a]['id']);
        var elm = document.getElementById(typeof(this.depIds[i][a]) == 'object' ? this.depIds[i][a].id : this.depIds[i][a]);
        if(!elm) continue;

        this.hideObj(elm);
        if(this.depIds[i][a].defaultValue) {
          var me = this;
          this.changedFields[this.depIds[i][a].id] = false;
          addListener(elm, 'change', function () { me.changedFields[this.getAttribute('id')] = true; });
          addListener(elm, 'keyup', function () { me.changedFields[this.getAttribute('id')] = true; });
          addListener(elm, 'keydown', function () { me.changedFields[this.getAttribute('id')] = true; });
        }
      }
    }
  }
}
