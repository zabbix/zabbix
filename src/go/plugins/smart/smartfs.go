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
	"errors"
	"fmt"
	"runtime"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	"git.zabbix.com/ap/plugin-support/zbxerr"
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

var (
	cpuCount     int
	lastVerCheck time.Time
	versionMux   sync.Mutex
)

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

type raidParameters struct {
	name  string
	rType string
}

type runner struct {
	plugin       *Plugin
	mux          sync.Mutex
	wg           sync.WaitGroup
	names        chan string
	err          chan error
	done         chan struct{}
	raidDone     chan struct{}
	megaRaidDone chan struct{}
	raids        chan raidParameters
	megaraids    chan raidParameters
	devices      map[string]deviceParser
	jsonDevices  map[string]jsonDevice
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

	r := &runner{
		names:        make(chan string, len(basicDev)),
		err:          make(chan error, cpuCount),
		done:         make(chan struct{}),
		raidDone:     make(chan struct{}),
		megaRaidDone: make(chan struct{}),
		plugin:       p,
	}

	if jsonRunner {
		r.jsonDevices = make(map[string]jsonDevice)
	} else {
		r.devices = make(map[string]deviceParser)
	}

	err = r.executeBase(basicDev, jsonRunner)
	if err != nil {
		return nil, err
	}

	r.executeRaids(raidDev, jsonRunner)
	r.executeMegaRaids(megaraidDev, jsonRunner)
	r.parseOutput(jsonRunner)

	return r, err
}

// executeSingle returns device data for single device from smartctl based on provided path.
func (p *Plugin) executeSingle(path string) (device []byte, err error) {
	device, err = p.executeSmartctl(fmt.Sprintf("-a %s -j", path), false)
	if err != nil {
		return nil, fmt.Errorf("Failed to execute smartctl: %w.", err)
	}

	return
}

//executeBase executed runners for basic devices retrieved from smartctl.
func (r *runner) executeBase(basicDev []deviceInfo, jsonRunner bool) error {
	r.startBasicRunners(jsonRunner)

	for _, dev := range basicDev {
		r.names <- dev.Name
	}

	close(r.names)

	return r.waitForExecution()
}

//executeRaids executes runners for raid devices (except megaraid) retrieved from smartctl
func (r *runner) executeRaids(raids []deviceInfo, jsonRunner bool) {
	raidTypes := []string{"3ware", "areca", "cciss", "sat"}

	r.raids = make(chan raidParameters, len(raids)*len(raidTypes))

	r.startRaidRunners(jsonRunner)

	for _, rDev := range raids {
		for _, rType := range raidTypes {
			r.raids <- raidParameters{rDev.Name, rType}
		}
	}

	close(r.raids)

	r.waitForRaidExecution(r.raidDone)
}

//executeMegaRaids executes runners for megaraid devices retrieved from smartctl
func (r *runner) executeMegaRaids(megaraids []deviceInfo, jsonRunner bool) {
	r.megaraids = make(chan raidParameters, len(megaraids))

	r.startMegaRaidRunners(jsonRunner)
	for _, mr := range megaraids {
		r.megaraids <- raidParameters{mr.Name, mr.DevType}
	}

	close(r.megaraids)

	r.waitForRaidExecution(r.megaRaidDone)
}

// startBasicRunners starts runners to get basic device information.
// Runner count is based on cpu core count.
func (r *runner) startBasicRunners(jsonRunner bool) {
	r.wg.Add(cpuCount)

	for i := 0; i < cpuCount; i++ {
		go r.getBasicDevices(jsonRunner)
	}
}

// startRaidRunners starts runners to get raid device information.
// Runner count is based on cpu core count.
func (r *runner) startRaidRunners(jsonRunner bool) {
	r.wg.Add(cpuCount)

	for i := 0; i < cpuCount; i++ {
		go r.getRaidDevices(jsonRunner)
	}
}

// startRaidRunners starts runners to get raid device information.
// Runner count is based on cpu core count.
func (r *runner) startMegaRaidRunners(jsonRunner bool) {
	r.wg.Add(cpuCount)

	for i := 0; i < cpuCount; i++ {
		go r.getMegaRaidDevices(jsonRunner)
	}
}

// waitForExecution waits for all execution to stop.
// Returns the first error a runner sends.
func (r *runner) waitForExecution() error {
	go func() {
		r.wg.Wait()

		close(r.done)
	}()

	select {
	case <-r.done:
		return nil
	case err := <-r.err:
		return err
	}
}

// waitForRaidExecution waits for all execution to stop.
func (r *runner) waitForRaidExecution(done chan struct{}) {
	go func() {
		r.wg.Wait()

		close(done)
	}()

	<-done
}

