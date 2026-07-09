<?php
/**
 * RailShot TV — Stream Watchdog (Windows Plesk / PHP CLI)
 *
 * Checks if each camera's FFmpeg process is running.
 * If not, starts it in the background.
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

// Catch all PHP errors and write them to the log
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    wlog("PHP ERROR [$errno] $errstr in $errfile on line $errline");
    return true;
});

wlog("=== Watchdog started ===");
wlog("PHP version: " . PHP_VERSION);
wlog("__DIR__: " . __DIR__);
wlog("__FILE__: " . __FILE__);

// ── Config ────────────────────────────────────────────────────────────────
// Try __DIR__ first, then fall back to the known absolute path
$confFile = __DIR__ . DIRECTORY_SEPARATOR . 'cameras.conf';
wlog("Looking for cameras.conf at: $confFile");

if (!file_exists($confFile)) {
    $confFile = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\cameras.conf';
    wlog("Not found, trying fallback: $confFile");
}

if (!file_exists($confFile)) {
    wlog("ERROR: cameras.conf not found at either path. Aborting.");
    exit(1);
}

wlog("Found cameras.conf at: $confFile");

// ── Find FFmpeg ───────────────────────────────────────────────────────────
$ffmpegCandidates = [
    // First check inside the streaming folder (within open_basedir)
    __DIR__ . '\\ffmpeg.exe',
    'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\ffmpeg.exe',
    // Windows Temp is also allowed
    'C:\\WINDOWS\\Temp\\ffmpeg.exe',
];

$ffmpeg = null;

// Check PATH first
exec('where ffmpeg 2>NUL', $whereOut, $whereRet);
if ($whereRet === 0 && !empty($whereOut[0])) {
    $ffmpeg = trim($whereOut[0]);
    wlog("Found ffmpeg in PATH: $ffmpeg");
}

if (!$ffmpeg) {
    foreach ($ffmpegCandidates as $candidate) {
        if (file_exists($candidate)) {
            $ffmpeg = $candidate;
            wlog("Found ffmpeg at: $ffmpeg");
            break;
        }
    }
}

if (!$ffmpeg) {
    wlog("ERROR: ffmpeg not found in PATH or any known location.");
    wlog("Checked: " . implode(', ', $ffmpegCandidates));
    exit(1);
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
    $pidFile   = 'C:\\WINDOWS\\Temp\\railshot-' . $table . '.pid';

    wlog("Checking camera: $table");

    // ── Check if process is still running ─────────────────────────────────
    $running = false;
    if (file_exists($pidFile)) {
        $pid = (int) file_get_contents($pidFile);
        if ($pid > 0) {
            exec("tasklist /FI \"PID eq $pid\" /NH 2>NUL", $taskOut);
            foreach ($taskOut as $taskLine) {
                if (strpos($taskLine, (string)$pid) !== false) {
                    $running = true;
                    break;
                }
            }
            wlog("  PID $pid is " . ($running ? "running" : "NOT running"));
        }
    } else {
        wlog("  No PID file found — stream not started yet");
    }

    if ($running) {
        wlog("  Stream OK — skipping");
        continue;
    }

    // ── Start FFmpeg ───────────────────────────────────────────────────────
    $ytUrl   = "rtmp://a.rtmp.youtube.com/live2/$ytKey";
    $ffmpegQ = '"' . $ffmpeg . '"';
    $rtspQ   = '"' . $rtspUrl . '"';
    $ytUrlQ  = '"' . $ytUrl . '"';

    $cmd = "$ffmpegQ -loglevel warning -rtsp_transport tcp -stimeout 10000000 "
         . "-i $rtspQ -c:v copy -c:a aac -b:a 128k -ar 44100 "
         . "-f flv -flvflags no_duration_filesize $ytUrlQ";

    wlog("  Starting FFmpeg: $cmd");

    // Use WScript.Shell to launch detached
    try {
        $wsh = new COM('WScript.Shell');
        $wshCmd = 'cmd /c start "" /B ' . $cmd . ' >> "' . $streamLog . '" 2>&1';
        wlog("  WScript command: $wshCmd");
        $wsh->Run($wshCmd, 0, false);
        wlog("  FFmpeg launched via WScript.Shell");
    } catch (Exception $e) {
        wlog("  WScript.Shell failed: " . $e->getMessage() . " — trying popen fallback");
        $handle = popen('start /B ' . $cmd . ' >> "' . $streamLog . '" 2>&1', 'r');
        if ($handle) {
            pclose($handle);
            wlog("  FFmpeg launched via popen");
        } else {
            wlog("  ERROR: Could not start FFmpeg");
            continue;
        }
    }

    // Find the new PID
    sleep(2);
    exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH /FO CSV 2>NUL', $procs);
    $newPid = 0;
    foreach (array_reverse($procs) as $proc) {
        $cols = str_getcsv($proc);
        if (isset($cols[1]) && is_numeric(trim($cols[1], '"'))) {
            $newPid = (int) trim($cols[1], '"');
            break;
        }
    }

    file_put_contents($pidFile, $newPid);
    wlog("  FFmpeg started — PID $newPid saved to $pidFile");

    // Trim stream log
    if (file_exists($streamLog)) {
        $streamLines = file($streamLog, FILE_IGNORE_NEW_LINES);
        if (count($streamLines) > 500) {
            file_put_contents($streamLog, implode("\n", array_slice($streamLines, -500)) . "\n");
        }
    }
}

if ($cameraCount === 0) {
    wlog("WARNING: No cameras found in cameras.conf");
}

// Trim watchdog log to last 1000 lines
if (file_exists($logFile)) {
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (count($allLines) > 1000) {
        file_put_contents($logFile, implode("\n", array_slice($allLines, -1000)) . "\n");
    }
}

wlog("=== Watchdog done ===");
