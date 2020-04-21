/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package wmi

import (
	"errors"
	"runtime"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
)

const S_FALSE = 0x1

type stopError int

const (
	stopErrorCol stopError = iota
	stopErrorRow
)

func (e stopError) Error() string {
	return ""
}

type resultWriter interface {
	write(v *ole.IDispatch) error
}

type valueResult struct {
	data interface{}
}

// DeviceID is always appended to results which are sorted alphabetically - so it's location is not fixed.
// The following processing rules will be applied depending on query:
// * only DeviceID column is returned - it means deviceid was explicitly selected and must be returned
// * DeviceID and more columns are returned - return value of the first column not named DeviceID
func (r *valueResult) write(rs *ole.IDispatch) (err error) {
	var deviceID interface{}
	oleErr := oleutil.ForEach(rs, func(vr *ole.VARIANT) (err error) {
		row := vr.ToIDispatch()
		defer row.Release()

		raw, err := row.GetProperty("Properties_")
		if err != nil {
			return
		}
		defer raw.Clear()
		props := raw.ToIDispatch()
		defer props.Release()
		err = oleutil.ForEach(props, func(vc *ole.VARIANT) (err error) {
			col := vc.ToIDispatch()
			defer col.Release()

			name, err := oleutil.GetProperty(col, "Name")
			if err != nil {
				return
			}
			defer name.Clear()

			val, err := oleutil.GetProperty(col, "Value")
			if err != nil {
				return
			}
			if name.Value().(string) != "DeviceID" {
				r.data = val.Value()
				return stopErrorCol
			} else {
				// remeber deviceid value in the case it was the only selected column
				deviceID = val.Value()
			}
			return
		})
		if err == nil {
			return stopErrorRow
		}
		return
	})
	if stop, ok := oleErr.(stopError); !ok {
		return oleErr
	} else {
		if oleErr == nil || stop == stopErrorRow {
			r.data = deviceID
		}
	}
	return
}

type tableResult struct {
	data []map[string]interface{}
}

func variantToValue(v *ole.VARIANT) (result interface{}) {
	if (v.VT & ole.VT_ARRAY) == 0 {
		return v.Value()
	}
	return v.ToArray().ToValueArray()
}

func (r *tableResult) write(rs *ole.IDispatch) (err error) {
	r.data = make([]map[string]interface{}, 0)
	oleErr := oleutil.ForEach(rs, func(v *ole.VARIANT) (err error) {
		rsRow := make(map[string]interface{})
		row := v.ToIDispatch()
		defer row.Release()

		raw, err := row.GetProperty("Properties_")
		if err != nil {
			return
		}
		defer raw.Clear()
		props := raw.ToIDispatch()
		defer props.Release()
		err = oleutil.ForEach(props, func(v *ole.VARIANT) (err error) {
			col := v.ToIDispatch()
			defer col.Release()

			name, err := oleutil.GetProperty(col, "Name")
			if err != nil {
				return
			}
			defer name.Clear()
			val, err := oleutil.GetProperty(col, "Value")
			if err != nil {
				return
			}
			defer val.Clear()
			rsRow[name.ToString()] = variantToValue(val)
			return
		})
		r.data = append(r.data, rsRow)
		return
	})
	if _, ok := oleErr.(stopError); !ok {
		return oleErr
	}
	return
}

func performQuery(namespace string, query string, w resultWriter) (err error) {
	runtime.LockOSThread()
	defer runtime.UnlockOSThread()
	if oleErr := ole.CoInitializeEx(0, ole.COINIT_MULTITHREADED); oleErr != nil {
		oleCode := oleErr.(*ole.OleError).Code()
		if oleCode != ole.S_OK && oleCode != S_FALSE {
			return oleErr
		}
	}
	defer ole.CoUninitialize()

	unknown, err := oleutil.CreateObject("WbemScripting.SWbemLocator")
	if err != nil {
		return
	}
	if unknown == nil {
		return errors.New("Cannot create SWbemLocator object.")
	}
	defer unknown.Release()

	disp, err := unknown.QueryInterface(ole.IID_IDispatch)
	if err != nil {
		return
	}
	defer disp.Release()

	raw, err := oleutil.CallMethod(disp, "ConnectServer", nil, namespace)
	if err != nil {
		return
	}
	service := raw.ToIDispatch()
	defer raw.Clear()

	if raw, err = oleutil.CallMethod(service, "ExecQuery", query); err != nil {
		return
	}
	result := raw.ToIDispatch()
	defer raw.Clear()

	v, err := oleutil.GetProperty(result, "Count")
	if err != nil {
		return
	}
	defer v.Clear()
	count := int64(v.Val)
	if count == 0 {
		return
	}
	return w.write(result)
}

// QueryValue returns the value of the first column of the first row returned by the query.
// The value type depends on the column type and can one of the following:
//   nil, int64, uin64, float64, string
func QueryValue(namespace string, query string) (value interface{}, err error) {
	var r valueResult
	if err = performQuery(namespace, query, &r); err != nil {
		return
	}
	return r.data, nil
}

// QueryValue returns the result set returned by the query in a slice of maps, containing
// field name, value pairs. The field values can be either nil (null value) or pointer of
// the value in string format.
func QueryTable(namespace string, query string) (table []map[string]interface{}, err error) {
	var r tableResult
	if err = performQuery(namespace, query, &r); err != nil {
		return
	}
	return r.data, nil
}
