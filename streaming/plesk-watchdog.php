<?php
/**
 * RailShot TV — Stream Watchdog (Windows Plesk / PHP CLI)
 *
 * Checks if each camera's FFmpeg process is running using wmic.
 * If not, starts it in the background via WScript.Shell.
 * Run every minute via Plesk Scheduled Tasks → Run a PHP script.
 *
 * SETUP in Plesk:
 *   Scheduled Tasks → Add Task
 *   Task type : Run a PHP script
 *   Script    : httpdocs/streaming/plesk-watchdog.php
 *   Schedule  : * * * * *  (every minute)
 *
 * LOG FILE: C:\Inetpub\vhosts\railshottv.com\httpdocs\streaming\watchdog.log
 */

// ── Logging helper ────────────────────────────────────────────────────────
$logFile = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\watchdog.log';

function wlog($msg) {
    global $logFile;
    $line = date('[Y-m-d H:i:s]') . ' ' . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    wlog("PHP ERROR [$errno] $errstr in $errfile on line $errline");
    return true;
});

wlog("=== Watchdog started ===");
wlog("PHP version: " . PHP_VERSION);

// ── Config ────────────────────────────────────────────────────────────────
$confFile = __DIR__ . DIRECTORY_SEPARATOR . 'cameras.conf';
if (!file_exists($confFile)) {
    $confFile = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\cameras.conf';
}
if (!file_exists($confFile)) {
    wlog("ERROR: cameras.conf not found. Aborting.");
    exit(1);
}
wlog("Found cameras.conf at: $confFile");

// ── Find FFmpeg ───────────────────────────────────────────────────────────
$ffmpegCandidates = [
    __DIR__ . '\\ffmpeg.exe',
    'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\ffmpeg.exe',
    'C:\\WINDOWS\\Temp\\ffmpeg.exe',
];

$ffmpeg = null;
foreach ($ffmpegCandidates as $candidate) {
    if (file_exists($candidate)) {
        $ffmpeg = $candidate;
        wlog("Found ffmpeg at: $ffmpeg");
        break;
    }
}

if (!$ffmpeg) {
    // Try PATH as last resort
    exec('where ffmpeg 2>NUL', $whereOut, $whereRet);
    if ($whereRet === 0 && !empty($whereOut[0])) {
        $ffmpeg = trim($whereOut[0]);
        wlog("Found ffmpeg in PATH: $ffmpeg");
    }
}

if (!$ffmpeg) {
    wlog("ERROR: ffmpeg not found. Aborting.");
    exit(1);
}

// ── Check if ANY ffmpeg is already running (via wmic) ─────────────────────
function isFFmpegRunning() {
    exec('wmic process where "name=\'ffmpeg.exe\'" get ProcessId /FORMAT:LIST 2>NUL', $out, $ret);
    foreach ($out as $line) {
        if (stripos($line, 'ProcessId=') !== false) {
            $pid = (int) trim(str_ireplace('ProcessId=', '', $line));
            if ($pid > 0) return $pid;
        }
    }
    // Fallback: tasklist
    exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH 2>NUL', $tOut);
    foreach ($tOut as $tLine) {
        if (stripos($tLine, 'ffmpeg.exe') !== false) {
            // Extract PID from tasklist output (format: name, pid, ...)
            $parts = preg_split('/\s+/', trim($tLine));
            if (isset($parts[1]) && is_numeric($parts[1])) return (int)$parts[1];
            return -1; // running but couldn't get PID
        }
    }
    return 0; // not running
}

// ── Parse cameras.conf ────────────────────────────────────────────────────
$lines = file($confFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$cameraCount = 0;

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;

    $parts = array_map('trim', explode('|', $line));
    if (count($parts) < 3) continue;

    [$table, $rtspUrl, $ytKey] = $parts;
    if (!$table || !$rtspUrl || !$ytKey) continue;

    $cameraCount++;
    $streamLog = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\stream-' . $table . '.log';

    wlog("Checking camera: $table");

    // ── Check if ffmpeg is already running ────────────────────────────────
    $runningPid = isFFmpegRunning();
    if ($runningPid !== 0) {
        wlog("  FFmpeg already running (PID $runningPid) — skipping");
        continue;
    }

    wlog("  FFmpeg not running — starting stream");

    // ── Build FFmpeg command ───────────────────────────────────────────────
    $ytUrl  = "rtmp://a.rtmp.youtube.com/live2/$ytKey";
    $ffmpegQ = '"' . $ffmpeg . '"';
    $rtspQ   = '"' . $rtspUrl . '"';
    $ytUrlQ  = '"' . $ytUrl . '"';
    $logQ    = '"' . $streamLog . '"';

    $cmd = "$ffmpegQ -loglevel warning -rtsp_transport tcp -timeout 10000000 "
         . "-i $rtspQ -c:v copy -c:a aac -b:a 128k -ar 44100 "
         . "-f flv -flvflags no_duration_filesize $ytUrlQ";

    wlog("  Command: $cmd");

    // ── Launch via WScript.Shell (detached, no window) ────────────────────
    $launched = false;
    try {
        $wsh = new COM('WScript.Shell');
        $wshCmd = 'cmd /c start "" /B ' . $cmd . ' >> ' . $logQ . ' 2>&1';
        $wsh->Run($wshCmd, 0, false);
        $launched = true;
        wlog("  Launched via WScript.Shell");
    } catch (Exception $e) {
        wlog("  WScript.Shell failed: " . $e->getMessage());
    }

    if (!$launched) {
        // Fallback: popen
        $handle = popen('cmd /c start "" /B ' . $cmd . ' >> ' . $logQ . ' 2>&1', 'r');
        if ($handle) {
            pclose($handle);
            $launched = true;
            wlog("  Launched via popen fallback");
        }
    }

    if (!$launched) {
        wlog("  ERROR: Could not launch FFmpeg");
        continue;
    }

    // Verify it started
    sleep(3);
    $verifyPid = isFFmpegRunning();
    if ($verifyPid !== 0) {
        wlog("  Verified running — PID $verifyPid");
    } else {
        wlog("  WARNING: FFmpeg may not have started — check stream log: $streamLog");
    }
}

if ($cameraCount === 0) {
    wlog("WARNING: No cameras found in cameras.conf");
}

// Trim watchdog log to last 1000 lines
$allLines = file($logFile, FILE_IGNORE_NEW_LINES);
if (count($allLines) > 1000) {
    file_put_contents($logFile, implode("\n", array_slice($allLines, -1000)) . "\n");
}

wlog("=== Watchdog done ===");
