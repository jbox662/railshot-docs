# RailShot TV - Static Website

A beautiful, mobile-optimized billiard livestreaming website that works on any web server.

## What This Is

This is a **static website** version of RailShot TV. It's built with pure HTML, CSS, and JavaScript - no backend server, no database, no complex setup required. Just upload the files to your web server and it works.

## Features

✅ **Mobile-Optimized Design** - Elegant emerald green theme, perfect for iOS devices  
✅ **Video Player** - Embedded video player with landscape orientation lock  
✅ **Stream Listings** - Display live and recent billiard broadcasts  
✅ **PWA Support** - Install as an app on iOS home screen  
✅ **Easy to Edit** - Simple configuration file to add/remove streams  
✅ **No Backend Required** - Works on any web hosting (Plesk, cPanel, etc.)

## Quick Start

1. **Upload all files** to your web server's public directory (usually `httpdocs` or `public_html`)
2. **Edit `js/config.js`** to add your own streams
3. **Visit your domain** - that's it!

## File Structure

```
railshottv-static/
├── index.html              # Main website page
├── manifest.json           # PWA configuration
├── css/
│   └── styles.css         # All styling
├── js/
│   ├── config.js          # Stream configuration (EDIT THIS)
│   └── app.js             # Website functionality
└── images/
    ├── icon-192.png       # PWA icon (192x192)
    └── icon-512.png       # PWA icon (512x512)
```

## How to Add Streams

Open `js/config.js` and edit the arrays:

```javascript
liveStreams: [
    {
        id: 1,
        title: "Your Stream Title",
        description: "Stream description",
        streamUrl: "https://www.youtube.com/embed/VIDEO_ID",
        thumbnailUrl: "https://example.com/thumb.jpg",
        viewCount: 0,
        status: "live"
    }
]
```

### Supported Platforms

- **YouTube**: Use `https://www.youtube.com/embed/VIDEO_ID`
- **Facebook**: Use Facebook video embed URL
- **Vimeo**: Use `https://player.vimeo.com/video/VIDEO_ID`
- **Twitch**: Use `https://player.twitch.tv/?video=VIDEO_ID`

## Deployment

See **PLESK_DEPLOYMENT_GUIDE.md** for detailed instructions on deploying to:
- Windows Server with Plesk
- Any web hosting with FTP access
- cPanel hosting

## Customization

### Change Colors

Edit `css/styles.css` and modify the CSS variables:

```css
:root {
    --primary: #0d7c66;        /* Main brand color */
    --secondary: #bfa888;      /* Accent color */
    --background: #fafaf9;     /* Page background */
}
```

### Change Text

Edit `index.html` to modify:
- Hero title and subtitle
- Section headings
- About section content
- Footer text

## Browser Support

- ✅ iOS Safari 12+
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Edge 90+
- ✅ Safari 14+

## Future Enhancements

This is Phase 1 - a static website. Future phases can add:

- **User Authentication** - Login/signup functionality
- **Database** - Store streams, users, viewing history
- **Admin Panel** - Manage content and users
- **Live Chat** - Real-time chat during streams
- **Analytics** - Track views and engagement

To add these features, you'll need to migrate to a backend framework (Node.js, PHP, etc.) or use third-party services like Firebase.

## Technical Details

- **No dependencies** - Uses vanilla JavaScript
- **CDN for icons** - Lucide icons loaded from CDN
- **Responsive design** - Mobile-first approach
- **PWA ready** - Installable on iOS devices
- **SEO friendly** - Semantic HTML structure

## License

© 2026 RailShot TV. All rights reserved.

## Support

For deployment help, see `PLESK_DEPLOYMENT_GUIDE.md`

---

**Built with ❤️ by Manus AI**
