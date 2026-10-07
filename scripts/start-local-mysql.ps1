$ErrorActionPreference = 'Stop'

function Test-MySqlPort {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $pending = $client.BeginConnect('127.0.0.1', 3306, $null, $null)
        if (-not $pending.AsyncWaitHandle.WaitOne(200)) { return $false }
        $client.EndConnect($pending)
        return $true
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

if (Test-MySqlPort) { exit 0 }

$mysqlExecutable = 'C:\xampp\mysql\bin\mysqld.exe'
$mysqlConfig = 'C:\xampp\mysql\bin\my.ini'
if (-not (Test-Path -LiteralPath $mysqlExecutable) -or -not (Test-Path -LiteralPath $mysqlConfig)) {
    throw 'The local XAMPP MySQL installation was not found in C:\xampp.'
}

# An existing server may still be initializing. Avoid a second instance.
if (-not (Get-Process -Name mysqld -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $mysqlExecutable -ArgumentList ('--defaults-file="' + $mysqlConfig + '"') -WorkingDirectory 'C:\xampp\mysql\bin' -WindowStyle Hidden
}

$deadline = (Get-Date).AddSeconds(15)
do {
    if (Test-MySqlPort) { exit 0 }
    Start-Sleep -Milliseconds 250
} while ((Get-Date) -lt $deadline)

throw 'MySQL did not become ready within 15 seconds. Check the XAMPP MySQL error log.'
