<?php
/** @noinspection SpellCheckingInspection */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Networks;

use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * This class is responsible for analyzing the User-Agent string and determining the type of client/browser.
 * It provides methods to check if the client is a mobile device, AI, bot, HTTP client, automation tool,
 * security client, or a standard web browser.
 */
class ClientBrowserType
{
    /**
     * @var string $browserName
     */
    private string $browserName;

    /**
     * Indicates whether the client appears to be a mobile device.
     *
     * @var bool $isMobile
     */
    private bool $isMobile;

    /**
     * Indicates whether the client is an AI (e.g., GPTBot, ChatGPT, etc.).
     *
     * @var bool $isAI
     */
    private bool $isAI;

    /**
     * Indicates whether the client is a bot (e.g., Googlebot, Bingbot, etc.).
     *
     * @var bool $isBot
     */
    private bool $isBot;

    /**
     * Indicates whether the client is an HTTP client (e.g., curl, wget, Postman, etc.).
     *
     * @var bool $isHttpClient
     */
    private bool $isHttpClient;

    /**
     * Indicates whether the client is an automation tool (e.g., Puppeteer, Selenium, etc.).
     *
     * @var bool $isAutomation
     */
    private bool $isAutomation;

    /**
     * Indicates whether the client is a security client (e.g., antivirus, firewall, etc.).
     *
     * @var bool $isSecurityClient
     */
    private bool $isSecurityClient;

    /**
     * Indicates whether the client is a standard web browser (e.g., Chrome, Firefox, Safari, etc.).
     *
     * @var bool $isBrowser
     */
    private bool $isBrowser;

    /**
     * ClientBrowserType constructor.
     *
     * @param UserAgent $userAgent The UserAgent instance to analyze.
     */
    public function __construct(public readonly UserAgent $userAgent)
    {
    }

    /**
     * Check whether the client appears to be a mobile device.
     */
    public function isMobile(): bool
    {
        return $this->isMobile ??= (bool)preg_match(
            <<<REGEXP
~
Mobile|Android|iPhone|iPad|iPod|Windows\s+Phone|IEMobile|
Opera\s+Mini|Opera\s+Mobi|BlackBerry|webOS|
Silk/|Kindle|PlayBook
~ix
REGEXP,
            $this->userAgent->userAgent
        );
    }

    /**
     * Get the browser identity in the format of "device_type/browser_name".
     * For example, "mobile/chrome" or "desktop/firefox".
     */
    public function getBrowserIdentity(): string
    {
        return sprintf(
            '%s/%s',
            $this->isMobile() ? 'mobile' : 'desktop',
            $this->getBrowserName()
        );
    }

