<?php

namespace Olakunlevpn\AgenticWeb\Pub\Controller;

use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Finder\NodeFinder;
use XF\Finder\ThreadFinder;
use XF\Pub\Controller\AbstractController;

class WebMcp extends AbstractController
{
    protected function jsonResponse(array $data, int $statusCode = 200)
    {
        $response = $this->app()->response();
        $response->httpCode($statusCode);
        $response->contentType('application/json', 'utf-8');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->body(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $response->send($this->request);
        exit;
    }

    protected function preDispatchController($action, \XF\Mvc\ParameterBag $params)
    {
        parent::preDispatchController($action, $params);

        $options = $this->options();
        if (empty($options->ol_agentic_web_enable) || empty($options->ol_agentic_web_mcp_enable))
        {
            $this->jsonResponse(['success' => false, 'error' => 'WebMCP is disabled on this community.'], 404);
        }

        // Basic rate limit check per IP
        $rateLimit = (int)($options->ol_agentic_web_rate_limit ?: 30);
        $ip = $this->request->getIp();
        $cacheKey = 'webmcp_rl_' . md5($ip);
        $cache = \XF::app()->cache();

        if ($cache)
        {
            $count = (int)$cache->fetch($cacheKey);
            if ($count >= $rateLimit)
            {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Rate limit exceeded. Maximum ' . $rateLimit . ' requests per minute.'
                ], 429);
            }
            $cache->save($cacheKey, $count + 1, 60);
        }
    }

    public function actionIndex()
    {
        $options = $this->options();
        $boardUrl = rtrim($options->boardUrl, '/');

        $activeTools = $options->ol_agentic_web_tools ?: [];
        $toolList = [];

        if (!empty($activeTools['searchForum']))
        {
            $toolList[] = [
                'name' => 'searchForum',
                'description' => 'Search community discussions and threads by keyword and optional forum ID.',
                'endpoint' => $boardUrl . '/index.php?webmcp/search',
                'method' => 'GET',
                'parameters' => [
                    'q' => ['type' => 'string', 'required' => true, 'description' => 'Search query keywords'],
                    'node_id' => ['type' => 'integer', 'required' => false, 'description' => 'Specific forum node ID to restrict search'],
                    'limit' => ['type' => 'integer', 'required' => false, 'default' => 10, 'description' => 'Number of results to return (max 25)']
                ]
            ];
        }

        if (!empty($activeTools['getThread']))
        {
            $toolList[] = [
                'name' => 'getThread',
                'description' => 'Retrieve structured content, original post, and replies for a specific thread.',
                'endpoint' => $boardUrl . '/index.php?webmcp/thread',
                'method' => 'GET',
                'parameters' => [
                    'thread_id' => ['type' => 'integer', 'required' => true, 'description' => 'Target thread ID'],
                    'page' => ['type' => 'integer', 'required' => false, 'default' => 1, 'description' => 'Page number of replies']
                ]
            ];
        }

        if (!empty($activeTools['getNodeList']))
        {
            $toolList[] = [
                'name' => 'getNodeList',
                'description' => 'Retrieve list of public forum categories, boards, and discussion counts.',
                'endpoint' => $boardUrl . '/index.php?webmcp/nodes',
                'method' => 'GET',
                'parameters' => []
            ];
        }

        if (!empty($activeTools['getRecentDiscussions']))
        {
            $toolList[] = [
                'name' => 'getRecentDiscussions',
                'description' => 'Retrieve latest active forum discussions and recently updated topics.',
                'endpoint' => $boardUrl . '/index.php?webmcp/recent',
                'method' => 'GET',
                'parameters' => [
                    'limit' => ['type' => 'integer', 'required' => false, 'default' => 10, 'description' => 'Number of topics (max 25)']
                ]
            ];
        }

        $this->jsonResponse([
            'protocol' => 'WebMCP/1.0',
            'server' => [
                'name' => $options->boardTitle ?: 'XenForo Community',
                'url' => $boardUrl,
                'version' => '1.0.0',
                'generator' => '[Olakunlevpn] Agentic Web & WebMCP'
            ],
            'tools' => $toolList,
            'timestamp' => time()
        ]);
    }

    public function actionSearch()
    {
        $q = trim((string)$this->filter('q', 'str'));
        if ($q === '')
        {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Search query "q" parameter is required.'
            ], 400);
        }

        $nodeId = $this->filter('node_id', 'uint');
        $limit = min(25, max(1, $this->filter('limit', 'uint') ?: 10));

        /** @var ThreadFinder $finder */
        $finder = $this->finder(ThreadFinder::class);
        $finder
            ->with(['Forum', 'User'])
            ->where('discussion_state', 'visible');

        if ($nodeId)
        {
            $finder->where('node_id', $nodeId);
        }

        $finder->where('title', 'LIKE', $finder->escapeLike($q, '%?%'));
        $finder->order('last_post_date', 'DESC');
        $finder->limit($limit);

        $threads = $finder->fetch();
        $results = [];

        foreach ($threads AS $thread)
        {
            /** @var Thread $thread */
            if (!$thread->canView())
            {
                continue;
            }

            $results[] = [
                'thread_id' => $thread->thread_id,
                'title' => $thread->title,
                'url' => $this->app()->router('public')->buildLink('canonical:threads', $thread),
                'starter' => $thread->username,
                'forum' => $thread->Forum ? $thread->Forum->title : '',
                'post_date' => $thread->post_date,
                'last_post_date' => $thread->last_post_date,
                'reply_count' => $thread->reply_count,
                'view_count' => $thread->view_count
            ];
        }

        $this->jsonResponse([
            'success' => true,
            'query' => $q,
            'total' => count($results),
            'results' => $results
        ]);
    }

    public function actionThread()
    {
        $threadId = $this->filter('thread_id', 'uint');
        if (!$threadId)
        {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Parameter "thread_id" is required.'
            ], 400);
        }

        /** @var Thread|null $thread */
        $thread = $this->em()->find(Thread::class, $threadId, ['Forum', 'FirstPost', 'User']);
        if (!$thread || !$thread->canView())
        {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Thread not found or permission denied.'
            ], 404);
        }

        $firstPostMessage = '';
        if ($thread->FirstPost)
        {
            $firstPostMessage = strip_tags($this->app()->stringFormatter()->stripBbCode($thread->FirstPost->message));
        }

        $postsFinder = $this->finder(\XF\Finder\PostFinder::class)
            ->where('thread_id', $threadId)
            ->where('message_state', 'visible')
            ->where('post_id', '<>', $thread->first_post_id)
            ->order('position', 'ASC')
            ->limit(5);

        $replies = [];
        foreach ($postsFinder->fetch() AS $post)
        {
            /** @var Post $post */
            if (!$post->canView())
            {
                continue;
            }
            $replies[] = [
                'post_id' => $post->post_id,
                'author' => $post->username,
                'date' => $post->post_date,
                'message' => strip_tags($this->app()->stringFormatter()->stripBbCode($post->message))
            ];
        }

        $this->jsonResponse([
            'success' => true,
            'thread_id' => $thread->thread_id,
            'title' => $thread->title,
            'url' => $this->app()->router('public')->buildLink('canonical:threads', $thread),
            'forum' => $thread->Forum ? $thread->Forum->title : '',
            'starter' => $thread->username,
            'post_date' => $thread->post_date,
            'reply_count' => $thread->reply_count,
            'first_post' => $firstPostMessage,
            'sample_replies' => $replies
        ]);
    }

    public function actionNodes()
    {
        /** @var NodeFinder $finder */
        $finder = $this->finder(NodeFinder::class);
        $nodes = $finder
            ->where('display_in_list', 1)
            ->order('lft')
            ->fetch();

        $results = [];
        foreach ($nodes AS $node)
        {
            /** @var Node $node */
            if (!$node->canView())
            {
                continue;
            }

            $data = $node->Data;
            $results[] = [
                'node_id' => $node->node_id,
                'title' => $node->title,
                'description' => strip_tags((string)$node->description),
                'url' => $this->app()->router('public')->buildLink('canonical:forums', $node),
                'type' => $node->node_type_id,
                'discussion_count' => isset($data['discussion_count']) ? (int)$data['discussion_count'] : 0,
                'message_count' => isset($data['message_count']) ? (int)$data['message_count'] : 0
            ];
        }

        $this->jsonResponse([
            'success' => true,
            'total' => count($results),
            'nodes' => $results
        ]);
    }

    public function actionRecent()
    {
        $limit = min(25, max(1, $this->filter('limit', 'uint') ?: 10));

        /** @var ThreadFinder $finder */
        $finder = $this->finder(ThreadFinder::class);
        $threads = $finder
            ->with(['Forum', 'User'])
            ->where('discussion_state', 'visible')
            ->order('last_post_date', 'DESC')
            ->limit($limit)
            ->fetch();

        $results = [];
        foreach ($threads AS $thread)
        {
            /** @var Thread $thread */
            if (!$thread->canView())
            {
                continue;
            }

            $results[] = [
                'thread_id' => $thread->thread_id,
                'title' => $thread->title,
                'url' => $this->app()->router('public')->buildLink('canonical:threads', $thread),
                'starter' => $thread->username,
                'forum' => $thread->Forum ? $thread->Forum->title : '',
                'post_date' => $thread->post_date,
                'last_post_date' => $thread->last_post_date,
                'reply_count' => $thread->reply_count
            ];
        }

        $this->jsonResponse([
            'success' => true,
            'total' => count($results),
            'recent_discussions' => $results
        ]);
    }
}
