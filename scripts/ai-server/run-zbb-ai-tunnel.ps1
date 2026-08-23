param(
    [string] $SshExecutable = 'C:\Windows\System32\OpenSSH\ssh.exe',
    [string] $IdentityFile = 'C:\ProgramData\ZBB\ssh\id_ed25519_ollama',
    [string] $KnownHostsFile = 'C:\ProgramData\ZBB\ssh\known_hosts',
    [string] $LogFile = 'C:\ProgramData\ZBB\logs\ai-tunnel.log'
)

$ErrorActionPreference = 'Continue'
$logDirectory = Split-Path -Parent $LogFile
[IO.Directory]::CreateDirectory($logDirectory) | Out-Null

function Write-TunnelLog([string] $Message) {
    $line = '{0} {1}' -f [DateTimeOffset]::Now.ToString('o'), $Message
    [IO.File]::AppendAllText($LogFile, "$line`r`n", [Text.UTF8Encoding]::new($false))
}

while ($true) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort 18000 -ErrorAction SilentlyContinue
    if ($listener) {
        try {
            $live = Invoke-RestMethod -Uri 'http://127.0.0.1:18000/health/live' -TimeoutSec 5
            if ($live.status -eq 'ok') {
                Start-Sleep -Seconds 15
                continue
            }
        } catch {
            Write-TunnelLog 'Port 18000 ist belegt, aber der Agent-Healthcheck schlug fehl.'
        }

        Start-Sleep -Seconds 15
        continue
    }

    Write-TunnelLog 'Starte SSH-Tunnel.'
    & $SshExecutable @(
        '-N',
        '-T',
        '-i', $IdentityFile,
        '-o', 'IdentitiesOnly=yes',
        '-o', 'BatchMode=yes',
        '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$KnownHostsFile",
        '-o', 'ExitOnForwardFailure=yes',
        '-o', 'ServerAliveInterval=30',
        '-o', 'ServerAliveCountMax=3',
        '-L', '127.0.0.1:18000:127.0.0.1:8000',
        'aminmasri@10.100.1.30'
    )
    Write-TunnelLog "SSH-Tunnel beendet (Exitcode $LASTEXITCODE); neuer Versuch in 5 Sekunden."
    Start-Sleep -Seconds 5
}
