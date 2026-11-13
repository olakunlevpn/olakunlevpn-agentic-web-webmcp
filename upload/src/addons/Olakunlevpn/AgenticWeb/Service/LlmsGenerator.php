<?php

namespace Olakunlevpn\AgenticWeb\Service;

use XF\App;
use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Finder\NodeFinder;
use XF\Service\AbstractService;

class LlmsGenerator extends AbstractService
{
    public function generateLlmsTxt(): string
    {
        $options = $this->app->options();
        $boardTitle = $options->boardTitle ?: 'Community Forum';
        $boardUrl = rtrim($options->boardUrl, '/');
        
        $customSummary = trim((string)($options->ol_agentic_web_site_summary ?? ''));
        if ($customSummary === '')
        {
            $customSummary = (string)($options->boardDescription ?: 'Welcome to ' . $boardTitle . '. A collaborative community discussing technology, digital tools, and community resources.');
        }

        $lines = [];
        $lines[] = '# ' . $boardTitle;
        $lines[] = '';
        $lines[] = '> ' . $customSummary;
        $lines[] = '';

        $lines[] = '## Core Navigation';
        $lines[] = '- [Forum Home](' . $boardUrl . '/): Main community discussion index';
        $lines[] = '- [Search](' . $boardUrl . '/search/): Search community threads, posts, and topics';
        $lines[] = '- [What\'s New](' . $boardUrl . '/whats-new/): Recent activity, unread discussions, and latest posts';
        $lines[] = '- [Members](' . $boardUrl . '/members/): Registered community members and staff list';
        $lines[] = '- [Full Documentation](' . $boardUrl . '/llms-txt/full): Comprehensive topic and node index (llms-full.txt)';
        $lines[] = '';

        $lines[] = '## Forum Categories & Topics';
        $nodes = $this->getPublicNodes();
        foreach ($nodes AS $node)
        {
            $nodeUrl = $this->app->router('public')->buildLink('canonical:forums', $node);
            $desc = trim((string)$node->description);
            $descText = $desc !== '' ? ': ' . strip_tags($desc) : '';
            $lines[] = '- [' . $node->title . '](' . $nodeUrl . ')' . $descText;
        }
        $lines[] = '';

        if (!empty($options->ol_agentic_web_mcp_enable))
        {
            $lines[] = '## AI Agent & WebMCP Access';
            $lines[] = 'This site provides native WebMCP (Model Context Protocol) API tools for autonomous browser agents and LLMs.';
            $lines[] = '- **WebMCP Tool Discovery Endpoint:** ' . $boardUrl . '/webmcp/';
            $lines[] = '- **Supported Tools:**';
            $lines[] = '  - `searchForum`: Query discussions with keyword and forum filtering';
            $lines[] = '  - `getThread`: Retrieve clean, structured thread messages by ID';
            $lines[] = '  - `getNodeList`: Retrieve full category hierarchy and discussion counts';
            $lines[] = '  - `getRecentDiscussions`: Retrieve active topics and recent replies';
            $lines[] = '';
        }

        if (!empty($options->ol_agentic_web_llms_include_rules))
        {
            $lines[] = '## Community Rules & Guidelines';
            $lines[] = '- Respect other members and engage constructively in all discussions.';
            $lines[] = '- Use the appropriate category when posting questions or resources.';
            $lines[] = '- Avoid spam, automated flooding, or posting unauthorized private data.';
            $lines[] = '- Full terms: ' . $boardUrl . '/help/terms/';
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '*Generated dynamically by [Olakunlevpn] Agentic Web & WebMCP on ' . gmdate('Y-m-d H:i:s') . ' UTC*';

        return implode("\n", $lines);
    }

    public function generateLlmsFullTxt(): string
    {
        $base = $this->generateLlmsTxt();

        $lines = [];
        $lines[] = $base;
        $lines[] = '';
        $lines[] = '## Detailed Forum Structure & Statistics';

        $nodes = $this->getPublicNodes();
        foreach ($nodes AS $node)
        {
            $nodeUrl = $this->app->router('public')->buildLink('canonical:forums', $node);
            $data = $node->Data;
            $threadCount = isset($data['discussion_count']) ? (int)$data['discussion_count'] : 0;
            $messageCount = isset($data['message_count']) ? (int)$data['message_count'] : 0;

            $lines[] = '### ' . $node->title;
            $lines[] = '- **URL:** ' . $nodeUrl;
            $lines[] = '- **Type:** ' . $node->node_type_id;
            if ($threadCount > 0)
            {
                $lines[] = '- **Discussions:** ' . number_format($threadCount);
                $lines[] = '- **Messages:** ' . number_format($messageCount);
            }
            if ($node->description)
            {
                $lines[] = '- **About:** ' . strip_tags($node->description);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function writeStaticFiles(): array
    {
        $rootDir = \XF::getRootDirectory();
        $results = [
            'llms_txt' => false,
            'llms_full_txt' => false
        ];

        $llmsTxt = $this->generateLlmsTxt();
        $llmsFullTxt = $this->generateLlmsFullTxt();

        $file1 = $rootDir . '/llms.txt';
        $file2 = $rootDir . '/llms-full.txt';

        if (is_writable($rootDir) || (file_exists($file1) && is_writable($file1)))
        {
            if (@file_put_contents($file1, $llmsTxt) !== false)
            {
                $results['llms_txt'] = true;
            }
        }

        if (is_writable($rootDir) || (file_exists($file2) && is_writable($file2)))
        {
            if (@file_put_contents($file2, $llmsFullTxt) !== false)
            {
                $results['llms_full_txt'] = true;
            }
        }

        return $results;
    }

    protected function getPublicNodes()
    {
        /** @var NodeFinder $finder */
        $finder = $this->finder(NodeFinder::class);
        $nodes = $finder
            ->where('display_in_list', 1)
            ->order('lft')
            ->fetch();

        return $nodes->filter(function(Node $node)
        {
            return $node->canView();
        });
    }
}
