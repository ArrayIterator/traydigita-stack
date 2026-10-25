<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Networks;

use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use TrayDigita\WP\Stack\Source\Libs\Helpers\RawParams;
use function preg_match;
use function array_any;

/**
 * This class provides methods to check if a request is from Cloudflare or Cloudflare WARP,
 * retrieve the real IP address of the client, and validate Cloudflare IP addresses.
 */
final class Cloudflare
{

    /**
     * @var string The regex pattern for validating Cloudflare Ray IDs.
     */
    public const CF_RAY_REGEX = '~^[0-9a-fA-F]{16}-[A-Z]{3,4}$~';

    /**
     * @var string The regex pattern for validating Cloudflare WARP IDs.
     */
    public const CF_WAP_REGEX = '~^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$~';

    /**
     * @var string The remote address of the client.
     */
    private static string $remoteAddress;

    /**
     * @var string The client IP address.
     */
    public string $clientIP;

    /**
     * @var bool Whether the request is from Cloudflare.
     */
    private bool $cloudflareRequest;

    /**
     * @var bool Whether the request is from Cloudflare WARP.
     */
    private bool $warp;

    /**
     * @var bool Whether the request IP is a Cloudflare IP.
     */
    private bool $cloudflareRequestIP;

    /**
     * @var Ip The IP address of the client.
     */
    public readonly Ip $ip;

    /**
     * @var string[] The server parameters for Cloudflare requests.
     */
    public const CLOUDFLARE_HEADERS = [
        'HTTP_X_FORWARDED_PROTO',
        'HTTP_X_FORWARDED_FOR',
        self::CLOUDFLARE_CF_RAY_HEADER,
        self::CLOUDFLARE_CLIENT_IP_HEADER,
    ];

    /**
     * @var string The header name for the Cloudflare Ray ID.
     */
    public const CLOUDFLARE_CF_RAY_HEADER = 'HTTP_CF_RAY';

    /**
     * @var string The header name for the Cloudflare client IP.
     */
    public const CLOUDFLARE_CLIENT_IP_HEADER = 'HTTP_CF_CONNECTING_IP';

    /**
     * @var string The header name for the Cloudflare WARP tag ID.
     */
    public const CLOUDFLARE_WARP_TAG_HEADER = 'HTTP_CF_WARP_TAG_ID';

    /**
     * @var string[] The server parameters for Cloudflare WARP requests.
     */
    public const CLOUDFLARE_WARP_HEADERS = [
        self::CLOUDFLARE_WARP_TAG_HEADER,
    ];

    /**
     * @var array<string, mixed> $serverParams The server parameters from the request.
     */
    public readonly array $serverParams;

    /**
     * Cloudflare constructor.
     *
     * @param array<string, mixed>|null $serverParams The server parameters from the request.
     * If null, $_SERVER will be used.
     */
    public function __construct(?array $serverParams = null)
    {
        $this->serverParams = $serverParams ?? RawParams::serverParams();
        $this->ip = new Ip($this->getRealIP());
    }

    /**
     * Create a new instance of the Cloudflare class with the given server parameters.
     *
     * @param array<string, mixed> $serverParams The server parameters from the request.
     * @return Cloudflare A new instance of the Cloudflare class.
     */
    public function withServerParams(array $serverParams): Cloudflare
    {
        return new self($serverParams);
    }

    /**
     * Check if the request is from Cloudflare.
     *
     * @return bool True if the request is from Cloudflare, false otherwise.
     */
    public function isCloudFlareRequest(): bool
    {
        if (isset($this->cloudflareRequest)) {
            return $this->cloudflareRequest;
        }
        $this->cloudflareRequest = false;
        foreach (self::CLOUDFLARE_HEADERS as $item) {
            if (!isset($this->serverParams[$item])) {
                return false;
            }
        }
        return $this->cloudflareRequest = (bool)preg_match(
            self::CF_RAY_REGEX,
            $this->serverParams[self::CLOUDFLARE_CF_RAY_HEADER]
        );
    }

    /**
     * Check if the request is from Cloudflare WARP.
     *
     * @return bool True if the request is from Cloudflare WARP, false otherwise.
     */
    public function isWarp() : bool
    {
        if (isset($this->warp)) {
            return $this->warp;
        }
        $this->warp = false;
        if (!$this->isCloudFlareRequest()) {
            return false;
        }
        foreach (self::CLOUDFLARE_WARP_HEADERS as $item) {
            if (!isset($this->serverParams[$item])) {
                return false;
            }
        }
        $header = $this->serverParams[self::CLOUDFLARE_WARP_TAG_HEADER];
        if (!$header) {
            return false;
        }
        return $this->warp = (bool)preg_match(self::CF_WAP_REGEX, $header);
    }

