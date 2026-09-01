<#
.SYNOPSIS
    Builds an installable WordPress plugin ZIP locally, without GitHub Actions.

.DESCRIPTION
    Mirrors the packaging steps of .github/workflows so a developer can produce
    the same artifact on their machine:

      dev      -> .github/workflows/build-dev.yml   (PUC injected, branch-suffixed name)
      release  -> .github/workflows/release.yml     (PUC + icon filter, analytics/branding on)
      wporg    -> .github/workflows/deploy-wp-org.yml (runs ci/build-wporg.sh + verify)

    Requires: PowerShell 5.1+. The wporg mode additionally needs bash
    (Git for Windows ships it) because it reuses ci/build-wporg.sh verbatim.

.EXAMPLE
    ci\build-local.ps1
    ci\build-local.ps1 -Mode release
    ci\build-local.ps1 -Mode wporg
    ci\build-local.ps1 -Mode release -Version 1.4.1 -SkipPuc
#>
[CmdletBinding()]
param(
    [ValidateSet('dev', 'release', 'wporg')]
    [string]$Mode = 'dev',

    # Override the version stamped into the plugin header / zip name.
    [string]$Version,

    # Skip the Plugin Update Checker download+injection (offline / faster).
    [switch]$SkipPuc,

    # Where build/ and the .zip land. Default: repo root (build/ is gitignored).
    [string]$OutDir
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$PluginSlug = 'ceypay-payment-gateway'
$PucVersion = '5.4'

$RepoRoot = Split-Path -Parent $PSScriptRoot
$SrcDir   = Join-Path $RepoRoot $PluginSlug
if (-not $OutDir) { $OutDir = $RepoRoot }
$OutDir    = (New-Item -ItemType Directory -Force -Path $OutDir).FullName
$BuildRoot = Join-Path $OutDir 'build'
$StageDir  = Join-Path $BuildRoot $PluginSlug
# Lives outside build/ so wiping the staging dir doesn't force a re-download.
$CacheDir  = Join-Path $RepoRoot '.build-cache'

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "    $msg" -ForegroundColor DarkGray }

if (-not (Test-Path (Join-Path $SrcDir "$PluginSlug.php"))) {
    throw "Plugin source not found at $SrcDir"
}

# --- Version -----------------------------------------------------------------
$MainSrc = Get-Content (Join-Path $SrcDir "$PluginSlug.php") -Raw
if (-not $Version) {
    if ($MainSrc -notmatch '(?m)^\s*\*\s*Version:\s*([0-9][0-9.]*)') {
        throw "Could not read Version from $PluginSlug.php"
    }
    $Version = $Matches[1]
}
Write-Step "Building '$Mode' package for $PluginSlug v$Version"

# --- Zip helper ---------------------------------------------------------------
# Compress-Archive on Windows PowerShell can emit backslash path separators,
# which WordPress' unzip refuses to nest correctly. Write entries by hand so the
# archive always uses forward slashes and contains a single top-level folder.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$ExcludePatterns = @('*.git*', '.DS_Store', '*.tmp', 'Thumbs.db', '*.orig', '*.rej')

