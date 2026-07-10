# RailShot Stream Worker - runs as SYSTEM via scheduled task.
# Uses PowerShell (no PHP path required). Same JSON command protocol as the PHP worker.

$ErrorActionPreference = 'Continue'
$StreamingDir = $PSScriptRoot
$RootDir = Split-Path $StreamingDir -Parent
$WorkerDir = Join-Path $RootDir 'App_Data\railshot\stream-worker'
$StateFile = Join-Path $RootDir 'App_Data\railshot\stream-state.json'
$ConfFile = Join-Path $StreamingDir 'cameras.conf'
$LogFile = Join-Path $StreamingDir 'worker.log'
$CommandFile = Join-Path $WorkerDir 'command.json'
$ResultFile = Join-Path $WorkerDir 'result.json'
$HeartbeatFile = Join-Path $WorkerDir 'heartbeat.json'
$WorkerStateFile = Join-Path $WorkerDir 'worker-state.json'

$script:LastProcessedId = ''
$script:LastRecoveryAt = 0

function Get-Epoch {
    return [int][DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
}

function Write-WorkerLog([string]$Message) {
    $line = '[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -Path $LogFile -Value $line -Encoding UTF8
    Write-Host $line
}

function Ensure-WorkerDir {
    if (-not (Test-Path $WorkerDir)) {
        New-Item -ItemType Directory -Path $WorkerDir -Force | Out-Null
    }
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $Content, $utf8)
}

function Write-Heartbeat {
    Ensure-WorkerDir
    $payload = @{
        ts  = Get-Epoch
        pid = $PID
    } | ConvertTo-Json -Compress
    Write-Utf8NoBom $HeartbeatFile $payload
}

function Sanitize-TableId([string]$Id) {
    return ($Id.ToLower() -replace '[^a-z0-9_-]', '')
}

function Get-RtspPort([string]$Rtsp) {
    if ($Rtsp -match '@[^/:]+:(\d+)') {
        return $Matches[1]
    }
    return ''
}

function Parse-Cameras {
    if (-not (Test-Path $ConfFile)) {
        return @()
    }
    $cameras = @()
    foreach ($line in Get-Content $ConfFile) {
        $line = $line.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { continue }
        $parts = $line -split '\|', 3 | ForEach-Object { $_.Trim() }
        if ($parts.Count -lt 3) { continue }
        $cameras += [PSCustomObject]@{
            table = $parts[0].ToLower()
            rtsp  = $parts[1]
            ytKey = $parts[2]
        }
    }
    return $cameras
}

function Find-Camera([string]$TableId) {
    $id = Sanitize-TableId $TableId
    foreach ($cam in Parse-Cameras) {
        if ($cam.table -eq $id) { return $cam }
    }
    return $null
}

function Find-Ffmpeg {
    $local = Join-Path $StreamingDir 'ffmpeg.exe'
    if (Test-Path $local) { return $local }
    $where = & where.exe ffmpeg 2>$null | Select-Object -First 1
    if ($where -and (Test-Path $where)) { return $where.Trim() }
    return $null
}

function Get-FfmpegPids {
    return @(Get-Process -Name ffmpeg -ErrorAction SilentlyContinue | ForEach-Object { $_.Id })
}

function Test-FfmpegRunning {
    return (Get-FfmpegPids).Count -gt 0
}

function Stop-LegacyTasks {
    $query = & schtasks.exe /query /fo TABLE /nh 2>$null
    foreach ($line in $query) {
        if ($line -match '(RailShot-table\S+)') {
            & schtasks.exe /end /tn $Matches[1] 2>$null | Out-Null
        }
    }
}

function Stop-Ffmpeg {
    Stop-LegacyTasks
    for ($i = 0; $i -lt 15; $i++) {
        foreach ($ffmpegPid in Get-FfmpegPids) {
            & taskkill.exe /F /T /PID $ffmpegPid 2>$null | Out-Null
        }
        & taskkill.exe /F /T /IM ffmpeg.exe 2>$null | Out-Null
        if (-not (Test-FfmpegRunning)) { break }
        Start-Sleep -Milliseconds 400
    }
    $state = @{ managedPid = 0; activeTable = ''; startedAt = 0 } | ConvertTo-Json -Compress
    Write-Utf8NoBom $WorkerStateFile $state
}

