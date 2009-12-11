<?php
require_once 'spyc/spyc.php';

//
// Page

class Page implements IteratorAggregate
{
    public static function load($path_to_page) {
        
        $structure = array('blocks' => array());

        $block_type     = null;
        $block_args     = array();
        $block_buffer   = '';

        $fh = fopen($path_to_page, 'r');
        while ($line = fgets($fh, 16384)) {
            if (preg_match('/^--- (\w+)(.*?)$/', $line, $matches)) {
                self::commit_block($structure, $block_type, $block_args, $block_buffer);
                $block_type     = $matches[1];
                $block_args     = self::parse_block_args($matches[2]);
                $block_buffer   = '';
            } else {
                $block_buffer .= $line;
            }
        }
        fclose($fh);

        self::commit_block($structure, $block_type, $block_args, $block_buffer);

        if (!isset($structure['type'])) {
            $structure['type'] = 'Page';
        }
        
        $blocks = $structure['blocks'];
        unset($structure['blocks']);
        
        if (isset($structure['published_at'])) {
            $dt = $structure['published_at'];
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $m)) {
                $h = $i = 0;
                if (isset($m[4])) { $h = $m[4]; $i = $m[5]; }
                $structure['published_at'] = mktime($h, $i, 0, $m[2], $m[3], $m[1]);
            } else {
                $structure['published_at'] = strtotime($structure['published_at']);
            }
        }
        
        if (!$structure['published_at']) {
            $structure['published_at'] = filemtime($path_to_page);
        }
        
        $class = $structure['type'];
        $page = new $class($structure, $blocks);

        return $page;
        
    }
    
    private static function commit_block(&$structure, $block_type, $block_args, $content) {
        if ($block_type === null) {
            foreach (Spyc::YAMLLoad($content) as $k => $v) {
                $structure[$k] = $v;
            }
        } else {
            $structure['blocks'][] = Block::create($block_type, $content, $block_args);
        }
    }
    
    private static function parse_block_args($string) {
        $offset = 0;
        $args   = array();
        while (preg_match('/\s*(\w+):\s*/', $string, $matches, 0, $offset)) {
            $offset += strlen($matches[0]);
            $key = $matches[1];
            if (preg_match('/[\'"]/', $string[$offset])) {
                $q = $string[$offset++];
                $b = '';
                while ($offset < strlen($string)) {
                    $c = $string[$offset++];
                    if ($c == '\\') {
                        $offset++;
                    } elseif ($c == $q) {
                        $args[$key] = $b;
                        break;
                    }
                    $b .= $c;
                }
            } else {
                preg_match('/([^\s]+)/', $string, $matches, 0, $offset);
                $offset += strlen($matches[0]);
                $args[$key] = $matches[0];
            }
        }
        return $args;
    }
    
    private $__meta;
    private $__blocks;
    
    public function __construct($meta, $blocks) {
        $this->__meta = $meta;
        $this->__blocks = $blocks;
    }
    
    public function __get($k) {
        return isset($this->__meta[$k]) ? $this->__meta[$k] : null;
    }
    
    public function __set($k, $v) {
        $this->__meta[$k] = $v;
    }
    
    public function getIterator() {
        return new ArrayIterator($this->__blocks);
    }
    
    public function block_html($options = array()) {
        $options += array('truncate' => false);
        $out = '';
        foreach ($this as $block) {
            if ($block instanceof PagebreakBlock && $options['truncate']) {
                break;
            }
            $out .= $block->to_html();
        }
        return $out;
    }
}

//
// Blocks

abstract class Block
{
    private static $registry = array(
        'html'          => 'HtmlBlock',
        'code'          => 'CodeBlock',
        'markdown'      => 'MarkdownBlock',
        'textile'       => 'TextileBlock',
        'pagebreak'     => 'PagebreakBlock'
    );
    
    public static function class_for_block_id($block_id) {
        return self::$registry[$block_id];
    }
    
