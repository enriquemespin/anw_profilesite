<?php
/**
 * Configuration file for Cybersecurity Blog Scraper
 */

return [
    // Database configuration
    'database' => [
        'path' => __DIR__ . '/data/blog_scraper.db',
    ],
    
    // Scraper settings
    'scraper' => [
        'request_delay' => 2,           // seconds between requests
        'timeout' => 30,                 // request timeout in seconds
        'max_posts_per_site' => 10,      // max posts to fetch per site
        'user_agent' => 'CyberSecurityBlogScraper/1.0',
        'cache_ttl' => 3600,             // cache TTL in seconds (1 hour)
    ],
    
    // Blog sources - semantic URL list
    'blog_sources' => [
        // Tier 1: High priority - investigative & authoritative
        [
            'name' => 'Krebs on Security',
            'url' => 'https://krebsonsecurity.com/feed/',
            'type' => 'rss',
            'category' => 'investigative',
            'priority' => 1,
            'description' => 'In-depth cybercrime investigations by Brian Krebs'
        ],
        [
            'name' => 'Schneier on Security',
            'url' => 'https://www.schneier.com/feed/atom/',
            'type' => 'rss',
            'category' => 'commentary',
            'priority' => 1,
            'description' => 'Security technology commentary by Bruce Schneier'
        ],
        [
            'name' => 'PortSwigger Blog',
            'url' => 'https://portswigger.net/blog/rss',
            'type' => 'rss',
            'category' => 'technical',
            'priority' => 1,
            'description' => 'Web security research and Burp Suite tutorials'
        ],
        [
            'name' => 'SANS Internet Storm Center',
            'url' => 'https://isc.sans.edu/feed/',
            'type' => 'rss',
            'category' => 'threat-intel',
            'priority' => 1,
            'description' => 'Daily threat alerts and incident response diaries'
        ],
        [
            'name' => 'CrowdStrike Blog',
            'url' => 'https://www.crowdstrike.com/blog/feed/',
            'type' => 'rss',
            'category' => 'threat-intel',
            'priority' => 1,
            'description' => 'APT tracking and threat intelligence research'
        ],
        [
            'name' => 'Mandiant Blog',
            'url' => 'https://www.mandiant.com/rss/default.aspx',
            'type' => 'rss',
            'category' => 'threat-research',
            'priority' => 1,
            'description' => 'Incident response insights and nation-state attack analysis'
        ],
        
        // Tier 2: Medium priority - news & analysis
        [
            'name' => 'Threatpost',
            'url' => 'https://threatpost.com/feed/',
            'type' => 'rss',
            'category' => 'news',
            'priority' => 2,
            'description' => 'Breaking cybersecurity news and vulnerability disclosures'
        ],
        [
            'name' => 'The Hacker News',
            'url' => 'https://feeds.feedburner.com/TheHackerNews',
            'type' => 'rss',
            'category' => 'news',
            'priority' => 2,
            'description' => 'Hacking news, malware alerts, and exploit disclosures'
        ],
        [
            'name' => 'Dark Reading',
            'url' => 'https://www.darkreading.com/rss.xml',
            'type' => 'rss',
            'category' => 'enterprise',
            'priority' => 2,
            'description' => 'Enterprise security news and analysis'
        ],
        [
            'name' => 'Security Week',
            'url' => 'https://www.securityweek.com/feed/',
            'type' => 'rss',
            'category' => 'news',
            'priority' => 2,
            'description' => 'Cybersecurity news, analysis, and research'
        ],
        [
            'name' => 'BleepingComputer',
            'url' => 'https://www.bleepingcomputer.com/feed/',
            'type' => 'rss',
            'category' => 'malware',
            'priority' => 2,
            'description' => 'Malware analysis and ransomware tracking'
        ],
        [
            'name' => 'Recorded Future',
            'url' => 'https://www.recordedfuture.com/feed',
            'type' => 'rss',
            'category' => 'threat-intel',
            'priority' => 2,
            'description' => 'OSINT and intelligence-driven threat research'
        ],
        [
            'name' => 'ESET Research',
            'url' => 'https://www.eset.com/international/blog/rss/',
            'type' => 'rss',
            'category' => 'research',
            'priority' => 2,
            'description' => 'Malware research and threat reports'
        ],
        [
            'name' => 'OWASP News',
            'url' => 'https://owasp.org/www-newsletter/feed/',
            'type' => 'rss',
            'category' => 'web-security',
            'priority' => 2,
            'description' => 'Web application security news and updates'
        ],
        
        // Tier 3: Additional sources
        [
            'name' => 'Trellix (FireEye) Blog',
            'url' => 'https://www.trellix.com/blogs/rss/',
            'type' => 'rss',
            'category' => 'threat-research',
            'priority' => 3,
            'description' => 'Cyber threat research and malware analysis'
        ],
        [
            'name' => 'Kaspersky Security Blog',
            'url' => 'https://www.kaspersky.com/blog/rss/',
            'type' => 'rss',
            'category' => 'threat-analysis',
            'priority' => 3,
            'description' => 'Cyber threat analysis and security reports'
        ],
        [
            'name' => 'Trend Micro Research',
            'url' => 'https://www.trendmicro.com/rss/research.xml',
            'type' => 'rss',
            'category' => 'threat-research',
            'priority' => 3,
            'description' => 'Cyber threat research and vulnerability analysis'
        ],
    ],
    
    // Blog posting configuration
    'blog_posting' => [
        'enabled' => false,
        'platform' => 'wordpress',  // wordpress, medium, custom
        'wordpress' => [
            'url' => 'https://yourblog.com/wp-json',
            'username' => '',
            'application_password' => '',
        ],
        'medium' => [
            'api_key' => '',
        ],
    ],
    
    // AI summarization (optional)
    'ai_summarization' => [
        'enabled' => false,
        'provider' => 'openai',  // openai, anthropic, local
        'api_key' => '',
        'model' => 'gpt-3.5-turbo',
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'info',  // debug, info, warning, error
        'file' => __DIR__ . '/data/scraper.log',
    ],
];