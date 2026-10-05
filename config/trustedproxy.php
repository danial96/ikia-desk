<?php

/*
 * Cloudflare edge addresses (https://www.cloudflare.com/ips-v4 and /ips-v6, fetched 2026-10-05). When the site is
 * behind Cloudflare the TCP peer is one of these, and the visitor is in X-Forwarded-For; trusting ONLY these ranges
 * means a visitor can not fake their IP (which the login throttle, sessions and logs rely on). The list changes
 * very rarely; refresh it from those two URLs if Cloudflare announces a change.
 */
return [
    'proxies' => [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
    ],
];
