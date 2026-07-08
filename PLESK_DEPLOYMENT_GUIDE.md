# RailShot TV - Plesk Deployment Guide

This guide provides step-by-step instructions for deploying your RailShot TV static website to Windows Server 2025 with Plesk.

---

## What You Have

This is a **static website** - meaning it consists of simple HTML, CSS, and JavaScript files that can be uploaded directly to any web server. No special server configuration, databases, or Node.js installation is required.

**Files included:**
- `index.html` - Main website page
- `css/styles.css` - All styling and design
- `js/config.js` - Stream configuration (edit this to add your streams)
- `js/app.js` - Website functionality
- `manifest.json` - PWA configuration for iOS
- `images/` - Folder for icons and images

---

## Step 1: Prepare Your Files

1. **Download all files** from the `railshottv-static` folder
2. Keep the folder structure intact:
   ```
   railshottv-static/
   ├── index.html
   ├── manifest.json
   ├── css/
   │   └── styles.css
   ├── js/
   │   ├── config.js
   │   └── app.js
   └── images/
       ├── icon-192.png
       └── icon-512.png
   ```

---

## Step 2: Access Plesk

1. Log in to your Plesk control panel
2. Navigate to **Websites & Domains**
3. Find your domain (railshottv.com) or click **Add Domain** if you haven't added it yet

---

## Step 3: Upload Files to Plesk

### Option A: Using Plesk File Manager (Easiest)

1. In Plesk, go to **Websites & Domains** → **railshottv.com**
2. Click on **File Manager**
3. Navigate to the **httpdocs** folder (this is your website root directory)
4. **Delete any existing files** in httpdocs (like default index.html)
5. Click **Upload Files**
6. Upload all files and folders from your `railshottv-static` directory
7. Ensure the folder structure is maintained:
   - `index.html` should be directly in httpdocs
   - `css/`, `js/`, and `images/` folders should be in httpdocs

### Option B: Using FTP (Alternative)

1. In Plesk, go to **Websites & Domains** → **railshottv.com** → **FTP Access**
2. Note your FTP credentials (username, password, server address)
3. Use an FTP client like FileZilla to connect
4. Navigate to the `httpdocs` folder
5. Upload all files from `railshottv-static` to `httpdocs`

---

## Step 4: Configure Your Domain

1. In Plesk, go to **Websites & Domains** → **railshottv.com**
2. Click **Hosting Settings**
3. Ensure these settings:
   - **Document root**: `/httpdocs`
   - **Preferred domain**: www.railshottv.com (or railshottv.com)
4. Click **OK** to save

---

## Step 5: Enable HTTPS (SSL Certificate)

1. In Plesk, go to **Websites & Domains** → **railshottv.com**
2. Click **SSL/TLS Certificates**
3. Choose one of these options:
   - **Let's Encrypt** (Free, recommended): Click "Install" and follow the wizard
   - **Upload existing certificate**: If you have one from GoDaddy
4. After installation, go back to **Hosting Settings**
5. Check **Permanent SEO-safe 301 redirect from HTTP to HTTPS**
6. Click **OK**

---

## Step 6: Configure PWA Icons (Optional)

For the best iOS home screen experience, you should add proper app icons:

1. Create two PNG icons:
   - `icon-192.png` (192x192 pixels)
   - `icon-512.png` (512x512 pixels)
2. Upload them to the `images/` folder in Plesk File Manager
3. The icons should feature your RailShot TV branding

If you skip this step, the PWA will still work but won't have custom icons.

---

## Step 7: Test Your Website

1. Open your browser and visit: `https://www.railshottv.com`
2. You should see the RailShot TV homepage with the elegant emerald green design
3. Test these features:
   - Click on a stream card to open the video player
   - Test navigation links (Home, Live, About)
   - Test on mobile device (iOS recommended)
   - Try adding to home screen on iOS (Safari → Share → Add to Home Screen)

---

## How to Add Your Own Streams

To add, edit, or remove streams:

1. In Plesk File Manager, navigate to `httpdocs/js/config.js`
2. Click **Edit** (or download, edit locally, and re-upload)
3. Modify the `liveStreams` and `recentStreams` arrays
4. Follow the instructions in the file comments

