# Deployment Guide for Cybersecurity Blog Scraper

## Quick Start Options

### Option 1: Docker (Recommended) ⭐

**Prerequisites:** Docker and Docker Compose installed

```bash
# Navigate to blog-scraper directory
cd blog-scraper

# Build and start
docker-compose up -d

# View logs
docker-compose logs -f blog-scraper

# Run a scan manually
docker-compose exec blog-scraper php run.php scan

# View pending posts
docker-compose exec blog-scraper php run.php pending

# Post to blog
docker-compose exec blog-scraper php run.php post 3

# View statistics
docker-compose exec blog-scraper php run.php stats
```

**To stop:**
```bash
docker-compose down
```

---

### Option 2: Shared Hosting / cPanel

**Prerequisites:** PHP 7.4+ with SQLite3, cURL, and SimpleXML

1. **Upload files** via FTP/cPanel File Manager to `public_html/blog-scraper/`

2. **Set permissions:**
```bash
chmod 755 run.php
mkdir -p data/cache
chmod 755 data data/cache
```

3. **Configure** `config.php` with your blog credentials

4. **Test:**
```bash
php run.php scan
php run.php pending
php run.php post
```

5. **Set up cron job** (cPanel → Cron Jobs):
```bash
# Scan every 4 hours
0 */4 * * * /usr/bin/php /home/username/public_html/blog-scraper/run.php scan >> /home/username/logs/scraper.log 2>&1

# Post daily at 9 AM
0 9 * * * /usr/bin/php /home/username/public_html/blog-scraper/run.php post 5 >> /home/username/logs/poster.log 2>&1
```

---

### Option 3: VPS (Ubuntu/Debian)

**1. Install PHP and dependencies:**
```bash
sudo apt update
sudo apt install -y php-cli php-sqlite3 php-curl php-xml sqlite3
```

**2. Clone and setup:**
```bash
cd /var/www
git clone https://github.com/enriquemespin/anw_profilesite.git
cd anw_profilesite/blog-scraper

mkdir -p data/cache
chmod 755 data data/cache
chmod +x run.php
```

**3. Configure:**
Edit `config.php` with your blog credentials

**4. Test:**
```bash
php run.php scan
php run.php pending
php run.php post
```

**5. Set up cron jobs:**
```bash
crontab -e

# Add these lines:
# Scan every 4 hours
0 */4 * * * /usr/bin/php /var/www/anw_profilesite/blog-scraper/run.php scan >> /var/log/blog-scraper.log 2>&1

# Post daily at 9 AM
0 9 * * * /usr/bin/php /var/www/anw_profilesite/blog-scraper/run.php post 5 >> /var/log/blog-poster.log 2>&1
```

---

### Option 4: AWS Lambda (Serverless)

**1. Create deployment package:**
```bash
cd blog-scraper
zip -r ../scraper.zip .
```

**2. Create Lambda function (Python wrapper):**
```python
import json
import subprocess

def lambda_handler(event, context):
    action = event.get('action', 'scan')
    result = subprocess.run(
        ['php', '/var/task/run.php', action],
        capture_output=True,
        text=True
    )
    return {
        'statusCode': 200,
        'body': json.dumps({'output': result.stdout})
    }
```

**3. Deploy via AWS Console or CLI**

**4. Set up CloudWatch Events for scheduling**

---

### Option 5: GitHub Actions (CI/CD)

Create `.github/workflows/scraper.yml`:

```yaml
name: Blog Scraper

on:
  schedule:
    # Every 4 hours
    - cron: '0 */4 * * *'
  workflow_dispatch:  # Manual trigger

jobs:
  scrape:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: sqlite3, curl, xml
      
      - name: Run scraper
        run: |
          cd blog-scraper
          php run.php scan
      
      - name: Post to blog
        run: |
          cd blog-scraper
          php run.php post 3
        env:
          WORDPRESS_URL: ${{ secrets.WORDPRESS_URL }}
          WORDPRESS_USER: ${{ secrets.WORDPRESS_USER }}
          WORDPRESS_PASSWORD: ${{ secrets.WORDPRESS_PASSWORD }}
```

---

## Configuration Examples

### WordPress Configuration
```php
'blog_posting' => [
    'enabled' => true,
    'platform' => 'wordpress',
    'wordpress' => [
        'url' => 'https://yourblog.com/wp-json',
        'username' => 'your_username',
        'application_password' => 'your_app_password',
    ],
],
```

### Medium Configuration
```php
'blog_posting' => [
    'enabled' => true,
    'platform' => 'medium',
    'medium' => [
        'api_key' => 'your_medium_api_key',
    ],
],
```

---

## Monitoring & Maintenance

### Check logs
```bash
# Docker
docker-compose logs -f blog-scraper

# VPS/Shared Hosting
tail -f /var/log/blog-scraper.log
tail -f /var/log/blog-poster.log

# View last 50 lines
tail -n 50 /var/log/blog-scraper.log
```

### Database management
```bash
# View database
sqlite3 data/blog_scraper.db

# Check statistics
sqlite3 data/blog_scraper.db "SELECT * FROM blog_posts WHERE status='pending';"

# Clear old scan history (older than 30 days)
sqlite3 data/blog_scraper.db "DELETE FROM scan_history WHERE scanned_at < date('now', '-30 days');"
```

### Update the scraper
```bash
# Docker
docker-compose pull
docker-compose up -d

# VPS/Shared Hosting
git pull origin main
```

---

## Security Best Practices

1. **Store credentials securely:**
   - Use environment variables
   - Never commit `config.php` with real credentials
   - Use `.env` files (not tracked by git)

2. **File permissions:**
```bash
chmod 755 data/
chmod 755 data/cache/
chmod 600 config.php  # Restrict config access
```

3. **Rate limiting:**
   - Keep `request_delay` at 2+ seconds
   - Respect robots.txt of target sites

4. **SSL/TLS:**
   - Always use HTTPS for blog posting
   - Verify SSL certificates

---

## Troubleshooting

### "SQLite database locked"
- Ensure only one instance runs at a time
- Check file permissions on `data/` directory

### "Failed to fetch feed"
- Check if the blog RSS URL is still valid
- Verify network connectivity
- Check if the site blocks automated requests

### "Post to blog failed"
- Verify blog credentials in `config.php`
- Check application password (WordPress)
- Verify API key (Medium)

### Cron jobs not running
- Check cron syntax
- Verify PHP path: `which php`
- Check cron logs: `grep CRON /var/log/syslog`

---

## Cost Estimates

| Option | Monthly Cost | Best For |
|--------|-------------|----------|
| Docker (local) | Free | Development, personal use |
| Shared Hosting | $5-15 | Small blogs, beginners |
| VPS | $5-20 | Full control, custom needs |
| AWS Lambda | ~$0.50 | Serverless, low traffic |
| GitHub Actions | Free | CI/CD, no server needed |

---

## Next Steps

1. **Choose your deployment option** above
2. **Configure `config.php`** with your blog credentials
3. **Test locally** before deploying
4. **Set up monitoring** to track scan success
5. **Schedule regular scans** via cron or scheduler