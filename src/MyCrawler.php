<?php

namespace Crawler;

use Crwlr\Crawler\Cache\FileCache;
use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

class MyCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return BotUserAgent::make('petalbot');
    }

    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        $loader = new HttpLoader(
            $userAgent,
            logger: $logger,
            defaultGuzzleClientConfig: [
                'verify' => false,
                'timeout' => 30,
            ],
        );

        $loader
            ->setCache(
                (new FileCache(__DIR__ . './../cache'))
                    ->ttl(31536000)
                    ->useCompression()
            )
            ->retryCachedErrorResponses();

        $loader
            ->robotsTxt()
            ->ignoreWildcardRules();

        $loader
            ->throttle()
            ->waitBetween(new MultipleOf(2.0), new MultipleOf(4.0));

        return $loader;
    }
}
