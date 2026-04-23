# Plugin Auto-Update Setup Guide

## Overview

The chatbot plugin now has automatic update checking enabled. When you push updates to GitHub, WordPress will detect them and show an "Update Available" notification in the plugin management panel.

## How It Works

1. **Version Checking**: The plugin checks GitHub releases for the latest version
2. **Auto-Detection**: When a newer version is available, it appears in WordPress admin
3. **One-Click Update**: Click "Update" to install the latest version automatically

## Setup Instructions

### Step 1: Create a GitHub Repository

If you don't have one already:

1. Go to https://github.com/new
2. Create a public repository named `chatbot-openai`
3. Clone it locally: `git clone https://github.com/YOUR-USERNAME/chatbot-openai.git`

### Step 2: Push Your Plugin Code

```bash
cd your-local-plugin-folder
git init
git add .
git commit -m "Initial commit: Chatbot plugin v1.4.0"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/chatbot-openai.git
git push -u origin main
```

### Step 3: Create a GitHub Release

When you want to release a new version:

1. Go to your GitHub repo
2. Click "Releases" → "Create a new release"
3. Tag version: `v1.4.0` (must start with "v")
4. Title: `Chatbot Plugin v1.4.0`
5. Add release notes
6. Click "Publish release"

### Step 4: WordPress Will Detect It

- Within minutes, WordPress will check the latest release
- If the version number is higher than the installed version, you'll see "Update available" badge
- Click "Update" to install automatically

## Version Number Format

- GitHub tags must be: `v1.4.0`, `v1.5.0`, etc. (with "v" prefix)
- Plugin version in code: `Version: 1.4.0` (without "v")
- The updater automatically removes the "v" prefix when comparing

## Workflow Example

**Local Development:**

```
1. Make changes to the plugin
2. Test in your local WordPress
3. Update version in chatbot-openai.php: Version: 1.5.0
4. Commit changes: git commit -am "Add new feature"
5. Push to GitHub: git push
```

**Release to Production:**

```
1. Go to GitHub Releases
2. Click "Create a new release"
3. Tag: v1.5.0
4. Click "Publish"
5. Check your WordPress admin → Updates page
6. You'll see "Update available"
7. Click "Update" → Done!
```

## Clearing Update Cache

If updates don't show immediately:

1. Go to WordPress admin
2. Run: `delete_transient( 'chatbot_latest_version' );` in a custom plugin or via WP-CLI
3. Or wait 12 hours (cache expires automatically)

## Troubleshooting

**Updates not showing?**

- Check that your GitHub repo is public
- Make sure the tag follows format: `v1.x.x`
- Verify plugin version in code is lower than tag version

**Getting 404 error?**

- Check the GitHub username in plugin-updater.php matches yours
- Update the `$repo_owner` variable if needed

## Files Involved

- [includes/plugin-updater.php](includes/plugin-updater.php) - Update checker
- [chatbot-openai.php](chatbot-openai.php) - Main plugin with version header

---

**Happy updating!** 🚀
