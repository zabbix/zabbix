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
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	"golang.org/x/sync/errgroup"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	supportedSmartctl = 7.1

	satType     = "sat"
	nvmeType    = "nvme"
	unknownType = "unknown"
	ssdType     = "ssd"
	hddType     = "hdd"

	spinUpAttrName  = "Spin_Up_Time"
	unknownAttrName = "Unknown_Attribute"

	ataSmartAttrFieldName      = "ata_smart_attributes"
	ataSmartAttrTableFieldName = "table"

	rotationRateFieldName = "rotation_rate"

	deviceFieldName = "device"
	typeFieldName   = "type"
)

const (
	parseError = 1 << iota
	openError
)

// Constant block of device types.
const (
	ThreeWare = DeviceType("3ware")
	Areca     = DeviceType("areca")
	CCISS     = DeviceType("cciss")
	SAT       = DeviceType("sat")
	SCSI      = DeviceType("scsi")
)

var (
	lastVerCheck time.Time
	versionMux   sync.Mutex

	// ErrNoSmartStatus error indicated that device has no smart status.
	ErrNoSmartStatus = errs.New("smartctl returned no smart status")
)

// SmartCtlDeviceData describes all data collected from smartctl for a particular
// device.
type SmartCtlDeviceData struct {
	Device *deviceParser
	Data   []byte
}

// DeviceType describes the type of device.
type DeviceType string

type devices struct {
	Info []deviceInfo `json:"devices"`
}

type device struct {
	Name         string `json:"{#NAME}"`
	DeviceType   string `json:"{#DISKTYPE}"`
	Model        string `json:"{#MODEL}"`
	SerialNumber string `json:"{#SN}"`
	Path         string `json:"{#PATH}"`
	RaidType     string `json:"{#RAIDTYPE}"`
	Attributes   string `json:"{#ATTRIBUTES}"`
}
type jsonDevice struct {
	serialNumber string
	jsonData     string
}

type singleDevice struct {
	DiskType        string              `json:"disk_type"`
	Firmware        string              `json:"firmware_version"`
	ModelName       string              `json:"model_name"`
	SerialNumber    string              `json:"serial_number"`
	Smartctl        smartctlField       `json:"smartctl"`
	HealthLog       healthLog           `json:"nvme_smart_health_information_log"`
	SmartAttributes singelRequestTables `json:"ata_smart_attributes"`
	Data            ataData             `json:"ata_smart_data"`
	Temperature     temperature         `json:"temperature"`
	PowerOnTime     power               `json:"power_on_time"`
	Err             string              `json:"-"`
	SelfTest        bool                `json:"-"`
}

type healthLog struct {
	Temperature     int `json:"temperature"`
	PowerOnTime     int `json:"power_on_hours"`
	CriticalWarning int `json:"critical_warning"`
	MediaErrors     int `json:"media_errors"`
	Percentage_used int `json:"percentage_used"`
}

type temperature struct {
	Current int `json:"current"`
}

type power struct {
	Hours int `json:"hours"`
}

type singelRequestTables struct {
	Table []singelRequestRaw `json:"table"`
}

type singelRequestRaw struct {
	Name string   `json:"name"`
	Raw  rawField `json:"raw"`
}

type singleRequestAttribute struct {
	Value int    `json:"value"`
	Raw   string `json:"raw"`
}

type rawField struct {
	Value int    `json:"value"`
	Str   string `json:"string"`
}

type ataData struct {
	SelfTest     selfTest     `json:"self_test"`
	Capabilities capabilities `json:"capabilities"`
}
type capabilities struct {
	SelfTestsSupported bool `json:"self_tests_supported"`
}
type selfTest struct {
	Status status `json:"status"`
}
type status struct {
	Passed bool `json:"passed"`
}

type attribute struct {
	Name       string `json:"{#NAME}"`
	DeviceType string `json:"{#DISKTYPE}"`
	ID         int    `json:"{#ID}"`
	Attrname   string `json:"{#ATTRNAME}"`
	Thresh     int    `json:"{#THRESH}"`
}

