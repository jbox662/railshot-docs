@echo off
REM Open Windows Firewall for MediaMTX (run as Administrator)

netsh advfirewall firewall delete rule name="RailShot MediaMTX HLS" >nul 2>&1
netsh advfirewall firewall delete rule name="RailShot MediaMTX WebRTC TCP" >nul 2>&1
netsh advfirewall firewall delete rule name="RailShot MediaMTX WebRTC UDP" >nul 2>&1

netsh advfirewall firewall add rule name="RailShot MediaMTX HLS" dir=in action=allow protocol=TCP localport=8888
netsh advfirewall firewall add rule name="RailShot MediaMTX WebRTC TCP" dir=in action=allow protocol=TCP localport=8889
netsh advfirewall firewall add rule name="RailShot MediaMTX WebRTC UDP" dir=in action=allow protocol=UDP localport=8189

echo Firewall rules added for TCP 8888, 8889 and UDP 8189.
pause
