# RailShot Admin Panel

Password-protected admin at **`/admin/`** for managing live cameras and site content on Plesk.

## First-time setup

1. Upload all site files including `admin/`, `api/`, and `App_Data/railshot/`
2. Visit **https://yourdomain.com/admin/**
3. Create your admin password (username: `admin`)
4. Sign in and configure cameras + site text

## What you can edit

- **Live tables** — name, path id, description, RTSP URL
- **MediaMTX host** and protocol (HLS / WebRTC)
- **Site content** — hero title/subtitle, contact email, download note
- **Admin password**

## MediaMTX on VPS

Saving cameras updates the website immediately. You still need to copy the generated **MediaMTX YAML** into `C:\mediamtx\mediamtx.yml` on the VPS and restart MediaMTX when adding new camera paths.

## Security

- `App_Data/railshot/admin.json` stores your password hash — never commit it to GitHub
- RTSP URLs with passwords are stored server-side only (not exposed on the public live API)
- Change the default admin password after setup
