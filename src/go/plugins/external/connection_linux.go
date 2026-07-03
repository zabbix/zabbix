/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package external

import (
	"net"
	"time"

	"golang.zabbix.com/sdk/errs"
)

type deadlineListener interface {
	net.Listener
	SetDeadline(deadline time.Time) error
}

func cancelAccept(listener net.Listener) (func(), error) {
	l, ok := listener.(deadlineListener)
	if !ok {
		return nil, errs.New("listener does not support deadlines")
	}

	err := l.SetDeadline(time.Now())
	if err != nil {
		return nil, errs.Wrap(err, "failed to expire listener deadline")
	}

	return func() {
		l.SetDeadline(time.Time{}) //nolint:errcheck,gosec // Best-effort deadline reset.
	}, nil
}
