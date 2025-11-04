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
	"fmt"
	"strings"

	"golang.zabbix.com/sdk/errs"
)

// handleI2CDevice processes I2C device information
func handleI2CDevice(devName, prefix string) (string, error) {
	var (
		busI2C int
		addr   uint
	)

	n, err := fmt.Sscanf(devName, "%d-%x", &busI2C, &addr)
	if err != nil || n != 2 {
		return "", errs.Wrap(err, "failed to parse I2C device name")
	}

	// find out if legacy ISA or not
	if busI2C == 9191 {
		return fmt.Sprintf("%s-isa-%04x", prefix, addr), nil
	}

	busPath := fmt.Sprintf("/sys/class/i2c-adapter/i2c-%d", busI2C)
	busSubfolder, busAttr, err := sysfsReadAttr(busPath)

	if err == nil && busSubfolder != "" {
		if !strings.HasPrefix(busAttr, "ISA ") {
			return "", errs.New("non-ISA I2C adapter not supported")
		}

		return fmt.Sprintf("%s-isa-%04x", prefix, addr), nil
	}

	return fmt.Sprintf("%s-i2c-%d-%02x", prefix, busI2C, addr), nil
}

// handleSPIDevice processes SPI device information
func handleSPIDevice(devName, prefix string) (string, error) {
	var busSPI int
	var address int

	n, err := fmt.Sscanf(devName, "spi%d.%d", &busSPI, &address)
	if err != nil || n != 2 {
		return "", errs.Wrap(err, "failed to parse SPI device name")
	}

	return fmt.Sprintf("%s-spi-%d-%x", prefix, busSPI, address), nil
}

// handlePCIDevice processes PCI device information
func handlePCIDevice(devName, prefix string) (string, error) {
	var domain, bus, slot, fn uint

	n, err := fmt.Sscanf(devName, "%x:%x:%x.%x", &domain, &bus, &slot, &fn)
	if err != nil || n != 4 {
		return "", errs.Wrap(err, "failed to parse PCI device name")
	}

	addr := (domain << 16) + (bus << 8) + (slot << 3) + fn
	return fmt.Sprintf("%s-pci-%04x", prefix, addr), nil
}

// handlePlatformDevice processes platform device information
func handlePlatformDevice(devName, prefix string) (string, error) {
	var address int

	n, _ := fmt.Sscanf(devName, "%*[a-z0-9_].%d", &address)
	if n != 1 {
		address = 0
	}

	return fmt.Sprintf("%s-isa-%04x", prefix, address), nil
}

// handleHIDDevice processes HID device information
func handleHIDDevice(devName, prefix string) (string, error) {
	var bus, vendor, product, addr uint

	n, err := fmt.Sscanf(devName, "%x:%x:%x.%x", &bus, &vendor, &product, &addr)
	if err != nil || n != 4 {
		return "", errs.Wrap(err, "failed to parse HID device name")
	}

	return fmt.Sprintf("%s-hid-%d-%x", prefix, bus, addr), nil
}
