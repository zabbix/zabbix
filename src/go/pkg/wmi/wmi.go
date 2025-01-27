/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package wmi

import (
	"errors"
	"runtime"

	"github.com/go-ole/go-ole"
	"github.com/go-ole/go-ole/oleutil"
	"golang.zabbix.com/sdk/log"
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

func clearOle(quals *ole.VARIANT) {
	if err := quals.Clear(); err != nil {
		log.Errf("cannot clear OLE: %s", err)
	}
}

func isPropertyKeyProperty(propsCol *ole.IDispatch) (isKeyProperty bool, err error) {
	rawQuals, err := propsCol.GetProperty("Qualifiers_")
	if err != nil {
		return false, err
	}

	quals := rawQuals.ToIDispatch()
	defer quals.Release()

	isKeyProperty = false

	// loop through Property Qualifiers to find if this Property
	// is a Key and should be skipped unless specifically queried for
	oleErr := oleutil.ForEach(quals, func(vc2 *ole.VARIANT) (err error) {
		qualsCol := vc2.ToIDispatch()
		defer qualsCol.Release()

		qualsName, err := oleutil.GetProperty(qualsCol, "Name")
		if err != nil {
			return
		}
		defer clearOle(qualsName)

		if qualsName.Value().(string) == "key" {
			isKeyProperty = true
			return stopErrorCol
		}
		return
	})
	if _, ok := oleErr.(stopError); !ok {
		return false, oleErr
	}

	return isKeyProperty, nil
}

// Key Qualifier ('Name', 'DeviceID', 'Tag' etc.) is always appended to results which are sorted alphabetically,
// so it's location is not fixed.
// The following processing rules will be applied depending on query:
//   - only Key Qualifier column is returned - it means Key Qualifier was explicitly selected and must be returned
//   - Key Qualifier and more columns are returned - return value of the first column not having a 'key' entry in
//     its Qualifiers_ property list
func (r *valueResult) write(rs *ole.IDispatch) (err error) {
	v, err := oleutil.GetProperty(rs, "Count")
	if err != nil {
		return err
	}
	defer clearOle(v)
	if v.Val == 0 {
		return errors.New("Empty WMI search result.")
	}

	var propertyKeyFieldValue interface{}

	oleErr := oleutil.ForEach(rs, func(vr *ole.VARIANT) (err error) {
		row := vr.ToIDispatch()
		defer row.Release()

		rawProps, err := row.GetProperty("Properties_")
		if err != nil {
			return
		}

		props := rawProps.ToIDispatch()
		defer props.Release()

		err = oleutil.ForEach(props, func(vc *ole.VARIANT) (err error) {
			propsCol := vc.ToIDispatch()
			defer propsCol.Release()

			propsName, err := oleutil.GetProperty(propsCol, "Name")
			if err != nil {
				return
			}
			defer clearOle(propsName)

			propsVal, err := oleutil.GetProperty(propsCol, "Value")
			if err != nil {
				return
			}
			defer clearOle(propsVal)

			if propsVal.Value() == nil {
				return errors.New("Empty WMI search result.")
			}

			isKeyProperty, err := isPropertyKeyProperty(propsCol)
			if err != nil {
				return
			}

			if !isKeyProperty {
				r.data = propsVal.Value()
				return stopErrorCol
			}
			// remember key field value in the case it was the only selected column
			propertyKeyFieldValue = propsVal.Value()
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
			r.data = propertyKeyFieldValue
		}
	}
	return
}

type tableResult struct {
	data []map[string]interface{}
}

func variantToValue(v *ole.VARIANT) (result interface{}) {
	if (v.VT & ole.VT_ARRAY) == 0 {
		ret := v.Value()

		if dispatch, ok := ret.(*ole.IDispatch); ok {
			prop, err := oleutil.GetProperty(dispatch, "Value")
			if err == nil {
				defer prop.Clear()
				return prop.Value()
			}

			return nil
		}

		return ret
	}

	return v.ToArray().ToValueArray()
}

// Key Qualifier is always appended to the result from ole library. This behavior is different from agent 1 which
// uses the wmi enumerator and can set the WBEM_FLAG_NONSYSTEM_ONLY flag that would filter it. Ole library has only
// the basic enumerator that cannot set any flags.
// So that, we end up with these results for wmi.getAll where cases 1 and 2 are inconsistent with agent 1:
//  1. 1 non-Key qualifier field selected	- key qualifier attached to the result, 2 elements are returned
//  2. N non-Key qualifier fields selected	- key qualifier attached to the result, N+1 elements are returned
//  3. Key qualifier field selected		- single key qualifier element is returned
//  4. 1 non-Key qualifier and 1 Key qualifier elements selected
//     - 2 elements are returned
//  5. N fields selected and one of them is a Key-qualifier
//     - N elements are returned
//  6. * is selected				- all elements are returned (including the Key-qualifier)
func (r *tableResult) write(rs *ole.IDispatch) (err error) {
	v, err := oleutil.GetProperty(rs, "Count")
	if err != nil {
		return err
	}
	defer clearOle(v)

	r.data = make([]map[string]interface{}, 0)

	oleErr := oleutil.ForEach(rs, func(v *ole.VARIANT) (err error) {
		rsRow := make(map[string]interface{})
		row := v.ToIDispatch()
		defer row.Release()

		rawProps, err := row.GetProperty("Properties_")
		if err != nil {
			return
		}

		props := rawProps.ToIDispatch()
		defer props.Release()
		err = oleutil.ForEach(props, func(v *ole.VARIANT) (err error) {
			propsCol := v.ToIDispatch()
			defer propsCol.Release()

			propsName, err := oleutil.GetProperty(propsCol, "Name")
			if err != nil {
				return
			}
			defer clearOle(propsName)

			propsVal, err := oleutil.GetProperty(propsCol, "Value")
			if err != nil {
				return
			}
			defer clearOle(propsVal)

			variantValue := variantToValue(propsVal)
			if variantValue != nil {
				rsRow[propsName.ToString()] = variantValue
			}

			return
		})

		if 0 < len(rsRow) {
			r.data = append(r.data, rsRow)
		}

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

	return w.write(result)
}

// QueryValue returns the value of the first column of the first row returned by the query.
// The value type depends on the column type and can one of the following:
//
//	nil, int64, uin64, float64, string
func QueryValue(namespace string, query string) (value interface{}, err error) {
	var r valueResult
	if err = performQuery(namespace, query, &r); err != nil {
		return
	}
	return r.data, nil
}

// QueryTable returns the result set returned by the query in a slice of maps, containing
// field name, value pairs. The field values can be either nil (null value) or pointer of
// the value in string format.
func QueryTable(namespace string, query string) (table []map[string]interface{}, err error) {
	var r tableResult
	if err = performQuery(namespace, query, &r); err != nil {
		return
	}
	return r.data, nil
}