    /**
     * Get the detected browser/client type.
     */
    public function getBrowserName(): string
    {
        if (isset($this->browserName)) {
            return $this->browserName;
        }

        $ua = strtolower($this->userAgent->userAgent);
        $browser = match (true) {
            /*
             * Automation / Headless
             */
            str_contains($ua, self::CLIENT_PUPPETEER) => self::CLIENT_PUPPETEER,
            str_contains($ua, self::CLIENT_PLAYWRIGHT) => self::CLIENT_PLAYWRIGHT,
            str_contains($ua, self::CLIENT_PHANTOMJS) => self::CLIENT_PHANTOMJS,
            str_contains($ua, 'headlesschrome') ||
            str_contains($ua, 'headless-chromium') => self::CLIENT_HEADLESS_CHROME,
            str_contains($ua, self::CLIENT_SELENIUM) => self::CLIENT_SELENIUM,
            str_contains($ua, self::CLIENT_WEBDRIVER) => self::CLIENT_WEBDRIVER,

            // security & antivirus clients
            str_contains($ua, self::CLIENT_COMODO) => self::CLIENT_COMODO,
            str_contains($ua, self::CLIENT_AVAST) => self::CLIENT_AVAST,

            /*
             * HTTP clients
             */
            str_contains($ua, 'curl/') || $ua === 'curl' => self::CLIENT_CURL,
            str_contains($ua, 'wget/') || $ua === 'wget' => self::CLIENT_WGET,
            str_contains($ua, 'httpie') => self::CLIENT_HTTPIE,
            str_contains($ua, 'postmanruntime') => self::CLIENT_POSTMAN,
            str_contains($ua, 'python-requests') => self::CLIENT_REQUESTS,
            str_contains($ua, 'python-httpx') || str_contains($ua, 'httpx/') => self::CLIENT_HTTPX,
            str_contains($ua, self::CLIENT_AIOHTTP) => self::CLIENT_AIOHTTP,
            str_contains($ua, 'python-urllib') ||
            str_starts_with($ua, 'python/') => self::CLIENT_PYTHON,
            str_contains($ua, 'guzzlehttp') ||
            str_contains($ua, 'guzzle/') => self::CLIENT_GUZZLE,
            str_contains($ua, 'php/') => self::CLIENT_PHP,
            str_contains($ua, 'go-http-client') => self::CLIENT_GO,
            str_contains($ua, self::CLIENT_REQUESTS) => self::CLIENT_REQWEST,
            str_contains($ua, 'rust/') => self::CLIENT_RUST,
            str_contains($ua, self::CLIENT_OKHTTP) => self::CLIENT_OKHTTP,
            str_contains($ua, 'apache-httpclient') => self::CLIENT_APACHE_HTTPCLIENT,
            str_contains($ua, 'node-fetch') => self::CLIENT_NODE_FETCH,
            str_contains($ua, self::CLIENT_UNDICI) => self::CLIENT_UNDICI,
            str_contains($ua, self::CLIENT_AXIOS) => self::CLIENT_AXIOS,
            str_contains($ua, 'node.js') || str_contains($ua, 'node/') => self::CLIENT_NODE,
            str_contains($ua, 'java-http-client') ||
            str_contains($ua, 'java/') => self::CLIENT_JAVA,
            str_contains($ua, 'libwww-perl') => self::CLIENT_LIBWWW_PERL,

            /*
             * AI / LLM
             */
            str_contains($ua, 'oai-searchbot') => self::CLIENT_OAI_SEARCHBOT,
            str_contains($ua, 'chatgpt-user') => self::CLIENT_CHATGPT,
            str_contains($ua, self::CLIENT_GPTBOT) => self::CLIENT_GPTBOT,
            str_contains($ua, self::CLIENT_CLAUDEBOT) || str_contains($ua, 'claude-web') => self::CLIENT_CLAUDEBOT,
            str_contains($ua, self::CLIENT_PERPLEXITYBOT) => self::CLIENT_PERPLEXITYBOT,
            str_contains($ua, 'bytespider') => self::CLIENT_BYTESPIDER,
            str_contains($ua, 'google-extended') || str_contains($ua, 'gemini') => self::CLIENT_GEMINIBOT,
            str_contains($ua, 'meta-externalagent') ||
            str_contains($ua, 'meta-externalfetch') => self::CLIENT_META_EXTERNAL_AGENT,
            str_contains($ua, self::CLIENT_AMAZONBOT) => self::CLIENT_AMAZONBOT,
            str_contains($ua, 'cohere-ai') => self::CLIENT_COHERE,

            /*
             * Search / social bots
             */
            str_contains($ua, self::CLIENT_GOOGLEBOT) => self::CLIENT_GOOGLEBOT,
            str_contains($ua, self::CLIENT_BINGBOT) => self::CLIENT_BINGBOT,
            str_contains($ua, self::CLIENT_DUCKDUCKBOT) => self::CLIENT_DUCKDUCKBOT,
            str_contains($ua, self::CLIENT_YANDEXBOT) => self::CLIENT_YANDEXBOT,
            str_contains($ua, self::CLIENT_BAIDUSPIDER) => self::CLIENT_BAIDUSPIDER,
            str_contains($ua, self::CLIENT_SOGOU_SPIDER) => self::CLIENT_SOGOU_SPIDER,

            str_contains($ua, self::CLIENT_EXABOT) => self::CLIENT_EXABOT,
            str_contains($ua, self::CLIENT_APPLEBOT) => self::CLIENT_APPLEBOT,
            str_contains($ua, 'facebookexternalhit') || str_contains($ua, 'facebookbot') => self::CLIENT_FACEBOOKBOT,
            str_contains($ua, self::CLIENT_TWITTERBOT) => self::CLIENT_TWITTERBOT,
            str_contains($ua, self::CLIENT_LINKEDINBOT) => self::CLIENT_LINKEDINBOT,
            str_contains($ua, self::CLIENT_PINTERESTBOT) => self::CLIENT_PINTERESTBOT,
            str_contains($ua, self::CLIENT_SEMRUSHBOT) => self::CLIENT_SEMRUSHBOT,
            str_contains($ua, self::CLIENT_AHREFSBOT) => self::CLIENT_AHREFSBOT,
            str_contains($ua, self::CLIENT_MJ12BOT) => self::CLIENT_MJ12BOT,
            str_contains($ua, self::CLIENT_DOTBOT) => self::CLIENT_DOTBOT,
            str_contains($ua, self::CLIENT_PETALBOT) => self::CLIENT_PETALBOT,
            str_contains($ua, self::CLIENT_ACUNETIX) => self::CLIENT_ACUNETIX,

            /*
             * Microsoft
             *
             * Edge must be checked before Chrome because Edge Chromium
             * also contains Chrome/ and Safari/.
             */
            str_contains($ua, 'edgios/') ||
            str_contains($ua, 'edga/') ||
            str_contains($ua, 'edg/') ||
            str_contains($ua, 'edge/') => self::CLIENT_EDGE,
            str_contains($ua, 'msie ') ||
            str_contains($ua, 'trident/') => self::CLIENT_IE,

            /*
             * Opera
             *
             * Opera Chromium also contains Chrome/ and Safari/.
             */
            str_contains($ua, 'opera mini') => self::CLIENT_OPERA_MINI,
            str_contains($ua, 'opera mobi') => self::CLIENT_OPERA_MOBILE,
            str_contains($ua, 'opr/') || str_contains($ua, 'opera/') => self::CLIENT_OPERA,

            /*
             * Samsung Browser
             *
             * Samsung Browser also contains Chrome/ and Safari/.
             */
            str_contains($ua, 'samsungbrowser/') => self::CLIENT_SAMSUNG,

            /*
             * Yandex
             */
            str_contains($ua, 'yabrowser/') => str_contains($ua, 'mobile')
                ? self::CLIENT_YANDEX_MOBILE
                : self::CLIENT_YANDEX,

            /*
             * UC Browser
             */
            str_contains($ua, 'ucbrowser/') ||
            str_contains($ua, 'ucweb/') => self::CLIENT_UC,

            /*
             * Firefox family
             */
            str_contains($ua, 'seamonkey/') => self::CLIENT_SEAMONKEY,
            str_contains($ua, 'palemoon/') => self::CLIENT_PALEMOON,
            str_contains($ua, 'waterfox/') => self::CLIENT_WATERFOX,
            str_contains($ua, 'firefox/') || str_contains($ua, 'fxios/') => self::CLIENT_FIREFOX,

            /*
             * Other Chromium browsers
             *
             * Must be before Chrome.
             */
            str_contains($ua, 'vivaldi/') ||
            str_contains($ua, self::CLIENT_VIVALDI) => self::CLIENT_VIVALDI,
            str_contains($ua, self::CLIENT_AVANT) => self::CLIENT_AVANT,
            str_contains($ua, 'brave/') ||
            str_contains($ua, self::CLIENT_BRAVE) => self::CLIENT_BRAVE,
            str_contains($ua, 'chromium/') => self::CLIENT_CHROMIUM,

            /*
             * Android WebView
             *
             * WebView UA can also contain Chrome/.
             */
            (
                str_contains($ua, 'android') &&
                (
                    str_contains($ua, '; wv') ||
                    str_contains($ua, ' wv)') ||
                    str_contains($ua, 'version/4.0')
                )
            ) => self::CLIENT_ANDROID_WEBVIEW,

            /*
             * Android stock browser
             */
            (
                str_contains($ua, 'android') &&
                str_contains($ua, 'version/') &&
                !str_contains($ua, 'chrome/') &&
                !str_contains($ua, 'crios/')
            ) => self::CLIENT_ANDROID,

            /*
             * Other browsers
             *
             * These need to be checked before Safari/Chrome where
             * applicable.
             */
            str_contains($ua, self::CLIENT_MIDORI) => self::CLIENT_MIDORI,
            str_contains($ua, self::CLIENT_EPIPHANY) => self::CLIENT_EPIPHANY,
            str_contains($ua, self::CLIENT_GNOME_WEB) => self::CLIENT_GNOME_WEB,
            str_contains($ua, self::CLIENT_KONQUEROR) => self::CLIENT_KONQUEROR,
            str_contains($ua, self::CLIENT_MAXTHON) => self::CLIENT_MAXTHON,
            str_contains($ua, self::CLIENT_SLIMJET) => self::CLIENT_SLIMJET,
            str_contains($ua, self::CLIENT_PUFFIN) => self::CLIENT_PUFFIN,
            str_contains($ua, self::CLIENT_DOLPHIN) => self::CLIENT_DOLPHIN,
            str_contains($ua, 'qqbrowser') => self::CLIENT_QQ,
            str_contains($ua, 'baidubrowser') => self::CLIENT_BAIDU,
            str_contains($ua, 'sogoumobilebrowser') => self::CLIENT_SOGOU,
            str_contains($ua, '360se') || str_contains($ua, '360ee') => self::CLIENT_360,

            /*
             * Game consoles
             */
            str_contains($ua, self::CLIENT_PLAYSTATION) => self::CLIENT_PLAYSTATION,
            str_contains($ua, self::CLIENT_XBOX) => self::CLIENT_XBOX,
            str_contains($ua, self::CLIENT_NINTENDO) => self::CLIENT_NINTENDO,

            /*
             * Chrome
             *
             * IMPORTANT:
             * Chrome UA contains Safari/, so Chrome MUST be checked
             * before Safari.
             */
            str_contains($ua, 'chrome/') || str_contains($ua, 'crios/') => self::CLIENT_CHROME,

            /*
             * Safari
             *
             * Reached only when no Chromium browser matched.
             */
            str_contains($ua, 'safari/') => self::CLIENT_SAFARI,

            default => $this->userAgent->isBot() ? self::CLIENT_BOT : self::CLIENT_UNKNOWN,
        };
        return $this->browserName = $browser;
    }

