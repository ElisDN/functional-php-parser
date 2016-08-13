<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

function normalizeUrl($url) {
    return 'http://yiiframework.ru/forum/'. ltrim($url, './');
}

function getHtml($url) {
    return file_get_contents(normalizeUrl($url));
}

function clearUrl($url) {
    return preg_replace('#\&sid=.{32}#s', '', $url);
}

function getForumMaxPageNumber($forumUrl) {
    echo 'Max page number for ' . clearUrl($forumUrl) . PHP_EOL;
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

function getForumPages($forumUrl) {
    echo 'Forum pages for ' . clearUrl($forumUrl) . PHP_EOL;
    return array_map(function ($number) use ($forumUrl) {
        return $forumUrl . ($number > 1 ? '&start=' . (25 * ($number - 1)) : '');
    }, range(1, getForumMaxPageNumber($forumUrl)));
}

function getForumPageTopics($forumPageUrl) {
    echo 'Forum page topics for ' . clearUrl($forumPageUrl) . PHP_EOL;
    $html = getHtml($forumPageUrl);
    return (new Crawler($html))
        ->filter('ul.topiclist.topics li dl')
        ->each(function (Crawler $topic) {
            $link = $topic->filter('div.list-inner a.topictitle');
            return [
                'title' => $link->html(),
                'url' => $link->attr('href'),
                'count' => intval($topic->filter('dd.posts')->text()) + 1,
            ];
        });
}

$forumUrl = './viewforum.php?f=28';

$forumPages = getForumPages($forumUrl);

echo clearUrl(print_r(getForumPageTopics($forumPages[0]), true));

echo PHP_EOL;