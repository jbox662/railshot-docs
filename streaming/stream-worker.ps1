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
$script:ConsecutiveFailures = 0
$script:GiveUpUntil = 0
$script:CrashCount = 0
$script:NextRetryAt = 0

function Get-ConfigPath {
    return Join-Path $RootDir 'App_Data\railshot\config.json'
}

function Find-CameraFromConfig([string]$TableId) {
    $configPath = Get-ConfigPath
    if (-not (Test-Path $configPath)) { return $null }
    try {
        $config = Get-Content $configPath -Raw | ConvertFrom-Json
    } catch {
        return $null
    }

    $rtspByName = @{}
    foreach ($cam in @($config.cameras)) {
        $name = [string]$cam.name
        $rtsp = [string]$cam.rtspUrl
        if ($name -ne '' -and $rtsp -ne '') {
            $rtspByName[$name] = $rtsp
        }
    }

    foreach ($venue in @($config.live.venues)) {
        foreach ($table in @($venue.tables)) {
            $id = Sanitize-TableId ([string]$table.id)
            if ($id -ne $TableId) { continue }
            $cameraName = [string]$table.cameraName
            $rtsp = ''
            if ($cameraName -ne '' -and $rtspByName.ContainsKey($cameraName)) {
                $rtsp = $rtspByName[$cameraName]
            }
            if ($rtsp -eq '') {
                $rtsp = [string]$table.rtspUrl
            }
            $streamKey = [string]$table.streamKey
            if ($rtsp -ne '' -and $streamKey -ne '') {
                return [PSCustomObject]@{
                    table = $id
                    rtsp  = $rtsp
                    ytKey = $streamKey
                }
            }
            return $null
        }
    }
    return $null
}

function Find-Camera([string]$TableId) {
    $id = Sanitize-TableId $TableId
    $fromConfig = Find-CameraFromConfig $id
    if ($fromConfig) { return $fromConfig }
    foreach ($cam in Parse-Cameras) {
        if ($cam.table -eq $id) { return $cam }
    }
    return $null
}

function Get-StreamLogTail([string]$Path, [int]$LineCount = 4) {
    if (-not (Test-Path $Path)) { return '' }
    try {
        $tail = @(Get-Content $Path -Tail $LineCount -ErrorAction Stop)
    } catch {
        return ''
    }
    if ($tail.Count -eq 0) { return '' }
    $text = ($tail -join ' ').Trim()
    if ($text.Length -gt 280) {
        $text = $text.Substring(0, 280) + '...'
    }
    return $text
}

function Build-StartFailureMessage([string]$TableId, $Camera, [string]$StreamLog) {
    $msg = 'FFmpeg exited right after start - check streaming/stream-' + $TableId + '.log'
    $port = Get-RtspPort $Camera.rtsp
    if ($port -ne '') {
        $msg += ' (RTSP port ' + $port + ')'
    }
    if ($TableId -eq 'table2' -and $port -eq '8554') {
        $msg += ' - table2 should use port 8555'
    }
    $hint = Get-StreamLogTail $StreamLog
    if ($hint -ne '') {
        $msg += '. ' + $hint
    }
    return $msg
}

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

function Test-StreamLogFatalError([string]$TableId) {
    $log = Join-Path $StreamingDir ('stream-' + $TableId + '.log')
    if (-not (Test-Path $log)) { return $false }
    try {
        $tail = @(Get-Content $log -Tail 40 -ErrorAction Stop)
    } catch {
        return $false
    }
    $text = ($tail -join ' ').ToLower()
    if ($text -match '401 unauthorized' -or $text -match 'authorization failed') {
        return $true
    }
    if ($text -match 'error opening input file rtsp') {
        return $true
    }
    return $false
}

function Get-WorkerState() {
    if (-not (Test-Path $WorkerStateFile)) { return $null }
    try {
        return Get-Content $WorkerStateFile -Raw | ConvertFrom-Json
    } catch {
        return $null
    }
}

