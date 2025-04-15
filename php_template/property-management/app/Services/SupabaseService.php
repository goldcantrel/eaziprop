<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SupabaseService
{
    private $url;
    private $key;
    private $schema;
    private $headers;

    public function __construct()
    {
        $this->url = config('supabase.url');
        $this->key = config('supabase.key');
        $this->schema = config('supabase.schema', 'public');
        $this->headers = [
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
        ];
    }

    /**
     * Execute a query on Supabase
     */
    public function query($table, array $params = [])
    {
        $endpoint = $this->url . '/rest/v1/' . $table;
        
        return Http::withHeaders($this->headers)
            ->get($endpoint, $params)
            ->throw()
            ->json();
    }

    /**
     * Insert records into a table
     */
    public function insert($table, array $data)
    {
        $endpoint = $this->url . '/rest/v1/' . $table;

        return Http::withHeaders(array_merge($this->headers, [
            'Prefer' => 'return=representation'
        ]))
            ->post($endpoint, $data)
            ->throw()
            ->json();
    }

    /**
     * Update records in a table
     */
    public function update($table, array $data, array $match)
    {
        $endpoint = $this->url . '/rest/v1/' . $table;
        $queryString = http_build_query($match);

        return Http::withHeaders(array_merge($this->headers, [
            'Prefer' => 'return=representation'
        ]))
            ->patch($endpoint . '?' . $queryString, $data)
            ->throw()
            ->json();
    }

    /**
     * Delete records from a table
     */
    public function delete($table, array $match)
    {
        $endpoint = $this->url . '/rest/v1/' . $table;
        $queryString = http_build_query($match);

        return Http::withHeaders($this->headers)
            ->delete($endpoint . '?' . $queryString)
            ->throw()
            ->json();
    }

    /**
     * Subscribe to real-time changes
     */
    public function subscribe($channel, $callback)
    {
        $endpoint = str_replace('https://', 'wss://', $this->url) . '/realtime/v1';
        
        $client = new \WebSocket\Client($endpoint);
        $client->send(json_encode([
            'event' => 'phx_join',
            'topic' => 'realtime:' . $channel,
            'payload' => [],
            'ref' => null
        ]));

        while (true) {
            $message = json_decode($client->receive(), true);
            if ($message && isset($message['event']) && $message['event'] === 'INSERT') {
                $callback($message['payload']);
            }
        }
    }

    /**
     * Upload a file to Supabase Storage
     */
    public function uploadFile($bucket, $path, $file)
    {
        $endpoint = $this->url . '/storage/v1/object/' . $bucket . '/' . $path;

        return Http::withHeaders($this->headers)
            ->attach('file', file_get_contents($file), basename($file))
            ->post($endpoint)
            ->throw()
            ->json();
    }

    /**
     * Get a signed URL for file download
     */
    public function getSignedUrl($bucket, $path, $expiresIn = 3600)
    {
        $endpoint = $this->url . '/storage/v1/object/sign/' . $bucket . '/' . $path;

        return Http::withHeaders($this->headers)
            ->post($endpoint, ['expiresIn' => $expiresIn])
            ->throw()
            ->json();
    }

    /**
     * Create database tables based on configuration
     */
    public function createTables()
    {
        $tables = config('supabase.tables', []);
        
        foreach ($tables as $table => $columns) {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->schema}.{$table} (\n";
            $columnDefinitions = [];
            
            foreach ($columns as $column => $type) {
                $columnDefinitions[] = "\"{$column}\" {$type}";
            }
            
            $sql .= implode(",\n", $columnDefinitions);
            $sql .= "\n);";
            
            $this->rawQuery($sql);
        }
    }

    /**
     * Apply RLS policies
     */
    public function applyPolicies()
    {
        $policies = config('supabase.policies', []);
        
        foreach ($policies as $table => $rules) {
            // Enable RLS
            $this->rawQuery("ALTER TABLE {$this->schema}.{$table} ENABLE ROW LEVEL SECURITY;");
            
            foreach ($rules as $operation => $policy) {
                $policyName = "{$table}_{$operation}_policy";
                $this->rawQuery(
                    "CREATE POLICY {$policyName} ON {$this->schema}.{$table} " .
                    "FOR {$operation} TO authenticated USING ({$policy});"
                );
            }
        }
    }

    /**
     * Execute a raw SQL query
     */
    private function rawQuery($sql)
    {
        $endpoint = $this->url . '/rest/v1/rpc/raw_query';
        
        return Http::withHeaders($this->headers)
            ->post($endpoint, ['query' => $sql])
            ->throw()
            ->json();
    }
}
