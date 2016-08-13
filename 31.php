<?php

use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

#####################################################

function flat_map(callable $func, array $array) {
    return reduce('array_merge',
        array_map($func, $array), []);
}

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
            $funcResult = $func($item);
            $shareData = serialize($funcResult);
            $sharedId = shmop_open($myPid, 'c', 0644, strlen($shareData));
            shmop_write($sharedId, $shareData, 0);
            exit(0);
        }
    }
    return $result;
}

function parallel_flat_map(callable $func, array $array) {
    return reduce('array_merge',
        parallel_map($func, $array), []);
}

function reduce(callable $func, array $array, $initial = null) {
    return array_reduce($array, $func, $initial);
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

function wrap(callable $func, callable $before, callable $after = null) {
    return function () use ($func, $before, $after) {
        $args = func_get_args();
        call_user_func_array($before, $args);
        $result = call_user_func_array($func, $args);
        if ($after) {
            call_user_func_array($after, $args);
        }
        return $result;
    };
}

#####################################################

function createNormalizeUrl($baseUrl) {
    return function ($url) use ($baseUrl) {
        return $baseUrl . ltrim($url, './');
    };
}

function createGetProxy(array $proxies) {
    return function () use ($proxies) {
        return $proxies ? $proxies[array_rand($proxies, 1)] : [];
    };
}

function newProxy($host, $port, $login, $password) {
    return [
        'host' => $host,
        'port' => $port,
        'login' => $login,
        'password' => $password,
    ];
}

function createGetHtml(callable $getProxy) {
    return function ($url) use ($getProxy) {
        $proxy = $getProxy();
        return file_get_contents($url, false, stream_context_create([
            'http' => array_merge(
                [
                    'user_agent' => 'Mozilla/5.0 (X11; Windows x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
                    'request_fulluri' => true,
                ],
                array_filter([
                    'proxy' => $proxy && $proxy['host'] ? 'tcp://' . $proxy['host'] . ':' . ($proxy['port'] ?: 3128) : false,
                    'header' => $proxy && $proxy['login'] ? 'Proxy-Authorization: Basic ' . base64_encode($proxy['login'] . ':' . $proxy['password']) : false,
                ])
            )
        ]));
    };
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
        return array_map(function ($number) use ($forumUrl, $perPage) {
            return $forumUrl . ($number > 1 ? '&start=' . ($perPage * ($number - 1)) : '');
        }, range(1, $getMaxPageNumber($forumUrl)));
    };
}

function createGetForumPageTopics(callable $crawler) {
    return function ($forumPageUrl) use ($crawler) {
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

function createBatchGetTopicPagesProfiles(callable $getTopicPageProfiles) {
    return function (array $urls) use ($getTopicPageProfiles) {
        return reduce('array_merge',
            parallel_map($getTopicPageProfiles,
                $urls), []);
    };
}

function createParseForumProfiles($squashProfiles, $batchGetTopicPagesProfiles, $getTopicPages, $getForumPageTopics, $getForumPages, $chunkSize) {
    return function ($url) use ($squashProfiles, $batchGetTopicPagesProfiles, $getTopicPages, $getForumPageTopics, $getForumPages, $chunkSize) {
        return
            reduce($squashProfiles,
                flat_map($batchGetTopicPagesProfiles,
                    array_chunk(
                        flat_map($getTopicPages,
                            parallel_flat_map($getForumPageTopics,
                                $getForumPages($url))), $chunkSize)), []);
    };
}

#####################################################

function clearUrl($url) {
    return preg_replace('#\&sid=.{32}#s', '', $url);
};

function formatUsage($memory) {
    return number_format($memory / 1024 / 1024, 2, '.', ' ') . ' Mb';
};

$crawler = createCrawler(
    fileCache(
        createGetHtml(
            createGetProxy([
                newProxy('127.0.0.1', 3128, null, null),
            ])
        ),
        __DIR__ . '/cache'
    ),
    createNormalizeUrl('http://yiiframework.ru/forum/')
);

$getForumPages = wrap(
    createGetForumPages(createGetForumMaxPageNumber($crawler), 25),
    function ($forumUrl) { echo 'Process ' . getmypid() . ': Pages for forum ' . clearUrl($forumUrl) . PHP_EOL; },
    function () { echo 'Process ' . getmypid() . ': Done ' . formatUsage(memory_get_peak_usage() . PHP_EOL); }
);

$getForumPageTopics = wrap(
    createGetForumPageTopics($crawler),
    function ($forumPageUrl) { echo 'Topics for forum page ' . clearUrl($forumPageUrl) . PHP_EOL; }
);

$getTopicPageProfiles = wrap(
    createGetTopicPageProfiles($crawler),
    function ($topicPageUrl) { echo 'Process ' . getmypid() . ': Profiles for topic page ' . clearUrl($topicPageUrl) . PHP_EOL; },
    function () { echo 'Process ' . getmypid() . ': Done ' . formatUsage(memory_get_peak_usage()) . PHP_EOL; }
);

$batchGetTopicPagesProfiles = wrap(
    createBatchGetTopicPagesProfiles($getTopicPageProfiles),
    function ($urls) { echo 'Getting profiles for ' . clearUrl(trim(print_r($urls, true))) . PHP_EOL; }
);

$getTopicPages = createGetTopicPages(20);

$parseForumProfiles = wrap(
    createParseForumProfiles('squashProfiles', $batchGetTopicPagesProfiles, $getTopicPages, $getForumPageTopics, $getForumPages, 10),
    function ($url) { echo 'Start parsing for forum ' . clearUrl($url) . PHP_EOL; },
    function () { echo 'Done ' . formatUsage(memory_get_peak_usage()) . PHP_EOL; }
);

#####################################################

print_r($parseForumProfiles('./viewforum.php?f=34'));