function Build-FfmpegLaunchBat($Ffmpeg, $Camera, $StreamLog, [string]$TableId) {
    $ytKey = [string]$Camera.ytKey
    $rtsp = [string]$Camera.rtsp
    if ([string]::IsNullOrWhiteSpace($ytKey) -or [string]::IsNullOrWhiteSpace($rtsp)) {
        return $null
    }
    $ytUrl = 'rtmp://a.rtmp.youtube.com/live2/' + $ytKey.Trim()
    $batPath = Join-Path $StreamingDir ('launch-' + $TableId + '.bat')
    $lines = @(
        '@echo off',
        'cd /d "' + $StreamingDir + '"',
        'start "" /B "' + $Ffmpeg + '" -loglevel warning -fflags +genpts -rtsp_transport tcp -stimeout 10000000 -i "' + $rtsp + '" -c:v copy -an -f flv -flvflags no_duration_filesize "' + $ytUrl + '" >> "' + $StreamLog + '" 2>&1'
    )
    Set-Content -Path $batPath -Value ($lines -join "`r`n") -Encoding ASCII
    return $batPath
}

function Start-FfmpegDetached($Ffmpeg, $Camera, $StreamLog, [string]$TableId) {
    $batPath = Build-FfmpegLaunchBat $Ffmpeg $Camera $StreamLog $TableId
    if (-not $batPath) {
        Add-Content -Path $StreamLog -Value 'ERROR: missing RTSP URL or YouTube stream key' -Encoding UTF8
        return $false
    }
    Add-Content -Path $StreamLog -Value ('--- start ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' ---') -Encoding UTF8
    Write-WorkerLog ('Launch script: ' + $batPath)
    try {
        $proc = Start-Process -FilePath $batPath -WindowStyle Hidden -PassThru -ErrorAction Stop
        return $null -ne $proc
    } catch {
        Add-Content -Path $StreamLog -Value ('Start-Process failed: ' + $_) -Encoding UTF8
        return $false
    }
}

function Test-FfmpegStarted([int]$MaxMs = 10000) {
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
        return @{ ok = $false; error = ('No camera configured for ' + $TableId + ' - set camera and stream key in admin, then Save live settings.') }
    }
    if ([string]::IsNullOrWhiteSpace([string]$camera.ytKey)) {
        return @{ ok = $false; error = ('YouTube stream key missing for ' + $TableId + ' in admin.') }
    }

    $ffmpeg = Find-Ffmpeg
    if (-not $ffmpeg) {
        return @{ ok = $false; error = 'FFmpeg not found on server' }
    }

    $streamLog = Join-Path $StreamingDir ("stream-$TableId.log")
    $port = Get-RtspPort $camera.rtsp
    Write-WorkerLog ('Starting ' + $TableId + ' RTSP port ' + $port)
    if ($TableId -eq 'table1' -and $port -eq '8555') {
        Write-WorkerLog 'WARNING: table1 is using port 8555 - should be 8554'
    }
    if ($TableId -eq 'table2' -and $port -eq '8554') {
        Write-WorkerLog 'WARNING: table2 is using port 8554 - should be 8555'
    }
    if (Test-StreamLogFatalError $TableId) {
        return @{ ok = $false; error = ('Camera auth/connection failed for ' + $TableId + ' - fix RTSP URL/password in admin (port ' + $port + ')') }
    }

    Stop-Ffmpeg
    Start-Sleep -Milliseconds 500

    $state = Get-StreamState
    foreach ($key in @($state.tables.Keys)) {
        $state.tables[$key] = 'stopped'
    }
    $state.tables[$TableId] = 'live'
    Set-StreamState $state

    if (-not (Start-FfmpegDetached $ffmpeg $camera $streamLog $TableId)) {
        $state.tables[$TableId] = 'stopped'
        Set-StreamState $state
        return @{ ok = $false; error = 'Failed to launch FFmpeg' }
    }

    if (-not (Test-FfmpegStarted 10000)) {
        Stop-Ffmpeg
        $state.tables[$TableId] = 'stopped'
        Set-StreamState $state
        $failMsg = Build-StartFailureMessage $TableId $camera $streamLog
        Write-WorkerLog ('Start failed: ' + $failMsg)
        return @{ ok = $false; error = $failMsg }
    }

    $script:ConsecutiveFailures = 0
    $script:CrashCount = 0
    $script:NextRetryAt = 0
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
    if ($action -eq 'stop') {
        $script:ConsecutiveFailures = 0
        $script:GiveUpUntil = 0
        $script:CrashCount = 0
        $script:NextRetryAt = 0
        return Stop-AllStreams
    }
    if ($action -eq 'start' -and $tableId -ne '') {
        $script:ConsecutiveFailures = 0
        $script:GiveUpUntil = 0
        $script:CrashCount = 0
        $script:NextRetryAt = 0
        return (Start-TableStream $tableId)
    }
    return @{ ok = $false; error = 'Unknown command action' }
}