type deviceParser struct {
	ModelName       string          `json:"model_name"`
	SerialNumber    string          `json:"serial_number"`
	RotationRate    int             `json:"rotation_rate"`
	Info            deviceInfo      `json:"device"`
	Smartctl        smartctlField   `json:"smartctl"`
	SmartStatus     *smartStatus    `json:"smart_status,omitempty"`
	SmartAttributes smartAttributes `json:"ata_smart_attributes"`
}

type deviceInfo struct {
	Name     string `json:"name"`
	InfoName string `json:"info_name"`
	DevType  string `json:"type"`
	name     string `json:"-"`
	raidType string `json:"-"`
}

type smartctl struct {
	Smartctl smartctlField `json:"smartctl"`
}

type smartctlField struct {
	Messages   []message `json:"messages"`
	ExitStatus int       `json:"exit_status"`
	Version    []int     `json:"version"`
}

type message struct {
	Str string `json:"string"`
}

type smartStatus struct {
	SerialNumber bool `json:"passed"`
}

type smartAttributes struct {
	Table []table `json:"table"`
}

type table struct {
	Attrname string `json:"name"`
	ID       int    `json:"id"`
	Thresh   int    `json:"thresh"`
}

type runner struct {
	devices     map[string]deviceParser
	jsonDevices map[string]jsonDevice
}

// execute returns the smartctl runner with all devices data returned by smartctl.
// If jsonRunner is 'true' the returned data is in json format in 'jsonDevices' field.
// If jsonRunner is 'false' the returned data is 'devices' field.
// Currently looks for 5 raid types "3ware", "areca", "cciss", "megaraid", "sat".
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) execute(jsonRunner bool) (*runner, error) {
	basicDev, raidDev, megaraidDev, err := p.getDevices()
	if err != nil {
		return nil, err
	}

	r := &runner{}

	if jsonRunner {
		r.jsonDevices = make(map[string]jsonDevice)
	} else {
		r.devices = make(map[string]deviceParser)
	}

	// Create an error group with the context.
	g, ctx := errgroup.WithContext(context.Background())

	g.SetLimit(p.cpuCount)

	resultChan := make(chan *SmartCtlDeviceData)
	collectorDone := make(chan struct{})

	go func() {
		for data := range resultChan {
			r.setDevicesData(data, jsonRunner)
		}

		close(collectorDone)
	}()

	for _, device := range basicDev {
		name := device.Name

		g.Go(func() error {
			select {
			case <-ctx.Done():
				return errs.Wrap(ctx.Err(), "errgroup context canceled") // Return error if context is canceled
			default:
				deviceInfo, err := getBasicDeviceInfo(p.ctl, name) //nolint:govet
				if err != nil {
					if errors.Is(err, ErrNoSmartStatus) {
						p.Logger.Debugf("skipping device with no smart status: %q", name)

						return nil
					}

					return err
				}

				resultChan <- deviceInfo

				return nil
			}
		})
	}

	for _, device := range raidDev {
		for _, deviceType := range []DeviceType{
			ThreeWare, Areca, CCISS, SAT, SCSI,
		} {
			name := device.Name
			devType := deviceType

			g.Go(func() error {
				devices := getRaidDevices(p.ctl, p.Base.Logger, name, devType)
				for _, device := range devices {
					resultChan <- device
				}

				return nil
			})
		}
	}

	for _, device := range megaraidDev {
		name := device.Name
		devType := device.DevType

		g.Go(func() error {
			device, err := getAllDeviceInfoByType(p.ctl, name, devType) //nolint:govet
			if err != nil {
				p.Tracef("got error executing for megaraid %q", err.Error())

				return nil
			}

			resultChan <- device

			return nil
		})
	}

	err = g.Wait()

	close(resultChan)

	if err != nil {
		return nil, errs.Wrap(err, "got error executing worker pool")
	}

	<-collectorDone

	r.parseOutput(jsonRunner)

	return r, nil
}

// checkVersion checks the version of smartctl.
// Currently supported versions are 7.1 and above.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) checkVersion() error {
	if !versionCheckNeeded() {
		return nil
	}

	out, err := p.ctl.Execute("-j", "-V")
	if err != nil {
		return errs.Wrap(err, "failed to execute smartctl")
	}

	body := &smartctl{}

	err = json.Unmarshal(out, body)
	if err != nil {
		return errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	return evaluateVersion(body.Smartctl.Version)
}

