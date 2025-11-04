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

package zabbixasync

import (
	"bufio"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"unicode"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

var locations = []string{"", "/device"}

type operationMode string

const (
	doOnce operationMode = "" //todo improve naming
	doAvg  operationMode = "avg"
	doMax  operationMode = "max"
	doMin  operationMode = "min"
)

var validOperationModes = map[operationMode]struct{}{
	doOnce: {},
	doAvg:  {},
	doMax:  {},
	doMin:  {},
}

func executeSensor(params []string) (*string, error) {
	if len(params) > 3 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	if len(params) == 0 {
		return nil, errs.Wrap(zbxerr.ErrorTooFewParameters, "invalid first parameter")
	}

	if len(params) == 1 {
		return nil, errs.Wrap(zbxerr.ErrorTooFewParameters, "invalid second parameter")
	}

	device := params[0]
	sensorName := params[1]

	mode := doOnce
	if len(params) == 3 {
		tempMode := operationMode(params[2])
		_, ok := validOperationModes[tempMode]
		if !ok {
			return nil, errs.New("invalid third parameter")
		}

		mode = tempMode
	}

	lastSensorNameSymbol := rune(sensorName[len(sensorName)-1])
	if mode != doOnce && unicode.IsDigit(lastSensorNameSymbol) {
		mode = doOnce
	}

	if mode != doOnce && !unicode.IsLetter(lastSensorNameSymbol) {
		return nil, errs.New("generic sensor name must be specified for selected mode")
	}

	valueCount, aggregatedValue, err := getDeviceSensors(mode, device, sensorName)
	if err != nil {
		return nil, err
	}

	if valueCount == 0 {
		return nil, errs.Wrap(zbxerr.ErrorTooFewParameters, "cannot obtain sensor information")
	}

	result := ""

	switch mode {
	case doAvg:
		avg := aggregatedValue / float64(valueCount)
		result = strconv.FormatFloat(avg, 'f', 6, 64)
	case doOnce, doMax, doMin:
		result = strconv.FormatFloat(aggregatedValue, 'f', 6, 64)
	}

	return &result, nil
}

func getDeviceSensors(mode operationMode, device string, name string) (int64, float64, error) {
	const deviceDir = "/sys/class/hwmon"
	entries, err := os.ReadDir(deviceDir)
	if err != nil {
		return 0, 0, errs.Wrap(err, "failed to read device sensors")
	}

	var aggregated float64
	var count int64

	for _, entry := range entries {
		if entry.Name() == "." || entry.Name() == ".." {
			continue
		}

		devicePath := fmt.Sprintf("%s/%s", deviceDir, entry.Name())
		devLinkPath := fmt.Sprintf("%s/device", devicePath)

		deviceRealDirectory, err := os.Readlink(devLinkPath)

		var deviceInfo, subfolder string

		if err != nil {
			deviceInfo, subfolder, err = getDeviceInfo(devicePath, "")
		} else {
			deviceP := filepath.Base(deviceRealDirectory)

			if deviceP == device {
				subfolder, _, err = sysfsReadAttr(devicePath)
				deviceInfo = device
			} else {
				deviceInfo, subfolder, err = getDeviceInfo(devicePath, deviceP)
			}
		}

		if err != nil {
			continue
		}

		if deviceInfo != device {
			continue
		}

		count, aggregated, err = processSensorFiles(mode, devicePath, subfolder, name)
		if err != nil {
			return 0, 0, errs.Wrap(err, "failed to process sensor files")
		}

	}

	return count, aggregated, nil
}

// getDeviceInfo extracts device information from sysfs path
func getDeviceInfo(devPath, devName string) (string, string, error) {
	nameSubfolder, prefix, err := sysfsReadAttr(devPath)
	if err != nil {
		return "", "", errs.Wrap(err, "failed to read device attribute")
	}

	if devName == "" {
		deviceInfo := fmt.Sprintf("%s-virtual-0", prefix)
		return deviceInfo, nameSubfolder, nil
	}

	linkPath := fmt.Sprintf("%s/device/subsystem", devPath)
	subsysPath, err := os.Readlink(linkPath)

	if err != nil && os.IsNotExist(err) {
		linkPath = fmt.Sprintf("%s/device/bus", devPath)
		subsysPath, err = os.Readlink(linkPath)
	}

	var subsys string
	if err != nil {
		if os.IsNotExist(err) {
			subsys = ""
		} else {
			return "", "", errs.Wrap(err, "failed to read subsystem link")
		}
	} else {
		subsys = filepath.Base(subsysPath)
	}

	deviceInfo, err := buildDeviceInfo(subsys, devName, prefix, devPath)
	if err != nil {
		return "", "", errs.Wrap(err, "failed to build device info")
	}

	return deviceInfo, nameSubfolder, nil
}

// processSensorFiles reads sensor input files and aggregates their values
func processSensorFiles(mode operationMode, devicePath, subfolder, name string) (int64, float64, error) {
	sensorPath := filepath.Join(devicePath, subfolder)

	if mode == doOnce {
		sensorName := filepath.Join(sensorPath, name+"_input")

		//todo make it more general for reusage.
		file, err := os.Open(sensorName)
		if err != nil {
			return 0, 0, errs.Wrap(err, "failed to open sensor file")
		}
		defer file.Close()

		scanner := bufio.NewScanner(file)
		if !scanner.Scan() {
			return 0, 0, nil
		}

		line := scanner.Text()
		value, err := strconv.ParseFloat(strings.TrimSpace(line), 64)
		if err != nil {
			return 0, 0, errs.Wrap(err, "failed to parse sensor value")
		}

		if !strings.Contains(sensorName, "fan") {
			value = value / 1000
		}

		return 1, value, nil
	}

	regexPattern := fmt.Sprintf("%s[0-9]*_input", regexp.QuoteMeta(name))
	regex, err := regexp.Compile(regexPattern)
	if err != nil {
		return 0, 0, errs.Wrap(err, "failed to compile regex pattern")
	}

	entries, err := os.ReadDir(sensorPath)
	if err != nil {
		return 0, 0, errs.Wrap(err, "failed to read sensor directory")
	}

	var (
		totalCount     int64
		totalAgregated float64
	)

	for _, entry := range entries {
		if !regex.MatchString(entry.Name()) {
			continue
		}

		sensorName := filepath.Join(sensorPath, entry.Name())
		_ = sensorName

		agregated, err := countSensor(sensorName)
		if err != nil {
			return 0, 0, errs.Wrap(err, "failed to count sensor")
		}

		if totalCount == 0 {
			totalAgregated = agregated
		}

		if totalCount != 0 {
			switch mode {
			case doAvg:
				totalAgregated += agregated
			case doMin:
				if agregated < totalAgregated {
					totalAgregated = agregated
				}
			case doMax:
				if agregated > totalAgregated {
					totalAgregated = agregated
				}
			}
		}

		totalCount++

	}

	return totalCount, totalAgregated, nil
}

func countSensor(sensorName string) (float64, error) {
	file, err := os.Open(sensorName)
	if err != nil {
		return 0, errs.Wrap(err, "failed to open sensor file")
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	if !scanner.Scan() {
		return 0, nil
	}

	line := scanner.Text()
	value, err := strconv.ParseFloat(strings.TrimSpace(line), 64)
	if err != nil {
		return 0, errs.Wrap(err, "failed to parse sensor value")
	}

	if !strings.Contains(sensorName, "fan") {
		value = value / 1000
	}

	return value, nil
}

// sysfsReadAttr locates and reads name attribute of sensor from sysfs
func sysfsReadAttr(device string) (string, string, error) {
	for _, location := range locations {
		path := fmt.Sprintf("%s%s/name", device, location)

		file, err := os.Open(path)
		if err != nil {
			continue
		}

		scanner := bufio.NewScanner(file)
		if !scanner.Scan() {
			break
		}

		attribute := strings.TrimSpace(scanner.Text())

		err = file.Close()
		if err != nil {
			log.Warningf("failed to close file %s: %s", path, err)
		}

		return location, attribute, nil
	}

	return "", "", errs.New("failed to read sensor attribute")
}

// buildDeviceInfo constructs device identifier based on subsystem type
func buildDeviceInfo(subsys, devName, prefix, devPath string) (string, error) {
	if subsys == "" || subsys == "i2c" {
		return handleI2CDevice(devName, prefix)
	}

	if subsys == "spi" {
		return handleSPIDevice(devName, prefix)
	}

	if subsys == "pci" {
		return handlePCIDevice(devName, prefix)
	}

	if subsys == "platform" || subsys == "of_platform" {
		return handlePlatformDevice(devName, prefix)
	}

	if subsys == "acpi" {
		return prefix + "-acpi-0", nil
	}

	if subsys == "hid" {
		return handleHIDDevice(devName, prefix)
	}

	return "", errs.New("unsupported subsystem type")
}
