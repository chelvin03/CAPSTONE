<?php
// Local CLI only. Do not upload this helper or turn it into a web endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root.'/composer.lock')) { fwrite(STDERR, "Supply the staged htdocs directory.\n"); exit(1); }
$lock = json_decode(file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$extensions = ['pdo_mysql'];
foreach ($lock['packages'] as $package) {
    foreach (array_keys($package['require'] ?? []) as $requirement) {
        if (str_starts_with($requirement, 'ext-')) $extensions[] = substr($requirement, 4);
    }
}
$extensions = array_values(array_unique($extensions)); sort($extensions);
$issues = []; $fileCount = 0; $bytes = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isLink()) { $issues[] = 'Symlink: '.substr($file->getPathname(), strlen($root)+1); continue; }
    if (!$file->isFile()) continue;
    $fileCount++; $size = $file->getSize(); $bytes += $size;
    $extension = strtolower($file->getExtension());
    $limit = $file->getFilename() === '.htaccess' ? 10000 : (in_array($extension, ['php','js','html','htm'], true) ? 1000000 : 10000000);
    if ($size >= $limit) $issues[] = 'File size limit: '.substr($file->getPathname(), strlen($root)+1).' ('.$size.' bytes)';
}
$manifestPath = $root.'/public/build/manifest.json';
if (!is_file($manifestPath)) $issues[] = 'Missing Vite manifest';
else {
    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    foreach (['resources/css/app.css','resources/js/app.js'] as $entry) if (!isset($manifest[$entry])) $issues[] = 'Missing Vite entry '.$entry;
    foreach ($manifest as $entry) if (!is_file($root.'/public/build/'.$entry['file'])) $issues[] = 'Missing compiled asset '.$entry['file'];
}
foreach (['public/hot','bootstrap/cache/config.php','bootstrap/cache/routes-v7.php','.env','node_modules','.git'] as $forbidden) {
    if (file_exists($root.'/'.$forbidden)) $issues[] = 'Unexpected local/generated file '.$forbidden;
}
if (glob($root.'/storage/framework/views/*.php')) $issues[] = 'Compiled local Blade views must not be uploaded';
foreach (['public/index.php','public/.htaccess','.htaccess','vendor/autoload.php','public/images/mcst-logo.png','public/images/mcst-gymnasium-exterior.png'] as $required) {
    if (!is_file($root.'/'.$required)) $issues[] = 'Missing '.$required;
}
foreach (['storage/app/private','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache'] as $directory) {
    if (!is_dir($root.'/'.$directory)) $issues[] = 'Missing writable directory '.$directory;
}
if ($fileCount >= 25000) $issues[] = 'Package file count leaves too little room for runtime files under the hosting inode quota';
$report = ['php_requirement' => '^8.2; 64-bit PHP required by locked packages', 'required_extensions' => $extensions,
    'files' => $fileCount, 'bytes' => $bytes, 'issues' => $issues,
    'hosting_verification_pending' => ['PHP extensions and writable paths','Apache rewrite and private-file denial','MySQL import','SMTP STARTTLS 587','CPU/memory/time limits']];
echo json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($issues ? 1 : 0);