// versionCheckNeeded returns true if version needs to be checked.
// Version is checked every 24 hours
func versionCheckNeeded() bool {
	versionMux.Lock()
	defer versionMux.Unlock()

	if lastVerCheck.IsZero() ||
		time.Now().After(lastVerCheck.Add(24*time.Hour)) {
		lastVerCheck = time.Now()

		return true
	}

	return false
}

// evaluateVersion checks version digits if they match the current allowed version or higher.
func evaluateVersion(versionDigits []int) error {
	if len(versionDigits) < 1 {
		return errs.Errorf("invalid smartctl version")
	}

	var version string
	if len(versionDigits) >= 2 {
		version = fmt.Sprintf("%d.%d", versionDigits[0], versionDigits[1])
	} else {
		version = fmt.Sprintf("%d", versionDigits[0])
	}

	v, err := strconv.ParseFloat(version, 64)
	if err != nil {
		return errs.WrapConst(err, zbxerr.ErrorCannotParseResult)
	}

	if v < supportedSmartctl {
		return errs.Errorf(
			"Incorrect smartctl version, must be %v or higher",
			supportedSmartctl,
		)
	}

	return nil
}

// cutPrefix cuts /dev/ prefix from a string and returns it.
func cutPrefix(in string) string {
	return strings.TrimPrefix(in, "/dev/")
}

func getBasicDeviceInfo(ctl SmartController, deviceName string) (*SmartCtlDeviceData, error) {
	device, err := ctl.Execute("-a", deviceName, "-j")
	if err != nil {
		return nil, errs.Wrap(err, "failed to execute smartctl")
	}

	dp := &deviceParser{}

	err = json.Unmarshal(device, dp)
	if err != nil {
		return nil, errs.Wrap(err, "failed to unmarshal JSON")
	}

	err = dp.checkErr()
	if err != nil {
		return nil, errs.Wrap(err, "smartctl returned error")
	}

	if dp.SmartStatus == nil {
		return nil, errs.Wrapf(ErrNoSmartStatus, "got no smart status for device %q", deviceName)
	}

	dp.Info.name = deviceName

	return &SmartCtlDeviceData{
		Device: dp,
		Data:   device,
	}, nil
}

// getAllDeviceInfoByType returns all device information by device type.
//
// runs: smartctl -a <deviceName> -d <deviceType> -j
// returns error if .smart_status field is not present in the output.
func getAllDeviceInfoByType(
	ctl SmartController, deviceName, deviceType string,
) (*SmartCtlDeviceData, error) {
	device, err := ctl.Execute("-a", deviceName, "-d", deviceType, "-j")
	if err != nil {
		return nil, errs.Wrap(err, "failed to execute smartctl")
	}

	dp := &deviceParser{}

	err = json.Unmarshal(device, dp)
	if err != nil {
		return nil, errs.Wrap(err, "failed to parse (unmarshal JSON) smartctl output")
	}

	err = dp.checkErr()
	if err != nil {
		return nil, errs.Wrap(err, "smartctl returned error")
	}

	if dp.SmartStatus == nil {
		return nil, ErrNoSmartStatus
	}

	dp.Info.Name = fmt.Sprintf("%s %s", deviceName, deviceType)
	dp.Info.name = deviceName
	dp.Info.raidType = deviceType

	return &SmartCtlDeviceData{
		Device: dp,
		Data:   device,
	}, nil
}

func getRaidDevices(
	ctl SmartController,
	logr log.Logger,
	deviceName string,
	deviceType DeviceType,
) []*SmartCtlDeviceData {
	switch deviceType {
	case SAT, SCSI:
		data, err := getAllDeviceInfoByType(ctl, deviceName, string(deviceType))
		if err != nil {
			logr.Debugf(
				"failed to get device %q info by type %q: %s",
				deviceName, deviceType, err.Error(),
			)

			return []*SmartCtlDeviceData{}
		}

		return []*SmartCtlDeviceData{data}
	default:
		var (
			devices []*SmartCtlDeviceData
			i       int
		)

		if deviceType == Areca {
			i = 1
		}

		for {
			data, err := getAllDeviceInfoByType(
				ctl,
				deviceName,
				fmt.Sprintf("%s,%d", deviceType, i),
			)
			if err != nil {
				logr.Debugf(
					"failed to get device %q info by type %q: %s",
					deviceName, deviceType, err.Error(),
				)

				break
			}

			devices = append(devices, data)
			i++
		}

		return devices
	}
}