    public static function create($block_id, $content, $args) {
        $class = self::class_for_block_id($block_id);
        return new $class($content, $args);
    }
    
    protected $content, $args;
    
    public function __get($k) {
        return isset($this->args[$k]) ? $this->args[$k] : null;
    }
    
    public function __construct($content, $args) {
        $this->content  = $content;
        $this->args     = $args;
    }
    
    abstract public function to_html();
}

class HtmlBlock extends Block
{
    public function to_html() {
        return $this->content;
    }
}

class CodeBlock extends Block
{
    public function get_language() {
        return empty($this->args['language']) ? 'text' : $this->args['language'];
    }
    
    public function to_html() {
        
        require_once 'geshi/geshi.php';
        
        $geshi = new GeSHi(trim($this->content), $this->get_language());
        if (function_exists('staticish_configure_geshi')) {
            staticish_configure_geshi($geshi, $this);
        }
        
        if ($this->highlight) {
            $geshi->highlight_lines_extra(explode(',', $this->highlight));
        }
        
        if ($this->start) {
            $geshi->start_line_numbers_at($this->start);
        }
        
        $out = "<div class='code-block'>";
        
        if ($this->caption) {
            $out .= "<div class='code-caption'>" . htmlentities($this->caption) . "</div>";
        }
        
        $out .= $geshi->parse_code();
        
        $out .= "</div>";
        
        return $out;
    }
}

abstract class FilteredBlock extends Block
{
    protected abstract function filter($content);
    
    public function to_html() {
        return $this->filter($this->content);
    }
}

class MarkdownBlock extends FilteredBlock
{
    protected function filter($content) {
        require_once 'markdown/markdown.php';
        return Markdown($content);
    }
}

class TextileBlock extends FilteredBlock
{
    protected function filter($content) {
        require_once 'textile/classTextile.php';
        $textile = new Textile();
        return $textile->TextileThis($content);
    }
}

class PagebreakBlock extends Block
{
    public function to_html() {
        return '';
    }
}

//
// Blog

class Blog
{
    private $path;
    private $info               = null;
    private $truncate           = true;
    
    public function __construct($path) {
        $this->path = $path;
    }
    
    public function set_truncate($t) {
        $this->truncate = (bool) $t;
    }
    
    public function get_info() {
        if ($this->info === null) {
            $this->info = Page::load($this->path . '/index.page');
        }
        return $this->info;
    }
    
    public function get_post($path) {
        
        if (!preg_match('|^\d{4}/\d{2}/[a-z0-9\._-]+$|', $path)) {
            $this->not_found();
        }
        
        if (strpos($path, '..') !== false) {
            $this->not_found();
        }
        
        $bits       = explode('/', $path);
        $search     = '|^(\d+-)?' . preg_quote(array_pop($bits)) . '\.page$|';
        $post_dir   = implode('/', $bits);
        
        foreach (scandir("{$this->path}/{$post_dir}") as $post_file) {
            if (preg_match($search, $post_file)) {
                return $this->load_post($post_dir . '/' . $post_file);
            }
        }
        
        $this->not_found();
        
    }
    
    public function get_page($page, $rpp) {
      
        $page = (int) $page;
        if ($page < 1) $this->not_found();
        
        $skip   = ($page - 1) * $rpp;
        $state  = 'out';
        $page   = array();
        
        foreach (scandir($this->path, 1) as $year) {
            if (!preg_match('/^\d{4}$/', $year)) continue;
            foreach (scandir("{$this->path}/$year", 1) as $month) {
                if (!preg_match('/^\d{2}$/', $month)) continue;
                if ($state == 'out') {
                    $num_pages = count(glob("{$this->path}/$year/$month/*.page"));
                    if ($num_pages > $skip) {
                        $state = 'in';
                    } else {
                        $skip -= $num_pages;
                    }
                }
                if ($state == 'in') {
                    $page = array_merge($page, $this->get_monthly_archive($year, $month));
                    if (count($page) >= $rpp + $skip) break 2;
                }
            }
        }
        
        while ($skip--) array_shift($page);
        while (count($page) > $rpp) array_pop($page);
        
        return $page;
        
    }
    
