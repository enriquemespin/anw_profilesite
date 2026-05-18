# Cybersecurity Blog Scraper

A PHP application that scrapes cybersecurity blogs, tracks scanned posts, generates summaries using a structured framework, and posts to your blog.

## Features

- **Multi-source scraping**: Fetches from 17+ cybersecurity blogs via RSS feeds
- **Duplicate tracking**: SQLite database tracks all scanned posts to avoid duplicates
- **Structured framework**: Each post is formatted with:
  - **Summary**: What's the post about?
  - **What's new**: Key findings or updates
  - **Question**: What should readers consider?
  - **Next steps**: Actionable takeaways
- **Blog integration**: Posts to WordPress, Medium, or custom platforms
- **Caching**: HTTP cache to reduce redundant requests
- **CLI interface**: Easy command-line operations

## Semantic URL List

The scraper monitors these cybersecurity blogs (organized by priority):

### Tier 1 (High Priority)
1. **Krebs on Security** - https://krebsonsecurity.com/feed/ - Investigative cybercrime journalism
2. **Schneier on Security** - https://www.schneier.com/feed/atom/ - Security commentary
3. **PortSwigger Blog** - https://portswigger.net/blog/rss - Web security research
4. **SANS Internet Storm Center** - https://isc.sans.edu/feed/ - Threat alerts
5. **CrowdStrike Blog** - https://www.crowdstrike.com/blog/feed/ - APT tracking
6. **Mandiant Blog** - https://www.mandiant.com/rss/default.aspx - Incident response

### Tier 2 (Medium Priority)
7. **Threatpost** - https://threatpost.com/feed/ - Breaking security news
8. **The Hacker News** - https://feeds.feedburner.com/TheHackerNews - Hacking news
9. **Dark Reading** - https://www.darkreading.com/rss.xml - Enterprise security
10. **Security Week** - https://www.securityweek.com/feed/ - Security analysis
11. **BleepingComputer** - https://www.bleepingcomputer.com/feed/ - Malware analysis
12. **Recorded Future** - https://www.recordedfuture.com/feed - OSINT research
13. **ESET Research** - https://www.eset.com/international/blog/rss/ - Malware research
14. **OWASP News** - https://owasp.org/www-newsletter/feed/ - Web app security

### Tier 3 (Additional)
15. **Trellix Blog** - https://www.trellix.com/blogs/rss/ - Threat research
16. **Kaspersky Security Blog** - https://www.kaspersky.com/blog/rss/ - Threat analysis
17. **Trend Micro Research** - https://www.trendmicro.com/rss/research.xml - Vulnerability analysis

## Installation

### Requirements
- PHP 7.4+
- SQLite3 extension
- cURL extension
- SimpleXML extension

### Setup

```bash
# Clone the repository
git clone https://github.com/yourusername/cybersecurity-blog-scraper.git
cd cybersecurity-blog-scraper

# Create data directory
mkdir -p data/cache

# Make run.php executable
chmod +x run.php
```

## Usage

### Scan all blog sources
```bash
php run.php scan
```

### View pending posts
```bash
php run.php pending
```

### Post to your blog
```bash
# Post 1 pending post
php run.php post

# Post 3 pending posts
php run.php post 3
```

### View statistics
```bash
php run.php stats
```

### View scan history
```bash
php run.php history
```

## Configuration

Edit `config.php` to customize:

```php
// Blog posting configuration
'blog_posting' => [
    'enabled' => true,
    'platform' => 'wordpress',  // wordpress, medium, custom
    'wordpress' => [
        'url' => 'https://yourblog.com/wp-json',
        'username' => 'your_username',
        'application_password' => 'your_app_password',
    ],
],
```

### WordPress Setup
1. Generate an Application Password in WordPress (Users → Profile → Application Passwords)
2. Update the `wordpress` configuration in `config.php`

### Medium Setup
1. Generate an API key from Medium's developer settings
2. Update the `medium` configuration in `config.php`

## Database Schema

The scraper uses SQLite with three tables:

- **blog_sources**: Stores blog feed URLs and metadata
- **blog_posts**: Stores scraped posts with formatted summaries
- **scan_history**: Tracks each scan operation

## Scheduling

Add to crontab for regular scanning:

```bash
# Scan every 4 hours
0 */4 * * * /usr/bin/php /path/to/blog-scraper/run.php scan >> /var/log/blog-scraper.log 2>&1

# Post pending posts daily at 9 AM
0 9 * * * /usr/bin/php /path/to/blog-scraper/run.php post 5 >> /var/log/blog-scraper.log 2>&1
```

## Framework Details

Each blog post is formatted using this framework:

### Summary
A concise 1-2 sentence overview of the post content, extracted from the RSS description.

### What's New
Key findings, updates, or important details from the post.

### Question
A thought-provoking question related to the post topic, generated based on keywords:
- Vulnerabilities → "How vulnerable is your infrastructure?"
- Ransomware → "Are your backups tested?"
- Data breaches → "What sensitive data could be exposed?"
- APTs → "Does your detection strategy account for nation-state actors?"

### Next Steps
Actionable recommendations based on the post topic:
- Vulnerabilities → Apply patches, check systems
- Ransomware → Verify backups, test restoration
- Phishing → Update filters, train users
- Cloud → Audit configurations, enforce least-privilege

## Extending

### Adding new blog sources
Add to the `blog_sources` array in `config.php`:

```php
[
    'name' => 'New Blog',
    'url' => 'https://example.com/feed/',
    'type' => 'rss',
    'category' => 'news',
    'priority' => 2,
    'description' => 'Description of the blog'
],
```

### Custom post formatting
Modify `PostFormatter.php` to customize the framework output.

### AI-powered summaries
Enable AI summarization in `config.php`:

```php
'ai_summarization' => [
    'enabled' => true,
    'provider' => 'openai',
    'api_key' => 'your_api_key',
    'model' => 'gpt-3.5-turbo',
],
```

## License

MIT License

## Acknowledgments

Blog sources compiled from popular cybersecurity resources including:
- Krebs on Security
- Schneier on Security
- PortSwigger Web Security Academy
- SANS Internet Storm Center
- And 13+ other industry-leading sources