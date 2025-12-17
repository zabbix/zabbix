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
const ZBX_STYLE_BTN_GREY_ICON = 'btn-grey-icon';
const ZBX_STYLE_BTN_ICON = 'btn-icon';
const ZBX_STYLE_BTN_LINK = 'btn-link';
const ZBX_STYLE_BTN_SMALL = 'btn-small';

const ZBX_STYLE_OVERLAY_DIALOGUE_HEADER = 'overlay-dialogue-header';

const ZBX_STYLE_GRID_COLUMN_FIRST = 'column-first';
const ZBX_STYLE_GRID_COLUMN_LAST = 'column-last';

const ZBX_STYLE_FORM_GRID = 'form-grid';
const ZBX_STYLE_FORM_FIELD = 'form-field';
const ZBX_STYLE_FORM_FIELDS_HINT = 'form-fields-hint';
const ZBX_STYLE_FORM_DESCRIPTION = 'form-description';

const ZBX_STYLE_FIELD_LABEL_ASTERISK = 'form-label-asterisk';

const ZBX_STYLE_MARKDOWN = 'markdown';
const ZBX_STYLE_FORMATED_TEXT = 'formated-text';

const ZBX_STYLE_TEXTAREA_FLEXIBLE = 'textarea-flexible';
const ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER = 'textarea-flexible-container';
const ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT = 'textarea-flexible-parent';

const ZBX_STYLE_COLOR_PICKER = 'color-picker';

const ZBX_STYLE_COLOR_WARNING = 'color-warning';

const ZBX_STYLE_ARROW_DOWN = 'arrow-down';
const ZBX_STYLE_ARROW_RIGHT = 'arrow-right';
const ZBX_STYLE_COLLAPSIBLE = 'collapsible';
const ZBX_STYLE_COLLAPSED = 'collapsed';
const ZBX_STYLE_DRAG_ICON = 'drag-icon';
const ZBX_STYLE_PROBLEM_ICON_LINK = 'problem-icon-link';
const ZBX_STYLE_PROBLEM_ICON_LIST_ITEM = 'problem-icon-list-item';
const ZBX_STYLE_TOGGLE = 'toggle';

const ZBX_STYLE_LINK_ALT = 'link-alt';

const ZBX_STYLE_LIST_TABLE = 'list-table';
const ZBX_STYLE_ROW_SELECTED = 'row-selected';

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

const ZBX_STYLE_DEFAULT_OPTION = 'default-option';

const ZBX_STYLE_TAG = 'tag';
const ZBX_STYLE_TAGS_LIST = 'tags-list';

const ZBX_STYLE_CHECKBOX_RADIO = 'checkbox-radio';

const ZBX_STYLE_OVERLAY_DIALOGUE = 'overlay-dialogue';

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
const ZBX_ICON_WIDGET_NOT_CONFIGURED_LARGE = ZBX_ICON_WIDGET_EMPTY_REFERENCES_LARGE;
const ZBX_ICON_WRENCH_ALT_SMALL = 'zi-wrench-alt-small';

const ITEM_VALUE_TYPE_FLOAT = 0;
const ITEM_VALUE_TYPE_STR = 1;
const ITEM_VALUE_TYPE_LOG = 2;
const ITEM_VALUE_TYPE_UINT64 = 3;
const ITEM_VALUE_TYPE_TEXT = 4;
const ITEM_VALUE_TYPE_BINARY = 5;

const TRIGGER_SEVERITY_NOT_CLASSIFIED = 0;
const TRIGGER_SEVERITY_INFORMATION = 1;
const TRIGGER_SEVERITY_WARNING = 2;
const TRIGGER_SEVERITY_AVERAGE = 3;
const TRIGGER_SEVERITY_HIGH = 4;
const TRIGGER_SEVERITY_DISASTER = 5;

const ZBX_SECRET_MASK = '******';

const PERM_READ = 2;

const TAG_EVAL_TYPE_AND_OR = 0;

