<?php

namespace Olakunlevpn\AgenticWeb\Pub\Controller;

use Olakunlevpn\AgenticWeb\Service\LlmsGenerator;
use XF\Pub\Controller\AbstractController;

class Llms extends AbstractController
{
    public function actionIndex()
    {
        $options = $this->options();
        if (empty($options->ol_agentic_web_enable) || empty($options->ol_agentic_web_llmstxt_enable))
        {
            return $this->notFound();
        }

        /** @var LlmsGenerator $generator */
        $generator = $this->service(LlmsGenerator::class);
        $content = $generator->generateLlmsTxt();

        $response = $this->app()->response();
        $response->contentType('text/markdown', 'utf-8');
        $response->header('Cache-Control', 'public, max-age=3600');
        $response->body($content);
        $response->send($this->request);
        exit;
    }

    public function actionFull()
    {
        $options = $this->options();
        if (empty($options->ol_agentic_web_enable) || empty($options->ol_agentic_web_llmstxt_enable))
        {
            return $this->notFound();
        }

        /** @var LlmsGenerator $generator */
        $generator = $this->service(LlmsGenerator::class);
        $content = $generator->generateLlmsFullTxt();

        $response = $this->app()->response();
        $response->contentType('text/markdown', 'utf-8');
        $response->header('Cache-Control', 'public, max-age=3600');
        $response->body($content);
        $response->send($this->request);
        exit;
    }
}
