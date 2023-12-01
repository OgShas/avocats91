<?php

use Crawler\MyCrawler;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use Crwlr\Crawler\Steps\Dom;
use Symfony\Component\DomCrawler\Crawler;

require_once __DIR__ . '/vendor/autoload.php';
include 'src/MyCrawler.php';

(new MyCrawler())
    ->setStore(new SimpleCsvFileStore('./Store', 'avocats'))
    ->input('https://www.avocats91.com/recherche-par-nom/')
    ->addStep(Http::get()->paginate('.art-post .cbUserListPagination')
    )
    ->addStep(
        Html::each('#cbUsersListInner .cbUserListTable [class^="sectiontableentry"]')
            ->extract([
                'firstName' => Dom::cssSelector('a')->last()->innerText() ,
                'lastname' => Dom::cssSelector('a')->first()->innerText(),
                'link' => Dom::cssSelector('a')->first()->link(),
        ])->addLaterToResult()
    )
    ->addStep(
        Http::get()
            ->keepInputData()
        ->useInputKey('link')
        ->outputKey('Link-Response')
    )
    ->addStep(
        Html::each('#cbProfileInner .cbpp-profile')
            ->useInputKey('Link-Response')
            ->keepInputData()
        ->extract([
            'Date of swearing in:' => Dom::cssSelector('.cbpp-profile p:nth-child(3)')->text(),
            'Contact details' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span')->text(),
            'Email' => Dom::cssSelector('.cbpp-profile p:nth-child(6) br+span>span')->first()->innerText(), //Can't select email
        ])
            ->refineOutput('Date of swearing in:',function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $output = str_replace(['Date de prestation de serment : ', '|'], '', $output);
                return $output;
            })
            ->addToResult([
                'Date of swearing in:',
                'Contact details',
                'Email',
            ])
    )->runAndTraverse();

