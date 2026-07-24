$ErrorActionPreference = 'Stop'

$repoDir = Split-Path -Parent $PSScriptRoot
$outputDir = Join-Path $repoDir 'outputs'
$pluginDir = Join-Path $outputDir 'fyremezzonine-conference-manager'
$themeDir = Join-Path $outputDir 'fyremezzonine-wp-theme'
$pluginZip = Join-Path $outputDir 'fyremezzonine-conference-manager.zip'
$themeZip = Join-Path $outputDir 'fyremezzonine-wp-theme.zip'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function New-ReleaseArchive {
    param(
        [Parameter(Mandatory = $true)]
        [string] $SourceDirectory,

        [Parameter(Mandatory = $true)]
        [string] $DestinationPath
    )

    if (Test-Path -LiteralPath $DestinationPath) {
        Remove-Item -LiteralPath $DestinationPath -Force
    }

    $sourceRoot = (Resolve-Path -LiteralPath $SourceDirectory).Path.TrimEnd('\', '/')
    $rootName = Split-Path -Leaf $sourceRoot
    $archive = [System.IO.Compression.ZipFile]::Open(
        $DestinationPath,
        [System.IO.Compression.ZipArchiveMode]::Create
    )

    try {
        Get-ChildItem -LiteralPath $sourceRoot -Recurse -File | ForEach-Object {
            $relativePath = $_.FullName.Substring($sourceRoot.Length).TrimStart('\', '/')
            $entryName = ($rootName + '/' + $relativePath).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $_.FullName,
                $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }
}

New-ReleaseArchive -SourceDirectory $pluginDir -DestinationPath $pluginZip
New-ReleaseArchive -SourceDirectory $themeDir -DestinationPath $themeZip

Write-Host "Release archives rebuilt:"
Write-Host "  $pluginZip"
Write-Host "  $themeZip"
