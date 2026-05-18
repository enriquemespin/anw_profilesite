<?php
/**
 * Cybersecurity Blog Scraper
 * 
 * Scrapes cybersecurity blogs, tracks scanned posts, generates summaries,
 * and posts to your blog.
 * 
 * Framework:
 * - Summary: What's the post about?
 * - What's new: Key findings or updates
 * - Question: What should readers consider?
 * - Next steps: Actionable takeaways
 */

class CybersecurityBlogScraper {
    
    // Database connection
    private $db;
    
    // Configuration
    private $config = [
        'db_path' => __DIR__ . '/data/blog_scraper.db',
        'cache_dir' => __DIR__ . '/data/cache',
        'log_file' => __DIR__ . '/data/scraper.log',
        'request_delay' => 2, // seconds between requests
        'timeout' => 30,
        'max_posts_per_site' => 10,
        'user_agent' => 'CyberSecurityBlogScraper/1.0 (https://yourblog.com)'
    ];
    
    // Semantic URL list of cybersecurity blogs
    private $blogSources = [
        [
            'name' => 'Krebs on Security',
            'url' => 'https://krebsonsecurity.com/feed/',
            'type' => 'rss',
            'category' => 'investigative',
            'priority' => 1
        ],
        [
            'name' => 'Schneier on Security',
            'url' => 'https://www.schneier.com/feed/atom/',
            'type' => 'rss',
            'category' => 'commentary',
            'priority' => 1
        ],
        [
            'name' => 'Threatpost',
            'url' => 'https://threatpost.com/feed/',
            'type' => 'rss',
            'category' => 'news',
            'priority' => 2
        ],
        [
            'name' => 'The Hacker News',
            'url' => 'https://feeds.feedburner.com/TheHackerNews',
            'type' => 'rss',
            'category' => 'news',
            'priority' => 2
        ],
        [
            'name' => 'Dark Reading',
            'url' => 'https://www.darkreading.com/rss.xml',
            'type' => 'rss',
            'category' => 'enterprise',
            'priority' => 2
        ],
        [
            'name' => 'Security Week',
            'url' => 'https://www.securityweek.com/feed/',
            'type' => 'rss',
            'category' => 'news',
            'priority' => 2
        ],
        [
            'name' => 'PortSwigger Blog',
            'url' => 'https://portswigger.net/blog/rss',
            'type' => 'rss',
            'category' => 'technical',
            'priority' => 1
        ],
        [
            'name' => 'SANS Internet Storm Center',
            'url' => 'https://isc.sans.edu/feed/',
            'type' => 'rss',
            'category' => 'threat-intel',
            'priority' => 1
        ],
        [
            'name' => 'BleepingComputer',
            'url' => 'https://www.bleepingcomputer.com/feed/',
            'type' => 'rss',
            'category' => 'malware',
            'priority' => 2
        ],
        [
            'name' => 'CrowdStrike Blog',
            'url' => 'https://www.crowdstrike.com/blog/feed/',
            'type' => 'rss',
            'category' => 'threat-intel',
            'priority' => 1
        ],
        [
            'name' => 'Mandiant Blog',
            'url' => 'https://www.mandiant.com/rss/default.aspx',
            'type' => 'rss',
            'category' => 'threat-research',
            'priority' => 1
        ],
        [
            'name' => 'Recorded Future',
            'url' => 'https://www.recordedfuture.com/feed',
            'type' => 'rss',
            'category' => 'threat-intel',
            'priority' => 2
        ],
        [
            'name' => 'ESET Research',
            'url' => 'https://www.eset.com/international/blog/rss/',
            'type' => 'rss',
            'category' => 'research',
            'priority' => 2
        ],
        [
            'name' => 'OWASP News',
            'url' => 'https://owasp.org/www-newsletter/feed/',
            'type' => 'rss',
            'category' => 'web-security',
            'priority' => 2
        ]
    ];
    
    /**
     * Constructor - initialize database and directories
     */
    public function __construct() {
        $this->initDatabase();
        $this->ensureDirectories();
        $this->log('Scraper initialized');
    }
    
