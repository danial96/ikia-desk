<?php

namespace App\Console\Commands\Bitrix;

use Illuminate\Console\Command;

abstract class BitrixCommand extends Command
{
    protected string $webhook;

    public function __construct()
    {
        parent::__construct();
        $this->webhook = rtrim(env('BITRIX_WEBHOOK', ''), '/') . '/';
    }

    /** Call any Bitrix REST method. Returns decoded array or null on failure. */
    protected function bx(string $method, array $params = []): ?array
    {
        $url = $this->webhook . $method . '.json';
        if ($params) {
            $url .= '?' . $this->buildQuery($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || !$raw) {
            $this->warn("  cURL error: $err");
            return null;
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn('  JSON decode error');
            return null;
        }
        if (!empty($data['error'])) {
            $this->warn("  Bitrix error [{$data['error']}]: " . ($data['error_description'] ?? ''));
            return null;
        }

        return $data;
    }

    /** Paginate through a list method, collecting all records. */
    protected function bxAll(string $method, array $params = [], string $resultKey = 'result'): array
    {
        $all   = [];
        $start = 0;

        do {
            $p        = array_merge($params, ['start' => $start]);
            $response = $this->bx($method, $p);

            if (!$response) break;

            $items = $response[$resultKey] ?? ($response['result'] ?? []);
            // tasks.task.list nests under result.tasks
            if (is_array($items) && isset($items['tasks'])) {
                $items = $items['tasks'];
            }
            if (!is_array($items) || empty($items)) break;

            $all  = array_merge($all, $items);
            $next = $response['next'] ?? null;
            $start = $next;

        } while ($next !== null && $next !== false);

        return $all;
    }

    /** php http_build_query with bracket notation Bitrix expects */
    protected function buildQuery(array $params, string $prefix = ''): string
    {
        $parts = [];
        foreach ($params as $k => $v) {
            $key = $prefix ? "{$prefix}[{$k}]" : $k;
            if (is_array($v)) {
                $parts[] = $this->buildQuery($v, $key);
            } else {
                $parts[] = urlencode($key) . '=' . urlencode((string)$v);
            }
        }
        return implode('&', $parts);
    }
}
