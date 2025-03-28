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
	"regexp"
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

var pathRegex = regexp.MustCompile(`^(?:\s*-|'*"*\s*-)`)

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
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	if err := conf.UnmarshalStrict(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}

	p.ctl = NewSmartCtl(p.Logger, p.options.Path, p.options.Timeout)
}

// Validate -
func (p *Plugin) Validate(options any) error { //nolint:revive
	var o Options

	err := conf.UnmarshalStrict(options, &o)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	return nil
}

// Export -
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	err := p.validateExport(key, params)
	if err != nil {
		return nil, errs.Wrap(err, "export validation failed")
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

func (p *Plugin) diskDiscovery() ([]byte, error) {
	r, err := p.execute(false)
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

func (p *Plugin) attributeDiscovery() ([]byte, error) {
	r, err := p.execute(false)
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

	out := make(map[string]any)
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
	out["self_test_passed"] = setSelfTest(&sd)

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
		out["media_errors"] = 0
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

// setSelfTest determines if device is self test capable and if the test is passed.
func setSelfTest(sd *singleDevice) *bool {
	if sd.Data.Capabilities.SelfTestsSupported {
		return &sd.Data.SelfTest.Status.Passed
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

// validateExport function validates key, export params and version.
func (p *Plugin) validateExport(key string, params []string) error {
	err := validateParams(key, params)
	if err != nil {
		return err
	}

	return p.checkVersion()
}

// validateParams validates the key's params quantity aspect.
func validateParams(key string, params []string) error {
	// No params - nothing to validate.
	if len(params) == all {
		return nil
	}

	// The params can only be for a specific function.
	if key != diskGet {
		return zbxerr.ErrorTooManyParameters
	}

	// Validates the param disk path in the context of an input sanitization.
	if pathRegex.MatchString(params[0]) {
		return errs.New("invalid disk descriptor format")
	}

	return nil
}
