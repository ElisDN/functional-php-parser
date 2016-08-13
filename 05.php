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

$html = getHtml($forumUrl);

$crawler = new Crawler($html);