function New-PluginZip {
    param([string]$SourceDir, [string]$ZipPath)

    if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }
    $root       = (Resolve-Path $SourceDir).Path.TrimEnd('\')
    $rootParent = Split-Path $root -Parent
    $count      = 0

    $archive = [System.IO.Compression.ZipFile]::Open($ZipPath, 'Create')
    try {
        foreach ($file in Get-ChildItem -LiteralPath $root -Recurse -File -Force) {
            $skip = $false
            foreach ($pat in $ExcludePatterns) {
                if ($file.Name -like $pat) { $skip = $true; break }
            }
            if ($file.FullName -match '[\\/]\.git[\\/]') { $skip = $true }
            if ($skip) { continue }

            $rel = $file.FullName.Substring($rootParent.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive, $file.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
            $count++
        }
    } finally {
        $archive.Dispose()
    }
    Write-Ok "$count files"
}

# --- wporg mode: reuse the shell script the deploy workflow uses --------------
if ($Mode -eq 'wporg') {
    # Prefer Git Bash. A bare `bash` on PATH is usually WSL's stub, which can't
    # see the Windows filesystem the same way and fails on these paths.
    $bashExe = @(
        (Join-Path $env:ProgramFiles 'Git\bin\bash.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Git\bin\bash.exe'),
        (Join-Path $env:LOCALAPPDATA 'Programs\Git\bin\bash.exe'),
        $(if ($cmd = Get-Command git.exe -ErrorAction SilentlyContinue) {
              Join-Path (Split-Path (Split-Path $cmd.Source -Parent) -Parent) 'bin\bash.exe' }),
        $(if ($cmd = Get-Command bash.exe -ErrorAction SilentlyContinue) {
              if ($cmd.Source -notmatch '\\System32\\') { $cmd.Source } })
    ) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1

    if (-not $bashExe) {
        throw "wporg mode needs Git Bash (install Git for Windows) - it reuses ci/build-wporg.sh so the .org rules never drift."
    }
    $bash = Get-Command $bashExe

    $wporgDir = Join-Path $BuildRoot 'wporg'
    New-Item -ItemType Directory -Force -Path $BuildRoot | Out-Null

    # bash can't read Windows-style paths (C:\x), so run from the repo root and
    # hand the scripts POSIX paths (/c/x) instead.
    $relOut = (Join-Path $BuildRoot 'wporg') -replace '^([A-Za-z]):', '/$1' -replace '\\', '/'
    $relOut = $relOut.Substring(0, 2).ToLower() + $relOut.Substring(2)
    Push-Location $RepoRoot
    try {
        Write-Step 'Running ci/build-wporg.sh'
        & $bash.Source 'ci/build-wporg.sh' $PluginSlug $relOut
        if ($LASTEXITCODE -ne 0) { throw "build-wporg.sh failed ($LASTEXITCODE)" }

        Write-Step 'Running ci/verify-wporg-build.sh'
        & $bash.Source 'ci/verify-wporg-build.sh' $relOut
        if ($LASTEXITCODE -ne 0) { throw "verify-wporg-build.sh failed ($LASTEXITCODE) - build is NOT .org compliant" }
    } finally {
        Pop-Location
    }

    # WordPress.org serves trunk/ as the plugin folder, so the zip mirrors that.
    $wporgPlugin = Join-Path $BuildRoot $PluginSlug
    if (Test-Path $wporgPlugin) { Remove-Item $wporgPlugin -Recurse -Force }
    Move-Item $wporgDir $wporgPlugin

    $zipPath = Join-Path $OutDir "$PluginSlug-v$Version-wporg.zip"
    Write-Step "Zipping -> $(Split-Path $zipPath -Leaf)"
    New-PluginZip -SourceDir $wporgPlugin -ZipPath $zipPath

    Write-Host ""
    Write-Host "Built: $zipPath" -ForegroundColor Green
    Write-Host "(WordPress.org build: analytics module and remote fonts stripped)" -ForegroundColor DarkGray
    return
}

# --- Stage source -------------------------------------------------------------
Write-Step 'Staging plugin source'
if (Test-Path $BuildRoot) { Remove-Item $BuildRoot -Recurse -Force -ErrorAction SilentlyContinue }
New-Item -ItemType Directory -Force -Path $StageDir | Out-Null
Copy-Item -Path (Join-Path $SrcDir '*') -Destination $StageDir -Recurse -Force
$MainFile = Join-Path $StageDir "$PluginSlug.php"

# --- Plugin Update Checker ----------------------------------------------------
if (-not $SkipPuc) {
    New-Item -ItemType Directory -Force -Path $CacheDir | Out-Null
    $pucZip = Join-Path $CacheDir "puc-$PucVersion.zip"
    if (-not (Test-Path $pucZip)) {
        Write-Step "Downloading Plugin Update Checker v$PucVersion"
        $url = "https://github.com/YahnisElsts/plugin-update-checker/archive/refs/tags/v$PucVersion.zip"
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $url -OutFile $pucZip -UseBasicParsing
    } else {
        Write-Step "Using cached Plugin Update Checker v$PucVersion"
    }

    $pucExtract = Join-Path $CacheDir 'puc-extract'
    if (Test-Path $pucExtract) { Remove-Item $pucExtract -Recurse -Force }
    Expand-Archive -LiteralPath $pucZip -DestinationPath $pucExtract -Force
    Move-Item (Join-Path $pucExtract "plugin-update-checker-$PucVersion") (Join-Path $StageDir 'plugin-update-checker')
    Write-Ok 'bundled as plugin-update-checker/'

    # Inject the bootstrap right after the ABSPATH guard's `exit;`, exactly like
    # the workflows do. The release build additionally registers icons/banners
    # so the WP updater screen renders them.
    $pucCode = @"

/**
* Plugin Update Checker - GitHub Updates
* Enables automatic updates from GitHub releases
*/
if ( file_exists( dirname( __FILE__ ) . '/plugin-update-checker/plugin-update-checker.php' ) ) {
    require_once dirname( __FILE__ ) . '/plugin-update-checker/plugin-update-checker.php';

    `$ceypay_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/CeyPay-io/woocommerce-plugin/',
        __FILE__,
        'ceypay-payment-gateway'
    );

    `$ceypay_update_checker->getVcsApi()->enableReleaseAssets();
"@

    if ($Mode -eq 'release') {
        $pucCode += @"


    // Add plugin icons and metadata
    `$ceypay_update_checker->addResultFilter( function ( `$plugin_info, `$http_response = null ) {
        `$plugin_info->icons = array(
            '1x' => plugins_url( 'assets/images/ceypay-icon.png', __FILE__ ),
            'svg' => plugins_url( 'assets/images/ceypay-logo.svg', __FILE__ ),
        );
        `$plugin_info->banners = array(
            'low' => plugins_url( 'assets/images/ceypay-logo.svg', __FILE__ ),
        );
        `$plugin_info->tested = '6.9';
        return `$plugin_info;
    } );
"@
    }
    $pucCode += "`n}`n"

    $lines = Get-Content $MainFile
    $exitIdx = ($lines | Select-String -SimpleMatch 'exit;' | Select-Object -First 1).LineNumber
    if (-not $exitIdx) { throw "Could not find the ABSPATH guard 'exit;' in $MainFile" }

    $head = $lines[0..($exitIdx)]           # through the line after `exit;` (the closing brace)
    $tail = $lines[($exitIdx + 1)..($lines.Count - 1)]
    Set-Content -Path $MainFile -Value ($head + ($pucCode -split "`r?`n") + $tail) -Encoding UTF8
    Write-Ok 'update-checker bootstrap injected'
} else {
    Write-Step 'Skipping Plugin Update Checker (-SkipPuc)'
}

# --- Release-only defaults ----------------------------------------------------
if ($Mode -eq 'release') {
    Write-Step 'Enabling analytics + branding defaults'
    $gatewayFile = Join-Path $StageDir 'includes\class-wc-gateway-ceypay.php'
    $gw = Get-Content $gatewayFile -Raw
    $gw = $gw -replace "'default'\s*=>\s*'no',\s*// analytics", "'default'     => 'yes', // analytics enabled for releases"
    $gw = $gw -replace "'default'\s*=>\s*'no',\s*// branding",  "'default'     => 'yes', // branding enabled for releases"
    Set-Content -Path $gatewayFile -Value $gw -Encoding UTF8 -NoNewline
}

# --- Version stamp ------------------------------------------------------------
if ($PSBoundParameters.ContainsKey('Version')) {
    Write-Step "Stamping version $Version"
    $main = Get-Content $MainFile -Raw
    $main = $main -replace '(?m)^(\s*\*\s*Version:\s*).*$', "`${1}$Version"
    Set-Content -Path $MainFile -Value $main -Encoding UTF8 -NoNewline

    $readme = Join-Path $StageDir 'readme.txt'
    if (Test-Path $readme) {
        $rd = Get-Content $readme -Raw
        $rd = $rd -replace '(?m)^(Stable tag:\s*).*$', "`${1}$Version"
        Set-Content -Path $readme -Value $rd -Encoding UTF8 -NoNewline
    }
}

# --- Zip ----------------------------------------------------------------------
if ($Mode -eq 'dev') {
    $branch = (git -C $RepoRoot rev-parse --abbrev-ref HEAD 2>$null)
    if (-not $branch) { $branch = 'local' }
    $suffix = ($branch -replace '[^a-zA-Z0-9._-]', '-').ToLower()
    $zipPath = Join-Path $OutDir "$PluginSlug-v$Version-$suffix.zip"
} else {
    $zipPath = Join-Path $OutDir "$PluginSlug-v$Version.zip"
}

Write-Step "Zipping -> $(Split-Path $zipPath -Leaf)"
New-PluginZip -SourceDir $StageDir -ZipPath $zipPath

Write-Host ""
Write-Host "Built: $zipPath" -ForegroundColor Green
Write-Host "Install via WordPress Admin > Plugins > Add New > Upload Plugin" -ForegroundColor DarkGray