const MAINTENANCE_TYPE_NORMAL = 0;
const MAINTENANCE_TYPE_NODATA = 1;

const SYSMAP_BACKGROUND_SCALE_COVER = 1;

const SYSMAP_ELEMENT_TYPE_HOST = 0;
const SYSMAP_ELEMENT_TYPE_MAP = 1;
const SYSMAP_ELEMENT_TYPE_TRIGGER = 2;
const SYSMAP_ELEMENT_TYPE_HOST_GROUP = 3;
const SYSMAP_ELEMENT_TYPE_IMAGE = 4;

const SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP = 0;
const SYSMAP_ELEMENT_SUBTYPE_HOST_GROUP_ELEMENTS = 1;

const SYSMAP_ELEMENT_AREA_TYPE_FIT = 0;
const SYSMAP_ELEMENT_AREA_TYPE_CUSTOM = 1;

const SYSMAP_ELEMENT_USE_ICONMAP_ON = 1;
const SYSMAP_ELEMENT_USE_ICONMAP_OFF = 0;

const SYSMAP_SHAPE_BORDER_TYPE_NONE = 0;
const SYSMAP_SHAPE_BORDER_TYPE_SOLID = 1;
const SYSMAP_SHAPE_BORDER_TYPE_DOTTED = 2;
const SYSMAP_SHAPE_BORDER_TYPE_DASHED = 3;

const SYSMAP_SHAPE_BORDER_WIDTH_DEFAULT = 2;

const SYSMAP_SHAPE_TYPE_RECTANGLE = 0;
const SYSMAP_SHAPE_TYPE_ELLIPSE = 1;
const SYSMAP_SHAPE_TYPE_LINE = 2;

const SYSMAP_SHAPE_LABEL_HALIGN_LEFT = 1;
const SYSMAP_SHAPE_LABEL_HALIGN_RIGHT = 2;

const SYSMAP_SHAPE_LABEL_VALIGN_TOP = 1;
const SYSMAP_SHAPE_LABEL_VALIGN_BOTTOM = 2;

const SYSMAP_EXPAND_MACROS_OFF = 0;
const SYSMAP_EXPAND_MACROS_ON = 1;

const SYSMAP_GRID_SHOW_OFF = 0;
const SYSMAP_GRID_SHOW_ON = 1;

const SYSMAP_GRID_ALIGN_OFF = 0;
const SYSMAP_GRID_ALIGN_ON = 1;

const MAP_LABEL_LOC_DEFAULT = -1;
const MAP_LABEL_LOC_BOTTOM = 0;
const MAP_LABEL_LOC_LEFT = 1;
const MAP_LABEL_LOC_RIGHT = 2;
const MAP_LABEL_LOC_TOP = 3;

const MAP_LABEL_TYPE_LABEL = 0;
const MAP_LABEL_TYPE_IP = 1;
const MAP_LABEL_TYPE_NAME = 2;
const MAP_LABEL_TYPE_STATUS = 3;
const MAP_LABEL_TYPE_NOTHING = 4;
const MAP_LABEL_TYPE_CUSTOM = 5;

const MAP_INDICATOR_TYPE_STATIC_LINK = 0;
const MAP_INDICATOR_TYPE_TRIGGER = 1;
const MAP_INDICATOR_TYPE_ITEM_VALUE = 2;

const MAP_SHOW_LABEL_DEFAULT = -1;
const MAP_SHOW_LABEL_AUTO_HIDE = 0;

const DRAWTYPE_LINE = 0;
const DRAWTYPE_BOLD_LINE = 2;
const DRAWTYPE_DOT = 3;
const DRAWTYPE_DASHED_LINE = 4;

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

const WRAPPER_PADDING_RIGHT = 10;

const GRAPH_HINTBOX_TYPE_SVG_GRAPH = 0;
const GRAPH_HINTBOX_TYPE_SCATTER_PLOT = 1;
