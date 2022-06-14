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

// Package uri provides a helper for URI validation and parsing
package uri

import (
	"errors"
	"fmt"
	"net"
	"net/url"
	"strconv"
	"strings"
)

type URI struct {
	scheme   string
	host     string
	port     string
	rawQuery string
	socket   string
	user     string
	password string
	rawUri   string
	path     string
}

func (u *URI) Scheme() string {
	return u.scheme
}

func (u *URI) Host() string {
	return u.host
}

func (u *URI) Socket() string {
	return u.socket
}

func (u *URI) Port() string {
	return u.port
}

func (u *URI) Query() string {
	return u.rawQuery
}

func (u *URI) Path() string {
	return u.path
}

func (u *URI) GetParam(key string) string {
	params, err := url.ParseQuery(u.rawQuery)
	if err != nil {
		return ""
	}

	return params.Get(key)
}

func (u *URI) Password() string {
	return u.password
}

func (u *URI) User() string {
	return u.user
}

// Addr combines a host and a port into a network address ("host:port") or returns a socket.
func (u *URI) Addr() string {
	if u.socket != "" {
		return u.socket
	}

	if u.port == "" {
		return u.host
	}

	return net.JoinHostPort(u.host, u.port)
}

// String reassembles the URI to a valid URI string.
func (u *URI) String() string {
	return u.string(u.rawQuery)
}

// NoQueryString reassembles the URI to a valid URI string with no query.
func (u *URI) NoQueryString() string {
	return u.string("")
}

func (u *URI) string(query string) string {
	t := &url.URL{
		Scheme:   u.scheme,
		RawQuery: query,
	}

	if u.socket != "" {
		t.Path = u.socket
	} else {
		if u.port == "" {
			t.Host = u.host
		} else {
			t.Host = net.JoinHostPort(u.host, u.port)
		}
	}

	if u.user != "" {
		if u.password != "" {
			t.User = url.UserPassword(u.user, u.password)
		} else {
			t.User = url.User(u.user)
		}
	}

	return t.String()
}

func (u *URI) withCreds(user, password string) *URI {
	u.password = password
	u.user = user

	return u
}

type Defaults struct {
	Port   string
	Scheme string
}

// New parses a given rawUri and returns a new filled URI structure.
// It ignores embedded credentials according to https://www.ietf.org/rfc/rfc3986.txt.
// Use NewWithCreds to add credentials to a structure.
func New(rawUri string, defaults *Defaults) (res *URI, err error) {
	var (
		isSocket bool
		noScheme bool
		port     string
	)

	rawUri = strings.TrimSpace(rawUri)

	res = &URI{
		rawUri: rawUri,
	}

	// https://tools.ietf.org/html/rfc6874#section-2
	// %25 is allowed to escape a percent sign in IPv6 scoped-address literals
	if !strings.Contains(rawUri, "%25") {
		rawUri = strings.Replace(rawUri, "%", "%25", -1)
	}

	if noScheme = !strings.Contains(rawUri, ":/"); noScheme {
		if defaults != nil && defaults.Scheme != "" {
			rawUri = defaults.Scheme + "://" + rawUri
		} else {
			rawUri = "tcp://" + rawUri
		}
	}

	u, err := url.Parse(rawUri)
	if err != nil {
		return nil, err
	}

	res.scheme = u.Scheme
	port = u.Port()

	if port == "" {
		if defaults != nil {
			port = defaults.Port
		}
	}

	if port != "" {
		if _, err = strconv.ParseUint(port, 10, 16); err != nil {
			return nil, errors.New("port must be integer and must be between 0 and 65535")
		}
	}

	isSocket = res.scheme == "unix" || (noScheme && u.Hostname() == "" && u.Path != "")
	if isSocket {
		if u.Path == "" {
			return nil, errors.New("socket is required")
		}

		res.scheme = "unix"
		res.socket = u.Path
	} else {
		if u.Hostname() == "" {
			return nil, errors.New("host is required")
		}

		res.host = u.Hostname()
		res.port = port
		res.path = u.Path
	}

	res.rawQuery = u.RawQuery

	return res, err
}

func NewWithCreds(rawUri, user, password string, defaults *Defaults) (res *URI, err error) {
	res, err = New(rawUri, defaults)
	if err != nil {
		return nil, err
	}

	return res.withCreds(user, password), nil
}

type URIValidator struct {
	Defaults       *Defaults
	AllowedSchemes []string
}

func (v URIValidator) Validate(value *string) error {
	if value == nil {
		return nil
	}

	res, err := New(*value, v.Defaults)
	if err != nil {
		return err
	}

	if v.AllowedSchemes != nil {
		for _, s := range v.AllowedSchemes {
			if res.Scheme() == s {
				return nil
			}
		}

		return fmt.Errorf("allowed schemes: %s", strings.Join(v.AllowedSchemes, ", "))
	}

	return nil
}

func IsHostnameOnly(host string) error {
	if strings.Contains(host, ":/") {
		return fmt.Errorf("must not contain scheme")
	}

	uri, err := New(host, &Defaults{Port: "", Scheme: ""})
	if err != nil {
		return err
	}

	if uri.Port() != "" {
		return fmt.Errorf("must not contain port")
	}

	if uri.Socket() != "" {
		return fmt.Errorf("must not contain socket")
	}

	if uri.User() != "" {
		return fmt.Errorf("must not contain user")
	}

	if uri.Password() != "" {
		return fmt.Errorf("must not contain password")
	}

	if uri.Query() != "" {
		return fmt.Errorf("must not contain query")
	}

	if uri.Path() != "" {
		return fmt.Errorf("must not contain path")
	}

	return nil
}
