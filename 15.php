<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

function reduce(callable $func, array $array, $initial = null) {
    return array_reduce($array, $func, $initial);
}

function normalizeUrl($url) {
    return 'http://yiiframework.ru/forum/'. ltrim($url, './');
}

function getHtml($url) {
    $file = __DIR__ . '/cache/' . md5($url);
    if (file_exists($file)) {
        return file_get_contents($file);
    } else {
        $html = file_get_contents($url);
        file_put_contents($file, $html);
        return $html;
    }
}

function crawler($url) {
    return new Crawler(getHtml(normalizeUrl($url)));
}

function clearUrl($url) {
    return preg_replace('#\&sid=.{32}#s', '', $url);
}

function getForumMaxPageNumber($forumUrl) {
    echo 'Max page num for ' . clearUrl($forumUrl) . PHP_EOL;
    return max(
        reset(crawler($forumUrl)
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
    return crawler($forumPageUrl)
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

$topics =
    reduce('array_merge',
        array_map('getForumPageTopics',
            getForumPages($forumUrl)), []);

echo clearUrl(print_r($topics[0], true));

echo PHP_EOL;