<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

function normalizeUrl($url) {
    return 'http://yiiframework.ru/forum/'. ltrim($url, './');
}

function getHtml($url) {
    return file_get_contents(normalizeUrl($url));
}

$forumUrl = './viewforum.php?f=28';

function getForumMaxPageNumber($forumUrl) {
    $html = getHtml($forumUrl);
    $crawler = new Crawler($html);
    return max(
        reset($crawler
            ->filter('div.action-bar.top .pagination li:nth-last-of-type(2)')
            ->each(function (Crawler $link) {
                return intval($link->text());
            })),
        1
    );
}

echo getForumMaxPageNumber($forumUrl);

echo PHP_EOL;