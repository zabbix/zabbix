/*
** Copyright (C) 2001-2025 Zabbix SIA
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

package mock

import (
	"context"
	"errors"

	"github.com/godror/godror"
	zbxlog "golang.zabbix.com/sdk/log"
)

var (
	_ zbxlog.Logger = (*MockLogger)(nil)
)

// MockLogger type is empty zbxlog.Logger implementation for unittests.
type MockLogger struct {
}

// Infof empty mock function.
func (ml *MockLogger) Infof(_ string, _ ...any) {} //nolint:revive

// Critf empty mock function.
func (ml *MockLogger) Critf(_ string, _ ...any) {} //nolint:revive

// Errf empty mock function.
func (ml *MockLogger) Errf(_ string, _ ...any) {} //nolint:revive

// Warningf mock function.
func (ml *MockLogger) Warningf(_ string, _ ...any) {} //nolint:revive

// Debugf mock function.
func (ml *MockLogger) Debugf(_ string, _ ...any) {} //nolint:revive

// Tracef empty mock function.
func (ml *MockLogger) Tracef(_ string, _ ...any) {} //nolint:revive

// ServerVersionMock function mocks godror's server version check function.
func ServerVersionMock(_ context.Context, ex godror.Execer) (godror.VersionInfo, error) {
	if ex == nil {
		return godror.VersionInfo{}, errors.New("server version error") //nolint:err113
	}

	return godror.VersionInfo{}, nil
}
