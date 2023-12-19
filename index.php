<?php

use Crawler\MyCrawler;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use Crwlr\Crawler\Steps\Dom;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
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
                'First Name' => Dom::cssSelector('a')->last()->innerText() ,
                'Last Name' => Dom::cssSelector('a')->first()->innerText(),
                'Mailing Street' =>Dom::cssSelector('.cbUserListCol2')->text(),
                'Mailing City' =>Dom::cssSelector('.cbUserListCol3')->text(),
                'link' => Dom::cssSelector('a')->first()->link(),
        ])->addToResult([
                 'First Name',
                'Last Name',
                'Mailing Street',
                'Mailing City',
            ])
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
            'Assermenté(e) en' => Dom::cssSelector('.cbpp-profile p:nth-child(3) span')->last()->text(),
            'Prestation de serment' => Dom::cssSelector('.cbpp-profile p:nth-child(3) span')->last()->text(),
            'Phone' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(4)')->text(),
            'Mobile' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(4)')->text(),
            'Email' => Dom::cssSelector('.cbpp-profile [class^="cbMailRepl"]')->text(),  //Can't select email
            'Website' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(4) span.a')->text(),  //Can't select Website
            'Mailing Postal Code' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(2)')->text(),
            'Full Name'=>Dom::cssSelector('h2')->text(),
            'Région affiliée'=> 'No Selector',
            'Entity' => 'No Selector',
            'Status Prospect' => 'invalid selector',
            'Numero de Toque' => 'invalid selector',
            'Mailing Country' => 'invalid selector',
        ])

            //refine Mailing Postal Code
            ->refineOutput('Mailing Postal Code', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                // Extract digits from the string using a regular expression
                preg_match_all('/\d+/', $output, $matches);
                $digits = implode('', $matches[0]);

                return $digits;
            })
            //Refine Phone
            ->refineOutput('Phone', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $parts = explode(' - ', $output);
                $result = isset($parts[0]) ? $parts[0] : '';
                $result = str_replace(['Tél.', ':','.', ' '], '', $result);

                $PhoneNumber = $result;

                $phoneUtil = PhoneNumberUtil::getInstance();
                try {
                    $phoneNumberProto = $phoneUtil->parse($PhoneNumber, "FR");
                    $formatedPhoneNumber = $phoneUtil->format($phoneNumberProto,PhoneNumberFormat::INTERNATIONAL);

                    return $formatedPhoneNumber;
                } catch (NumberParseException $e) {

                    return null;
                }
            })
            //Refine Fax
            ->refineOutput('Mobile', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $parts = explode(' - ', $output);
                $result = isset($parts[1]) ? $parts[1] : '';
                $result = str_replace(['Fax', ':','.', ' '], '', $result);

                $PhoneNumber = $result;

                $phoneUtil = PhoneNumberUtil::getInstance();
                try {
                    $phoneNumberProto = $phoneUtil->parse($PhoneNumber, "FR");
                    $formatedPhoneNumber = $phoneUtil->format($phoneNumberProto,PhoneNumberFormat::INTERNATIONAL);

                    return $formatedPhoneNumber;
                } catch (NumberParseException $e) {
                    return null;
                }
            })
            //Date of Swearing in refine
            ->refineOutput('Date of swearing in:',function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }
               $output = str_replace(['Date de prestation de serment : ', ' | '], '', $output);
                return $output;
            })
            //Refine Assermenté(e) en
            ->refineOutput('Assermenté(e) en', function (mixed $output) {
                if (is_array($output)) {
                    return $output;
                }

                $dateParts = explode('/', $output);

                $year = end($dateParts);

                return $year;
            })
            //refine Prestation de serment
            ->refineOutput('Prestation de serment',function (mixed $output){
                if(is_array($output)){
                    return $output;
                }

                $newData = substr($output,strpos($output,':') + 1);

                return $newData;
            })
            //Hard Data
            ->refineOutput(function (array $output) {
                $output['Barreau'] = 'L\'ORDRE';
                $output['country code'] = 'fr';
                $output[ 'Numero de Toque'] = null;
                $output['Mailing Country'] = 'France';
                $output['Région affiliée'] = 'Paris';
                $output['Entity'] = 'LAW-FR';
                $output['Statut Prospect'] = 'À qualifier';

                return $output;
            })



            ->addToResult([
                'Full Name',
                'Assermenté(e) en',
                'Prestation de serment',
                'Mobile',
                'Phone',
                'Email',
                'Website',
                'Mailing Postal Code',
                'Barreau',
                'country code',
                'Numero de Toque',
                'Mailing Country',
                'Région affiliée',
                'Entity',
                'Statut Prospect'
            ])
    )->runAndTraverse();

