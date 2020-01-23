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

package redis

import (
	"errors"
	"net/url"
	"strconv"
	"strings"
)

type URI struct {
	scheme   string
	host     string
	port     string
	socket   string
	password string
}

func (u *URI) Scheme() string {
	return u.scheme
}

func (u *URI) Addr() string {
	if u.socket != "" {
		return u.socket
	}
	return u.host + ":" + u.port
}

func (u *URI) Password() string {
	return u.password
}

func (u *URI) Uri() string {
	if len(u.password) == 0 {
		return u.scheme + "://" + u.Addr()
	}
	return u.scheme + "://user:" + u.password + "@" + u.Addr()
}

func newUriWithCreds(uri string, password string) (res URI, err error) {
	res, err = parseUri(uri)

	if err == nil {
		res.password = password
	}

	return
}

const DefaultPort = "6379"

// parseUri splits a given URI to scheme, host:port/socket, password and returns a URI structure.
// It uses DefaultPort if URI does not consist of port. The only allowed schemes are: tcp and unix.
// If an error occurs it returns error and an empty structure.
func parseUri(uri string) (res URI, err error) {
	if u, err := url.Parse(string(uri)); err == nil {
		switch strings.ToLower(u.Scheme) {
		case "tcp":
			res.host = u.Hostname()
			if len(res.host) == 0 {
				return URI{}, errors.New("host is required")
			}

			port := u.Port()

			if portInt, err := strconv.Atoi(port); err == nil {
				if portInt < 1 || portInt > 65535 {
					return URI{}, errors.New("port must be integer and must be between 1 and 65535")
				}
			}

			if len(port) == 0 {
				port = DefaultPort
			}

			res.port = port

		case "unix":
			if len(u.Path) == 0 {
				return URI{}, errors.New("socket is required")
			}

			res.socket = u.Path

		default:
			return URI{}, errors.New("the only supported schemes are: tcp and unix")
		}

		res.scheme = u.Scheme

	} else {
		return URI{}, errors.New("failed to parse connection string")
	}

	return
}

// validateUri wraps parseURI in order to return a comprehensible error when validating a URI.
func validateUri(uri string) (err error) {
	_, err = parseUri(uri)

	return
}

// isUri returns true if s is URI or false if not
func isLooksLikeUri(s string) bool {
	return strings.Contains(s, "tcp://") || strings.Contains(s, "unix:/")
}