    /**
     * Check whether the client is an AI (e.g., GPTBot, ChatGPT, etc.).
     * @return bool true if the client is an AI, false otherwise.
     */
    public function isAI(): bool
    {
        return $this->isAI ??= in_array($this->getBrowserName(), self::AI, true);
    }

    /**
     * Check whether the client is a bot (e.g., Googlebot, Bingbot, etc.).
     * @return bool true if the client is a bot, false otherwise.
     */
    public function isBot(): bool
    {
        return $this->isBot ??= in_array($this->getBrowserName(), self::BOTS, true);
    }

    /**
     * Check whether the client is an HTTP client (e.g., curl, wget, Postman, etc.).
     * @return bool true if the client is an HTTP client, false otherwise.
     */
    public function isHttpClient(): bool
    {
        return $this->isHttpClient ??= in_array($this->getBrowserName(), self::HTTP_CLIENTS, true);
    }

    /**
     * Check whether the client is an automation tool (e.g., Puppeteer, Selenium, etc.).
     * @return bool true if the client is an automation tool, false otherwise.
     */
    public function isAutomation(): bool
    {
        return $this->isAutomation ??= in_array($this->getBrowserName(), self::AUTOMATION, true);
    }

    /**
     * Check whether the client is a security client (e.g., antivirus, firewall, etc.).
     * @return bool true if the client is a security client, false otherwise.
     */
    public function isSecurityClient(): bool
    {
        return $this->isSecurityClient ??= in_array($this->getBrowserName(), self::SECURITY, true);
    }

