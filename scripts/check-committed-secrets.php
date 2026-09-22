<?php

$keys = ['PAYMOB_SECRET_KEY', 'CLOUDFLARE_API_TOKEN', 'ONLINENIC_PASSWORD'];
$files = preg_split('/\R/', trim((string) shell_exec('git ls-files')));
$found = [];
foreach ($files as $file) {
    if ($file === '' || ! is_file($file) || filesize($file) > 1_000_000) {
        continue;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line => $value) {
        foreach ($keys as $key) {
            if (preg_match('/^'.preg_quote($key, '/').'=(.+)$/', trim($value))) {
                $found[] = $file.':'.($line + 1).' '.$key;
            }
        }
    }
}
if ($found !== []) {
    fwrite(STDERR, "Potential committed secret values:\n".implode("\n", $found)."\n");
    exit(1);
}
echo "No non-empty protected environment values found.\n";