    /**
     * Initialize SQLite database
     */
    private function initDatabase() {
        if (!file_exists($this->config['db_path'])) {
            $db = new SQLite3($this->config['db_path']);
            
            // Create tables
            $db->exec('CREATE TABLE IF NOT EXISTS blog_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                url TEXT UNIQUE NOT NULL,
                type TEXT DEFAULT "rss",
                category TEXT,
                priority INTEGER DEFAULT 2,
                enabled INTEGER DEFAULT 1,
                last_scanned DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            
            $db->exec('CREATE TABLE IF NOT EXISTS blog_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id INTEGER,
                post_url TEXT UNIQUE NOT NULL,
                title TEXT,
                author TEXT,
                published_date DATETIME,
                scraped_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                summary TEXT,
                whats_new TEXT,
                question TEXT,
                next_steps TEXT,
                status TEXT DEFAULT "pending",
                FOREIGN KEY (source_id) REFERENCES blog_sources(id)
            )');
            
            $db->exec('CREATE TABLE IF NOT EXISTS scan_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id INTEGER,
                scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                posts_found INTEGER DEFAULT 0,
                posts_new INTEGER DEFAULT 0,
                posts_skipped INTEGER DEFAULT 0,
                error TEXT,
                FOREIGN KEY (source_id) REFERENCES blog_sources(id)
            )');
            
            // Insert blog sources
            $this->insertBlogSources($db);
            
            $db->close();
        }
        
        $this->db = new SQLite3($this->config['db_path']);
    }
    
    /**
     * Insert blog sources into database
     */
    private function insertBlogSources($db) {
        $stmt = $db->prepare('INSERT OR IGNORE INTO blog_sources (name, url, type, category, priority) VALUES (:name, :url, :type, :category, :priority)');
        
        foreach ($this->blogSources as $source) {
            $stmt->bindValue(':name', $source['name'], SQLITE3_TEXT);
            $stmt->bindValue(':url', $source['url'], SQLITE3_TEXT);
            $stmt->bindValue(':type', $source['type'], SQLITE3_TEXT);
            $stmt->bindValue(':category', $source['category'], SQLITE3_TEXT);
            $stmt->bindValue(':priority', $source['priority'], SQLITE3_INTEGER);
            $stmt->execute();
            $stmt->reset();
        }
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories() {
        $dirs = [
            dirname($this->config['db_path']),
            $this->config['cache_dir']
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Log a message
     */
    private function log($message) {
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
        file_put_contents($this->config['log_file'], $logEntry, FILE_APPEND);
    }
    
    /**
     * Fetch RSS feed
     */
    public function fetchFeed($url) {
        $cacheFile = $this->config['cache_dir'] . '/' . md5($url) . '.xml';
        
        // Check cache (valid for 1 hour)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $this->log("Using cached feed: $url");
            return file_get_contents($cacheFile);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            file_put_contents($cacheFile, $response);
            $this->log("Fetched feed: $url");
            return $response;
        }
        
        $this->log("Failed to fetch feed: $url (HTTP $httpCode)");
        return false;
    }
    
    /**
     * Parse RSS feed and extract posts
     */
    public function parseFeed($rssContent) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rssContent);
        
        if ($xml === false) {
            $this->log("Failed to parse RSS feed");
            return [];
        }
        
        $posts = [];
        $channel = $xml->channel;
        
        if (!$channel) {
            $channel = $xml->rdf->channel ?? null;
        }
        
        if (!$channel) {
            $channel = $xml->feed ?? null;
        }
        
        if ($channel) {
            // Standard RSS/Atom items
            $items = $channel->item ?? $channel->entry ?? [];
            
            foreach ($items as $item) {
                $post = [
                    'title' => (string)($item->title ?? ''),
                    'url' => (string)($item->link['href'] ?? $item->link ?? ''),
                    'author' => (string)($item->author ?? $item->dc['creator'] ?? ''),
                    'published' => (string)($item->pubDate ?? $item->published ?? $item->updated ?? ''),
                    'description' => (string)($item->description ?? $item->summary ?? '')
                ];
                
                if (!empty($post['title']) && !empty($post['url'])) {
                    $posts[] = $post;
                }
            }
        }
        
        return array_slice($posts, 0, $this->config['max_posts_per_site']);
    }
    
    /**
     * Check if post has already been scanned
     */
    private function isPostScanned($postUrl) {
        $stmt = $this->db->prepare('SELECT id FROM blog_posts WHERE post_url = :url LIMIT 1');
        $stmt->bindValue(':url', $postUrl, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }
    
    /**
     * Generate summary using framework
     */
    private function generateSummary($post) {
        // In production, you'd integrate with an AI API (OpenAI, Anthropic, etc.)
        // For now, we'll create a basic summary structure
        
        $summary = [
            'title' => $post['title'],
            'url' => $post['url'],
            'framework' => [
                'summary' => $this->generateSummaryText($post),
                'whats_new' => $this->extractKeyPoints($post),
                'question' => $this->generateQuestion($post),
                'next_steps' => $this->generateNextSteps($post)
            ]
        ];
        
        return $summary;
    }
    
    /**
     * Generate summary text
     */
    private function generateSummaryText($post) {
        // Extract first 2-3 sentences from description
        $description = strip_tags($post['description'] ?? '');
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        
        // Remove HTML entities and special chars
        $description = preg_replace('/[^a-zA-Z0-9\s.,;:!?()-]/', '', $description);
        
        // Get first 200 characters
        $summary = substr($description, 0, 200);
        $summary = rtrim($summary, '.!?:;');
        $summary .= '.';
        
        return $summary ?: 'No summary available for this post.';
    }
    
    /**
     * Extract key points (what's new)
     */
    private function extractKeyPoints($post) {
        // In production, use AI to extract key findings
        // For now, return placeholder
        return "Key findings from this post will be extracted here.";
    }
    
    /**
     * Generate thought-provoking question
     */
    private function generateQuestion($post) {
        // In production, use AI to generate relevant question
        return "What implications does this have for your security posture?";
    }
    
    /**
     * Generate actionable next steps
     */
    private function generateNextSteps($post) {
        // In production, use AI to generate specific recommendations
        return "Review your current security controls and update incident response procedures.";
    }
    
    /**
     * Scan a single blog source
     */
    public function scanSource($sourceId) {
        $stmt = $this->db->prepare('SELECT * FROM blog_sources WHERE id = :id');
        $stmt->bindValue(':id', $sourceId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $source = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$source) {
            $this->log("Source not found: $sourceId");
            return false;
        }
        
        $this->log("Scanning source: {$source['name']}");
        
        $rssContent = $this->fetchFeed($source['url']);
        
        if (!$rssContent) {
            // Record scan with error
            $stmt = $this->db->prepare('INSERT INTO scan_history (source_id, error) VALUES (:source_id, :error)');
            $stmt->bindValue(':source_id', $sourceId, SQLITE3_INTEGER);
            $stmt->bindValue(':error', 'Failed to fetch feed', SQLITE3_TEXT);
            $stmt->execute();
            return false;
        }
        
        $posts = $this->parseFeed($rssContent);
        $newPosts = 0;
        $skippedPosts = 0;
        
        foreach ($posts as $post) {
            if ($this->isPostScanned($post['url'])) {
                $skippedPosts++;
                continue;
            }
            
            // Generate summary
            $summary = $this->generateSummary($post);
            
            // Insert into database
            $stmt = $this->db->prepare('INSERT INTO blog_posts (source_id, post_url, title, author, published_date, summary, whats_new, question, next_steps, status) VALUES (:source_id, :url, :title, :author, :published, :summary, :whats_new, :question, :next_steps, :status)');
            $stmt->bindValue(':source_id', $sourceId, SQLITE3_INTEGER);
            $stmt->bindValue(':url', $post['url'], SQLITE3_TEXT);
            $stmt->bindValue(':title', $post['title'], SQLITE3_TEXT);
            $stmt->bindValue(':author', $post['author'], SQLITE3_TEXT);
            $stmt->bindValue(':published', $post['published'], SQLITE3_TEXT);
            $stmt->bindValue(':summary', $summary['framework']['summary'], SQLITE3_TEXT);
            $stmt->bindValue(':whats_new', $summary['framework']['whats_new'], SQLITE3_TEXT);
            $stmt->bindValue(':question', $summary['framework']['question'], SQLITE3_TEXT);
            $stmt->bindValue(':next_steps', $summary['framework']['next_steps'], SQLITE3_TEXT);
            $stmt->bindValue(':status', 'pending', SQLITE3_TEXT);
            $stmt->execute();
            
            $newPosts++;
        }
        
        // Update scan history
        $stmt = $this->db->prepare('INSERT INTO scan_history (source_id, posts_found, posts_new, posts_skipped) VALUES (:source_id, :found, :new, :skipped)');
        $stmt->bindValue(':source_id', $sourceId, SQLITE3_INTEGER);
        $stmt->bindValue(':found', count($posts), SQLITE3_INTEGER);
        $stmt->bindValue(':new', $newPosts, SQLITE3_INTEGER);
        $stmt->bindValue(':skipped', $skippedPosts, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Update last scanned time
        $stmt = $this->db->prepare('UPDATE blog_sources SET last_scanned = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->bindValue(':id', $sourceId, SQLITE3_INTEGER);
        $stmt->execute();
        
        $this->log("Scanned {$source['name']}: {$newPosts} new posts, {$skippedPosts} skipped");
        
        return [
            'source' => $source['name'],
            'new_posts' => $newPosts,
            'skipped_posts' => $skippedPosts
        ];
    }
    
    /**
     * Scan all blog sources
     */
    public function scanAll() {
        $stmt = $this->db->prepare('SELECT id, name FROM blog_sources WHERE enabled = 1 ORDER BY priority ASC');
        $result = $stmt->execute();
        
        $results = [];
        
        while ($source = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $this->scanSource($source['id']);
            sleep($this->config['request_delay']);
        }
        
        return $results;
    }
    
    /**
     * Get pending posts ready for posting
     */
    public function getPendingPosts($limit = 5) {
        $stmt = $this->db->prepare('
            SELECT bp.*, bs.name as source_name 
            FROM blog_posts bp 
            JOIN blog_sources bs ON bp.source_id = bs.id 
            WHERE bp.status = "pending" 
            ORDER BY bp.published_date DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $posts = [];
        while ($post = $result->fetchArray(SQLITE3_ASSOC)) {
            $posts[] = $post;
        }
        
        return $posts;
    }
    
    /**
     * Mark post as posted
     */
    public function markAsPosted($postId) {
        $stmt = $this->db->prepare('UPDATE blog_posts SET status = "posted" WHERE id = :id');
        $stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    
    /**
     * Get scan statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total sources
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM blog_sources');
        $result = $stmt->execute();
        $stats['total_sources'] = $result->fetchArray()['count'];
        
        // Total posts scanned
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM blog_posts');
        $result = $stmt->execute();
        $stats['total_posts'] = $result->fetchArray()['count'];
        
        // Pending posts
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM blog_posts WHERE status = "pending"');
        $result = $stmt->execute();
        $stats['pending_posts'] = $result->fetchArray()['count'];
        
        // Scans today
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM scan_history WHERE DATE(scanned_at) = DATE("now")');
        $result = $stmt->execute();
        $stats['scans_today'] = $result->fetchArray()['count'];
        
        return $stats;
    }
    
    /**
     * Close database connection
     */
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $scraper = new CybersecurityBlogScraper();
    
    $action = $argv[1] ?? 'help';
    
    switch ($action) {
        case 'scan':
            echo "Starting scan...\n";
            $results = $scraper->scanAll();
            foreach ($results as $result) {
                echo "✓ {$result['source']}: {$result['new_posts']} new, {$result['skipped_posts']} skipped\n";
            }
            break;
            
        case 'pending':
            $posts = $scraper->getPendingPosts();
            if (empty($posts)) {
                echo "No pending posts.\n";
            } else {
                echo "Pending posts:\n";
                foreach ($posts as $post) {
                    echo "\n[{$post['source_name']}] {$post['title']}\n";
                    echo "URL: {$post['post_url']}\n";
                    echo "Summary: {$post['summary']}\n";
                }
            }
            break;
            
        case 'stats':
            $stats = $scraper->getStatistics();
            echo "Blog Scraper Statistics:\n";
            echo "  Total Sources: {$stats['total_sources']}\n";
            echo "  Total Posts: {$stats['total_posts']}\n";
            echo "  Pending Posts: {$stats['pending_posts']}\n";
            echo "  Scans Today: {$stats['scans_today']}\n";
            break;
            
        case 'help':
        default:
            echo "Cybersecurity Blog Scraper\n";
            echo "Usage: php scraper.php <action>\n";
            echo "Actions:\n";
            echo "  scan    - Scan all blog sources\n";
            echo "  pending - Show pending posts\n";
            echo "  stats   - Show statistics\n";
            echo "  help    - Show this help\n";
            break;
    }
}