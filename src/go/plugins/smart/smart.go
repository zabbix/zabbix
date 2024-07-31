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
	"runtime"
	"strings"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	twoParameters = 2
	oneParameter  = 1
	all           = 0

	firstParameter  = 0
	secondParameter = 1

	diskGet            = "smart.disk.get"
	diskDiscovery      = "smart.disk.discovery"
	attributeDiscovery = "smart.attribute.discovery"
)

var impl Plugin

// Options -
type Options struct {
	Timeout int    `conf:"optional,range=1:30"`
	Path    string `conf:"optional"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options  Options
	ctl      SmartController
	cpuCount int
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "Smart",
		"smart.disk.discovery", "Returns JSON array of smart devices.",
		"smart.disk.get", "Returns JSON data of smart device.",
		"smart.attribute.discovery", "Returns JSON array of smart device attributes.",
	)

	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	cpuCount := runtime.NumCPU()
	if cpuCount < 1 {
		cpuCount = 1
	}

	impl.cpuCount = cpuCount
}

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options, true); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}

	p.ctl = NewSmartCtl(p.Logger, p.options.Path, p.options.Timeout)
}

// Validate -
func (p *Plugin) Validate(options interface{}) error {
	var o Options

	err := conf.Unmarshal(options, &o, true)
	if err != nil {
		return errs.Errorf("plugin config validation failed, %s", err.Error())
	}

	return nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 0 && key != diskGet {
		return nil, zbxerr.ErrorTooManyParameters
	}

	if err = p.checkVersion(); err != nil {
		return
	}

	var jsonArray []byte

	switch key {
	case diskDiscovery:
		jsonArray, err = p.diskDiscovery()
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
		}

	case diskGet:
		jsonArray, err = p.diskGet(params)
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
		}

	case attributeDiscovery:
		jsonArray, err = p.attributeDiscovery()
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
		}

	default:
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	return string(jsonArray), nil
}

func (p *Plugin) diskDiscovery() (jsonArray []byte, err error) {
	out := []device{}

	r, err := p.execute(false)
	if err != nil {
		return nil, err
	}

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

	jsonArray, err = json.Marshal(out)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return
}

func (p *Plugin) diskGet(params []string) ([]byte, error) {
	switch len(params) {
	case twoParameters:
		return p.diskGetSingle(params[firstParameter], params[secondParameter])
	case oneParameter:
		return p.diskGetSingle(params[firstParameter], "")
	case all:
		return p.diskGetAll()
	default:
		return nil, zbxerr.ErrorTooManyParameters
	}
}

func (p *Plugin) diskGetSingle(path, raidType string) ([]byte, error) {
	args := []string{"-a", path, "-j"}

	if raidType != "" {
		args = []string{"-a", path, "-d", raidType, "-j"}
	}

	device, err := p.ctl.Execute(args...)
	if err != nil {
		return nil, errs.Wrap(err, "failed to execute smartctl")
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
	r, err := p.execute(true)
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

func (p *Plugin) attributeDiscovery() (jsonArray []byte, err error) {
	out := []attribute{}

	r, err := p.execute(false)
	if err != nil {
		return nil, err
	}

	for _, dev := range r.devices {
		t := getAttributeType(dev.Info.DevType, dev.RotationRate, dev.SmartAttributes.Table)
		for _, attr := range dev.SmartAttributes.Table {
			out = append(
				out, attribute{
					Name:       cutPrefix(dev.Info.Name),
					DeviceType: t,
					ID:         attr.ID,
					Attrname:   attr.Attrname,
					Thresh:     attr.Thresh,
				})
		}
	}

	jsonArray, err = json.Marshal(out)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return
}

// setSingleDiskFields goes through provided device json data and sets required output fields.
// It returns an error if there is an issue with unmarshal for the provided input JSON map.
func setSingleDiskFields(dev []byte) (out map[string]interface{}, err error) {
	attr := make(map[string]interface{})
	if err = json.Unmarshal(dev, &attr); err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	var sd singleDevice
	if err = json.Unmarshal(dev, &sd); err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	diskType := getType(getTypeFromJson(attr), getRateFromJson(attr), getTablesFromJson(attr))

	out = map[string]interface{}{}
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
	out["self_test_passed"] = setSelfTest(sd)

	if diskType == nvmeType {
		out["temperature"] = sd.HealthLog.Temperature
		out["power_on_time"] = sd.HealthLog.PowerOnTime
		out["critical_warning"] = sd.HealthLog.CriticalWarning
		out["media_errors"] = sd.HealthLog.MediaErrors
		out["percentage_used"] = sd.HealthLog.Percentage_used
	} else {
		out["temperature"] = sd.Temperature.Current
		out["power_on_time"] = sd.PowerOnTime.Hours
		out["critical_warning"] = 0
		out["media_errors"] = 0
		out["percentage_used"] = 0
	}

	for _, a := range sd.SmartAttributes.Table {
		if a.Name == unknownAttrName {
			continue
		}

		out[strings.ToLower(a.Name)] = singleRequestAttribute{a.Raw.Value, a.Raw.Str}
	}

	return
}

// setSelfTest determines if device is self test capable and if the test is passed.
func setSelfTest(sd singleDevice) *bool {
	if sd.Data.Capabilities.SelfTestsSupported {
		return &sd.Data.SelfTest.Status.Passed
	}

	return nil
}

// setDiskFields goes through provided device json map and sets disk_name
// disk_type and returns the devices in a slice.
// It returns an error if there is an issue with unmarshal for the provided input JSON map
func setDiskFields(deviceJsons map[string]jsonDevice) (out []interface{}, err error) {
	for k, v := range deviceJsons {
		b := make(map[string]interface{})
		if err = json.Unmarshal([]byte(v.jsonData), &b); err != nil {
			return out, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
		}

		b["disk_name"] = cutPrefix(k)
		b["disk_type"] = getType(getTypeFromJson(b), getRateFromJson(b), getTablesFromJson(b))

		out = append(out, b)
	}

	return
}

func getRateFromJson(in map[string]interface{}) (out int) {
	if r, ok := in[rotationRateFieldName]; ok {
		switch rate := r.(type) {
		case int:
			out = rate
		case float64:
			out = int(rate)
		}
	}

	return
}

func getTypeFromJson(in map[string]interface{}) (out string) {
	if dev, ok := in[deviceFieldName]; ok {
		m, ok := dev.(map[string]interface{})
		if ok {
			if t, ok := m[typeFieldName]; ok {
				s, ok := t.(string)
				if ok {
					out = s
				}
			}
		}
	}

	return
}

func getTablesFromJson(in map[string]interface{}) (out []table) {
	attr, ok := in[ataSmartAttrFieldName]
	if !ok {
		return
	}

	a, ok := attr.(map[string]interface{})
	if !ok {
		return
	}

	tables, ok := a[ataSmartAttrTableFieldName]
	if !ok {
		return
	}

	tmp, ok := tables.([]interface{})
	if !ok {
		return
	}

	b, err := json.Marshal(tmp)
	if err != nil {
		return
	}

	err = json.Unmarshal(b, &out)
	if err != nil {
		return
	}

	return
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
