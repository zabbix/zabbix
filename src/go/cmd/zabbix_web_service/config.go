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

package main

var options serviceOptions

type serviceOptions struct {
	ListenPort          string `conf:"optional,range=1024:32767,default=10053"`
	AllowedIP           string `conf:"optional"`
	LogType             string `conf:"optional,default=file"`
	LogFile             string `conf:"optional,default=/tmp/zabbix_web_service.log"`
	LogFileSize         int    `conf:"optional,range=0:1024,default=1"`
	Timeout             int    `conf:"optional,range=1:30,default=3"`
	DebugLevel          int    `conf:"range=0:5,default=3"`
	TLSAccept           string `conf:"optional"`
	TLSCAFile           string `conf:"optional"`
	TLSCertFile         string `conf:"optional"`
	TLSKeyFile          string `conf:"optional"`
	IgnoreURLCertErrors int    `conf:"optional,range=0:1,default=0"`
}
