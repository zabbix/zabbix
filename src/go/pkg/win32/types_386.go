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
	ARRAY_MAX = 1 << 30
)

type PROCESS_MEMORY_COUNTERS_EX struct {
	Cb                         uint32
	PageFaultCount             uint32
	PeakWorkingSetSize         uint32
	WorkingSetSize             uint32
	QuotaPeakPagedPoolUsage    uint32
	QuotaPagedPoolUsage        uint32
	QuotaPeakNonPagedPoolUsage uint32
	QuotaNonPagedPoolUsage     uint32
	PagefileUsage              uint32
	PeakPagefileUsage          uint32
	PrivateUsage               uint32
}

type PERFORMANCE_INFORMATION struct {
	Cb                uint32
	CommitTotal       uint32
	CommitLimit       uint32
	CommitPeak        uint32
	PhysicalTotal     uint32
	PhysicalAvailable uint32
	SystemCache       uint32
	KernelTotal       uint32
	KernelPaged       uint32
	KernelNonpaged    uint32
	PageSize          uint32
	HandleCount       uint32
	ProcessCount      uint32
	ThreadCount       uint32
}