    /**
     * Get the real IP address of the client, considering Cloudflare headers if present.
     *
     * @return string The real IP address of the client.
     */
    public function getRealIP(): string
    {
        if (!isset($this->clientIP)) {
            $this->clientIP = $this->isCloudFlareRequest()
                ? $this->serverParams[self::CLOUDFLARE_CLIENT_IP_HEADER]
                : $this->remoteAddress();
        }
        return $this->clientIP;
    }

    /**
     * Get the remote address of the client from the server parameters.
     *
     * @return string The remote address of the client.
     */
    public function remoteAddress(): string
    {
        if (isset(self::$remoteAddress)) {
            return self::$remoteAddress;
        }
        $remote_addr = $this->serverParams['REMOTE_ADDR'] ?? null;
        return self::$remoteAddress = $remote_addr !== null
            ? ((string)$remote_addr ?: (Is::cli() ? Ip::DEFAULT_LOCAL_IP : Ip::DEFAULT_EMPTY_IP))
            : (Is::cli() ? Ip::DEFAULT_LOCAL_IP : Ip::DEFAULT_EMPTY_IP);
    }

    /**
     * Check if the request IP is a Cloudflare IP.
     *
     * @return bool True if the request IP is a Cloudflare IP, false otherwise.
     */
    public function isCloudflareRequestIP() : bool
    {
        if (isset($this->cloudflareRequestIP)) {
            return $this->cloudflareRequestIP;
        }
        $this->cloudflareRequestIP = false;
        if (!$this->isCloudFlareRequest()) {
            return false;
        }
        if ($this->ip->isBogon()) {
            return false;
        }
        if ($this->ip->isIpv4()) {
            return $this->cloudflareRequestIP = array_any(
                self::IPV4,
                fn($cidr) => $this->ip->ipInRange($this->ip->address, $cidr)
            );
        }
        if ($this->ip->isIpv6()) {
            return $this->cloudflareRequestIP = array_any(
                self::IPV6,
                fn($cidr) => $this->ip->ipInRange($this->ip->address, $cidr)
            );
        }
        return false;
    }

    /**
     * Check if the given IP address is a Cloudflare IP.
     *
     * @param string $ip The IP address to check.
     * @return bool True if the IP is a Cloudflare IP, false otherwise.
     */
    public static function inRange(string $ip): bool
    {
        $ipObj = new Ip($ip);
        if ($ipObj->isValidIpv4($ip)) {
            if (array_any(self::IPV4, fn($cidr) => $ipObj->ipInRange($ip, $cidr))) {
                return true;
            }
        } elseif ($ipObj->isValidIpv6($ip)) {
            if (array_any(self::IPV6, fn($cidr) => $ipObj->ipInRange($ip, $cidr))) {
                return true;
            }
        }
        return false;
    }