    /**
     * Check whether the client is a standard web browser (e.g., Chrome, Firefox, Safari, etc.).
     * @return bool true if the client is a standard web browser, false otherwise.
     */
    public function isBrowser() : bool
    {
        return $this->isBrowser ??= $this->getBrowserName() !== self::CLIENT_UNKNOWN
            && !$this->isAI()
            && !$this->isBot()
            && !$this->isHttpClient()
            && !$this->isAutomation()
            && !$this->isSecurityClient();
    }

    public const AI = [
        self::CLIENT_GPTBOT,
        self::CLIENT_CHATGPT,
        self::CLIENT_OAI_SEARCHBOT,
        self::CLIENT_CLAUDEBOT,
        self::CLIENT_PERPLEXITYBOT,
        self::CLIENT_GEMINIBOT,
        self::CLIENT_BYTESPIDER,
        self::CLIENT_META_EXTERNAL_AGENT,
        self::CLIENT_AMAZONBOT,
        self::CLIENT_COHERE,
    ];

    public const BOTS = [
        self::CLIENT_GOOGLEBOT,
        self::CLIENT_BINGBOT,
        self::CLIENT_DUCKDUCKBOT,
        self::CLIENT_YANDEXBOT,
        self::CLIENT_BAIDUSPIDER,
        self::CLIENT_SOGOU_SPIDER,
        self::CLIENT_EXABOT,
        self::CLIENT_APPLEBOT,
        self::CLIENT_FACEBOOKBOT,
        self::CLIENT_TWITTERBOT,
        self::CLIENT_LINKEDINBOT,
        self::CLIENT_PINTERESTBOT,
        self::CLIENT_SEMRUSHBOT,
        self::CLIENT_AHREFSBOT,
        self::CLIENT_MJ12BOT,
        self::CLIENT_DOTBOT,
        self::CLIENT_PETALBOT,
        // bot
        self::CLIENT_BOT
    ];

