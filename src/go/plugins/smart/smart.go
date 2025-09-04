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

package smart

import (
	"encoding/json"
	"strings"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func (p *Plugin) diskDiscovery(params map[string]string) ([]byte, error) {
	r, err := p.execute(discoverByID(params[typeParameterName]), false)
	if err != nil {
		return nil, err
	}

	out := make([]device, 0, len(r.devices))
	for _, dev := range r.devices {
		out = append(out, device{
			Name:         cutPrefix(dev.Info.Name),
			DeviceType:   getType(dev.Info.DevType, dev.RotationRate, dev.SmartAttributes.Table),
			Model:        dev.ModelName,
			SerialNumber: dev.SerialNumber,
			Path:         dev.Info.name,
			RaidType:     dev.Info.raidType,
			Attributes:   getAttributes(dev),
		})
	}

	jsonArray, err := json.Marshal(out)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return jsonArray, nil
}

func (p *Plugin) diskGet(params map[string]string) ([]byte, error) {
	if params[pathParameterName] == "" && params[raidTypeParameterName] == "" {
		return p.diskGetAll()
	}

	return p.diskGetSingle(params[pathParameterName], params[raidTypeParameterName])
}

// diskGetSingle returns all SMART information about the device. Path to device, e.g., /dev/sda must be specified in
// path. If raidType specified, the device type is taken into account. It returns result in JSON.
func (p *Plugin) diskGetSingle(path, raidType string) ([]byte, error) {
	args := []string{"-a", path, "-j"}

	if raidType != "" {
		args = []string{"-a", path, "-d", raidType, "-j"}
	}

	device, err := p.ctl.Execute(args...)
	if err != nil {
		return nil, errs.Wrap(err, errFailedToExecute)
	}

	out, err := setSingleDiskFields(device)
	if err != nil {
		return nil, err
	}

	jsonArray, err := json.Marshal(out)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return jsonArray, nil
}

func (p *Plugin) diskGetAll() (jsonArray []byte, err error) {
	r, err := p.execute(false, true)
	if err != nil {
		return nil, err
	}

	fields, err := setDiskFields(r.jsonDevices)
	if err != nil {
		return nil, err
	}

	if fields == nil {
		jsonArray, err = json.Marshal([]string{})
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
		}

		return
	}

	jsonArray, err = json.Marshal(fields)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return
}

func (p *Plugin) attributeDiscovery(_ map[string]string) ([]byte, error) {
	r, err := p.execute(false, false)
	if err != nil {
		return nil, err
	}

	out := []attribute{}
	for _, dev := range r.devices {
		t := getAttributeType(dev.Info.DevType, dev.RotationRate, dev.SmartAttributes.Table)
		for _, attr := range dev.SmartAttributes.Table {
			out = append(
				out,
				attribute{
					Name:       cutPrefix(dev.Info.Name),
					DeviceType: t,
					ID:         attr.ID,
					Attrname:   attr.Attrname,
					Thresh:     attr.Thresh,
				})
		}
	}

	jsonArray, err := json.Marshal(out)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return jsonArray, nil
}

// setSingleDiskFields goes through provided device json data and sets required output fields.
// It returns an error if there is an issue with unmarshal for the provided input JSON map.
func setSingleDiskFields(dev []byte) (map[string]any, error) {
	attr := make(map[string]any)

	var err error
	if err = json.Unmarshal(dev, &attr); err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	var sd singleDevice
	if err = json.Unmarshal(dev, &sd); err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	diskType := getType(getTypeFromJSON(attr), getRateFromJSON(attr), getTablesFromJSON(attr))

	out := map[string]any{}
	out["disk_type"] = diskType
	out["firmware_version"] = sd.Firmware
	out["model_name"] = sd.ModelName
	out["serial_number"] = sd.SerialNumber
	out["exit_status"] = sd.Smartctl.ExitStatus

	var errors []string
	for _, msg := range sd.Smartctl.Messages {
		errors = append(errors, msg.Str)
	}

	out["error"] = strings.Join(errors, ", ")
	out["self_test_passed"] = selfTestPassed(&sd)
	out["self_test_in_progress"] = selfTestInProgress(&sd)

	if diskType == nvmeType {
		out["temperature"] = sd.HealthLog.Temperature
		out["power_on_time"] = sd.HealthLog.PowerOnTime
		out["critical_warning"] = sd.HealthLog.CriticalWarning
		out["media_errors"] = sd.HealthLog.MediaErrors
		out["percentage_used"] = sd.HealthLog.PercentageUsed
	} else {
		out["temperature"] = sd.Temperature.Current
		out["power_on_time"] = sd.PowerOnTime.Hours
		out["critical_warning"] = 0
		out["media_errors"] = json.Number("0")
		out["percentage_used"] = 0
	}

	for _, a := range sd.SmartAttributes.Table {
		if a.Name == unknownAttrName {
			continue
		}

		out[strings.ToLower(a.Name)] = singleRequestAttribute{a.Raw.Value, a.Raw.Str, a.NormalizedValue}
	}

	return out, nil
}

