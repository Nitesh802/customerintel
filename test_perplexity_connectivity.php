<?php
$start = microtime(true);
$ch = curl_init("https://api.perplexity.ai/chat/completions");
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
$elapsed = round(microtime(true) - $start, 2);
echo "HTTP Code: $httpcode\n";
echo "cURL Error: $error\n";
echo "Time: {$elapsed}s\n";
?>