<?php

use Crawler\MyCrawler;
use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use function Symfony\Component\String\u;

require_once __DIR__ . '/vendor/autoload.php';

$phoneNumberUtil = PhoneNumberUtil::getInstance();

(new MyCrawler())
    ->setStore(new SimpleCsvFileStore('./store', 'avocats'))
    ->input('https://www.avocats91.com/recherche-par-nom/')
    ->addStep(
        Http::get()
            ->paginate('.art-post .cbUserListPagination')
    )
    ->addStep(
        Html::each('#cbUsersListInner .cbUserListTable [class^="sectiontableentry"]')
            ->extract([
                'First Name' => Dom::cssSelector('a')->last()->innerText(),
                'Last Name' => Dom::cssSelector('a')->first()->innerText(),
                'Mailing Street' => Dom::cssSelector('.cbUserListCol2')->text(),
                'Mailing City' => Dom::cssSelector('.cbUserListCol3')->text(),
                'Barreau URL profile' => Dom::cssSelector('a')->first()->link(),
            ])->addToResult([
                'First Name',
                'Last Name',
                'Mailing Street',
                'Mailing City',
                'Barreau URL profile'
            ])
    )
    ->addStep(
        Http::get()
            ->useInputKey('Barreau URL profile')
    )
    ->addStep(
        Crawler::group()
            ->addStep(
                Html::first('#cbProfileInner .cbpp-profile')
                    ->extract([
                        'Assermenté(e) en' => Dom::cssSelector('.cbpp-profile p:nth-child(3) span')->last()->text(),
                        'Prestation de serment' => Dom::cssSelector('.cbpp-profile p:nth-child(3) span')->last()->text(),
                        'Phone' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(4)')->text(),
                        'Mobile' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(4)')->text(),
                        'Mailing Postal Code' => Dom::cssSelector('.cbpp-profile p:nth-child(6) span:nth-child(2)')->text(),
                        'Full Name' => Dom::cssSelector('h2')->text(),
                        'Région affiliée' => 'Invalid Selector',
                        'Entity' => 'Invalid Selector',
                        'Status Prospect' => 'invalid selector',
                        'Numero de Toque' => 'invalid selector',
                        'Mailing Country' => 'invalid selector',
                        'specialities' => 'invalid selector',
                    ])
                    ->refineOutput('Mailing Postal Code', function (mixed $output) {
                        if (is_array($output)) {
                            return $output;
                        }


                        preg_match_all('/\d+/', $output, $matches);
                        $digits = implode('', $matches[0]);

                        return $digits;
                    })
                    ->refineOutput('Phone', function (mixed $output) use ($phoneNumberUtil) {
                        if (is_array($output)) {
                            return $output;
                        }

                        try {
                            $parts = explode(' - ', $output);
                            $result = isset($parts[0]) ? $parts[0] : '';
                            $result = str_replace(['Tél.', ':', '.', ' '], '', $result);

                            if ($result === '') {
                                return $result;
                            }

                            $phoneNumber = $phoneNumberUtil->parse($result, "FR");

                            return $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);
                        } catch (NumberParseException) {
                            dd('error with phone', $result);
                        }
                    })
                    ->refineOutput('Mobile', function (mixed $output) use ($phoneNumberUtil) {
                        if (is_array($output)) {
                            return $output;
                        }

                        $parts = explode(' - ', $output);
                        $result = isset($parts[1]) ? $parts[1] : '';
                        $result = str_replace(['Fax', ':', '.', ' '], '', $result);

                        try {
                            if ($result === '') {
                                return null;
                            }
                            $phoneNumber = $phoneNumberUtil->parse($result, "FR");

                            return $phoneNumberUtil->format($phoneNumber, PhoneNumberFormat::E164);
                        } catch (NumberParseException) {
                            dd('error with phone', $result);
                        }
                    })
                    ->refineOutput('Date of swearing in:', function (mixed $output) {
                        if (is_array($output)) {
                            return $output;
                        }
                        $output = str_replace(['Date de prestation de serment : ', ' | '], '', $output);
                        return $output;
                    })
                    ->refineOutput('Assermenté(e) en', function (mixed $output) {
                        if (is_array($output)) {
                            return $output;
                        }

                        $dateParts = explode('/', $output);

                        $year = end($dateParts);

                        return $year;
                    })
                    ->refineOutput('Prestation de serment', function (mixed $output) {
                        if (is_array($output)) {
                            return $output;
                        }

                        $newData = substr($output, strpos($output, ':') + 1);

                        return $newData;
                    })
                    ->refineOutput(function (array $output) {
                        $output['Barreau'] = 'Essonne';
                        $output['country code'] = 'fr';
                        $output['Numero de Toque'] = null;
                        $output['Mailing Country'] = 'France';
                        $output['Région affiliée'] = 'Paris';
                        $output['Entity'] = 'LAW-FR';
                        $output['Statut Prospect'] = 'À qualifier';
                        $output['specialities'] = null;

                        return $output;
                    })
                    ->addToResult([
                        'Full Name',
                        'Assermenté(e) en',
                        'Prestation de serment',
                        'Mobile',
                        'Phone',
                        'Mailing Postal Code',
                        'Barreau',
                        'country code',
                        'Numero de Toque',
                        'Mailing Country',
                        'Région affiliée',
                        'Entity',
                        'Statut Prospect',
                        'specialities'
                    ])
            )
            ->addStep(
                Html::root()
                    ->extract([
                        'Email' => 'html',
                        'Website' => 'html'
                    ])
                    ->refineOutput('Email', function (mixed $output) {
                        $matches = u($output)->match('/var addy\d+\s*=\s*\'(.+?)\';/');
                        $variable = u($matches[1])->replaceMatches('/\'\s*\+\s*\'/', '')->toString();

                        return html_entity_decode($variable);
                    })
                    ->refineOutput('Website', function (mixed $output) {
                        $matches = u($output)->match('/var\s+website\s*=\s*\'(.+?)\';/');

                        if (isset($matches[1])) {
                            $variable = u($matches[1])->replaceMatches('/\'\s*\+\s*\'/', '')->toString();
                            return html_entity_decode($variable);
                        } else {
                            return null;
                        }
                    })
                    ->addToResult([
                        'Email',
                        'Website'
                    ])
            )
    )
    ->runAndTraverse();