function Invoke-Recovery {
    $now = Get-Epoch
    if ($now -lt $script:GiveUpUntil) { return }
    if ($now -lt $script:NextRetryAt) { return }
    if ($now - $script:LastRecoveryAt -lt 10) { return }
    $script:LastRecoveryAt = $now
    if (Test-Path $CommandFile) { return }

    $liveTable = Get-LiveTableId
    $ffmpegRunning = Test-FfmpegRunning
    $ws = Get-WorkerState
    $activeTable = ''
    $startedAt = 0
    if ($ws) {
        $activeTable = [string]$ws.activeTable
        $startedAt = [int]$ws.startedAt
    }

    if ($liveTable -eq '') {
        if ($ffmpegRunning) {
            Write-WorkerLog 'No table marked live - stopping stray FFmpeg'
            Stop-Ffmpeg
        }
        $script:ConsecutiveFailures = 0
        $script:CrashCount = 0
        return
    }

    if ($ffmpegRunning -and $activeTable -eq $liveTable) {
        if ($startedAt -gt 0 -and ($now - $startedAt) -gt 60) {
            $script:CrashCount = 0
            $script:ConsecutiveFailures = 0
        }
        return
    }

    if (Test-StreamLogFatalError $liveTable) {
        Write-WorkerLog ('Fatal camera error on ' + $liveTable + ' - going off air until admin fixes RTSP/password')
        Stop-AllStreams
        $script:GiveUpUntil = $now + 600
        $script:CrashCount = 0
        return
    }

    if ($ffmpegRunning -eq $false -and $activeTable -eq $liveTable -and $startedAt -gt 0) {
        $script:CrashCount++
        $waitSec = [Math]::Min(300, 30 * $script:CrashCount)
        $script:NextRetryAt = $now + $waitSec
        Write-WorkerLog ('FFmpeg stopped on ' + $liveTable + ' (crash ' + $script:CrashCount + ') - retry in ' + $waitSec + 's')
    }

    if ($script:CrashCount -ge 5) {
        Write-WorkerLog ('Too many crashes on ' + $liveTable + ' - going off air for 5 minutes')
        Stop-AllStreams
        $script:GiveUpUntil = $now + 300
        $script:CrashCount = 0
        $script:NextRetryAt = 0
        return
    }

    if ($now -lt $script:NextRetryAt) { return }

    if ($script:ConsecutiveFailures -ge 3) {
        Write-WorkerLog ('Giving up on ' + $liveTable + ' after 3 start failures - off air 5 minutes')
        Stop-AllStreams
        $script:GiveUpUntil = $now + 300
        $script:ConsecutiveFailures = 0
        $script:CrashCount = 0
        return
    }

    Write-WorkerLog ('Recovery: restarting ' + $liveTable)
    $result = Start-TableStream $liveTable
    if (-not $result.ok) {
        $script:ConsecutiveFailures++
        $script:NextRetryAt = $now + (45 * $script:ConsecutiveFailures)
        Write-WorkerLog ('Recovery failed (' + $script:ConsecutiveFailures + '/3): ' + $result.error)
    } else {
        $script:NextRetryAt = $now + 30
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
