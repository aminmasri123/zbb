param(
    [string] $LaravelEnv = 'C:\xampp\htdocs\zbb\.env',
    [string] $CredentialFile = 'C:\xampp\backups\zbb-ai-deployment\phase5c-agent-credentials.env'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $LaravelEnv)) {
    throw "Laravel environment file is missing: $LaravelEnv"
}

$bytes = New-Object byte[] 48
[Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
$secret = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')

$lines = [Collections.Generic.List[string]]::new()
foreach ($line in [IO.File]::ReadAllLines($LaravelEnv)) {
    if ($line -notmatch '^ZBB_AI_AGENT_(BASE_URL|KEY_ID|SECRET|CONNECT_TIMEOUT|TIMEOUT|MAX_RESPONSE_BYTES)=') {
        $lines.Add($line)
    }
}

$lines.Add('ZBB_AI_AGENT_BASE_URL=http://127.0.0.1:18000')
$lines.Add('ZBB_AI_AGENT_KEY_ID=laravel')
$lines.Add("ZBB_AI_AGENT_SECRET=$secret")
$lines.Add('ZBB_AI_AGENT_CONNECT_TIMEOUT=3')
$lines.Add('ZBB_AI_AGENT_TIMEOUT=130')
$lines.Add('ZBB_AI_AGENT_MAX_RESPONSE_BYTES=1000000')
[IO.File]::WriteAllLines($LaravelEnv, $lines, [Text.UTF8Encoding]::new($false))

$credentialDirectory = Split-Path -Parent $CredentialFile
[IO.Directory]::CreateDirectory($credentialDirectory) | Out-Null
[IO.File]::WriteAllText(
    $CredentialFile,
    "ZBB_AGENT_KEY_ID=laravel`nZBB_AGENT_SECRET=$secret`n",
    [Text.UTF8Encoding]::new($false)
)

$acl = New-Object Security.AccessControl.FileSecurity
$currentUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$acl.SetOwner([Security.Principal.NTAccount]::new($currentUser))
$acl.SetAccessRuleProtection($true, $false)
$rule = New-Object Security.AccessControl.FileSystemAccessRule(
    $currentUser,
    [Security.AccessControl.FileSystemRights]::FullControl,
    [Security.AccessControl.AccessControlType]::Allow
)
$acl.AddAccessRule($rule)
Set-Acl -LiteralPath $CredentialFile -AclObject $acl

Write-Output 'credentials_prepared=true'
Write-Output "secret_length=$($secret.Length)"
