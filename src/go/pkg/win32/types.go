// +build windows

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

package win32

import (
	"syscall"

	"golang.org/x/sys/windows"
)

type Hlib syscall.Handle

const (
	ANY_SIZE = 1

	IF_MAX_STRING_SIZE         = 256
	IF_MAX_PHYS_ADDRESS_LENGTH = 32
)

type GUID struct {
	Data1 uint32
	Data2 uint16
	Data3 uint16
	Data4 [8]byte
}

type MIB_IF_ROW2 struct {
	InterfaceLuid               uint64
	InterfaceIndex              uint32
	InterfaceGuid               GUID
	Alias                       [IF_MAX_STRING_SIZE + 1]uint16
	Description                 [IF_MAX_STRING_SIZE + 1]uint16
	PhysicalAddressLength       uint32
	PhysicalAddress             [IF_MAX_PHYS_ADDRESS_LENGTH]byte
	PermanentPhysicalAddress    [IF_MAX_PHYS_ADDRESS_LENGTH]byte
	Mtu                         uint32
	Type                        uint32
	TunnelType                  int32
	MediaType                   int32
	PhysicalMediumType          int32
	AccessType                  int32
	DirectionType               int32
	InterfaceAndOperStatusFlags byte
	OperStatus                  int32
	AdminStatus                 int32
	MediaConnectState           int32
	NetworkGuid                 GUID
	ConnectionType              int32
	_                           [4]byte
	TransmitLinkSpeed           uint64
	ReceiveLinkSpeed            uint64
	InOctets                    uint64
	InUcastPkts                 uint64
	InNUcastPkts                uint64
	InDiscards                  uint64
	InErrors                    uint64
	InUnknownProtos             uint64
	InUcastOctets               uint64
	InMulticastOctets           uint64
	InBroadcastOctets           uint64
	OutOctets                   uint64
	OutUcastPkts                uint64
	OutNUcastPkts               uint64
	OutDiscards                 uint64
	OutErrors                   uint64
	OutUcastOctets              uint64
	OutMulticastOctets          uint64
	OutBroadcastOctets          uint64
	OutQLen                     uint64
}

type MIB_IF_TABLE2 struct {
	NumEntries uint32
	_          [4]byte
	Table      [ANY_SIZE]MIB_IF_ROW2
}

type MIB_IPADDRROW struct {
	Addr      uint32
	Index     uint32
	Mask      uint32
	BCastAddr uint32
	ReasmSize uint32
	_         uint16
	_         uint16
}
type MIB_IPADDRTABLE struct {
	NumEntries uint32
	Table      [ANY_SIZE]MIB_IPADDRROW
}

type (
	PDH_HQUERY   windows.Handle
	PDH_HCOUNTER windows.Handle
)

type PDH_COUNTER_PATH_ELEMENTS struct {
	MachineName    uintptr
	ObjectName     uintptr
	InstanceName   uintptr
	ParentInstance uintptr
	InstanceIndex  uint32
	CounterName    uintptr
}
type LP_PDH_COUNTER_PATH_ELEMENTS *PDH_COUNTER_PATH_ELEMENTS

type PDH_FMT_COUNTERVALUE_DOUBLE struct {
	Status uint32
	_      uint32
	Value  float64
}

type PDH_FMT_COUNTERVALUE_LARGE struct {
	Status uint32
	_      uint32
	Value  int64
}