    public const IPV4 = [
        "103.31.4.0/22",
        "141.101.64.0/18",
        "188.114.96.0/20",
        "104.16.0.0/13",
        "104.24.0.0/14",
        "8.29.230.0/23",
        "199.100.12.8/29",
        "149.14.74.112/29",
        "8.27.64.0/24",
        "8.39.212.0/23",
        "8.47.69.0/24",
        "38.104.87.248/29",
        "149.11.203.152/29",
        "199.100.2.96/29",
        "8.19.8.0/24",
        "8.24.244.0/24",
        "8.38.147.0/24",
        "8.31.163.0/24",
        "184.104.203.156/30",
        "141.101.120.0/22",
        "206.249.0.208/29",
        "149.14.192.8/29",
        "210.163.15.168/29",
        "213.229.176.168/29",
        "8.42.172.0/24",
        "45.8.211.0/24",
        "8.43.226.0/24",
        "103.21.244.0/23",
        "149.6.191.232/29",
        "209.133.122.162/31",
        "149.6.142.128/29",
        "38.32.186.176/29",
        "209.66.114.234/31",
        "149.6.131.96/29",
        "38.122.46.216/31",
        "38.104.2.152/29",
        "38.104.105.248/29",
        "8.45.144.0/24",
        "38.104.234.160/29",
        "8.45.151.0/24",
        "103.31.4.0/23",
        "162.158.0.0/15",
        "8.34.146.0/24",
        "38.142.120.80/29",
        "38.140.49.40/29",
        "8.36.216.0/22",
        "8.36.220.0/24",
        "38.122.229.0/29",
        "149.6.24.232/30",
        "212.98.116.40/30",
        "8.17.206.0/23",
        "8.29.228.0/24",
        "149.6.182.72/29",
        "8.48.134.0/24",
        "38.142.2.104/29",
        "38.32.111.40/29",
        "131.0.72.0/22",
        "149.11.120.88/29",
        "125.17.150.244/30",
        "8.28.82.0/24",
        "8.45.102.0/24",
        "38.140.185.232/29",
        "8.44.59.0/24",
        "180.189.36.84/30",
        "212.47.213.184/30",
        "209.51.175.216/30",
        "8.47.12.0/22",
        "8.45.42.0/24",
        "38.122.79.56/29",
        "149.6.55.192/29",
        "38.88.189.12/31",
        "184.104.191.96/30",
        "8.21.13.0/24",
        "149.11.21.216/30",
        "149.11.170.80/29",
        "149.14.44.8/29",
        "38.122.1.72/29",
        "185.162.157.0/29",
        "149.6.174.64/29",
        "8.29.109.0/24",
        "8.6.144.0/23",
        "8.34.69.0/24",
        "84.14.42.200/29",
        "8.39.6.0/24",
        "38.140.36.192/31",
        "38.140.213.168/29",
        "27.111.205.104/29",
        "199.100.2.48/29",
        "149.6.184.8/29",
        "185.122.2.0/23",
        "149.6.30.208/29",
        "38.88.240.184/30",
        "8.40.107.0/24",
        "89.202.71.184/30",
        "149.11.204.216/29",
        "149.14.228.104/29",
        "8.24.87.0/24",
        "8.24.243.0/24",
        "199.100.4.96/29",
        "38.140.30.208/30",
        "207.210.209.232/29",
        "38.32.212.128/29",
        "149.6.71.16/29",
        "216.66.89.228/30",
        "8.45.146.0/23",
        "38.122.74.160/29",
        "173.245.48.0/20",
        "38.104.49.112/29",
        "8.39.202.0/23",
        "188.114.108.0/22",
        "8.41.129.0/24",
        "176.124.223.0/24",
        "8.42.164.0/24",
        "104.16.0.0/12",
        "141.101.96.0/20",
        "8.35.211.0/24",
        "198.58.78.16/29",
        "8.39.127.0/24",
        "8.41.36.0/23",
        "8.6.146.0/24",
        "8.44.0.0/22",
        "149.6.22.48/29",
        "193.136.250.200",
        "8.43.121.0/24",
        "149.6.23.16/29",
        "149.6.12.24/29",
        "8.44.6.0/24",
        "197.234.240.0/22",
        "51.89.132.116/30",
        "38.122.74.152/29",
        "185.176.24.0/23",
        "154.18.0.176/29",
        "89.47.56.0/23",
        "62.96.156.96/29",
        "8.39.204.0/24",
        "102.177.189.0/24",
        "154.18.97.232/29",
        "38.32.176.56/29",
        "8.37.43.0/24",
        "38.140.149.80/29",
        "8.44.58.0/24",
        "149.6.39.208/30",
        "185.212.144.0/22",
        "8.34.201.0/24",
        "38.140.240.40/29",
        "141.101.126.0/24",
        "149.6.68.96/29",
        "8.39.125.0/24",
        "216.66.83.124/31",
        "149.14.232.120/31",
        "8.14.202.0/23",
        "193.82.5.64/29",
        "216.117.96.120/29",
        "206.249.2.96/29",
        "8.45.145.0/24",
        "8.31.2.0/24",
        "198.41.128.0/17",
        "125.19.146.8/30",
        "62.23.197.80/29",
        "8.21.8.0/23",
        "38.140.176.80/29",
        "8.40.28.0/22",
        "38.142.50.152/29",
        "38.140.98.184/29",
        "8.46.118.0/23",
        "154.18.100.48/29",
        "122.187.9.0/29",
        "154.18.6.80/29",
        "125.17.208.92/30",
        "8.46.116.0/24",
        "8.34.200.0/24",
        "45.131.208.0/22",
        "8.30.234.0/24",
        "8.45.100.0/23",
        "154.18.20.80/29",
        "141.101.124.0/23",
        "38.104.174.152/29",
        "38.122.124.96/29",
        "8.47.71.0/24",
        "149.14.135.80/29",
        "8.18.194.0/23",
        "8.39.215.0/24",
        "8.26.180.0/24",
        "8.39.201.0/24",
        "149.6.67.96/29",
        "8.23.139.0/24",
        "149.11.36.40/29",
        "38.104.50.96/31",
        "38.122.74.136/29",
        "8.39.205.0/24",
        "202.147.11.120/29",
        "184.105.39.104/29",
        "188.114.106.0/23",
        "141.101.72.0/23",
        "38.122.1.80/29",
        "185.122.1.0/24",
        "8.14.199.0/24",
        "149.6.154.128/30",
        "8.35.58.0/23",
        "8.35.149.0/24",
        "154.18.33.32/29",
        "8.26.182.0/24",
        "8.27.79.0/24",
        "154.18.24.8/29",
        "141.101.78.0/23",
        "38.104.44.12/31",
        "8.48.132.0/23",
        "8.45.111.0/24",
        "8.38.172.0/24",
        "40.133.36.40/29",
        "8.45.97.0/24",
        "8.43.122.0/23",
        "8.17.205.0/24",
        "8.21.11.0/24",
        "104.254.64.48/29",
        "8.40.140.0/24",
        "201.234.119.184/29",
        "8.21.110.0/23",
        "193.82.17.40/29",
        "149.6.153.216/29",
        "184.105.45.8/30",
        "38.142.64.152/29",
        "149.6.58.192/29",
        "38.122.181.132/30",
        "149.6.150.12/31",
        "103.22.200.0/22",
        "149.6.138.252/30",
        "208.184.122.52/31",
        "38.140.159.72/29",
        "188.114.96.0/21",
        "8.20.122.0/23",
        "8.28.126.0/23",
        "65.205.150.0/24",
        "8.40.26.0/23",
        "38.104.108.208/29",
        "8.21.10.0/24",
        "8.42.161.0/24",
        "202.79.213.130/31",
        "8.14.201.0/24",
        "213.164.8.232/29",
        "8.35.57.0/24",
        "8.18.50.0/24",
        "74.209.240.160/29",
        "38.142.1.64/29",
        "38.104.109.12/31",
        "199.27.128.0/21",
        "149.6.188.40/29",
        "8.46.113.0/24",
        "149.6.145.16/29",
        "185.122.0.0/24",
        "38.142.110.96/29",
        "8.45.43.0/24",
        "8.27.70.0/24",
        "38.88.189.248/29",
        "38.140.136.72/29",
        "38.32.132.16/29",
        "8.41.5.0/24",
        "213.246.233.88/29",
        "38.122.79.80/29",
        "208.184.102.132/30",
        "8.6.112.0/24",
        "8.42.51.0/24",
        "8.34.70.0/24",
        "149.14.74.88/29",
        "119.27.27.96/29",
        "38.140.174.232/29",
        "213.128.134.216/29",
        "154.18.7.192/29",
        "38.88.165.36/30",
        "149.6.137.0/29",
        "8.28.213.0/24",
        "59.145.84.180/30",
        "154.18.98.136/29",
        "149.14.58.104/29",
        "38.104.44.8/30",
        "38.122.41.184/29",
        "38.88.232.224/29",
        "8.29.105.0/24",
        "8.39.18.0/24",
        "8.45.44.0/22",
        "38.88.7.56/29",
        "209.51.175.32/30",
        "38.122.49.104/29",
        "196.207.45.0/24",
        "38.140.148.104/29",
        "38.104.108.48/30",
        "149.14.228.144/29",
        "8.31.161.0/24",
        "216.244.96.168/29",
        "38.142.1.192/29",
        "103.21.246.0/23",
        "38.104.84.252/30",
        "8.43.224.0/23",
        "185.135.44.0/24",
        "8.42.245.0/24",
        "92.8.0.0/15",
        "38.122.42.112/29",
        "202.79.213.134",
        "103.31.6.0/23",
        "38.140.177.64/28",
        "8.34.202.0/24",
        "8.38.148.0/23",
        "8.42.52.0/24",
        "190.93.240.0/20",
        "154.18.20.104/29",
        "77.232.140.0/24",
        "92.243.74.0/23",
        "38.122.78.240/29",
        "213.215.169.8/29",
        "149.6.116.192/29",
        "149.11.0.124/30",
        "58.13.125.192/30",
        "216.66.90.124/30",
        "8.20.253.0/24",
        "8.35.216.0/24",
        "38.142.88.208/29",
        "8.39.126.0/24",
        "38.104.54.232/29",
        "95.57.207.224/28",
        "91.93.64.144/28",
        "8.20.124.0/23",
        "8.37.41.0/24",
        "8.20.126.0/24",
        "38.88.53.56/29",
        "8.48.130.0/23",
        "154.18.32.32/29",
        "38.88.164.216/29",
        "149.6.177.168/29",
        "8.40.111.0/24",
        "8.23.240.0/24",
        "183.177.20.208/29",
        "206.249.0.168/29",
        "1.1.1.0/24",
        "8.41.39.0/24",
        "212.123.224.120/29",
        "8.21.238.0/24",
        "93.114.64.0/23",
        "8.20.127.0/24",
        "176.126.206.0/23",
        "38.32.228.0/29",
        "141.101.80.0/20",
        "8.28.20.0/24",
        "8.34.71.0/24",
        "213.219.247.0/24",
        "88.205.102.244/30",
        "1.0.0.0/24",
        "46.254.92.0/23",
        "108.162.192.0/18",
        "149.14.45.88/29",
        "182.66.237.32/29",
        "8.25.249.0/24",
        "8.20.103.0/24",
        "194.53.53.0/24",
        "38.32.1.120/29",
        "38.104.212.120/29",
        "8.9.231.0/24",
        "149.6.191.136/29",
        "141.101.127.0/24",
        "38.88.214.140/30",
        "8.42.54.0/23",
        "172.64.0.0/13",
        "45.131.4.0/22",
        "149.14.68.24/29",
        "141.101.112.0/21",
        "8.45.108.0/24",
        "8.46.117.0/24",
        "38.140.187.56/29",
        "149.14.102.64/29",
        "8.20.100.0/23",
        "38.140.104.96/29",
        "8.46.114.0/23",
        "84.14.38.96/29",
        "125.17.76.200/29",
        "89.202.33.124/30",
        "208.184.122.48/30",
        "154.18.28.112/29",
        "8.39.206.0/23",
        "8.24.242.0/24",
        "149.11.162.28/30",
        "38.88.56.104/29",
        "38.104.135.16/29",
        "8.47.9.0/24",
        "141.101.64.0/21",
        "38.142.51.248/29",
        "141.101.74.0/23",
        "8.21.239.0/24",
        "149.14.83.56/29",
        "38.104.154.184/29",
        "201.33.221.0/24",
        "82.112.192.248/29",
        "198.24.51.200/29",
        "8.10.148.0/24",
        "38.104.108.176/29",
        "188.114.104.0/23",
        "125.18.29.176/29",
        "149.14.250.240/29",
        "8.31.160.0/24",
        "8.25.96.0/23",
        "8.44.60.0/22",
        "38.32.185.176/29",
        "141.101.76.0/23",
        "149.6.12.96/29",
        "8.45.41.0/24",
        "149.11.72.80/29",
        "8.27.66.0/23",
        "149.6.169.64/29",
        "8.39.214.0/24",
        "8.18.113.0/24",
        "8.41.6.0/23",
        "38.104.83.168/29",
        "149.6.50.72/29",
        "8.27.68.0/23",
        "185.148.104.0/23",
        "38.32.60.88/29",
        "8.18.196.0/24",
        "5.182.84.0/23",
        "202.79.213.129",
        "8.21.12.0/24",
        "149.14.228.136/29",
        "149.14.44.184/29",
        "125.19.36.56/29",
        "210.145.183.200/29",
        "8.14.204.0/24",
        "38.88.224.84/30",
        "202.79.213.132/31",
        "149.14.36.184/29",
        "149.14.10.152/29"
    ];