function Get-StreamState {
    if (-not (Test-Path $StateFile)) {
        return @{ tables = @{} }
    }
    try {
        $data = Get-Content $StateFile -Raw | ConvertFrom-Json
    } catch {
        return @{ tables = @{} }
    }
    $tables = @{}
    if ($data.tables) {
        $props = $data.tables.PSObject.Properties
        foreach ($p in $props) {
            $id = Sanitize-TableId $p.Name
            if ($id -ne '') {
                $tables[$id] = if ($p.Value -eq 'live') { 'live' } else { 'stopped' }
            }
        }
    }
    return @{ tables = $tables }
}

function Set-StreamState($State) {
    $dir = Split-Path $StateFile -Parent
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $out = @{ tables = $State.tables }
    $json = $out | ConvertTo-Json -Depth 5
    Write-Utf8NoBom $StateFile $json
}

function Get-LiveTableId {
    $state = Get-StreamState
    foreach ($key in $state.tables.Keys) {
        if ($state.tables[$key] -eq 'live') { return $key }
    }
    return ''
}

function Build-FfmpegArgs($Camera) {
    $ytUrl = 'rtmp://a.rtmp.youtube.com/live2/' + $Camera.ytKey
    return @(
        '-loglevel', 'warning',
        '-rtsp_transport', 'tcp',
        '-timeout', '10000000',
        '-i', $Camera.rtsp,
        '-c:v', 'libx264',
        '-preset', 'veryfast',
        '-b:v', '2500k',
        '-maxrate', '2500k',
        '-bufsize', '5000k',
        '-r', '30',
        '-g', '60',
        '-keyint_min', '60',
        '-sc_threshold', '0',
        '-c:a', 'aac',
        '-b:a', '128k',
        '-ar', '44100',
        '-f', 'flv',
        '-flvflags', 'no_duration_filesize',
        $ytUrl
    )
}

function Start-FfmpegDetached($Ffmpeg, $Args, $StreamLog) {
    $argLine = ($Args | ForEach-Object {
        if ($_ -match '\s') { '"' + $_ + '"' } else { $_ }
    }) -join ' '
    $cmd = 'start "" /B "' + $Ffmpeg + '" ' + $argLine + ' >> "' + $StreamLog + '" 2>&1'
    $p = Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', $cmd -WindowStyle Hidden -PassThru
    return $null -ne $p
}

function Test-FfmpegStarted([int]$MaxMs = 6000) {
    $elapsed = 0
    while ($elapsed -lt $MaxMs) {
        if (-not (Test-FfmpegRunning)) { return $false }
        Start-Sleep -Milliseconds 500
        $elapsed += 500
    }
    return Test-FfmpegRunning
}

function Write-Result($Result) {
    Ensure-WorkerDir
    Write-Utf8NoBom $ResultFile ($Result | ConvertTo-Json -Depth 5)
}

function Start-TableStream([string]$TableId) {
    $TableId = Sanitize-TableId $TableId
    if ($TableId -eq '') {
        return @{ ok = $false; error = 'Invalid table id' }
    }

    $camera = Find-Camera $TableId
    if (-not $camera) {
        return @{ ok = $false; error = ('No camera configured for ' + $TableId + ' in cameras.conf - save live settings in admin.') }
    }

    $ffmpeg = Find-Ffmpeg
    if (-not $ffmpeg) {
        return @{ ok = $false; error = 'FFmpeg not found on server' }
    }

    Stop-Ffmpeg
    Start-Sleep -Milliseconds 500

    $state = Get-StreamState
    foreach ($key in @($state.tables.Keys)) {
        $state.tables[$key] = 'stopped'
    }
    $state.tables[$TableId] = 'live'
    Set-StreamState $state

    $streamLog = Join-Path $StreamingDir ("stream-$TableId.log")
    $args = Build-FfmpegArgs $camera
    if (-not (Start-FfmpegDetached $ffmpeg $args $streamLog)) {
        $state.tables[$TableId] = 'stopped'
        Set-StreamState $state
        return @{ ok = $false; error = 'Failed to launch FFmpeg' }
    }

    if (-not (Test-FfmpegStarted 6000)) {
        Stop-Ffmpeg
        $state.tables[$TableId] = 'stopped'
        Set-StreamState $state
        return @{ ok = $false; error = ('FFmpeg exited right after start - check streaming/stream-' + $TableId + '.log') }
    }

    $ffmpegPid = (Get-FfmpegPids | Select-Object -First 1)
    $ws = @{ managedPid = $ffmpegPid; activeTable = $TableId; startedAt = (Get-Epoch) } | ConvertTo-Json -Compress
    Write-Utf8NoBom $WorkerStateFile $ws
    Write-WorkerLog ('Started FFmpeg for ' + $TableId + ' (PID ' + $ffmpegPid + ')')

    return @{
        ok         = $true
        action     = 'started'
        tableId    = $TableId
        sourcePort = (Get-RtspPort $camera.rtsp)
        pid        = $ffmpegPid
    }
}

