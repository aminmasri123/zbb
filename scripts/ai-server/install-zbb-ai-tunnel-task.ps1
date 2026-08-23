param(
    [string] $SourceIdentity = "$env:USERPROFILE\.ssh\id_ed25519_ollama",
    [string] $SourceKnownHosts = "$env:USERPROFILE\.ssh\known_hosts",
    [string] $Runner = 'C:\xampp\htdocs\zbb\scripts\ai-server\run-zbb-ai-tunnel.ps1'
)

$ErrorActionPreference = 'Stop'
$taskName = 'ZBB-AI-Agent-Tunnel'
$sshDirectory = 'C:\ProgramData\ZBB\ssh'
$logDirectory = 'C:\ProgramData\ZBB\logs'

foreach ($path in @($SourceIdentity, $SourceKnownHosts, $Runner)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Erforderliche Datei fehlt: $path"
    }
}

[IO.Directory]::CreateDirectory($sshDirectory) | Out-Null
[IO.Directory]::CreateDirectory($logDirectory) | Out-Null
Copy-Item -LiteralPath $SourceIdentity -Destination "$sshDirectory\id_ed25519_ollama" -Force
Copy-Item -LiteralPath $SourceKnownHosts -Destination "$sshDirectory\known_hosts" -Force

& icacls.exe 'C:\ProgramData\ZBB' /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Die ACL fuer C:\ProgramData\ZBB konnte nicht gesetzt werden.'
}

$action = New-ScheduledTaskAction `
    -Execute 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' `
    -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$Runner`""
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -MultipleInstances IgnoreNew `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -StartWhenAvailable

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings `
    -Description 'Maintains the loopback-only SSH tunnel from Laravel to the ZBB AI agent.' `
    -Force | Out-Null

Start-ScheduledTask -TaskName $taskName
Write-Output "task_installed=$taskName"
