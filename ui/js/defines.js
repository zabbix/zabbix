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


const CSRF_TOKEN_NAME = '_csrf_token';

const ZBX_COLOR_SCHEME_DARK = 'dark';
const ZBX_COLOR_SCHEME_LIGHT = 'light';

const ZBX_STYLE_DISPLAY_NONE = 'display-none';

const ZBX_STYLE_NO_DATA = 'no-data';
const ZBX_STYLE_NO_DATA_DESCRIPTION = 'no-data-description';
const ZBX_STYLE_NO_DATA_MESSAGE = 'no-data-message';

const ZBX_STYLE_BTN = 'btn';
const ZBX_STYLE_BTN_ALT = 'btn-alt';
const ZBX_STYLE_BTN_GREY = 'btn-grey';
const ZBX_STYLE_BTN_ICON = 'btn-icon';
const ZBX_STYLE_BTN_LINK = 'btn-link';
const ZBX_STYLE_BTN_SMALL = 'btn-small';

const ZBX_STYLE_FORM_GRID = 'form-grid';

const ZBX_STYLE_COLOR_WARNING = 'color-warning';

const ZBX_STYLE_ARROW_DOWN = 'arrow-down';
const ZBX_STYLE_ARROW_RIGHT = 'arrow-right';
const ZBX_STYLE_COLLAPSIBLE = 'collapsible';
const ZBX_STYLE_COLLAPSED = 'collapsed';
const ZBX_STYLE_DRAG_ICON = 'drag-icon';
const ZBX_STYLE_PROBLEM_ICON_LINK = 'problem-icon-link';
const ZBX_STYLE_PROBLEM_ICON_LIST_ITEM = 'problem-icon-list-item';

const ZBX_STYLE_LINK_ALT = 'link-alt';

const ZBX_STYLE_LIST_TABLE = 'list-table';
const ZBX_STYLE_ROW_SELECTED = 'row-selected';

const ZBX_ICON_BELL = 'zi-bell';
const ZBX_ICON_BELL_OFF = 'zi-bell-off';
const ZBX_ICON_CHECK = 'zi-check';
const ZBX_ICON_CHEVRON_DOWN = 'zi-chevron-down';
const ZBX_ICON_CHEVRON_DOWN_SMALL = 'zi-chevron-down-small';
const ZBX_ICON_CHEVRON_LEFT = 'zi-chevron-left';
const ZBX_ICON_CHEVRON_RIGHT = 'zi-chevron-right';
const ZBX_ICON_CHEVRON_UP = 'zi-chevron-up';
const ZBX_ICON_COG_FILLED = 'zi-cog-filled';
const ZBX_ICON_COPY = 'zi-copy';
const ZBX_ICON_EYE_OFF = 'zi-eye-off';
const ZBX_ICON_FILTER = 'zi-filter';
const ZBX_ICON_HELP_SMALL = 'zi-help-small';
const ZBX_ICON_HOME = 'zi-home';
const ZBX_ICON_LOCK = 'zi-lock';
const ZBX_ICON_MORE = 'zi-more';
const ZBX_ICON_PAUSE = 'zi-pause';
const ZBX_ICON_PENCIL = 'zi-pencil';
const ZBX_ICON_PLAY = 'zi-play';
const ZBX_ICON_PLUS = 'zi-plus';
const ZBX_ICON_REFERENCE = 'zi-reference';
const ZBX_ICON_REMOVE_SMALL = 'zi-remove-small';
const ZBX_ICON_REMOVE_SMALLER = 'zi-remove-smaller';
const ZBX_ICON_SEARCH_LARGE = 'zi-search-large';
const ZBX_ICON_SPEAKER = 'zi-speaker';
const ZBX_ICON_SPEAKER_OFF = 'zi-speaker-off';
const ZBX_ICON_TEXT = 'zi-text';
const ZBX_ICON_WIDGET_AWAITING_DATA_LARGE = 'zi-widget-awaiting-data-large';
const ZBX_ICON_WIDGET_EMPTY_REFERENCES_LARGE = 'zi-widget-empty-references-large';
const ZBX_ICON_WRENCH_ALT_SMALL = 'zi-wrench-alt-small';

const TRIGGER_SEVERITY_NOT_CLASSIFIED = 0;
const TRIGGER_SEVERITY_INFORMATION = 1;
const TRIGGER_SEVERITY_WARNING = 2;
const TRIGGER_SEVERITY_AVERAGE = 3;
const TRIGGER_SEVERITY_HIGH = 4;
const TRIGGER_SEVERITY_DISASTER = 5;

const ZBX_STYLE_NA_BG = 'na-bg';
const ZBX_STYLE_INFO_BG = 'info-bg';
const ZBX_STYLE_WARNING_BG = 'warning-bg';
const ZBX_STYLE_AVERAGE_BG = 'average-bg';
const ZBX_STYLE_HIGH_BG = 'high-bg';
const ZBX_STYLE_DISASTER_BG = 'disaster-bg';

const ZBX_STYLE_STATUS_NA_BG = 'status-na-bg';
const ZBX_STYLE_STATUS_INFO_BG = 'status-info-bg';
const ZBX_STYLE_STATUS_WARNING_BG = 'status-warning-bg';
const ZBX_STYLE_STATUS_AVERAGE_BG = 'status-average-bg';
const ZBX_STYLE_STATUS_HIGH_BG = 'status-high-bg';
const ZBX_STYLE_STATUS_DISASTER_BG = 'status-disaster-bg';

const MAINTENANCE_TYPE_NORMAL = 0;
const MAINTENANCE_TYPE_NODATA = 1;

const SYSMAP_ELEMENT_TYPE_HOST = 0;
const SYSMAP_ELEMENT_TYPE_MAP = 1;
const SYSMAP_ELEMENT_TYPE_TRIGGER = 2;
const SYSMAP_ELEMENT_TYPE_HOST_GROUP = 3;
const SYSMAP_ELEMENT_TYPE_IMAGE = 4;

const KEY_ARROW_DOWN = 40;
const KEY_ARROW_LEFT = 37;
const KEY_ARROW_RIGHT = 39;
const KEY_ARROW_UP = 38;
const KEY_BACKSPACE = 8;
const KEY_DELETE = 46;
const KEY_ENTER = 13;
const KEY_ESCAPE = 27;
const KEY_TAB = 9;
const KEY_PAGE_UP = 33;
const KEY_PAGE_DOWN = 34;
const KEY_END = 35;
const KEY_HOME = 36;
const KEY_SPACE = 32;

const PAGE_TYPE_TEXT_RETURN_JSON = 11;

const ZBX_SCRIPT_MANUALINPUT_DISABLED = 0;
const ZBX_SCRIPT_MANUALINPUT_ENABLED = 1;

const MFA_TYPE_TOTP = 1;
const MFA_TYPE_DUO = 2;

// IMPORTANT!!! by priority DESC
const GROUP_GUI_ACCESS_SYSTEM = 0;
const GROUP_GUI_ACCESS_INTERNAL = 1;
const GROUP_GUI_ACCESS_LDAP = 2;
const GROUP_GUI_ACCESS_DISABLED = 3;

const NAME_DELIMITER = ': ';