    public const IPV6 = [
        "2a06:98c0::/29",
        "2c0f:f248::/32",
        "2606:54c0::/28",
        "2001:920:602a:3300::/56",
        "2a06:98c0:1800::/38",
        "2a06:98c0:2000::/36",
        "2a06:98c0:1000::/38",
        "2600:cc0c:c003::/64",
        "2a06:98c4::/30",
        "2a06:98c2::/31",
        "2001:920:6001:2200::/56",
        "2001:868:100:5300::/56",
        "2600:cc01:c003:2::/64",
        "2a06:98c0::/36",
        "2803:f800::/32",
        "2c0f:f248:2000::/35",
        "2a06:98c1::/32",
        "2c0f:f248:8000::/33",
        "2600:cc0a:c003::/64",
        "2a06:98c0:1c00::/38",
        "2a06:98c0:8000::/33",
        "2a09:bac0::/29",
        "2c0f:f248::/36",
        "2a06:98c0:3000::/36",
        "2400:cb00::/32",
        "2405:8100::/32",
        "2405:b500::/32",
        "2600:cc0b:c003::/64",
        "2600:cc06:c003::/64",
        "2a06:98c0:1400::/38",
        "2c0f:f248:1000::/36",
        "2c0f:f248:4000::/34",
        "2606:4700::/32",
        "2a06:98c0:4000::/34"
    ];
}
