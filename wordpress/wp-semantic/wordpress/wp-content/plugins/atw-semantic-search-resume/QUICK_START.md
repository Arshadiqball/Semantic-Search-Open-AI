# Quick Start Guide

## 🚀 Setup in 5 Minutes

### 1. Start Node.js API
```bash
cd /Users/arshadiqbal/Documents/Faisal/semantic
npm run local
```
✅ API running on `http://localhost:3000`

### 2. Start WordPress (if not running)
```bash
cd wordpress/wp-semantic
docker-compose up -d
```
✅ WordPress running on `http://localhost:8080`

### 3. Activate Plugin
1. Go to `http://localhost:8080/wp-admin`
2. **Plugins** → Find **ATW Semantic Search Resume**
3. Click **Activate**

### 4. Configure API URL
**⚠️ IMPORTANT:** WordPress is in Docker, so use `host.docker.internal`:

1. Go to **Semantic Search** in WordPress admin
2. Set **API Base URL** to: `http://localhost:3000`
3. Click **Save Settings**
4. Click **Re-register with API**

### 5. Verify Connection
- Check **Client Information** section
- Should show: ✅ **Registered**
- Client ID and API Key should be displayed

### 6. Use the Plugin
Add shortcode to any page:
```
[atw_semantic_job_search]
```

## 🔧 Configuration

**API Base URL:**
- Docker: `http://localhost:3000`
- Host machine: `http://localhost:3000`

**Settings:**
- Similarity Threshold: `0.5` (0.0 - 1.0)
- Recommended Jobs: `10` (1 - 50)
- Job Categories: Select as needed
- Tech Stack: Enter one per line

## ✅ Verification Checklist

- [ ] Node.js API running (`curl http://localhost:3000/health`)
- [ ] WordPress accessible (`http://localhost:8080`)
- [ ] Plugin activated
- [ ] API URL configured correctly
- [ ] Client registered (shows Client ID & API Key)
- [ ] Test page created with shortcode
- [ ] Resume upload works

## 🐛 Common Issues

**"Failed to register with API"**
→ Use `host.docker.internal:3000` instead of `localhost:3000`

**"API key not configured"**
→ Click "Re-register with API" button

**CORS errors in browser**
→ Check CORS is enabled in `local-server.js`

**Resume upload fails**
→ Check file is PDF, < 5MB, and API is running

## 📝 Next Steps

1. Seed jobs in Node.js database for your client
2. Test resume upload
3. Customize settings
4. Style to match your theme

