//go:build windows
// +build windows

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

package win32

import (
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
)

type Hlib syscall.Handle

const (
	ANY_SIZE = 1

	IF_MAX_STRING_SIZE         = 256
	IF_MAX_PHYS_ADDRESS_LENGTH = 32
)

const (
	MIB_TCP_STATE_CLOSED     uint32 = 1
	MIB_TCP_STATE_LISTEN     uint32 = 2
	MIB_TCP_STATE_SYN_SENT   uint32 = 3
	MIB_TCP_STATE_SYN_RCVD   uint32 = 4
	MIB_TCP_STATE_ESTAB      uint32 = 5
	MIB_TCP_STATE_FIN_WAIT1  uint32 = 6
	MIB_TCP_STATE_FIN_WAIT2  uint32 = 7
	MIB_TCP_STATE_CLOSE_WAIT uint32 = 8
	MIB_TCP_STATE_CLOSING    uint32 = 9
	MIB_TCP_STATE_LAST_ACK   uint32 = 10
	MIB_TCP_STATE_TIME_WAIT  uint32 = 11
	MIB_TCP_STATE_DELETE_TCB uint32 = 12
)

type RGWSTR [ARRAY_MAX / unsafe.Sizeof(uint16(0))]uint16

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

type RGMIB_IF_ROW2 [ARRAY_MAX / unsafe.Sizeof(MIB_IF_ROW2{})]MIB_IF_ROW2

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

type RGMIB_IPADDRROW [ARRAY_MAX / unsafe.Sizeof(MIB_IPADDRROW{})]MIB_IPADDRROW

type MIB_IPADDRTABLE struct {
	NumEntries uint32
	Table      [ANY_SIZE]MIB_IPADDRROW
}

type MIB_TCPROW struct {
	State      uint32
	LocalAddr  uint32
	LocalPort  uint32
	RemoteAddr uint32
	RemotePort uint32
}

type RGMIB_TCPROW [ARRAY_MAX / unsafe.Sizeof(MIB_TCPROW{})]MIB_TCPROW

type MIB_TCPTABLE struct {
	NumEntries uint32
	Table      [ANY_SIZE]MIB_TCPROW
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

type SYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX struct {
	Relationship uint32
	Size         uint32
	Data         [1]byte
}

type GROUP_AFFINITY struct {
	Mask     uintptr
	Group    uint16
	Reserved [3]uint16
}

type RGGROUP_AFFINITY [ARRAY_MAX / unsafe.Sizeof(GROUP_AFFINITY{})]GROUP_AFFINITY

type NUMA_NODE_RELATIONSHIP struct {
	NodeNumber uint32
	Reserved   [20]uint8
	GroupMask  GROUP_AFFINITY
}

type PROCESSOR_RELATIONSHIP struct {
	Flags           uint8
	EfficiencyClass uint8
	Reserved        [20]uint8
	GroupCount      uint16
	GroupMask       [1]GROUP_AFFINITY
}

type MEMORYSTATUSEX struct {
	Length               uint32
	MemoryLoad           uint32
	TotalPhys            uint64
	AvailPhys            uint64
	TotalPageFile        uint64
	AvailPageFile        uint64
	TotalVirtual         uint64
	AvailVirtual         uint64
	AvailExtendedVirtual uint64
}

const (
	GR_GDIOBJECTS       = 0
	GR_GDIOBJECTS_PEAK  = 2
	GR_USEROBJECTS      = 1
	GR_USEROBJECTS_PEAK = 4
)

type IO_COUNTERS struct {
	ReadOperationCount  uint64
	WriteOperationCount uint64
	OtherOperationCount uint64
	ReadTransferCount   uint64
	WriteTransferCount  uint64
	OtherTransferCount  uint64
}

type CLUSTER struct {
	LpSectorsPerCluster     uint32
	LpBytesPerSector        uint32
	LpNumberOfFreeClusters  uint32
	LpTotalNumberOfClusters uint32
}
