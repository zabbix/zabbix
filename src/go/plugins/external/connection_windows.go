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

	"github.com/Microsoft/go-winio"
	"golang.zabbix.com/sdk/errs"
)

func cancelAccept(listener net.Listener) (func(), error) {
	// The go-winio pipe listener has no deadline support. Connecting to the
	// listening pipe releases the pending Accept without closing the listener,
	// allowing it to be reused by subsequent plugin starts.
	cancelTimeout := time.Second
	cancelConn, cancelErr := winio.DialPipe(listener.Addr().String(), &cancelTimeout)
	if cancelErr != nil {
		return nil, errs.Wrap(cancelErr, "failed to connect cancellation pipe")
	}

	return func() {
		cancelConn.Close() //nolint:errcheck,gosec // Best-effort cancellation connection close.
	}, nil
}
