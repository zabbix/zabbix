/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package log

import "fmt"

type Logger interface {
	Tracef(format string, args ...interface{})
	Debugf(format string, args ...interface{})
	Warningf(format string, args ...interface{})
	Infof(format string, args ...interface{})
	Errf(format string, args ...interface{})
	Critf(format string, args ...interface{})
}

type loggerImpl struct {
	prefix string
}

func New(module string) Logger {
	var prefix string
	if module != "" {
		prefix = fmt.Sprintf("[%s] ", module)
	}
	return &loggerImpl{prefix: prefix}
}

func (l *loggerImpl) Critf(format string, args ...interface{}) {
	Critf(l.prefix+format, args...)
}

func (l *loggerImpl) Infof(format string, args ...interface{}) {
	Infof(l.prefix+format, args...)
}

func (l *loggerImpl) Warningf(format string, args ...interface{}) {
	Warningf(l.prefix+format, args...)
}

func (l *loggerImpl) Tracef(format string, args ...interface{}) {
	Tracef(l.prefix+format, args...)
}

func (l *loggerImpl) Debugf(format string, args ...interface{}) {
	Debugf(l.prefix+format, args...)
}

func (l *loggerImpl) Errf(format string, args ...interface{}) {
	Errf(l.prefix+format, args...)
}
