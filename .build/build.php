<?php

use Composer\Semver\Comparator;
use GitWrapper\GitCommand;
use GitWrapper\GitWrapper;
use GitWrapper\Event\GitLoggerEventSubscriber;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require 'vendor/autoload.php';

$url = "https://api.github.com/repos/conversejs/converse.js/releases";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_USERAGENT, "Awesome-Octocat-App");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$since = 'v5.0.0';

$data = json_decode($response, true);

$parsedData = [];
$sourceTags = [];

foreach($data as $release) {

  $tag = $release['tag_name'];

  // To include v prefix if missing like (3.3.2)
  if (is_numeric($tag{0})) {
    $tag = 'v'.$tag;
  }

  if (Comparator::lessThan($tag, $since)) {
    continue;
  }

  if (!isset($release['assets'][0]['browser_download_url'])) {
      continue;
  }

  $releaseData = [
    'tag_name' => $release['tag_name'],
    'download_url' => $release['assets'][0]['browser_download_url']
  ];

  $sourceTags[] = $release['tag_name'];

  $time = strtotime($release['published_at']);

  $parsedData[$time] = $releaseData;
}

ksort($parsedData);

$target = dirname(__DIR__);

echo $target . PHP_EOL;

// Log to a file named "git.log"
$log = new Logger('git');
$log->pushHandler(new StreamHandler('git.log', Logger::DEBUG));

$sourceFolder = __DIR__ . '/tmp/source';
$targetFolder = dirname(__DIR__);

// TARGET
$gitTargetWrapper = new GitWrapper();
$gitTargetWrapper->addLoggerEventSubscriber(new GitLoggerEventSubscriber($log));

$gitTargetRepo = $gitTargetWrapper->workingCopy($targetFolder);

$listTagsCommand = new GitCommand('tag', '-l', '--sort=version:refname', 'v*');

$targetTags = $gitTargetWrapper->run($listTagsCommand, $gitTargetRepo->getDirectory());
$targetTags = explode(PHP_EOL, $targetTags);

$file = 'conversejs.tgz';

foreach ($parsedData as $release) {

    if (file_exists($file)) {
      unlink($file);
    }

    if (is_dir($sourceFolder)) {
      `rm -rf $sourceFolder`;
    }

    mkdir($sourceFolder, 0777, true);

    $version = $release['tag_name'];

    if (in_array($version, $targetTags)) {
      continue;
    }

    if (Comparator::lessThan($version, $since)) {
      continue;
    }

    echo 'Detected ' . $version . PHP_EOL;

    $wget = sprintf('wget %s -O %s', $release['download_url'], $file);
    `$wget`;

    $unpack = sprintf('tar -xf %s -C %s', $file, $sourceFolder);
    `$unpack`;

    // Rsync repositories
    $rsync = sprintf(
        "rsync -aL --delete '%s' '%s' 2>&1",
        $sourceFolder . '/package/dist',
        $gitTargetRepo->getDirectory()
    );
    `$rsync`;

    // Add all changes (if file was to be ignored with .gitignore it should not be versioned in source repo)
    $command = new GitCommand('add', 'dist');
    $gitTargetWrapper->run($command, $gitTargetRepo->getDirectory());

    // Commit
    $gitTargetRepo->commit('Add conversejs/converse.js-dist ' . $version);
    $gitTargetRepo->tag($version);

    echo 'Added conversejs/converse.js-dist ' . $version . PHP_EOL;
}
