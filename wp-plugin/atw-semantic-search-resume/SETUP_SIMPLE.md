# Simple Setup Guide

## Your Setup
- **Node.js API:** `http://localhost:3000`
- **WordPress:** `http://localhost:8080`

## Quick Start

### 1. Start Node.js API
```bash
cd /Users/arshadiqbal/Documents/Faisal/semantic
npm run local
```

### 2. Start WordPress
```bash
cd wordpress/wp-semantic
docker-compose up -d
```

### 3. Configure Plugin
1. Go to `http://localhost:8080/wp-admin`
2. Navigate to **Semantic Search** → **Settings**
3. API Base URL should be: `http://localhost:3000`
4. Click **Save Settings**
5. Click **Re-register with API**

## If Connection Fails

If WordPress is in Docker and can't connect to `localhost:3000`, you have two options:

### Option 1: Use host.docker.internal (Recommended)
Change API Base URL to: `http://host.docker.internal:3000`

### Option 2: Use Host Network Mode
Modify `docker-compose.yml` to use host network:
```yaml
wordpress:
  network_mode: "host"
  # ... rest of config
```

## Verify Connection

Test from WordPress container:
```bash
docker exec -it <wordpress-container> curl http://localhost:3000/health
```

Or test from host:
```bash
curl http://localhost:3000/health
```

## That's It!

The plugin is now configured to use `http://localhost:3000` for your Node.js API.

