<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\Slim();

$domFactory = function($html) {
    return new Symfony\Component\DomCrawler\Crawler($html);
};

$msg = function($channel, $message) use ($domFactory) {
    $ch = curl_init('http://news.php.net/' . $channel . '/' . $message);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_exec($ch);

    /** @var Crawler $crawler */
    $crawler = $domFactory(str_replace('&nbsp;', ' ', $res));

    $fromAndDate = $crawler->filter('tr.vcard td.headervalue');
    $refsAndGroups = $crawler->filter('tr')->slice(3, 1)->filter('td.headervalue');

    $getHref = function(Crawler $a) {return $a->attr('href');};

    $refs = $refsAndGroups->first()->filter('a')->each($getHref);
    $groups = $refsAndGroups->last()->filter('a')->each($getHref);
    list($prev, $next) = $crawler->filter('th.nav a')->each($getHref);

    return [
        '_links' => [
            'self' => ['href' => '/' . $channel . '/' . $message],
            'prev' => ['href' => $prev],
            'next' => ['href' => $next],
            'refs' => array_map(function($ref) {return ['href' => $ref];}, $refs),
            'groups' => array_map(function($group) {return ['href' => $group];}, $groups),
        ],
        'from' => trim($fromAndDate->first()->text()),
        'date' => trim($fromAndDate->last()->text()),
        'subject' => trim($crawler->filter('td[colspan=3]')->text()),
        'body' => trim($crawler->filter('pre')->first()->text())
    ];
};

$app->get('/:channel/:message', function($channel, $message) use ($app, $msg) {
    if ($app->request->params('view')) {
        $app->response->header('Content-type', 'text/javascript');
    } else {
        $app->response->header('Content-type', 'application/json+hal');
    }

    $app->response->body(json_encode($msg($channel, $message), JSON_PRETTY_PRINT));
});

$app->get('/:channel', function($channel) use ($app) {
    $app->response->status(501);
});

$app->run();
