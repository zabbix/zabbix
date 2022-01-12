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

// package std is used to create wrappers for standard Go functions to support
// mocking in tests where necessary
package std

import (
	"io"
	"os"
)

// File interface is used to mock os.File structure
type File interface {
	io.Reader
	io.Closer
}

// Os interface is used to mock os package
type Os interface {
	Open(name string) (File, error)
	Stat(name string) (os.FileInfo, error)
	IsExist(err error) bool
}

// A FileMode represents a file's mode and permission bits.
type FileMode uint32

// wrappers for standard os functionality

type sysOs struct {
}

func (o *sysOs) Open(name string) (File, error) {
	return os.Open(name)
}

func (o *sysOs) Stat(name string) (os.FileInfo, error) {
	return os.Stat(name)
}

func (o *sysOs) IsExist(err error) bool {
	return os.IsExist(err)
}

// NewOs returns Os interface that forwards supported methods to os package.
func NewOs() Os {
	return &sysOs{}
}