// selfTestPassed determines if self-test passed returning the values:
// null:  device is not self-test capable | test is in progress;
// true:   the test is passed;
// false:  the test is passed | test is being interrupted.
func selfTestPassed(sd *singleDevice) *bool {
	inPr := selfTestInProgress(sd)
	if inPr == nil || *inPr {
		return nil
	}

	return &sd.Data.SelfTest.Status.Passed
}

// selfTestInProgress determines if self-test is in progress returning the values:
// null:  device is not self-test capable;
// true:   the test is in progress;
// false:  the test is not in progress.
func selfTestInProgress(sd *singleDevice) *bool {
	if sd.Data.Capabilities.SelfTestsSupported {
		inPr := (sd.Data.SelfTest.Status.Value >> 4) == 0xf // all in progress values

		return &inPr
	}

	return nil
}

// setDiskFields goes through provided device json map and sets disk_name
// disk_type and returns the devices in a slice.
// It returns an error if there is an issue with unmarshal for the provided input JSON map
func setDiskFields(deviceJsons map[string]jsonDevice) ([]any, error) {
	out := make([]any, 0, len(deviceJsons))

	for k, v := range deviceJsons {
		b := make(map[string]any)
		if err := json.Unmarshal([]byte(v.jsonData), &b); err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON) //nolint:wrapcheck
		}

		b["disk_name"] = cutPrefix(k)
		b["disk_type"] = getType(getTypeFromJSON(b), getRateFromJSON(b), getTablesFromJSON(b))

		out = append(out, b)
	}

	return out, nil
}

func getRateFromJSON(in map[string]any) int {
	var out int
	if r, ok := in[rotationRateFieldName]; ok {
		switch rate := r.(type) {
		case int:
			out = rate
		case float64:
			out = int(rate)
		}
	}

	return out
}

func getTypeFromJSON(in map[string]any) string {
	if dev, ok := in[deviceFieldName]; ok {
		m, ok := dev.(map[string]any)
		if ok {
			if t, ok := m[typeFieldName]; ok {
				s, ok := t.(string)
				if ok {
					return s
				}
			}
		}
	}

	return ""
}

func getTablesFromJSON(in map[string]any) []table {
	var out []table

	attr, ok := in[ataSmartAttrFieldName]
	if !ok {
		return nil
	}

	a, ok := attr.(map[string]any)
	if !ok {
		return nil
	}

	tables, ok := a[ataSmartAttrTableFieldName]
	if !ok {
		return nil
	}

	tmp, ok := tables.([]any)
	if !ok {
		return nil
	}

	b, err := json.Marshal(tmp)
	if err != nil {
		return nil
	}

	err = json.Unmarshal(b, &out)
	if err != nil {
		return nil
	}

	return out
}

func getAttributeType(devType string, rate int, tables []table) string {
	if devType == unknownType {
		return unknownType
	}

	return getTypeByRateAndAttr(rate, tables)
}

func getAttributes(in deviceParser) (out string) {
	for _, table := range in.SmartAttributes.Table {
		if table.Attrname == unknownAttrName {
			continue
		}

		out = out + " " + table.Attrname
	}

	return strings.TrimSpace(out)
}

func getType(devType string, rate int, tables []table) string {
	switch devType {
	case nvmeType:
		return nvmeType
	case unknownType:
		return unknownType
	default:
		return getTypeByRateAndAttr(rate, tables)
	}
}

func getTypeByRateAndAttr(rate int, tables []table) string {
	if rate > 0 {
		return hddType
	}

	for _, t := range tables {
		if t.Attrname == spinUpAttrName {
			return hddType
		}
	}

	return ssdType
}

func discoverByID(in string) bool {
	switch in {
	case "name":
		return false
	case "id":
		return true
	default:
		panic("unknown type " + in)
	}
}
