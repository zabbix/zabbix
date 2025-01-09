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

const (
	ARRAY_MAX = 1 << 49
)

type PROCESS_MEMORY_COUNTERS_EX struct {
	Cb                         uint32
	PageFaultCount             uint32
	PeakWorkingSetSize         uint64
	WorkingSetSize             uint64
	QuotaPeakPagedPoolUsage    uint64
	QuotaPagedPoolUsage        uint64
	QuotaPeakNonPagedPoolUsage uint64
	QuotaNonPagedPoolUsage     uint64
	PagefileUsage              uint64
	PeakPagefileUsage          uint64
	PrivateUsage               uint64
}

type PERFORMANCE_INFORMATION struct {
	Cb                uint32
	_                 [4]byte
	CommitTotal       uint64
	CommitLimit       uint64
	CommitPeak        uint64
	PhysicalTotal     uint64
	PhysicalAvailable uint64
	SystemCache       uint64
	KernelTotal       uint64
	KernelPaged       uint64
	KernelNonpaged    uint64
	PageSize          uint64
	HandleCount       uint32
	ProcessCount      uint32
	ThreadCount       uint32
}