    public function get_monthly_archive_counts() {
        $counts = array();
        foreach (scandir($this->path, 1) as $year) {
            if (!preg_match('/^\d{4}$/', $year)) continue;
            foreach (scandir("{$this->path}/$year", 1) as $month) {
                if (!preg_match('/^\d{2}$/', $month)) continue;
                $counts[mktime(0, 0, 0, $month, 1, $year)] = count(glob("{$this->path}/$year/$month/*.page"));
            }
        }
        return $counts;
    }
    
    public function get_article_count() {
        return array_sum($this->get_monthly_archive_counts());
    }
    
    public function get_page_count($rpp) {
        return ceil($this->get_article_count() / $rpp);
    }
    
    public function get_monthly_archive($year, $month = null) {
        if ($month === null) {
            $month = date('m', $year);
            $year  = date('Y', $year);
        }
        $year = sprintf("%04d", (int) $year);
        $month  = sprintf("%02d", (int) $month);
        return $this->load_directory("$year/$month");
    }
    
    //
    // Page loading
    
    private function load_directory($directory) {
        $pages = array();
        
        if (!($dh = @opendir($this->blog_path($directory)))) {
            $this->not_found();
        }
        
        while (($file = readdir($dh)) !== false) {
            if (preg_match('/\.page$/', $file)) {
                $pages[] = $this->load_post($directory . '/' . $file);
            }
        }
        
        closedir($dh);
        
        usort($pages, array($this, 'compare'));
        
        return $pages;
    }
    
    // loads an article relative to the blog root, e.g. "2009/10/14-foobar.page"
    // sets up article URL by stripping page extension and day number (if present)
    private function load_post($post_path) {
        $post = Page::load($this->path . '/' . $post_path);
        $post->url = $this->relative_url($this->post_path_to_url($post_path));
        return $post;
    }
    
    public function compare($page1, $page2) {
        return $page2->published_at - $page1->published_at;
    }
    
    //
    // RSS Feed Generation
    
    public function get_feed($count) {
      
        $info = $this->get_info();
        
        $doc = new DOMDocument;
        $root = $doc->createElement('channel');
        $root = $doc->appendChild($root);
        
        $this->add_text_node($doc, $root, 'title', $info->title);
        $this->add_text_node($doc, $root, 'description', $info->description);
        
        if ($info->language)    $this->add_text_node($doc, $root, 'language', $info->language);
        if ($info->copyright)   $this->add_text_node($doc, $root, 'copyright', $info->copyright);
        
        $this->add_text_node($doc, $root, 'generator', 'static-ish');
        
        foreach ($this->get_page(1, $count) as $post) {
            
            $item = $doc->createElement('item');
            
            $options = array('truncate' => $this->truncate);
            
            $this->add_text_node($doc, $item, 'title', $post->title);
            $this->add_text_node($doc, $item, 'pubDate', date('r', $post->published_at));
            $this->add_text_node($doc, $item, 'description', $post->block_html($options));
            
            $root->appendChild($item);
            
        }
        
        return $doc;
    
    }
    
    private function add_text_node($doc, $root, $name, $text) {
        $ele = $doc->createElement($name);
        $ele->appendChild($doc->createTextNode($text));
        $root->appendChild($ele);
    }
    
    //
    // Utility
    
    private function blog_path($path) {
        return $this->path . '/' . $path;
    }
    
    private function relative_url($path) {
        return $this->get_info()->base_url . '/' . $path;
    }
    
    private function post_path_to_url($path) {
        $path = preg_replace('|\.page$|', '', $path);
        $path = preg_replace('|/\d+-|', '/', $path);
        return $path;
    }
    
    private function not_found() {
        throw new NotFoundException;
    }
}
?>