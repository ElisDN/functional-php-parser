<?php

function normalizeUrl($url) {
    return 'http://yiiframework.ru/forum/'. ltrim($url, './');
}

$forumUrl = './viewforum.php?f=28';

$html = file_get_contents(normalizeUrl($forumUrl));