<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

#####################################################

function parallel_map(callable $func, array $items) {
    $childPids = [];
    $result = [];
    foreach ($items as $i => $item) {
        $newPid = pcntl_fork();
        if ($newPid == -1) {
            die('Can\'t fork process');
        } elseif ($newPid) {
            $childPids[] = $newPid;
            if ($i == count($items) - 1) {
                foreach ($childPids as $childPid) {
                    pcntl_waitpid($childPid, $status);
                    $sharedId = shmop_open($childPid, 'a', 0, 0);
                    $shareData = shmop_read($sharedId, 0, shmop_size($sharedId));
                    $result[] = unserialize($shareData);
                    shmop_delete($sharedId);
                    shmop_close($sharedId);
                }
            }
        } else {
            $myPid = getmypid();
            echo 'Start ' . $myPid . PHP_EOL;
            $funcResult = $func($item);
            $shareData = serialize($funcResult);
            $sharedId = shmop_open($myPid, 'c', 0644, strlen($shareData));
            shmop_write($sharedId, $shareData, 0);
            echo 'Done ' . $myPid . ' ' . formatUsage(memory_get_peak_usage()) . PHP_EOL;
            exit(0);
        }
    }
    return $result;
}

function reduce(callable $func, array $array, $initial = null) {
    return array_reduce($array, $func, $initial);
}

function clearUrl($url) {
    return preg_replace('#\&sid=.{32}#s', '', $url);
}

function formatUsage($memory) {
    return number_format($memory / 1024 / 1024, 2, '.', ' ') . ' Mb';
}

function fileCache(callable $func, $path) {
    return function() use ($func, $path) {
        $args = func_get_args();
        $file = $path . '/' . md5(serialize($args));
        if (file_exists($file)) {
            return unserialize(file_get_contents($file));
        } else {
            $value = call_user_func_array($func, $args);
            file_put_contents($file, serialize($value));
            return $value;
        }
    };
}

#####################################################

function createNormalizeUrl($baseUrl) {
    return function ($url) use ($baseUrl) {
        return $baseUrl . ltrim($url, './');
    };
}

function getHtml($url) {
    return file_get_contents($url);
}

function createCrawler(callable $getContent, callable $normalizeUrl) {
    return function ($url) use ($getContent, $normalizeUrl) {
        return new Crawler($getContent($normalizeUrl($url)));
    };
}

function createGetForumMaxPageNumber(callable $crawler) {
    return function ($url) use ($crawler) {
        return max(
            reset($crawler($url)
                ->filter('div.action-bar.top .pagination li:nth-last-of-type(2)')
                ->each(function (Crawler $link) {
                    return intval($link->text());
                })),
            1
        );
    };
}

function createGetForumPages(callable $getMaxPageNumber, $perPage) {
    return function ($forumUrl) use ($getMaxPageNumber, $perPage) {
        echo 'Forum pages for ' . clearUrl($forumUrl) . PHP_EOL;
        return array_map(function ($number) use ($forumUrl, $perPage) {
            return $forumUrl . ($number > 1 ? '&start=' . ($perPage * ($number - 1)) : '');
        }, range(1, $getMaxPageNumber($forumUrl)));
    };
}

function createGetForumPageTopics(callable $crawler) {
    return function ($forumPageUrl) use ($crawler) {
        echo 'Forum page topics for ' . clearUrl($forumPageUrl) . PHP_EOL;
        return $crawler($forumPageUrl)
            ->filter('ul.topiclist.topics li dl')
            ->each(function (Crawler $topic) {
                $link = $topic->filter('div.list-inner a.topictitle');
                return [
                    'title' => $link->html(),
                    'url' => $link->attr('href'),
                    'count' => intval($topic->filter('dd.posts')->text()) + 1,
                ];
            });
    };
}

function createGetTopicPages($perPage) {
    return function ($topic) use ($perPage) {
        return array_map(function ($number) use ($topic, $perPage) {
            return $topic['url'] . ($number > 1 ? '&start=' . ($perPage * ($number - 1)) : '');
        }, range(1, intval(($topic['count'] - 1) / $perPage) + 1));
    };
}

function createGetTopicPageProfiles(callable $crawler) {
    return function ($topicPageUrl) use ($crawler) {
        echo 'Topic profiles for ' . clearUrl($topicPageUrl) . PHP_EOL;
        return $crawler($topicPageUrl)
            ->filter('dl.postprofile')
            ->each(function (Crawler $profile) {
                return [
                    'username' => $profile->filter('dt a.username, dt a.username-coloured')->text(),
                    'total' => $profile->filter('dd.profile-posts a')->text(),
                ];
            });
    };
}

function squashProfiles(array $total, array $current) {
    $existsFilter = function ($item) use ($current) {
        return $item['username'] === $current['username'];
    };
    $notExistsFilter = function ($item) use ($current) {
        return $item['username'] !== $current['username'];
    };
    $increase = function ($exists) {
        return [
            'username' => $exists['username'],
            'count' => $exists['count'] + 1,
            'total' => $exists['total'],
        ];
    };
    $create = function ($current) {
        return [
            'username' => $current['username'],
            'count' => 1,
            'total' => $current['total'],
        ];
    };
    if ($exists = reset(array_filter($total, $existsFilter))) {
        return array_merge(array_filter($total, $notExistsFilter), [$increase($exists)]);
    } else {
        return array_merge($total, [$create($current)]);
    }
}

function createParseForumProfiles($squashProfiles, $getTopicPageProfiles, $getTopicPages, $getForumPageTopics, $getForumPages) {
    return function ($forumUrl) use ($squashProfiles, $getTopicPageProfiles, $getTopicPages, $getForumPageTopics, $getForumPages) {
        return
            reduce($squashProfiles,
                reduce('array_merge',
                    array_map($getTopicPageProfiles,
                        reduce('array_merge',
                            array_map($getTopicPages,
                                reduce('array_merge',
                                    parallel_map($getForumPageTopics,
                                        $getForumPages($forumUrl)), [])), [])), []), []);
    };
}

#####################################################

$crawler = createCrawler(
    fileCache('getHtml', __DIR__ . '/cache'),
    createNormalizeUrl('http://yiiframework.ru/forum/')
);
$getForumMaxPageNumber = createGetForumMaxPageNumber($crawler);
$getForumPages = createGetForumPages($getForumMaxPageNumber, 25);
$getForumPageTopics = createGetForumPageTopics($crawler);
$getTopicPages = createGetTopicPages(20);
$getTopicPageProfiles = createGetTopicPageProfiles($crawler);

$parse = createParseForumProfiles('squashProfiles', $getTopicPageProfiles, $getTopicPages, $getForumPageTopics, $getForumPages);

#####################################################

$profiles = $parse('./viewforum.php?f=34');

echo 'Done ' . formatUsage(memory_get_peak_usage()) . PHP_EOL;

echo clearUrl(print_r($profiles, true));

echo PHP_EOL;