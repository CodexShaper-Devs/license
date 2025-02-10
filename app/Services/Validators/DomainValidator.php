<?php

namespace App\Services\Validators;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class DomainValidator
{
    private const CURRENT_TIME = '2025-02-10 06:33:25';
    private const CURRENT_USER = 'maab16';

    /**
     * List of valid top-level domains
     * This is a small subset, you might want to use a more complete list
     */
    private array $validTlds = [
        'com', 'net', 'org', 'edu', 'gov', 'mil', 'int',
        'info', 'biz', 'name', 'pro', 'museum', 'coop',
        'aero', 'xxx', 'idv', 'mobi', 'asia', 'tel', 'pub',
        'dev', 'app', 'io', 'co', 'me', 'tv', 'us', 'uk',
        'eu', 'de', 'fr', 'es', 'it', 'nl', 'ru', 'cn',
        'jp', 'br', 'au', 'in', 'ca'
    ];

    /**
     * List of known local domain suffixes
     */
    private array $localDomainSuffixes = [
        '.local',
        '.localhost',
        '.test',
        '.example',
        '.invalid',
        '.dev.local',
        '.staging.local',
        '.development'
    ];

    public function isValidDomain(string $domain): bool
    {
        try {
            // Remove any whitespace
            $domain = trim($domain);

            // Basic format validation
            if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $domain)) {
                Log::warning('Invalid domain format', [
                    'domain' => $domain,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                return false;
            }

            // Check domain length
            if (strlen($domain) > 253) {
                Log::warning('Domain too long', [
                    'domain' => $domain,
                    'length' => strlen($domain),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                return false;
            }

            // Split domain into parts
            $parts = explode('.', $domain);

            // Check each part
            foreach ($parts as $part) {
                if (strlen($part) > 63) {
                    Log::warning('Domain label too long', [
                        'domain' => $domain,
                        'label' => $part,
                        'length' => strlen($part),
                        'timestamp' => self::CURRENT_TIME,
                        'user' => self::CURRENT_USER
                    ]);
                    return false;
                }
            }

            // Get the TLD (last part)
            $tld = end($parts);

            // Validate TLD if it's not a local domain
            if (!$this->isLocalDomain($domain) && !$this->isValidTld($tld)) {
                Log::warning('Invalid TLD', [
                    'domain' => $domain,
                    'tld' => $tld,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                return false;
            }

            Log::info('Domain validation successful', [
                'domain' => $domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Domain validation error', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            return false;
        }
    }

    public function isLocalDomain(string $domain): bool
    {
        try {
            $domain = strtolower($domain);

            // Check if domain is an IP address
            if (filter_var($domain, FILTER_VALIDATE_IP)) {
                return $this->isLocalIp($domain);
            }

            // Check known local domain suffixes
            foreach ($this->localDomainSuffixes as $suffix) {
                if (str_ends_with($domain, $suffix)) {
                    return true;
                }
            }

            // Check if domain resolves to a local IP
            $ips = @dns_get_record($domain, DNS_A | DNS_AAAA);
            if ($ips) {
                foreach ($ips as $record) {
                    $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                    if ($ip && $this->isLocalIp($ip)) {
                        return true;
                    }
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Local domain check failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            return false;
        }
    }

    public function isValidTld(string $tld): bool
    {
        return in_array(strtolower($tld), $this->validTlds);
    }

    private function isLocalIp(string $ip): bool
    {
        // Check for loopback addresses
        if (str_starts_with($ip, '127.') || $ip === '::1') {
            return true;
        }

        // Check private IPv4 ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $privateRanges = [
                ['10.0.0.0', '10.255.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.168.0.0', '192.168.255.255']
            ];

            $longIp = ip2long($ip);
            foreach ($privateRanges as $range) {
                if ($longIp >= ip2long($range[0]) && $longIp <= ip2long($range[1])) {
                    return true;
                }
            }
        }

        // Check private IPv6 ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // fc00::/7 - Unique Local Addresses
            if (str_starts_with(strtolower($ip), 'fc') || str_starts_with(strtolower($ip), 'fd')) {
                return true;
            }
        }

        return false;
    }

    public function getDomainInfo(string $domain): array
    {
        try {
            $domain = strtolower(trim($domain));
            $parts = explode('.', $domain);
            $tld = end($parts);
            
            // Handle special cases like co.uk
            $sld = prev($parts) ?: '';
            $isSpecialTld = in_array("$sld.$tld", ['co.uk', 'com.au', 'co.nz']);
            
            return [
                'full_domain' => $domain,
                'subdomain' => $isSpecialTld ? 
                    implode('.', array_slice($parts, 0, -3)) : 
                    implode('.', array_slice($parts, 0, -2)),
                'domain' => $isSpecialTld ? 
                    implode('.', array_slice($parts, -3)) : 
                    implode('.', array_slice($parts, -2)),
                'tld' => $isSpecialTld ? "$sld.$tld" : $tld,
                'is_local' => $this->isLocalDomain($domain),
                'is_valid' => $this->isValidDomain($domain),
                'timestamp' => self::CURRENT_TIME,
                'checked_by' => self::CURRENT_USER
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get domain info', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw new RuntimeException('Failed to get domain info: ' . $e->getMessage());
        }
    }

    public function addCustomTld(string $tld): void
    {
        $tld = strtolower(trim($tld));
        if (!in_array($tld, $this->validTlds)) {
            $this->validTlds[] = $tld;
            Log::info('Custom TLD added', [
                'tld' => $tld,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
        }
    }

    public function addLocalDomainSuffix(string $suffix): void
    {
        $suffix = strtolower(trim($suffix));
        if (!in_array($suffix, $this->localDomainSuffixes)) {
            $this->localDomainSuffixes[] = $suffix;
            Log::info('Local domain suffix added', [
                'suffix' => $suffix,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
        }
    }
}