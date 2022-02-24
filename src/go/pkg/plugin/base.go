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

package plugin

import (
	"zabbix.com/pkg/log"
)

type Accessor interface {
	Init(name string)
	Name() string
	Capacity() int
	SetCapacity(capactity int)
	IsExternal() bool
}

type Base struct {
	log.Logger
	name     string
	capacity int
	external bool
}

func (b *Base) Init(name string) {
	b.Logger = log.New(name)
	b.name = name
	b.capacity = DefaultCapacity
}

func (b *Base) Name() string {
	return b.name
}

func (b *Base) Capacity() int {
	return b.capacity
}

func (b *Base) SetCapacity(capacity int) {
	b.capacity = capacity
}

func (b *Base) IsExternal() bool {
	return b.external
}

func (b *Base) SetExternal(isExternal bool) {
	b.external = isExternal
}

type SystemOptions struct {
	Path     string `conf:"optional"`
	Capacity string `conf:"optional"`
}
