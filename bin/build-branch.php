<?php

function makeComposerPackage($version, $zipURL)
{
  return [
    'name' => 'leocolomb/wordpress',
    'type' => 'wordpress-core',
    'description' => 'WordPress is web software you can use to create a beautiful website or blog.',
    'keywords' => [
      'wordpress',
      'blog',
      'cms'
    ],
    'homepage' => 'https://wordpress.org/',
    'version' => $version,
    'license' => 'GPL-2.0-or-later',
    'authors' => [
      [
        'name' => 'WordPress Community',
        'homepage' => 'https://wordpress.org/about/'
      ]
    ],
    'require' => [
      'php' => '>=5.3.2',
      'roots/wordpress-core-installer' => '>=1.0.0'
    ],
    'provide' => [
        'wordpress/core-implementation' => $version
    ],
    'support' => [
      'issues' => 'https://core.trac.wordpress.org/',
      'forum' => 'https://wordpress.org/support/',
      'wiki' => 'https://codex.wordpress.org/',
      'irc' => 'irc://irc.freenode.net/wordpress',
      'source' => 'https://core.trac.wordpress.org/browser',
      'docs' => 'https://developer.wordpress.org/',
      'rss' => 'https://wordpress.org/news/feed/'
    ],
    'dist' => [
      'url' => $zipURL,
      'type' => 'zip'
    ],
    'source' => [
      'url' => 'git://develop.git.wordpress.org/wordpress.git',
      'type' => 'git',
      'reference' => $version
    ]
  ];
}

/**
 * @param array $package
 * @param string $path
 * @return bool|int
 */
function writeComposerJSON($package, $path)
{
  $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
  return file_put_contents(
    $path,
    json_encode($package, $json_options)
  );
}

function buildBranch($version, $zipURL, $dir)
{
  return (bool)writeComposerJSON(
    makeComposerPackage($version, $zipURL),
    "${dir}/composer.json"
  );
}