    public const HTTP_CLIENTS = [
        self::CLIENT_CURL,
        self::CLIENT_WGET,
        self::CLIENT_HTTPIE,
        self::CLIENT_POSTMAN,
        self::CLIENT_PYTHON,
        self::CLIENT_REQUESTS,
        self::CLIENT_HTTPX,
        self::CLIENT_AIOHTTP,
        self::CLIENT_PHP,
        self::CLIENT_GUZZLE,
        self::CLIENT_GO,
        self::CLIENT_RUST,
        self::CLIENT_REQWEST,
        self::CLIENT_JAVA,
        self::CLIENT_OKHTTP,
        self::CLIENT_APACHE_HTTPCLIENT,
        self::CLIENT_NODE,
        self::CLIENT_NODE_FETCH,
        self::CLIENT_AXIOS,
        self::CLIENT_UNDICI,
        self::CLIENT_LIBWWW_PERL,
    ];

    public const AUTOMATION = [
        self::CLIENT_PUPPETEER,
        self::CLIENT_PLAYWRIGHT,
        self::CLIENT_SELENIUM,
        self::CLIENT_WEBDRIVER,
        self::CLIENT_HEADLESS_CHROME,
        self::CLIENT_PHANTOMJS,
    ];

    public const SECURITY = [
        self::CLIENT_COMODO,
        self::CLIENT_AVAST,
        self::CLIENT_ACUNETIX
    ];

    // Chromium / Desktop Browsers
    public const CLIENT_CHROME = 'chrome';

    public const CLIENT_CHROMIUM = 'chromium';

    public const CLIENT_EDGE = 'edge';

    public const CLIENT_OPERA = 'opera';

    public const CLIENT_BRAVE = 'brave';

    public const CLIENT_VIVALDI = 'vivaldi';

    public const CLIENT_YANDEX = 'yandex';

    // Firefox Family
    public const CLIENT_FIREFOX = 'firefox';

    public const CLIENT_SEAMONKEY = 'seamonkey';

    public const CLIENT_PALEMOON = 'palemoon';

    public const CLIENT_WATERFOX = 'waterfox';

    // Apple
    public const CLIENT_SAFARI = 'safari';

    // Microsoft
    public const CLIENT_IE = 'ie';

    // Android / Mobile Browsers
    public const CLIENT_ANDROID = 'android';

    public const CLIENT_ANDROID_WEBVIEW = 'android_webview';

    public const CLIENT_SAMSUNG = 'samsung';

    public const CLIENT_UC = 'uc';

    public const CLIENT_OPERA_MINI = 'opera_mini';

    public const CLIENT_OPERA_MOBILE = 'opera_mobile';

    public const CLIENT_YANDEX_MOBILE = 'yandex_mobile';

    // Other Browsers
    public const CLIENT_MIDORI = 'midori';

    public const CLIENT_EPIPHANY = 'epiphany';

    public const CLIENT_GNOME_WEB = 'gnome_web';

    public const CLIENT_KONQUEROR = 'konqueror';

    public const CLIENT_MAXTHON = 'maxthon';

    public const CLIENT_AVANT = 'avant';

    public const CLIENT_SLIMJET = 'slimjet';

    public const CLIENT_COMODO = 'comodo';

    public const CLIENT_AVAST = 'avast';

