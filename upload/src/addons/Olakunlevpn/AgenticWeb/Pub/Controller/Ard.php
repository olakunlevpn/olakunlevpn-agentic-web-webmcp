<?php

declare(strict_types=1);

namespace Olakunlevpn\AgenticWeb\Pub\Controller;

use Olakunlevpn\AgenticWeb\Service\ArdGenerator;
use XF\Pub\Controller\AbstractController;

class Ard extends AbstractController
{
    public function actionIndex()
    {
        $options = $this->options();
        if (empty($options->ol_agentic_web_enable) || empty($options->ol_agentic_web_ard_enable))
        {
            return $this->notFound();
        }

        /** @var ArdGenerator $generator */
        $generator = $this->service(ArdGenerator::class);
        $json = $generator->generateJson();

        $canonicalUrl = $this->app->router('public')->buildLink('canonical:ard-json');

        $response = $this->app()->response();
        $response->contentType('application/json', 'utf-8');
        $response->header('Cache-Control', 'public, max-age=3600');
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Link', '<' . $canonicalUrl . '>; rel="ard"');
        $response->body($json);
        $response->send($this->request);
        exit;
    }
}
