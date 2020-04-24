/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package mysql

import (
	"net/url"
	"time"

	"github.com/go-sql-driver/mysql"
)

func checkURI(s *Session) (sessionURL *url.URL, err error) {

	sessionURL, err = url.Parse(s.Uri)
	if err != nil {
		return nil, err
	}

	switch sessionURL.Scheme {
	case "tcp":
		if len(sessionURL.Host) == 0 {
			return nil, errorParameterNotURI
		}
	case "unix":
		if len(sessionURL.Path) == 0 {
			return nil, errorParameterNotURI
		}
		sessionURL.Host = sessionURL.Path
	default:
		return nil, errorParameterNotURI
	}

	return sessionURL, nil
}

func (p *Plugin) getConfigDSN(s *Session) (result *mysql.Config, err error) {

	sessionURL, err := checkURI(s)
	if err != nil {
		return nil, err
	}

	result = &mysql.Config{
		User:                 s.User,
		Passwd:               s.Password,
		Net:                  sessionURL.Scheme,
		Addr:                 sessionURL.Host,
		AllowNativePasswords: true,
		Timeout:              time.Duration(p.options.Timeout-1) * time.Second,
		ReadTimeout:          time.Duration(p.options.Timeout-1) * time.Second,
	}

	return
}
