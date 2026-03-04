<?php
header('Content-Type: text/plain');
echo "Loaded Configuration File: " . php_ini_loaded_file() . "\n";
echo "Scan this dir for additional .ini files: " . php_ini_scanned_files() . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