// checkVersion checks the version of smartctl.
// Currently supported versions are 7.1 and above.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) checkVersion() error {
	var smartctl smartctl

	if !versionCheckNeeded() {
		return nil
	}

	info, err := p.executeSmartctl("-j -V", true)
	if err != nil {
		return fmt.Errorf("Failed to execute smartctl: %s.", err.Error())
	}

	if err = json.Unmarshal(info, &smartctl); err != nil {
		return zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	return evaluateVersion(smartctl.Smartctl.Version)
}

// versionCheckNeeded returns true if version needs to be checked.
// Version is checked every 24 hours
func versionCheckNeeded() bool {
	versionMux.Lock()
	defer versionMux.Unlock()

	if lastVerCheck.IsZero() || time.Now().After(lastVerCheck.Add(24*time.Hour)) {
		lastVerCheck = time.Now()

		return true
	}

	return false
}

// evaluateVersion checks version digits if they match the current allowed version or higher.
func evaluateVersion(versionDigits []int) error {
	if len(versionDigits) < 1 {
		return fmt.Errorf("Invalid smartctl version")
	}

	var version string
	if len(versionDigits) >= 2 {
		version = fmt.Sprintf("%d.%d", versionDigits[0], versionDigits[1])
	} else {
		version = fmt.Sprintf("%d", versionDigits[0])
	}

	v, err := strconv.ParseFloat(version, 64)
	if err != nil {
		return zbxerr.ErrorCannotParseResult.Wrap(err)
	}

	if v < supportedSmartctl {
		return fmt.Errorf("Incorrect smartctl version, must be %v or higher", supportedSmartctl)
	}

	return nil
}

// cutPrefix cuts /dev/ prefix from a string and returns it.
func cutPrefix(in string) string {
	return strings.TrimPrefix(in, "/dev/")
}

// getBasicDevices sets non raid device information returned by smartctl.
// Sets device data to runner 'devices' field.
// If jsonRunner is true, sets raw json outputs to runner 'jsonDevices' map instead.
// It sends an error if there is an issue with getting or parsing results from smartctl.
func (r *runner) getBasicDevices(jsonRunner bool) {
	defer r.wg.Done()

	for name := range r.names {
		devices, err := r.plugin.executeSmartctl(fmt.Sprintf("-a %s -j", name), false)
		if err != nil {
			r.err <- fmt.Errorf("Failed to execute smartctl: %s.", err.Error())
			return
		}

		var dp deviceParser

		if err = json.Unmarshal(devices, &dp); err != nil {
			r.err <- zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
			return
		}

		if dp.SmartStatus == nil {
			r.plugin.Debugf("skipping device %s", dp.Info.Name)
			return
		}

		dp.Info.name = name

		r.mux.Lock()

		if jsonRunner {
			r.jsonDevices[name] = jsonDevice{dp.SerialNumber, string(devices)}
		} else {
			r.devices[name] = dp
		}

		r.mux.Unlock()
	}
}

// getRaidDevices sets raid device information returned by smartctl.
// Works by incrementing raid disk number till there is an error from smartctl.
// Sets device data to runner 'devices' field.
// If jsonRunner is true, sets raw json outputs to runner 'jsonDevices' map instead.
// It logs an error when there is an issue with getting or parsing results from smartctl.
func (r *runner) getRaidDevices(jsonRunner bool) {
	defer r.wg.Done()

runner:
	for {
		raid, ok := <-r.raids
		if !ok {
			return
		}

		var i int

		if raid.rType == "areca" {
			i = 1
		}

		for {
			var name string

			if raid.rType == satType {
				name = fmt.Sprintf("%s -d %s", raid.name, raid.rType)
			} else {
				name = fmt.Sprintf("%s -d %s,%d", raid.name, raid.rType, i)
			}

			device, err := r.plugin.executeSmartctl(fmt.Sprintf("-a %s -j ", name), false)
			if err != nil {
				r.plugin.Tracef(
					"stopped looking for RAID devices of %s type, err: %s",
					raid.rType, fmt.Errorf("failed to get RAID disk data from smartctl: %s", err.Error()),
				)

				continue runner
			}

			var dp deviceParser
			if err = json.Unmarshal(device, &dp); err != nil {
				r.plugin.Tracef(
					"stopped looking for RAID devices of %s type, err: %s",
					raid.rType, fmt.Errorf("failed to parse RAID disk data from smartctl: %s", err.Error()),
				)

				continue runner
			}

			err = dp.checkErr()
			if err != nil {
				r.plugin.Tracef(
					"stopped looking for RAID devices of %s type, err: %s",
					raid.rType, fmt.Errorf("failed to get disk data from smartctl: %s", err.Error()),
				)

				continue runner
			}

			if dp.SmartStatus != nil {
				dp.Info.name = raid.name

				if raid.rType == satType {
					dp.Info.Name = fmt.Sprintf("%s %s", raid.name, raid.rType)
					dp.Info.raidType = raid.rType
				} else {
					dp.Info.Name = fmt.Sprintf("%s %s,%d", raid.name, raid.rType, i)
					dp.Info.raidType = fmt.Sprintf("%s,%d", raid.rType, i)
				}

				if r.setRaidDevices(dp, device, raid.rType, jsonRunner) {
					continue runner
				}
			}

			if raid.rType == satType {
				continue runner
			}

			i++
		}
	}
}

