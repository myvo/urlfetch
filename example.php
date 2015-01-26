<?php
include "urlfetch.class.php";
$base_url = 'https://api.twitter.com/1.1';

$consumer_key = 'TfRa4qvHYQYEYfOGEXxbRbizn';
$consumer_secret = '8uI8Hg5k3aMfK3W4YyWIc97lV823MEplFDvoquHrpSv5p5brKO';
$token = '84473800-yqXbeGiPrkfifLW0DmzWlRVYxCEOdQmMP7XIzpOTc';
$token_secret = 'c25QZqgB8hHpGejXlSQsX5ouAwXmHeeXnXjTdwkXIEZJL';

$params = array(
  'screen_name' => 'myvo85',
  'count' => 1,
);

$client = new UrlFetch($base_url);
$client->setOAuth($consumer_key, $consumer_secret, $token, $token_secret);

$append_url = '/statuses/user_timeline.json';
$data = $client->execute($append_url, $params);
var_dump('<pre>DATA', $data, '<br><br>LAST-RESPONSE', $client->lastResponse,'</pre>');
