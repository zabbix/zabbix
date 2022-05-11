/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package smart

import (
	"encoding/json"
	"fmt"
	"strings"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/zbxerr"
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

// Options -
type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int    `conf:"optional,range=1:30"`
	Path                 string `conf:"optional"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

// Validate -
func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
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
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

	case diskGet:
		jsonArray, err = p.diskGet(params)
		if err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

	case attributeDiscovery:
		jsonArray, err = p.attributeDiscovery()
		if err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
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
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
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
	executable := path

	if raidType != "" {
		executable = fmt.Sprintf("%s -d %s", executable, raidType)
	}

	device, err := p.executeSingle(executable)
	if err != nil {
		return nil, err
	}

	out, err := setSingleDiskFields(device)
	if err != nil {
		return nil, err
	}

	jsonArray, err := json.Marshal(out)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
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
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

		return
	}

	jsonArray, err = json.Marshal(fields)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
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
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return
}

// setSingleDiskFields goes through provided device json data and sets required output fields.
// It returns an error if there is an issue with unmarshal for the provided input JSON map.
func setSingleDiskFields(dev []byte) (out map[string]interface{}, err error) {
	attr := make(map[string]interface{})
	if err = json.Unmarshal(dev, &attr); err != nil {
		return out, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	var sd singleDevice
	if err = json.Unmarshal(dev, &sd); err != nil {
		return out, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
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
			return out, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
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

func init() {
	plugin.RegisterMetrics(&impl, "Smart",
		"smart.disk.discovery", "Returns JSON array of smart devices.",
		"smart.disk.get", "Returns JSON data of smart device.",
		"smart.attribute.discovery", "Returns JSON array of smart device attributes.",
	)
}