// getMegaRaidDevices sets megaraid device information returned by smartctl.
// Works by executing megaraid based on megaraid name in info_name field.
// Sets device data to runner 'devices' field.
// If jsonRunner is true, sets raw json outputs to runner 'jsonDevices' map instead.
// It logs an error when there is an issue with getting or parsing results from smartctl.
func (r *runner) getMegaRaidDevices(jsonRunner bool) {
	defer r.wg.Done()

	for {
		raid, ok := <-r.megaraids
		if !ok {
			return
		}

		name := fmt.Sprintf("%s -d %s", raid.name, raid.rType)

		device, err := r.plugin.executeSmartctl(fmt.Sprintf("-a %s -j ", name), false)
		if err != nil {
			r.plugin.Tracef(
				"failed to get megaraid device with name %s, %s", name, err.Error(),
			)

			continue
		}

		var dp deviceParser
		if err = json.Unmarshal(device, &dp); err != nil {
			r.plugin.Tracef(
				"failed to unmarshal megaraid device with name %s, %s", name, err.Error(),
			)

			continue
		}

		err = dp.checkErr()
		if err != nil {
			r.plugin.Tracef(
				"got error from smartctl for megaraid devices with name %s, %s",
				name, err.Error(),
			)

			continue
		}

		if dp.SmartStatus == nil {
			continue
		}

		dp.Info.Name = fmt.Sprintf("%s %s", raid.name, raid.rType)
		dp.Info.name = raid.name
		dp.Info.raidType = raid.rType

		r.setRaidDevices(dp, device, raid.rType, jsonRunner)
	}
}

// setRaidDevices sets device data to runner.
// If json runner then raw byte data is set, else sets parsed data
// Returns true if the runner should go to next raid value.
func (r *runner) setRaidDevices(dp deviceParser, device []byte, raidType string, json bool) bool {
	r.mux.Lock()
	defer r.mux.Unlock()

	if json {
		r.jsonDevices[dp.Info.Name] = jsonDevice{dp.SerialNumber, string(device)}
	} else {
		r.devices[dp.Info.Name] = dp
	}

	if raidType == satType {
		return true
	}

	return false
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

func (dp *deviceParser) checkErr() (err error) {
	if dp.Smartctl.ExitStatus != 2 {
		return
	}

	for _, m := range dp.Smartctl.Messages {
		if err == nil {
			err = errors.New(m.Str)

			continue
		}

		err = fmt.Errorf("%s, %s", err.Error(), m.Str)
	}

	if err == nil {
		err = errors.New("unknown error from smartctl")
	}

	return
}

// getDevices returns a parsed slices of all devices returned by smartctl scan.
// Returns a separate slice for both normal and raid devices.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getDevices() (basic, raid, megaraid []deviceInfo, err error) {
	basicTmp, err := p.scanDevices("--scan -j")
	if err != nil {
		return nil, nil, nil, fmt.Errorf("Failed to scan for devices: %w.", err)
	}

	raidTmp, err := p.scanDevices("--scan -d sat -j")
	if err != nil {
		return nil, nil, nil, fmt.Errorf("Failed to scan for sat devices: %w.", err)
	}

	basic, raid, megaraid = formatDeviceOutput(basicTmp, raidTmp)

	return
}

// formatDeviceOutput removes raid devices from basic device list and separates megaraid devices from the rest of raid
// devices.
func formatDeviceOutput(basic, raid []deviceInfo) (basicDev, raidDev, megaraidDev []deviceInfo) {
loop:
	for _, tmp := range basic {
		for _, r := range raid {
			if tmp.Name == r.Name {
				continue loop
			}
		}

		basicDev = append(basicDev, tmp)
	}

	for _, r := range raid {
		if strings.Contains(r.DevType, "megaraid") {
			megaraidDev = append(megaraidDev, r)

			continue
		}

		raidDev = append(raidDev, r)
	}

	return
}

// scanDevices executes smartctl.
// It parses the smartctl data into a slice with deviceInfo.
// The data is sorted based on device name in alphabet order.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) scanDevices(args string) ([]deviceInfo, error) {
	var d devices

	devices, err := p.executeSmartctl(args, false)
	if err != nil {
		return nil, err
	}

	if err = json.Unmarshal(devices, &d); err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	var names []string
	for _, info := range d.Info {
		names = append(names, info.InfoName)
	}

	sort.Strings(names)

	var out []deviceInfo

names:
	for _, name := range names {
		for _, info := range d.Info {
			if name == info.InfoName {
				out = append(out, info)

				continue names
			}
		}
	}

	return out, nil
}

func init() {
	cpuCount = runtime.NumCPU()
	if cpuCount < 1 {
		cpuCount = 1
	}
}
