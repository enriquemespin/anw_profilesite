<?php
/**
 * Database Manager for Cybersecurity Blog Scraper
 * Handles all database operations
 */

class DatabaseManager {
    private $db;
    private $dbPath;
    
    public function __construct($dbPath) {
        $this->dbPath = $dbPath;
        $this->connect();
    }
    
    private function connect() {
        // Ensure directory exists
        $dir = dirname($this->dbPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->db = new SQLite3($this->dbPath);
        $this->db->busyTimeout(5000);
        $this->createTables();
    }
    
    private function createTables() {
        $this->db->exec('CREATE TABLE IF NOT EXISTS blog_sources (
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
        
        $this->db->exec('CREATE TABLE IF NOT EXISTS blog_posts (
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
        
        $this->db->exec('CREATE TABLE IF NOT EXISTS scan_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_id INTEGER,
            scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            posts_found INTEGER DEFAULT 0,
            posts_new INTEGER DEFAULT 0,
            posts_skipped INTEGER DEFAULT 0,
            error TEXT,
            FOREIGN KEY (source_id) REFERENCES blog_sources(id)
        )');
        
        // Create indexes for performance
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_blog_posts_url ON blog_posts(post_url)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_blog_posts_status ON blog_posts(status)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_scan_history_source ON scan_history(source_id)');
    }
    
    /**
     * Insert or ignore blog source
     */
    public function insertSource($name, $url, $type = 'rss', $category = '', $priority = 2) {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO blog_sources (name, url, type, category, priority) VALUES (:name, :url, :type, :category, :priority)');
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':priority', $priority, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get all enabled blog sources
     */
    public function getSources($orderBy = 'priority', $orderDir = 'ASC') {
        $stmt = $this->db->prepare("SELECT * FROM blog_sources WHERE enabled = 1 ORDER BY $orderBy $orderDir");
        $result = $stmt->execute();
        $sources = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $sources[] = $row;
        }
        $stmt->close();
        return $sources;
    }
    
    /**
     * Check if post URL already exists
     */
    public function postExists($url) {
        $stmt = $this->db->prepare('SELECT id FROM blog_posts WHERE post_url = :url LIMIT 1');
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $result = $stmt->execute();
        $exists = $result->fetchArray() !== false;
        $stmt->close();
        return $exists;
    }
    
    /**
     * Insert a new blog post
     */
    public function insertPost($sourceId, $postUrl, $title, $author = '', $publishedDate = '', $summary = '', $whatsNew = '', $question = '', $nextSteps = '') {
        $stmt = $this->db->prepare('INSERT INTO blog_posts (source_id, post_url, title, author, published_date, summary, whats_new, question, next_steps, status) VALUES (:source_id, :url, :title, :author, :published, :summary, :whats_new, :question, :next_steps, :status)');
        $stmt->bindValue(':source_id', $sourceId, SQLITE3_INTEGER);
        $stmt->bindValue(':url', $postUrl, SQLITE3_TEXT);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':author', $author, SQLITE3_TEXT);
        $stmt->bindValue(':published', $publishedDate, SQLITE3_TEXT);
        $stmt->bindValue(':summary', $summary, SQLITE3_TEXT);
        $stmt->bindValue(':whats_new', $whatsNew, SQLITE3_TEXT);
        $stmt->bindValue(':question', $question, SQLITE3_TEXT);
        $stmt->bindValue(':next_steps', $nextSteps, SQLITE3_TEXT);
        $stmt->bindValue(':status', 'pending', SQLITE3_TEXT);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Record scan history
     */
    public function recordScan($sourceId, $postsFound, $postsNew, $postsSkipped, $error = null) {
        $stmt = $this->db->prepare('INSERT INTO scan_history (source_id, posts_found, posts_new, posts_skipped, error) VALUES (:source_id, :found, :new, :skipped, :error)');
        $stmt->bindValue(':source_id', $sourceId, SQLITE3_INTEGER);
        $stmt->bindValue(':found', $postsFound, SQLITE3_INTEGER);
        $stmt->bindValue(':new', $postsNew, SQLITE3_INTEGER);
        $stmt->bindValue(':skipped', $postsSkipped, SQLITE3_INTEGER);
        $stmt->bindValue(':error', $error, SQLITE3_TEXT);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Update last scanned time
     */
    public function updateLastScanned($sourceId) {
        $stmt = $this->db->prepare('UPDATE blog_sources SET last_scanned = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->bindValue(':id', $sourceId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get pending posts
     */
    public function getPendingPosts($limit = 5, $offset = 0) {
        $stmt = $this->db->prepare('
            SELECT bp.*, bs.name as source_name, bs.category 
            FROM blog_posts bp 
            JOIN blog_sources bs ON bp.source_id = bs.id 
            WHERE bp.status = "pending" 
            ORDER BY bp.published_date DESC 
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $posts = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $posts[] = $row;
        }
        $stmt->close();
        return $posts;
    }
    
    /**
     * Mark post as posted
     */
    public function markAsPosted($postId) {
        $stmt = $this->db->prepare('UPDATE blog_posts SET status = "posted" WHERE id = :id');
        $stmt->bindValue(':id', $postId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        $stats = [];
        
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM blog_sources');
        $result = $stmt->execute();
        $stats['total_sources'] = $result->fetchArray()['count'];
        $stmt->close();
        
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM blog_posts');
        $result = $stmt->execute();
        $stats['total_posts'] = $result->fetchArray()['count'];
        $stmt->close();
        
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM blog_posts WHERE status = "pending"');
        $result = $stmt->execute();
        $stats['pending_posts'] = $result->fetchArray()['count'];
        $stmt->close();
        
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM scan_history WHERE DATE(scanned_at) = DATE("now")');
        $result = $stmt->execute();
        $stats['scans_today'] = $result->fetchArray()['count'];
        $stmt->close();
        
        return $stats;
    }
    
    /**
     * Get scan history
     */
    public function getScanHistory($limit = 10) {
        $stmt = $this->db->prepare('
            SELECT sh.*, bs.name as source_name 
            FROM scan_history sh 
            JOIN blog_sources bs ON sh.source_id = bs.id 
            ORDER BY sh.scanned_at DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $history = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $history[] = $row;
        }
        $stmt->close();
        return $history;
    }
    
    public function close() {
        if ($this->db) {
            $this->db->close();
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}