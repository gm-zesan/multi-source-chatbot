<?php
$cleanQuery = '#1024';
if (preg_match('/^(?:\s*(?:order|অর্ডার|#)?\s*\d{3,8}\s*)$/ui', $cleanQuery)) {
    echo "MATCHES!\n";
} else {
    echo "DOES NOT MATCH!\n";
}
