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

package ceph

import (
	"errors"
	"net"
	"net/url"
	"strconv"
	"strings"
)

type URI struct {
	scheme   string
	host     string
	port     string
	user     string
	password string
}

func (u *URI) Scheme() string {
	return u.scheme
}

// Addr combines host and port into a network address of the form "host:port".
func (u *URI) Addr() string {
	return net.JoinHostPort(u.host, u.port)
}

func (u *URI) Host() string {
	return u.host
}

func (u *URI) Port() string {
	return u.port
}

func (u *URI) Password() string {
	return u.password
}

func (u *URI) User() string {
	return u.user
}

// String reassembles the URI into a valid URI string.
func (u *URI) String() string {
	uri := &url.URL{
		Scheme:   u.scheme,
		Host:     net.JoinHostPort(u.host, u.port),
		Path:     "/request",
		RawQuery: "wait=1",
	}

	if u.user != "" && u.password != "" {
		uri.User = url.UserPassword(u.user, u.password)
	}

	return uri.String()
}

// newURIWithCreds calls parseURI with given credentials.
func newURIWithCreds(uri, user, password string) (res *URI, err error) {
	res, err = parseURI(uri)

	if err == nil {
		res.password = password
		res.user = user
	}

	return res, err
}

const DefaultPort = "8003"

// parseURI splits a given URI to scheme, host:port/socket and returns a URI structure.
// It uses DefaultPort if a URI does not consist of port.
// If an error occurs it returns error and an empty structure.
// It ignores embedded credentials according to https://www.ietf.org/rfc/rfc3986.txt.
func parseURI(uri string) (res *URI, err error) {
	res = &URI{}

	// https://tools.ietf.org/html/rfc6874#section-2
	// %25 is allowed to escape a percent sign in IPv6 scoped-address literals
	if !strings.Contains(uri, "%25") {
		uri = strings.Replace(uri, "%", "%25", -1)
	}

	if u, err := url.Parse(uri); err == nil {
		res.host = u.Hostname()
		if len(res.host) == 0 {
			return nil, errors.New("host is required")
		}

		port := u.Port()

		if len(port) == 0 {
			port = DefaultPort
		} else if _, err := strconv.ParseUint(port, 10, 16); err != nil {
			return nil, errors.New("port must be integer and must be between 0 and 65535")
		}

		res.port = port

		res.scheme = u.Scheme
	} else {
		return nil, errors.New("failed to parse connection string")
	}

	return res, err
}

// validateURI wraps parseURI in order to return a comprehensible error when validating a URI.
func validateURI(uri string) (err error) {
	_, err = parseURI(uri)

	return
}

// isLooksLikeURI returns true if s is URI or false if not.
func isLooksLikeURI(s string) bool {
	return strings.Contains(s, "https://")
}
