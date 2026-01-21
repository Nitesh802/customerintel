<?php
/**
 * Text Processing Helper
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\helpers;

defined('MOODLE_INTERNAL') || die();

/**
 * Text processor helper class
 * 
 * Handles text extraction, chunking, and processing
 */
class text_processor {
    
    /** @var int Default chunk size in characters */
    const DEFAULT_CHUNK_SIZE = 2000;
    
    /** @var int Overlap between chunks in characters */
    const CHUNK_OVERLAP = 200;
    
    /**
     * Extract text from PDF file
     * 
     * @param string $filepath Path to PDF file
     * @return string Extracted text
     */
    public static function extract_from_pdf($filepath) {
        global $CFG;
        
        // Check if we have pdf2text or similar tool available
        if (is_executable('/usr/bin/pdftotext')) {
            $tempfile = tempnam(sys_get_temp_dir(), 'pdf_extract_');
            $command = escapeshellcmd('/usr/bin/pdftotext') . ' ' . 
                       escapeshellarg($filepath) . ' ' . 
                       escapeshellarg($tempfile) . ' 2>&1';
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($tempfile)) {
                $text = file_get_contents($tempfile);
                unlink($tempfile);
                return self::clean_extracted_text($text);
            }
        }
        
        // Fallback to basic PHP library if available
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filepath);
                $text = $pdf->getText();
                return self::clean_extracted_text($text);
            } catch (\Exception $e) {
                debugging('PDF extraction failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        
        // If no PDF tools available, return empty
        debugging('No PDF extraction tools available', DEBUG_DEVELOPER);
        return '';
    }
    
    /**
     * Extract text from DOCX file
     * 
     * @param string $filepath Path to DOCX file
     * @return string Extracted text
     */
    public static function extract_from_docx($filepath) {
        $text = '';
        
        // DOCX files are ZIP archives containing XML
        $zip = new \ZipArchive();
        if ($zip->open($filepath) === true) {
            // Main document content is in word/document.xml
            $content = $zip->getFromName('word/document.xml');
            if ($content !== false) {
                // Strip XML tags but preserve structure
                $content = str_replace('</w:p>', "\n", $content);
                $content = str_replace('</w:br/>', "\n", $content);
                $text = strip_tags($content);
                $text = self::clean_extracted_text($text);
            }
            $zip->close();
        }
        
        return $text;
    }
    
    /**
     * Extract text from plain text file
     * 
     * @param string $filepath Path to text file
     * @return string File contents
     */
    public static function extract_from_txt($filepath) {
        $text = file_get_contents($filepath);
        return self::clean_extracted_text($text);
    }
    
    /**
     * Clean extracted text
     * 
     * @param string $text Raw extracted text
     * @return string Cleaned text
     */
    public static function clean_extracted_text($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove non-printable characters
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text);
        
        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove multiple consecutive line breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Chunk text into smaller segments
     * 
     * @param string $text Text to chunk
     * @param int $chunksize Target chunk size in characters
     * @param int $overlap Overlap between chunks
     * @return array Array of text chunks with metadata
     */
    public static function chunk_text($text, $chunksize = self::DEFAULT_CHUNK_SIZE, $overlap = self::CHUNK_OVERLAP) {
        $chunks = [];
        $textlength = strlen($text);
        
        // If text is smaller than chunk size, return as single chunk
        if ($textlength <= $chunksize) {
            return [[
                'text' => $text,
                'start' => 0,
                'end' => $textlength,
                'index' => 0,
                'hash' => sha1($text)
            ]];
        }
        
        // Split into sentences first for better boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $currentchunk = '';
        $currentstart = 0;
        $chunkindex = 0;
        $position = 0;
        
        foreach ($sentences as $sentence) {
            $sentencelength = strlen($sentence);
            
            // If adding this sentence would exceed chunk size
            if (strlen($currentchunk) + $sentencelength + 1 > $chunksize) {
                // Save current chunk if it has content
                if (!empty($currentchunk)) {
                    $chunks[] = [
                        'text' => trim($currentchunk),
                        'start' => $currentstart,
                        'end' => $position,
                        'index' => $chunkindex,
                        'hash' => sha1(trim($currentchunk))
                    ];
                    $chunkindex++;
                    
                    // Start new chunk with overlap
                    if ($overlap > 0 && strlen($currentchunk) > $overlap) {
                        // Get last N characters for overlap
                        $overlaptext = substr($currentchunk, -$overlap);
                        $currentchunk = $overlaptext . ' ' . $sentence;
                        $currentstart = max(0, $position - $overlap);
                    } else {
                        $currentchunk = $sentence;
                        $currentstart = $position;
                    }
                } else {
                    $currentchunk = $sentence;
                    $currentstart = $position;
                }
            } else {
                // Add sentence to current chunk
                if (!empty($currentchunk)) {
                    $currentchunk .= ' ';
                }
                $currentchunk .= $sentence;
            }
            
            $position += $sentencelength + 1;
        }
        
        // Don't forget the last chunk
        if (!empty($currentchunk)) {
            $chunks[] = [
                'text' => trim($currentchunk),
                'start' => $currentstart,
                'end' => $textlength,
                'index' => $chunkindex,
                'hash' => sha1(trim($currentchunk))
            ];
        }
        
        return $chunks;
    }
    
    /**
     * Extract key phrases from text for indexing
     * 
     * @param string $text Text to analyze
     * @return array Key phrases
     */
    public static function extract_key_phrases($text) {
        // Simple keyword extraction - can be enhanced with NLP
        $words = str_word_count(strtolower($text), 1);
        $wordfreq = array_count_values($words);
        
        // Filter out common words
        $stopwords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'as', 'are', 'was', 'were', 'to', 'of', 'and', 'in', 'for', 'with'];
        foreach ($stopwords as $stopword) {
            unset($wordfreq[$stopword]);
        }
        
        // Sort by frequency
        arsort($wordfreq);
        
        // Return top 10
        return array_slice(array_keys($wordfreq), 0, 10);
    }
    
    /**
     * Calculate text similarity using simple algorithm
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score (0-1)
     */
    public static function calculate_similarity($text1, $text2) {
        $words1 = str_word_count(strtolower($text1), 1);
        $words2 = str_word_count(strtolower($text2), 1);
        
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        
        if (count($union) == 0) {
            return 0;
        }
        
        // Jaccard similarity
        return count($intersection) / count($union);
    }
    
    /**
     * Estimate token count for text
     * 
     * @param string $text Text to count
     * @return int Estimated token count
     */
    public static function estimate_tokens($text) {
        // Rough estimation: ~0.75 tokens per word for English
        $wordcount = str_word_count($text);
        return (int)($wordcount * 0.75);
    }
}