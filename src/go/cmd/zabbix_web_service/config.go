/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

import (
	"errors"
	"strings"

	"zabbix.com/pkg/tls"
)

var options serviceOptions

type serviceOptions struct {
	ListenPort  string `conf:"optional,range=1024:32767,default=10053"`
	AllowedIP   string `conf:"optional"`
	LogType     string `conf:"optional,default=file"`
	LogFile     string `conf:"optional,default=/tmp/zabbix_agent2.log"`
	LogFileSize int    `conf:"optional,range=0:1024,default=1"`
	Timeout     int    `conf:"optional,range=1:30,default=3"`
	TLSAccept   string `conf:"optional"`
	TLSCAFile   string `conf:"optional"`
	TLSCertFile string `conf:"optional"`
	TLSKeyFile  string `conf:"optional"`
}

func GetTLSConfig(options *serviceOptions) (cfg *tls.Config, err error) {
	c := &tls.Config{}
	switch options.TLSAccept {
	case "", "unencrypted":
		c.Connect = tls.ConnUnencrypted
	case "cert":
		c.Connect = tls.ConnCert
	default:
		return nil, errors.New("invalid TLSConnect configuration parameter")
	}

	if options.TLSAccept != "" {
		opts := strings.Split(options.TLSAccept, ",")
		for _, o := range opts {
			switch strings.Trim(o, " \t") {
			case "unencrypted":
				c.Accept |= tls.ConnUnencrypted
			case "cert":
				c.Accept |= tls.ConnCert
			default:
				return nil, errors.New("invalid TLSAccept configuration parameter")
			}
		}
	} else {
		c.Accept = tls.ConnUnencrypted
	}

	if (c.Accept|c.Connect)&tls.ConnCert != 0 {
		if options.TLSCAFile != "" {
			c.CAFile = options.TLSCAFile
		} else {
			return nil, errors.New("missing TLSCAFile configuration parameter")
		}
		if options.TLSCertFile != "" {
			c.CertFile = options.TLSCertFile
		} else {
			return nil, errors.New("missing TLSCertFile configuration parameter")
		}
		if options.TLSKeyFile != "" {
			c.KeyFile = options.TLSKeyFile
		} else {
			return nil, errors.New("missing TLSKeyFile configuration parameter")
		}
	} else {
		if options.TLSCAFile != "" {
			return nil, errors.New("TLSCAFile configuration parameter set without certificates being used")
		}
		if options.TLSCertFile != "" {
			return nil, errors.New("TLSCertFile configuration parameter set without certificates being used")
		}
		if options.TLSKeyFile != "" {
			return nil, errors.New("TLSKeyFile configuration parameter set without certificates being used")
		}
	}

	return c, nil
}