func (r *runner) setDevicesData(data *SmartCtlDeviceData, jsonRunner bool) {
	if !jsonRunner {
		r.devices[data.Device.Info.Name] = *data.Device

		return
	}

	r.jsonDevices[data.Device.Info.Name] = jsonDevice{
		data.Device.SerialNumber,
		string(data.Data),
	}
}

func (r *runner) parseOutput(jsonRunner bool) {
	found := make(map[string]bool)

	var keys []string

	if jsonRunner {
		tmp := make(map[string]jsonDevice)

		for k := range r.jsonDevices {
			keys = append(keys, k)
		}

		sort.Strings(keys)

		for _, k := range keys {
			dev := r.jsonDevices[k]
			if !found[dev.serialNumber] {
				found[dev.serialNumber] = true
				tmp[k] = dev
			}
		}

		r.jsonDevices = tmp
	} else {
		tmp := make(map[string]deviceParser)

		for k := range r.devices {
			keys = append(keys, k)
		}

		sort.Strings(keys)

		for _, k := range keys {
			dev := r.devices[k]
			if !found[dev.SerialNumber] {
				found[dev.SerialNumber] = true
				tmp[k] = dev
			}
		}

		r.devices = tmp
	}
}

func (dp *deviceParser) checkErr() error {
	if (parseError|openError)&dp.Smartctl.ExitStatus == 0 {
		return nil
	}

	messages := make([]string, 0, len(dp.Smartctl.Messages))

	for _, m := range dp.Smartctl.Messages {
		if m.Str == "" {
			continue
		}

		messages = append(messages, m.Str)
	}

	if len(messages) == 0 {
		return errs.New("unknown error from smartctl")
	}

	return errs.New(strings.Join(messages, ", "))
}

// getDevices returns a parsed slices of all devices returned by smartctl scan.
// Returns a separate slice for basic, raid and megaraid devices. (in the described order)
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getDevices() ([]deviceInfo, []deviceInfo, []deviceInfo, error) {
	basicTmp, err := p.scanDevices("--scan", "-j")
	if err != nil {
		return nil, nil, nil, errs.Wrap(err, "failed to scan for devices")
	}

	raidTmp, err := p.scanDevices("--scan", "-d", "sat", "-j")
	if err != nil {
		return nil, nil, nil, errs.Wrap(err, "failed to scan for sat devices")
	}

	basic, raid, megaraid := formatDeviceOutput(basicTmp, raidTmp)

	return basic, raid, megaraid, nil
}

// formatDeviceOutput removes raid devices from basic device list and
// separates megaraid devices from the rest of raid devices.
//
// return order: basic, raid, megaraid.
func formatDeviceOutput(
	basic, raid []deviceInfo,
) ([]deviceInfo, []deviceInfo, []deviceInfo) {
	//nolint:prealloc
	var (
		basicDev []deviceInfo //nolint:prealloc
		isRaid   = map[string]bool{}
	)

	for _, r := range raid {
		isRaid[r.Name] = true
	}

	for _, b := range basic {
		if isRaid[b.Name] {
			continue
		}

		basicDev = append(basicDev, b)
	}

	//nolint:prealloc
	var raidDev, megaraidDev []deviceInfo

	for _, r := range raid {
		if strings.Contains(r.DevType, "megaraid") {
			megaraidDev = append(megaraidDev, r)

			continue
		}

		raidDev = append(raidDev, r)
	}

	return basicDev, raidDev, megaraidDev
}

// scanDevices executes smartctl.
// It parses the smartctl data into a slice with deviceInfo.
// The data is sorted based on device name in alphabet order.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) scanDevices(args ...string) ([]deviceInfo, error) {
	out, err := p.ctl.Execute(args...)
	if err != nil {
		return nil, errs.Wrapf(err, "got error executing scanDevices with arguments: %q", args)
	}

	var d devices

	err = json.Unmarshal(out, &d)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	sort.SliceStable(
		d.Info,
		func(i, j int) bool {
			return d.Info[i].Name < d.Info[j].Name
		},
	)

	return d.Info, nil
}
