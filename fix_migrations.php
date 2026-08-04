<?php

$dir = __DIR__ . '/database/migrations/';
$files = glob($dir . '*.php');

$modifiedCount = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // find Schema::create
    if (preg_match_all('/([ \t]*)Schema::create\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function\s*\(\s*Blueprint\s+\$table\s*\)\s*\{/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
        
        $offset_adjustment = 0;
        foreach ($matches[0] as $index => $match) {
            $full_match_str = $match[0];
            $pos = $match[1] + $offset_adjustment;
            $indent = $matches[1][$index][0];
            $tableName = $matches[2][$index][0];
            
            // Check if it's already wrapped in hasTable
            $before = substr($content, 0, $pos);
            if (preg_match('/if\s*\(\s*!Schema::hasTable\([\'"]' . preg_quote($tableName, '/') . '[\'"]\)\s*\)\s*\{\s*$/s', $before)) {
                continue; // Already wrapped
            }
            
            // Find the matching closing brace for this Schema::create
            $startSearch = $pos + strlen($full_match_str);
            
            $braceCount = 1;
            $endPos = -1;
            for ($i = $startSearch; $i < strlen($content); $i++) {
                if ($content[$i] === '{') {
                    $braceCount++;
                } elseif ($content[$i] === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        // found the closing brace
                        $endPos = $i;
                        if (substr($content, $i, 3) === '});') {
                            $endPos = $i + 2;
                        }
                        break;
                    }
                }
            }
            
            if ($endPos !== -1) {
                $originalBlock = substr($content, $pos, $endPos - $pos + 1);
                
                // Indent the original block by 4 spaces
                $lines = explode("\n", $originalBlock);
                foreach ($lines as &$line) {
                    if (trim($line) !== '') {
                        $line = '    ' . $line;
                    }
                }
                $indentedBlock = implode("\n", $lines);
                
                $newBlock = $indent . "if (!Schema::hasTable('" . $tableName . "')) {\n" . ltrim($indentedBlock) . "\n" . $indent . "}";
                
                $content = substr($content, 0, $pos) . $newBlock . substr($content, $endPos + 1);
                
                $offset_adjustment += strlen($newBlock) - strlen($originalBlock);
            }
        }
        
        if ($content !== $originalContent) {
            file_put_contents($file, $content);
            $modifiedCount++;
        }
    }
}
echo "Modified $modifiedCount migration files.\n";
