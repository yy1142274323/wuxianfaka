<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Payment notify endpoint is intentionally disabled in this clean installable source.\n";
