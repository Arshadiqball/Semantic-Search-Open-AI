# Deployment Guide

Complete guide for deploying the Semantic Job Matcher to production.

## Deployment Options

- [Local Development](#local-development)
- [Docker Deployment](#docker-deployment)
- [Heroku](#heroku-deployment)
- [AWS](#aws-deployment)
- [DigitalOcean](#digitalocean-deployment)

---

## Local Development

Already covered in [QUICKSTART.md](QUICKSTART.md)

```bash
npm install
npm run init-db
npm run seed
npm start
```

---

## Docker Deployment

### Prerequisites
- Docker
- Docker Compose

### Quick Start

1. **Set OpenAI API Key**

```bash
export OPENAI_API_KEY=your_api_key_here
```

2. **Start Services**

```bash
docker-compose up -d
```

This will:
- Start PostgreSQL with pgvector
- Initialize database schema
- Seed sample jobs
- Start the API server

3. **Access Application**

```
API: http://localhost:3000
Test UI: Open test-client.html in browser
```

4. **Stop Services**

```bash
docker-compose down
```

5. **View Logs**

```bash
docker-compose logs -f app
```

### Production Docker

Update `docker-compose.yml`:

```yaml
services:
  app:
    environment:
      NODE_ENV: production
      # Use secrets for production
      OPENAI_API_KEY: ${OPENAI_API_KEY}
    restart: always
```

---

## Heroku Deployment

### Prerequisites
- Heroku CLI installed
- Heroku account

### Steps

1. **Create Heroku App**

```bash
heroku create your-app-name
```

2. **Add PostgreSQL Add-on**

```bash
heroku addons:create heroku-postgresql:mini
```

3. **Install pgvector on Heroku**

Create `bin/post_compile`:

```bash
#!/bin/bash
# Install pgvector
git clone https://github.com/pgvector/pgvector.git
cd pgvector
make
sudo make install
```

4. **Set Environment Variables**

```bash
heroku config:set OPENAI_API_KEY=your_api_key
heroku config:set NODE_ENV=production
```

5. **Deploy**

```bash
git push heroku main
```

6. **Initialize Database**

```bash
heroku run npm run init-db
heroku run npm run seed
```

7. **Open App**

```bash
heroku open
```

### Cost Estimate
- Hobby Plan: $7/month (PostgreSQL)
- Dyno: $7/month (Basic)
- **Total: ~$14/month**

---

## AWS Deployment

### Architecture
- EC2 instance (t3.small or better)
- RDS PostgreSQL with pgvector
- S3 for resume storage (optional)
- Elastic Load Balancer (for scaling)

### Steps

1. **Launch EC2 Instance**

```bash
# Ubuntu 22.04 LTS
# t3.small (2 vCPU, 2GB RAM)
```

2. **Install Dependencies**

```bash
# Connect to EC2
ssh -i your-key.pem ubuntu@your-ec2-ip

# Update system
sudo apt update && sudo apt upgrade -y

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Install pgvector
git clone https://github.com/pgvector/pgvector.git
cd pgvector
make
sudo make install
```

3. **Setup Application**

```bash
# Clone repository
git clone your-repo-url
cd semantic

# Install dependencies
npm install --production

# Setup environment
nano .env
# Add your configuration
```

4. **Setup PostgreSQL**

```bash
sudo -u postgres psql
CREATE DATABASE semantic_job_matcher;
\q

# Initialize
npm run init-db
npm run seed
```

5. **Setup PM2 (Process Manager)**

```bash
sudo npm install -g pm2

# Start application
pm2 start src/server.js --name semantic-job-matcher

# Auto-restart on reboot
pm2 startup systemd
pm2 save
```

6. **Setup Nginx (Reverse Proxy)**

```bash
sudo apt install -y nginx

# Configure
sudo nano /etc/nginx/sites-available/semantic
```

```nginx
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/semantic /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

7. **Setup SSL (Let's Encrypt)**

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### Cost Estimate (Monthly)
- EC2 t3.small: ~$15
- RDS db.t3.micro: ~$15
- Data transfer: ~$5
- **Total: ~$35/month**

---

## DigitalOcean Deployment

### Using App Platform (Easiest)

1. **Create App**
   - Go to DigitalOcean → Create → App
   - Connect GitHub repository
   - Choose branch

2. **Configure Build**
   - Build Command: `npm install`
   - Run Command: `npm start`

3. **Add Database**
   - Add PostgreSQL Dev Database ($7/month)
   - Connection details auto-configured

4. **Add Environment Variables**
   - `OPENAI_API_KEY`
   - Other variables from `.env`

5. **Deploy**
   - Click "Deploy"
   - Wait for build to complete

### Using Droplet (More Control)

Similar to AWS EC2 deployment above.

1. **Create Droplet**
   - Ubuntu 22.04 LTS
   - $12/month (2GB RAM)

2. **Follow AWS Steps** (Install Node, PostgreSQL, etc.)

### Cost Estimate
- App Platform: $12/month (Basic)
- Database: $7/month (Dev)
- **Total: ~$19/month**

---

## Environment Variables for Production

```env
# Required
OPENAI_API_KEY=your_production_key

# Database
DB_HOST=your_production_db_host
DB_PORT=5432
DB_NAME=semantic_job_matcher
DB_USER=your_db_user
DB_PASSWORD=strong_secure_password

# Server
PORT=3000
NODE_ENV=production

# Security (add these)
CORS_ORIGIN=https://your-domain.com
MAX_FILE_SIZE=5242880

# Optional: Monitoring
SENTRY_DSN=your_sentry_dsn
LOG_LEVEL=info
```

---

## Production Checklist

### Security
- [ ] Use strong database passwords
- [ ] Store secrets in environment variables (never in code)
- [ ] Enable HTTPS/SSL
- [ ] Configure CORS properly
- [ ] Set up firewall rules
- [ ] Use rate limiting
- [ ] Implement authentication (if needed)

### Performance
- [ ] Enable connection pooling
- [ ] Add caching (Redis) if needed
- [ ] Optimize database indexes
- [ ] Use CDN for static files
- [ ] Enable gzip compression
- [ ] Monitor database query performance

### Monitoring
- [ ] Set up error tracking (Sentry, Rollbar)
- [ ] Configure logging (Winston, Pino)
- [ ] Monitor server metrics (CPU, memory, disk)
- [ ] Set up uptime monitoring
- [ ] Track API response times
- [ ] Monitor OpenAI API usage/costs

### Backup
- [ ] Automated database backups (daily)
- [ ] Test restore procedures
- [ ] Store backups in different location
- [ ] Backup uploaded files (if stored)

### Scaling
- [ ] Use process manager (PM2)
- [ ] Consider horizontal scaling with load balancer
- [ ] Monitor database connection limits
- [ ] Plan for increased OpenAI API costs
- [ ] Optimize vector similarity queries

---

## Cost Optimization

### OpenAI Costs

**Embeddings (text-embedding-3-small):**
- $0.02 per 1M tokens
- Average resume: ~500 tokens
- Average job: ~200 tokens
- **Cost per match:** < $0.001

**GPT Analysis (gpt-3.5-turbo):**
- $0.50 per 1M input tokens
- Average analysis: ~300 tokens input, ~200 output
- **Cost per match:** ~$0.0002

**Monthly estimate (100 resumes, 10 jobs each):**
- Embeddings: $0.20
- GPT analysis: $0.40
- **Total: ~$0.60/month**

### Database Costs

Use managed PostgreSQL:
- Development: $7-15/month
- Production: $15-50/month

Or self-host:
- Save 30-50% but requires maintenance

### Server Costs

- Smallest viable: $5-12/month
- Recommended: $15-35/month
- Scale up as needed

---

## Monitoring & Logging

### Using PM2

```bash
# View logs
pm2 logs semantic-job-matcher

# Monitor
pm2 monit

# Restart
pm2 restart semantic-job-matcher
```

### Using Sentry

```bash
npm install @sentry/node
```

Add to `server.js`:

```javascript
import * as Sentry from '@sentry/node';

Sentry.init({
  dsn: process.env.SENTRY_DSN,
  environment: process.env.NODE_ENV,
});

// Error handler
app.use(Sentry.Handlers.errorHandler());
```

---

## Troubleshooting Production Issues

### High Memory Usage

```bash
# Check memory
free -h

# Restart app
pm2 restart semantic-job-matcher
```

**Solutions:**
- Increase server RAM
- Optimize database queries
- Clear uploaded files periodically

### Slow API Responses

**Causes:**
- Database not optimized
- OpenAI API slow
- Too many concurrent requests

**Solutions:**
- Add caching layer (Redis)
- Optimize vector queries
- Add queue system for background processing

### Database Connection Errors

```bash
# Check PostgreSQL
systemctl status postgresql

# Check connections
sudo -u postgres psql
SELECT count(*) FROM pg_stat_activity;
```

**Solutions:**
- Increase connection pool size
- Check network connectivity
- Restart PostgreSQL

---

## Scaling Strategies

### Vertical Scaling (Simple)
- Upgrade server size
- More RAM, CPU, storage
- Good for: 0-1000 requests/day

### Horizontal Scaling (Advanced)
- Multiple application servers
- Load balancer (Nginx, AWS ELB)
- Shared database
- Good for: 1000+ requests/day

### Database Scaling
- Read replicas for queries
- Master for writes
- Connection pooling (PgBouncer)

---

## CI/CD Pipeline

### GitHub Actions Example

`.github/workflows/deploy.yml`:

```yaml
name: Deploy

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup Node.js
        uses: actions/setup-node@v2
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Run tests
        run: npm test
      
      - name: Deploy to server
        run: |
          ssh user@server 'cd /app && git pull && npm install && pm2 restart app'
```

---

## Support & Maintenance

### Regular Tasks
- Weekly: Check logs, monitor errors
- Monthly: Update dependencies, review costs
- Quarterly: Security audit, performance review
- Annually: Major updates, architecture review

### Useful Commands

```bash
# Update dependencies
npm update

# Security audit
npm audit
npm audit fix

# Database backup
pg_dump -U postgres semantic_job_matcher > backup.sql

# Database restore
psql -U postgres semantic_job_matcher < backup.sql
```

---

**Choose the deployment option that best fits your needs and budget!** 🚀