    public const CLIENT_PUFFIN = 'puffin';

    public const CLIENT_DOLPHIN = 'dolphin';

    public const CLIENT_QQ = 'qq';

    public const CLIENT_BAIDU = 'baidu';

    public const CLIENT_SOGOU = 'sogou';

    public const CLIENT_360 = '360';

    // Console / Embedded Browsers
    public const CLIENT_PLAYSTATION = 'playstation';

    public const CLIENT_XBOX = 'xbox';

    public const CLIENT_NINTENDO = 'nintendo';

    // Bots / Crawlers
    public const CLIENT_GOOGLEBOT = 'googlebot';

    public const CLIENT_BINGBOT = 'bingbot';

    public const CLIENT_DUCKDUCKBOT = 'duckduckbot';

    public const CLIENT_YANDEXBOT = 'yandexbot';

    public const CLIENT_BAIDUSPIDER = 'baiduspider';

    public const CLIENT_SOGOU_SPIDER = 'sogouspider';

    public const CLIENT_EXABOT = 'exabot';

    public const CLIENT_APPLEBOT = 'applebot';

    public const CLIENT_FACEBOOKBOT = 'facebookbot';

    public const CLIENT_TWITTERBOT = 'twitterbot';

    public const CLIENT_LINKEDINBOT = 'linkedinbot';

    public const CLIENT_PINTERESTBOT = 'pinterestbot';

    public const CLIENT_SEMRUSHBOT = 'semrushbot';

    public const CLIENT_AHREFSBOT = 'ahrefsbot';

    public const CLIENT_MJ12BOT = 'mj12bot';

    public const CLIENT_DOTBOT = 'dotbot';

    public const CLIENT_PETALBOT = 'petalbot';

    // AI / LLM Crawlers
    public const CLIENT_GPTBOT = 'gptbot';

    public const CLIENT_CHATGPT = 'chatgpt';

    public const CLIENT_OAI_SEARCHBOT = 'oai_searchbot';

    public const CLIENT_CLAUDEBOT = 'claudebot';

    public const CLIENT_PERPLEXITYBOT = 'perplexitybot';

    public const CLIENT_GEMINIBOT = 'gemini';

    public const CLIENT_BYTESPIDER = 'bytespider';

    public const CLIENT_META_EXTERNAL_AGENT = 'meta_external_agent';

    public const CLIENT_AMAZONBOT = 'amazonbot';

    public const CLIENT_ACUNETIX = 'acunetix';

    public const CLIENT_COHERE = 'cohere';

    // HTTP Clients
    public const CLIENT_CURL = 'curl';

    public const CLIENT_WGET = 'wget';

    public const CLIENT_HTTPIE = 'httpie';

    public const CLIENT_POSTMAN = 'postman';

    public const CLIENT_PYTHON = 'python';

    public const CLIENT_REQUESTS = 'requests';

    public const CLIENT_HTTPX = 'httpx';

    public const CLIENT_AIOHTTP = 'aiohttp';

    public const CLIENT_PHP = 'php';

    public const CLIENT_GUZZLE = 'guzzle';

    public const CLIENT_GO = 'go';

    public const CLIENT_RUST = 'rust';

    public const CLIENT_REQWEST = 'reqwest';

    public const CLIENT_JAVA = 'java';

    public const CLIENT_OKHTTP = 'okhttp';

    public const CLIENT_APACHE_HTTPCLIENT = 'apache_httpclient';

    public const CLIENT_NODE = 'node';

    public const CLIENT_NODE_FETCH = 'node_fetch';

    public const CLIENT_AXIOS = 'axios';

    public const CLIENT_UNDICI = 'undici';

    public const CLIENT_LIBWWW_PERL = 'libwww_perl';

    // Automation / Headless
    public const CLIENT_PUPPETEER = 'puppeteer';

    public const CLIENT_PLAYWRIGHT = 'playwright';

    public const CLIENT_SELENIUM = 'selenium';

    public const CLIENT_WEBDRIVER = 'webdriver';

    public const CLIENT_HEADLESS_CHROME = 'headless_chrome';

    public const CLIENT_PHANTOMJS = 'phantomjs';

    // Miscellaneous
    public const CLIENT_BOT = '(bot)';

    public const CLIENT_UNKNOWN = 'unknown';
}
