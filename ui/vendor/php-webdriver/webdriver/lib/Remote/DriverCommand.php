<?php

namespace Facebook\WebDriver\Remote;

/**
 * This list of command defined in the WebDriver json wire protocol.
 *
 * @codeCoverageIgnore
 */
class DriverCommand
{
    public const GET_ALL_SESSIONS = 'getAllSessions';
    public const GET_CAPABILITIES = 'getCapabilities';
    public const NEW_SESSION = 'newSession';
    public const STATUS = 'status';
    public const CLOSE = 'close';
    public const QUIT = 'quit';
    public const GET = 'get';
    public const GO_BACK = 'goBack';
    public const GO_FORWARD = 'goForward';
    public const REFRESH = 'refresh';
    public const ADD_COOKIE = 'addCookie';
    public const GET_ALL_COOKIES = 'getCookies';
    public const DELETE_COOKIE = 'deleteCookie';
    public const DELETE_ALL_COOKIES = 'deleteAllCookies';
    public const FIND_ELEMENT = 'findElement';
    public const FIND_ELEMENTS = 'findElements';
    public const FIND_CHILD_ELEMENT = 'findChildElement';
    public const FIND_CHILD_ELEMENTS = 'findChildElements';
    public const CLEAR_ELEMENT = 'clearElement';
    public const CLICK_ELEMENT = 'clickElement';
    public const SEND_KEYS_TO_ELEMENT = 'sendKeysToElement';
    public const SEND_KEYS_TO_ACTIVE_ELEMENT = 'sendKeysToActiveElement';
    public const SUBMIT_ELEMENT = 'submitElement';
    public const UPLOAD_FILE = 'uploadFile';
    public const GET_CURRENT_WINDOW_HANDLE = 'getCurrentWindowHandle';
    public const GET_WINDOW_HANDLES = 'getWindowHandles';
    public const GET_CURRENT_CONTEXT_HANDLE = 'getCurrentContextHandle';
    public const GET_CONTEXT_HANDLES = 'getContextHandles';
    // Switching between to window/frame/iframe
    public const SWITCH_TO_WINDOW = 'switchToWindow';
    public const SWITCH_TO_CONTEXT = 'switchToContext';
    public const SWITCH_TO_FRAME = 'switchToFrame';
    public const SWITCH_TO_PARENT_FRAME = 'switchToParentFrame';
    public const GET_ACTIVE_ELEMENT = 'getActiveElement';
    // Information of the page
    public const GET_CURRENT_URL = 'getCurrentUrl';
    public const GET_PAGE_SOURCE = 'getPageSource';
    public const GET_TITLE = 'getTitle';
    // Javascript API
    public const EXECUTE_SCRIPT = 'executeScript';
    public const EXECUTE_ASYNC_SCRIPT = 'executeAsyncScript';
    // API getting information from an element.
    public const GET_ELEMENT_TEXT = 'getElementText';
    public const GET_ELEMENT_TAG_NAME = 'getElementTagName';
    public const IS_ELEMENT_SELECTED = 'isElementSelected';
    public const IS_ELEMENT_ENABLED = 'isElementEnabled';
    public const IS_ELEMENT_DISPLAYED = 'isElementDisplayed';
    public const GET_ELEMENT_LOCATION = 'getElementLocation';
    public const GET_ELEMENT_LOCATION_ONCE_SCROLLED_INTO_VIEW = 'getElementLocationOnceScrolledIntoView';
    public const GET_ELEMENT_SIZE = 'getElementSize';
    public const GET_ELEMENT_ATTRIBUTE = 'getElementAttribute';
    public const GET_ELEMENT_VALUE_OF_CSS_PROPERTY = 'getElementValueOfCssProperty';
    public const ELEMENT_EQUALS = 'elementEquals';
    public const SCREENSHOT = 'screenshot';
    // Alert API
    public const ACCEPT_ALERT = 'acceptAlert';
    public const DISMISS_ALERT = 'dismissAlert';
    public const GET_ALERT_TEXT = 'getAlertText';
    public const SET_ALERT_VALUE = 'setAlertValue';
    // Timeout API
    public const SET_TIMEOUT = 'setTimeout';
    public const IMPLICITLY_WAIT = 'implicitlyWait';
    public const SET_SCRIPT_TIMEOUT = 'setScriptTimeout';
    /** @deprecated */
    public const EXECUTE_SQL = 'executeSQL';
    public const GET_LOCATION = 'getLocation';
    public const SET_LOCATION = 'setLocation';
    public const GET_APP_CACHE = 'getAppCache';
    public const GET_APP_CACHE_STATUS = 'getStatus';
    public const CLEAR_APP_CACHE = 'clearAppCache';
    public const IS_BROWSER_ONLINE = 'isBrowserOnline';
    public const SET_BROWSER_ONLINE = 'setBrowserOnline';
    // Local storage
    public const GET_LOCAL_STORAGE_ITEM = 'getLocalStorageItem';
    public const GET_LOCAL_STORAGE_KEYS = 'getLocalStorageKeys';
    public const SET_LOCAL_STORAGE_ITEM = 'setLocalStorageItem';
    public const REMOVE_LOCAL_STORAGE_ITEM = 'removeLocalStorageItem';
    public const CLEAR_LOCAL_STORAGE = 'clearLocalStorage';
    public const GET_LOCAL_STORAGE_SIZE = 'getLocalStorageSize';
    // Session storage
    public const GET_SESSION_STORAGE_ITEM = 'getSessionStorageItem';
    public const GET_SESSION_STORAGE_KEYS = 'getSessionStorageKey';
    public const SET_SESSION_STORAGE_ITEM = 'setSessionStorageItem';
    public const REMOVE_SESSION_STORAGE_ITEM = 'removeSessionStorageItem';
    public const CLEAR_SESSION_STORAGE = 'clearSessionStorage';
    public const GET_SESSION_STORAGE_SIZE = 'getSessionStorageSize';
    // Screen orientation
    public const SET_SCREEN_ORIENTATION = 'setScreenOrientation';
    public const GET_SCREEN_ORIENTATION = 'getScreenOrientation';
    // These belong to the Advanced user interactions - an element is optional for these commands.
    public const CLICK = 'mouseClick';
    public const DOUBLE_CLICK = 'mouseDoubleClick';
    public const MOUSE_DOWN = 'mouseButtonDown';
    public const MOUSE_UP = 'mouseButtonUp';
    public const MOVE_TO = 'mouseMoveTo';
    // Those allow interactions with the Input Methods installed on the system.
    public const IME_GET_AVAILABLE_ENGINES = 'imeGetAvailableEngines';
    public const IME_GET_ACTIVE_ENGINE = 'imeGetActiveEngine';
    public const IME_IS_ACTIVATED = 'imeIsActivated';
    public const IME_DEACTIVATE = 'imeDeactivate';
    public const IME_ACTIVATE_ENGINE = 'imeActivateEngine';
    // These belong to the Advanced Touch API
    public const TOUCH_SINGLE_TAP = 'touchSingleTap';
    public const TOUCH_DOWN = 'touchDown';
    public const TOUCH_UP = 'touchUp';
    public const TOUCH_MOVE = 'touchMove';
    public const TOUCH_SCROLL = 'touchScroll';
    public const TOUCH_DOUBLE_TAP = 'touchDoubleTap';
    public const TOUCH_LONG_PRESS = 'touchLongPress';
    public const TOUCH_FLICK = 'touchFlick';
    // Window API (beta)
    public const SET_WINDOW_SIZE = 'setWindowSize';
    public const SET_WINDOW_POSITION = 'setWindowPosition';
    public const GET_WINDOW_SIZE = 'getWindowSize';
    public const GET_WINDOW_POSITION = 'getWindowPosition';
    public const MAXIMIZE_WINDOW = 'maximizeWindow';
    public const FULLSCREEN_WINDOW = 'fullscreenWindow';
    // Logging API
    public const GET_AVAILABLE_LOG_TYPES = 'getAvailableLogTypes';
    public const GET_LOG = 'getLog';
    public const GET_SESSION_LOGS = 'getSessionLogs';
    // Mobile API
    public const GET_NETWORK_CONNECTION = 'getNetworkConnection';
    public const SET_NETWORK_CONNECTION = 'setNetworkConnection';
    // Custom command
    public const CUSTOM_COMMAND = 'customCommand';

    // W3C specific
    public const ACTIONS = 'actions';
    public const GET_ELEMENT_PROPERTY = 'getElementProperty';
    public const GET_NAMED_COOKIE = 'getNamedCookie';
    public const NEW_WINDOW = 'newWindow';
    public const TAKE_ELEMENT_SCREENSHOT = 'takeElementScreenshot';
    public const MINIMIZE_WINDOW = 'minimizeWindow';
    public const GET_ELEMENT_SHADOW_ROOT = 'getElementShadowRoot';
    public const FIND_ELEMENT_FROM_SHADOW_ROOT = 'findElementFromShadowRoot';
    public const FIND_ELEMENTS_FROM_SHADOW_ROOT = 'findElementsFromShadowRoot';

    private function __construct()
    {
    }
}
