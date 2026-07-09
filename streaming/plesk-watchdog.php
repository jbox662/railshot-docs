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
 *   Script    : streaming/plesk-watchdog.php   (relative to httpdocs)
 *   Schedule  : * * * * *  (every minute)
 */

// ── Config ────────────────────────────────────────────────────────────────
// Resolve the httpdocs root regardless of how Plesk sets __DIR__
$httpdocs  = rtrim(str_replace('\\', '/', dirname(__DIR__, 0)), '/') . '/';
// __DIR__ is the streaming/ folder; cameras.conf is in the same directory
$confFile  = __DIR__ . DIRECTORY_SEPARATOR . 'cameras.conf';
// Fallback: try the known absolute Plesk path if __DIR__ resolves unexpectedly
if (!file_exists($confFile)) {
    $confFile = 'C:\\Inetpub\\vhosts\\railshottv.com\\httpdocs\\streaming\\cameras.conf';
}
$logDir    = sys_get_temp_dir();   // C:\Windows\Temp on Windows Plesk
$pidDir    = sys_get_temp_dir();

// Find ffmpeg — check common Windows locations
$ffmpegCandidates = [
    'C:\\Users\\funbucket\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-8.1.2-full_build\\bin\\ffmpeg.exe',
    'C:\\ffmpeg\\bin\\ffmpeg.exe',
    'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
    'ffmpeg',  // if it's in PATH
];

$ffmpeg = null;
foreach ($ffmpegCandidates as $candidate) {
    if ($candidate === 'ffmpeg') {
        // Check PATH
        exec('where ffmpeg 2>NUL', $out, $ret);
        if ($ret === 0 && !empty($out[0])) {
            $ffmpeg = 'ffmpeg';
            break;
        }
    } elseif (file_exists($candidate)) {
        $ffmpeg = $candidate;
        break;
    }
}

if (!$ffmpeg) {
    file_put_contents($logDir . '\\railshot-watchdog.log',
        date('[Y-m-d H:i:s]') . " ERROR: ffmpeg not found\n", FILE_APPEND);
    exit(1);
}

// ── Parse cameras.conf ────────────────────────────────────────────────────
if (!file_exists($confFile)) {
    file_put_contents($logDir . '\\railshot-watchdog.log',
        date('[Y-m-d H:i:s]') . " ERROR: cameras.conf not found at $confFile\n", FILE_APPEND);
    exit(1);
}

$lines = file($confFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;

    $parts = array_map('trim', explode('|', $line));
    if (count($parts) < 3) continue;

    [$table, $rtspUrl, $ytKey] = $parts;
    if (!$table || !$rtspUrl || !$ytKey) continue;

    $logFile = $logDir . "\\railshot-stream-$table.log";
    $pidFile = $pidDir . "\\railshot-stream-$table.pid";

    // ── Check if process is still running ─────────────────────────────────
    $running = false;
    if (file_exists($pidFile)) {
        $pid = (int) file_get_contents($pidFile);
        if ($pid > 0) {
            // Check if PID is alive using tasklist
            exec("tasklist /FI \"PID eq $pid\" /NH 2>NUL", $taskOut);
            foreach ($taskOut as $taskLine) {
                if (strpos($taskLine, (string)$pid) !== false) {
                    $running = true;
                    break;
                }
            }
        }
    }

    if ($running) {
        continue; // Already streaming — nothing to do
    }

    // ── Start FFmpeg ───────────────────────────────────────────────────────
    $ytUrl   = "rtmp://a.rtmp.youtube.com/live2/$ytKey";
    $ffmpegQ = '"' . str_replace('"', '\"', $ffmpeg) . '"';
    $rtspQ   = '"' . str_replace('"', '\"', $rtspUrl) . '"';
    $ytUrlQ  = '"' . str_replace('"', '\"', $ytUrl) . '"';

    $cmd = "$ffmpegQ -loglevel warning -rtsp_transport tcp -stimeout 10000000 "
         . "-i $rtspQ -c:v copy -c:a aac -b:a 128k -ar 44100 "
         . "-f flv -flvflags no_duration_filesize $ytUrlQ";

    // Launch detached (START /B keeps it running after PHP exits)
    $wshCmd = "cmd /c start /B $cmd >> \"$logFile\" 2>&1";

    $wsh = new COM('WScript.Shell');
    $wsh->Run($wshCmd, 0, false); // 0 = hidden window, false = don't wait

    // Give it a moment then find the PID by matching ffmpeg processes
    sleep(2);
    exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH /FO CSV 2>NUL', $procs);
    $newPid = 0;
    foreach (array_reverse($procs) as $proc) {
        $cols = str_getcsv($proc);
        if (isset($cols[1]) && is_numeric($cols[1])) {
            $newPid = (int)$cols[1];
            break; // take the most recently started ffmpeg
        }
    }

    file_put_contents($pidFile, $newPid);
    file_put_contents($logFile,
        date('[Y-m-d H:i:s]') . " Started FFmpeg for $table (PID $newPid)\n", FILE_APPEND);

    // Keep log to last 500 lines
    $logLines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (count($logLines) > 500) {
        file_put_contents($logFile, implode("\n", array_slice($logLines, -500)) . "\n");
    }
}

echo "Watchdog OK " . date('Y-m-d H:i:s') . "\n";
