# Test local complet avec Laragon : base MySQL de test isolée (test-output/mysql, port 3399), serveur PHP sur
# http://localhost:8080, puis parcours tools/parcours-test.php. Les bases de Laragon ne sont jamais touchées.
#   powershell -ExecutionPolicy Bypass -File tools/test-local.ps1            # parcours puis arrêt
#   powershell -ExecutionPolicy Bypass -File tools/test-local.ps1 -Garder    # laisse le site ouvert pour le navigateur
param([switch]$Garder)
$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$php = (Get-ChildItem "C:\laragon\bin\php" -Directory | Sort-Object Name -Descending | Select-Object -First 1).FullName + "\php.exe"
$mysqlBin = (Get-ChildItem "C:\laragon\bin\mysql" -Directory | Sort-Object Name -Descending | Select-Object -First 1).FullName + "\bin"
$out = "$root\test-output"
$data = "$out\mysql"
New-Item -ItemType Directory -Force $out | Out-Null

if (-not (Test-Path "$data\mysql")) {
    & "$mysqlBin\mysqld.exe" --initialize-insecure "--datadir=$data" 2>&1 | Out-Null
}
$mysqld = Start-Process -FilePath "$mysqlBin\mysqld.exe" -ArgumentList "--datadir=`"$data`"", "--port=3399", "--mysqlx=OFF", "--console" -PassThru -WindowStyle Hidden
for ($i = 0; $i -lt 30; $i++) {
    $tcp = New-Object Net.Sockets.TcpClient
    try { $tcp.Connect("127.0.0.1", 3399); $tcp.Close(); break } catch { }
    Start-Sleep -Seconds 1
}
& "$mysqlBin\mysql.exe" -uroot -P3399 "--host=127.0.0.1" -e "DROP DATABASE IF EXISTS suivi_test; CREATE DATABASE suivi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$schema = ("$root\database\schema.sql").Replace([char]92, [char]47)
& "$mysqlBin\mysql.exe" -uroot -P3399 "--host=127.0.0.1" --default-character-set=utf8mb4 suivi_test -e "source $schema"

@"
<?php
return [
    'base_url' => 'http://localhost:8080',
    'db' => ['dsn' => 'mysql:host=127.0.0.1;port=3399;dbname=suivi_test;charset=utf8mb4', 'user' => 'root', 'password' => ''],
    'mail' => ['mode' => 'log', 'from' => 'contact@suivi-des-animaux.fr', 'from_name' => 'Suivi des animaux'],
    'support_email' => 'contact@suivi-des-animaux.fr',
    'download_pc' => 'https://github.com/Tepan-Games/SuiviAnimaux-versions/releases/latest',
    'download_android' => 'https://play.google.com/store/apps/details?id=com.tepangames.suivianimaux',
    'debug' => true,
];
"@ | ForEach-Object { [IO.File]::WriteAllText("$out\config.test.php", $_, (New-Object Text.UTF8Encoding $false)) }
$env:SUIVI_SITE_CONFIG = "$out\config.test.php"
Remove-Item "$root\storage\mails.log" -ErrorAction SilentlyContinue

$server = Start-Process -FilePath $php -ArgumentList "-S", "localhost:8080", "-t", "`"$root\www`"", "`"$root\www\index.php`"" -PassThru -WindowStyle Hidden
Start-Sleep -Seconds 2
& $php "$root\tools\parcours-test.php"
$result = $LASTEXITCODE

if ($Garder) {
    "Site de test ouvert : http://localhost:8080 (arrêter : Stop-Process -Id $($server.Id), $($mysqld.Id))"
} else {
    Stop-Process -Id $server.Id -ErrorAction SilentlyContinue
    & "$mysqlBin\mysqladmin.exe" -uroot -P3399 "--host=127.0.0.1" shutdown 2>$null | Out-Null
}
exit $result