**Example: Adding a YouTube Live stream**

```javascript
{
    id: 7,
    title: "My Championship Match",
    description: "Watch my billiard match live!",
    streamUrl: "https://www.youtube.com/embed/YOUR_VIDEO_ID",
    thumbnailUrl: "https://example.com/thumbnail.jpg",
    viewCount: 0,
    status: "live"
}
```

**To get YouTube video ID:**
- From URL `https://www.youtube.com/watch?v=dQw4w9WgXcQ`
- The ID is: `dQw4w9WgXcQ`
- Embed URL: `https://www.youtube.com/embed/dQw4w9WgXcQ`

---

## Troubleshooting

### Website shows "403 Forbidden" or "404 Not Found"

**Solution:**
- Ensure `index.html` is directly in the `httpdocs` folder (not in a subfolder)
- Check file permissions in Plesk File Manager (should be readable)
- Verify Document Root is set to `/httpdocs` in Hosting Settings

### CSS/JavaScript not loading (website looks broken)

**Solution:**
- Verify the folder structure is correct (`css/`, `js/` folders exist)
- Check that file paths in `index.html` start with `/` (e.g., `/css/styles.css`)
- Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)

### Video player not working

**Solution:**
- Ensure you're using valid embed URLs (not regular video URLs)
- Check that the stream URLs in `config.js` are correct
- Some platforms require specific embed permissions

### HTTPS not working

**Solution:**
- Ensure SSL certificate is properly installed in Plesk
- Check that HTTP to HTTPS redirect is enabled
- Wait 5-10 minutes after SSL installation for changes to propagate

### PWA not installing on iOS

**Solution:**
- Ensure you're using Safari browser on iOS
- HTTPS must be enabled
- Add proper icon files to the `images/` folder
- Clear Safari cache and try again

---

## Updating Your Website

To make changes to your website:

1. Edit the files locally on your computer
2. Upload the modified files to Plesk File Manager (overwrite existing)
3. Clear browser cache to see changes

**Common updates:**
- **Add streams**: Edit `js/config.js`
- **Change colors**: Edit `css/styles.css`
- **Modify text**: Edit `index.html`

---

## Performance Optimization (Optional)

For better performance, you can enable these Plesk features:

1. **Gzip Compression**:
   - Go to **Apache & nginx Settings**
   - Enable **Gzip compression**

2. **Browser Caching**:
   - Add this to `.htaccess` file in httpdocs:
   ```apache
   <IfModule mod_expires.c>
     ExpiresActive On
     ExpiresByType image/jpg "access plus 1 year"
     ExpiresByType image/jpeg "access plus 1 year"
     ExpiresByType image/png "access plus 1 year"
     ExpiresByType text/css "access plus 1 month"
     ExpiresByType application/javascript "access plus 1 month"
   </IfModule>
   ```

---

## Adding Backend Features Later

This is currently a static website. If you want to add user accounts, databases, or admin panels later, you have two options:

**Option 1: Keep it simple with third-party services**
- Use Firebase for authentication and database
- Use Airtable or Google Sheets as a simple CMS
- Embed forms using Typeform or Google Forms

**Option 2: Migrate to a server with Node.js support**
- Move to a Linux VPS (Ubuntu recommended)
- Deploy the full Node.js version of RailShot TV
- This requires technical expertise or developer assistance

---

## Support

If you encounter issues:

1. Check the Troubleshooting section above
2. Review Plesk documentation: https://docs.plesk.com
3. Contact your hosting provider's support team
4. Verify all files were uploaded correctly with proper folder structure

---

## Summary Checklist

- [ ] Downloaded all files from railshottv-static folder
- [ ] Logged into Plesk control panel
- [ ] Uploaded files to httpdocs folder
- [ ] Configured domain settings
- [ ] Installed SSL certificate (HTTPS)
- [ ] Tested website on desktop and mobile
- [ ] Edited config.js to add your own streams
- [ ] Added custom PWA icons (optional)
- [ ] Tested PWA installation on iOS (optional)

---

**Congratulations!** Your RailShot TV website is now live. Visit https://www.railshottv.com to see it in action.

**Document Version**: 1.0  
**Last Updated**: February 2026  
**Author**: Manus AI
