<?php

declare(strict_types=1);

namespace Olakunlevpn\AgenticWeb\Service;

use XF\Service\AbstractService;

class ArdGenerator extends AbstractService
{
    public function generateManifest(): array
    {
        $options = $this->app->options();
        $boardTitle = (string)($options->boardTitle ?: 'Community Forum');
        $boardUrl = rtrim((string)$options->boardUrl, '/');
        $host = (string)(parse_url($boardUrl, PHP_URL_HOST) ?: 'localhost');

        $rawQueries = (string)($options->ol_agentic_web_ard_queries ?? '');
        $queries = [];
        if (trim($rawQueries) !== '')
        {
            $lines = explode("\n", str_replace("\r", "", $rawQueries));
            foreach ($lines AS $line)
            {
                $trimmed = trim($line);
                if ($trimmed !== '')
                {
                    $queries[] = $trimmed;
                }
            }
        }

        if (count($queries) < 2)
        {
            $queries = [
                'search ' . strtolower($boardTitle) . ' discussions and threads',
                'find latest community tutorials and guides',
                'lookup marketplace listings and active sellers',
                'browse forum category board structure'
            ];
        }

        // Limit to 5 representative queries as per ARD specification
        $queries = array_slice($queries, 0, 5);

        $mcpUrl = $this->app->router('public')->buildLink('canonical:webmcp');

        return [
            '$schema' => 'https://agentic-resource-discovery.org/schemas/v1/manifest.json',
            'entries' => [
                [
                    'identifier' => 'urn:air:' . $host . ':mcp:forum',
                    'displayName' => $boardTitle . ' WebMCP Service',
                    'description' => 'Exposes Model Context Protocol (WebMCP) endpoints to search forum topics, retrieve thread content, inspect category hierarchies, and fetch active community discussions.',
                    'type' => 'mcp',
                    'url' => $mcpUrl,
                    'representativeQueries' => $queries
                ]
            ]
        ];
    }

    public function generateJson(): string
    {
        $manifest = $this->generateManifest();
        return (string)json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function writeStaticFiles(): array
    {
        $rootDir = \XF::getRootDirectory();
        $results = [
            'well_known_ard' => false,
            'root_ard' => false,
            'robots_updated' => false
        ];

        $json = $this->generateJson();

        $wellKnownDir = $rootDir . '/.well-known';
        if (!is_dir($wellKnownDir))
        {
            @mkdir($wellKnownDir, 0755, true);
        }

        $file1 = $wellKnownDir . '/ard.json';
        $file2 = $rootDir . '/ard.json';

        if (is_writable($wellKnownDir) || (file_exists($file1) && is_writable($file1)))
        {
            if (@file_put_contents($file1, $json) !== false)
            {
                $results['well_known_ard'] = true;
            }
        }

        if (is_writable($rootDir) || (file_exists($file2) && is_writable($file2)))
        {
            if (@file_put_contents($file2, $json) !== false)
            {
                $results['root_ard'] = true;
            }
        }

        $results['robots_updated'] = $this->updateRobotsTxt();

        return $results;
    }

    public function updateRobotsTxt(): bool
    {
        $rootDir = \XF::getRootDirectory();
        $robotsPath = $rootDir . '/robots.txt';

        $options = $this->app->options();
        $boardUrl = rtrim((string)$options->boardUrl, '/');
        $agentmapLine = 'Agentmap: ' . $boardUrl . '/.well-known/ard.json';

        if (file_exists($robotsPath) && is_writable($robotsPath))
        {
            $content = (string)file_get_contents($robotsPath);
            if (strpos($content, 'Agentmap:') === false)
            {
                $content = rtrim($content) . "\n\n" . $agentmapLine . "\n";
                return @file_put_contents($robotsPath, $content) !== false;
            }
            return true;
        }

        return false;
    }
}
