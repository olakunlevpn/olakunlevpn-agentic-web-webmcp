<?php

declare(strict_types=1);

namespace Olakunlevpn\AgenticWeb\Cron;

use Olakunlevpn\AgenticWeb\Service\ArdGenerator;
use Olakunlevpn\AgenticWeb\Service\LlmsGenerator as GeneratorService;

class LlmsGenerator
{
    public static function regenerate(): void
    {
        $options = \XF::options();
        if (empty($options->ol_agentic_web_enable))
        {
            return;
        }

        if (!empty($options->ol_agentic_web_llmstxt_enable))
        {
            /** @var GeneratorService $generator */
            $generator = \XF::service(GeneratorService::class);
            $generator->writeStaticFiles();
        }

        if (!empty($options->ol_agentic_web_ard_enable))
        {
            /** @var ArdGenerator $ardGenerator */
            $ardGenerator = \XF::service(ArdGenerator::class);
            $ardGenerator->writeStaticFiles();
        }
    }
}
