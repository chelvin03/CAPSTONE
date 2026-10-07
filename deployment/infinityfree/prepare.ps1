param(
    [Parameter(Mandatory = $true)][string]$SourcePath
)
$ErrorActionPreference = 'Stop'
$source = (Resolve-Path -LiteralPath $SourcePath).Path
$releaseBase = Join-Path $PSScriptRoot 'releases'
$release = Join-Path $releaseBase ('prepared-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N').Substring(0,6))
$stage = Join-Path $release 'htdocs'
if (!(Test-Path -LiteralPath (Join-Path $source 'vendor/autoload.php'))) { throw 'Source dependencies must already be installed.' }
if (!(Test-Path -LiteralPath (Join-Path $source 'node_modules'))) { throw 'Source frontend dependencies must already be installed.' }
$envPath = Join-Path $source '.env'
$originalEnvHash = (Get-FileHash -LiteralPath $envPath).Hash
$originalComposerHash = (Get-FileHash -LiteralPath (Join-Path $source 'composer.json')).Hash
$originalIndexHash = (Get-FileHash -LiteralPath (Join-Path $source 'public/index.php')).Hash
$originalHtaccessHash = (Get-FileHash -LiteralPath (Join-Path $source 'public/.htaccess')).Hash
New-Item -ItemType Directory -Path $stage -Force | Out-Null

function Copy-Tree([string]$from, [string]$to, [string[]]$excludedDirectories = @(), [string[]]$excludedFiles = @()) {
    $arguments = @($from, $to, '/E', '/XJ', '/R:1', '/W:1', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
    if ($excludedDirectories.Count) { $arguments += '/XD'; $arguments += $excludedDirectories }
    if ($excludedFiles.Count) { $arguments += '/XF'; $arguments += $excludedFiles }
    & robocopy @arguments | Out-Null
    if ($LASTEXITCODE -ge 8) { throw 'Copy failed; the original source is unchanged.' }
}

foreach ($directory in @('app','config','resources','routes','database')) {
    Copy-Tree (Join-Path $source $directory) (Join-Path $stage $directory) @() @('*.sqlite','*.sqlite-*','*.sql','*.sql.gz')
}
Copy-Tree (Join-Path $source 'bootstrap') (Join-Path $stage 'bootstrap') @('cache')
Copy-Tree (Join-Path $source 'public') (Join-Path $stage 'public') @('storage','build') @('hot')
Copy-Tree (Join-Path $source 'vendor') (Join-Path $stage 'vendor')
# Keep uploaded reservation documents; omit local backups, sessions, caches, and logs.
Copy-Tree (Join-Path $source 'storage/app/private') (Join-Path $stage 'storage/app/private') @('backups')
Copy-Tree (Join-Path $source 'storage/app/public') (Join-Path $stage 'storage/app/public')
foreach ($directory in @('storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache')) {
    New-Item -ItemType Directory -Path (Join-Path $stage $directory) -Force | Out-Null
}
foreach ($file in @('artisan','composer.json','composer.lock')) { Copy-Item -LiteralPath (Join-Path $source $file) -Destination $stage }
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'production.env.example') -Destination (Join-Path $stage '.env.example')
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'htdocs.htaccess') -Destination (Join-Path $stage '.htaccess')
foreach ($directory in @('app','bootstrap','config','database','resources','routes','storage','vendor')) {
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'private.htaccess') -Destination (Join-Path $stage ($directory+'/.htaccess'))
}

Push-Location $stage
try {
    # Only the isolated package changes. No install/update command runs in the source.
    & composer config optimize-autoloader false
    if ($LASTEXITCODE -ne 0) { throw 'Staged Composer configuration failed.' }
    & composer install --no-dev --no-scripts --no-plugins --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Staged production dependency preparation failed.' }
    & composer check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'Local PHP cannot run the production dependencies.' }
    & php artisan package:discover --ansi
    if ($LASTEXITCODE -ne 0) { throw 'Staged Laravel package discovery failed.' }
} finally { Pop-Location }

Push-Location $source
try {
    # Compile the actual source views into the isolated public/build, not the local one.
    & npm.cmd run build -- --outDir (Join-Path $stage 'public/build')
    if ($LASTEXITCODE -ne 0) { throw 'Production frontend build failed.' }
} finally { Pop-Location }

$audit = & php (Join-Path $PSScriptRoot 'audit-package.php') $stage
$auditExit = $LASTEXITCODE
$audit | Set-Content -LiteralPath (Join-Path $release 'package-audit.json') -Encoding UTF8
if ($originalEnvHash -ne (Get-FileHash -LiteralPath $envPath).Hash -or
    $originalComposerHash -ne (Get-FileHash -LiteralPath (Join-Path $source 'composer.json')).Hash -or
    $originalIndexHash -ne (Get-FileHash -LiteralPath (Join-Path $source 'public/index.php')).Hash -or
    $originalHtaccessHash -ne (Get-FileHash -LiteralPath (Join-Path $source 'public/.htaccess')).Hash) {
    throw 'Source integrity check failed. Stop and inspect before continuing.'
}
@(('Source: '+$source),('Package: '+$stage),'Local environment, APP_KEY, Composer configuration, index.php, and public/.htaccess unchanged.','No database command ran. No production credentials or .env included.','Do not upload yet. Complete the deployment guide and final account configuration first.') |
    Set-Content -LiteralPath (Join-Path $release 'PREPARATION.txt') -Encoding UTF8
if ($auditExit -ne 0) { $audit; throw 'Package audit failed; see package-audit.json.' }
Write-Output ('Prepared only: '+$stage)
Write-Output ('Audit: '+(Join-Path $release 'package-audit.json'))
