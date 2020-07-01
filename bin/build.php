<?php

const RELEASE_URL = 'https://api.wordpress.org/core/stable-check/1.0/';

require_once __DIR__ . '/build-branch.php';

// Fetch releases
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, RELEASE_URL);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

try {
    $result = curl_exec($curl);

    // Check HTTP status code
    if (!curl_errno($curl)) {
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            throw new RuntimeException("api returned code '$http_code' with $result");
        }
    }
} finally {
    curl_close($curl);
}
unset($curl);

// Parse releases
$releases = json_decode($result, true);
unset($result);

// Get validated release array
$releases = array_filter($releases, function ($tag) {
    return version_compare($tag, '4.0', '>=');
}, ARRAY_FILTER_USE_KEY);
array_walk($releases, function (&$tag, $version) {
    $tag = "https://downloads.wordpress.org/release/wordpress-{$version}-no-content.zip";
});


// Setting up temp repository
function run($cmd) {
    $code = null;
    system($cmd, $code);
    return $code === 0;
}

$githubToken = getenv('TOKEN');
if (!preg_match('/^[a-z0-9]+$/i', $githubToken)) {
    throw new RuntimeException("refusing to proceed with possibly invalid GITHUB TOKEN");
}
$githubRepo = getenv('REPO_SLUG');
$remote = "https://$githubToken@github.com/$githubRepo.git";

$tempfile = tempnam(sys_get_temp_dir(), 'wordpress-');
if (file_exists($tempfile)) {
    unlink($tempfile);
}
if (!run("git clone $remote $tempfile")) {
    throw new RuntimeException('could not clone repo');
}

if (!chdir($tempfile)) {
    throw new RuntimeException("couldn't switch to $tempfile");
}

$githubUser = getenv('GITHUB_ACTOR');
if (empty($githubUser)) {
    throw new RuntimeException("refusing to proceed with possibly invalid GITHUB USER");
}

if (!run("git config user.email $githubUser")) {
    throw new RuntimeException("could not set git info for $tempfile");
}

// Building releases
foreach ($releases as $version => $url) {
    try {
        if (empty($version)) {
            throw new RuntimeException('bogus release');
        }

        if (!preg_match('/^[0-9.]+$/', $version)) {
            throw new RuntimeException("tag '$version' does not look like a version number");
        }

        if (empty($url)) {
            throw new RuntimeException("tag '$version' does does not have a dist url");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($statusCode !== 200) {
            throw new RuntimeException("tag '$version' does does not have a reachable dist url (status $statusCode for {$url})");
        }

        $ref = escapeshellarg("refs/tags/$version");
        if (run("git show-ref --tags --quiet --verify -- $ref")) {
            throw new RuntimeException("tag '$version' already exist");
        }
    } catch (RuntimeException $e) {
        continue;
    }

    $safeVersion = escapeshellarg($version);
    $branch = escapeshellarg("$version-branch");

    if (!run("git checkout --orphan $branch") || !run('git rm --cached -r .')) {
        throw new RuntimeException("failed to git create branch $safeVersion");
    }

    $built = buildBranch($version, $url, $tempfile);
    if (!$built) {
        throw new RuntimeException("failed to build out $version");
    }

    if (
        !run('git add composer.json') ||
        !run("git commit -a -m $safeVersion")
    ) {
        throw new RuntimeException("failed to commit $safeVersion");
    }
    if (!run("git tag $safeVersion")) {
        throw new RuntimeException("failed to tag $safeVersion");
    }

    echo "made $version successfully\n";
}

if (!run("git push --tags $remote")) {
    throw new RuntimeException("failed to push tags to remote");
}