function Stop-AllStreams {
    Stop-Ffmpeg
    $state = Get-StreamState
    foreach ($key in @($state.tables.Keys)) {
        $state.tables[$key] = 'stopped'
    }
    Set-StreamState $state
    Write-WorkerLog 'Stopped all FFmpeg streams'
    return @{ ok = $true; action = 'stopped' }
}

function Invoke-WorkerCommand($Command) {
    $action = ([string]$Command.action).ToLower().Trim()
    $tableId = Sanitize-TableId ([string]$Command.tableId)
    if ($action -eq 'stop') { return Stop-AllStreams }
    if ($action -eq 'start' -and $tableId -ne '') { return (Start-TableStream $tableId) }
    return @{ ok = $false; error = 'Unknown command action' }
}

function Invoke-Recovery {
    if ((Get-Epoch) - $script:LastRecoveryAt -lt 8) { return }
    $script:LastRecoveryAt = Get-Epoch
    if (Test-Path $CommandFile) { return }

    $liveTable = Get-LiveTableId
    $ffmpegRunning = Test-FfmpegRunning
    $activeTable = ''
    if (Test-Path $WorkerStateFile) {
        try {
            $ws = Get-Content $WorkerStateFile -Raw | ConvertFrom-Json
            $activeTable = [string]$ws.activeTable
        } catch {}
    }

    if ($liveTable -eq '') {
        if ($ffmpegRunning) {
            Write-WorkerLog 'No table marked live - stopping stray FFmpeg'
            Stop-Ffmpeg
        }
        return
    }

    if (-not $ffmpegRunning -or $activeTable -ne $liveTable) {
        Write-WorkerLog "Recovery: ensuring live table $liveTable"
        $result = Start-TableStream $liveTable
        if (-not $result.ok) {
            Write-WorkerLog ('Recovery failed: ' + $result.error)
        }
    }
}

# --- Main ---
Ensure-WorkerDir
Write-Heartbeat
Write-WorkerLog "=== Stream worker started (PowerShell PID $PID) ==="

while ($true) {
    Write-Heartbeat

    if (Test-Path $CommandFile) {
        try {
            $command = Get-Content $CommandFile -Raw | ConvertFrom-Json
            $cmdId = [string]$command.id
            if ($cmdId -ne '' -and $cmdId -ne $script:LastProcessedId) {
                Write-WorkerLog ("Command $cmdId : $($command.action) $($command.tableId)")
                $result = Invoke-WorkerCommand $command
                $result.id = $cmdId
                $result.completedAt = Get-Epoch
                Write-Result $result
                $script:LastProcessedId = $cmdId
                Remove-Item $CommandFile -Force -ErrorAction SilentlyContinue
            }
        } catch {
            Write-WorkerLog ("Command parse error: $_")
        }
    } else {
        Invoke-Recovery
    }

    if (Test-Path $LogFile) {
        $lines = Get-Content $LogFile
        if ($lines.Count -gt 2000) {
            $lines | Select-Object -Last 1500 | Set-Content $LogFile -Encoding UTF8
        }
    }

    Start-Sleep -Milliseconds 500
}
