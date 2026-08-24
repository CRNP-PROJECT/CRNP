<?php
/**
 * health.php — platform liveness endpoint.
 * Deliberately touches nothing (no session, no Firebase): answers 200 as long
 * as PHP itself is serving, so deploys don't flap when upstreams hiccup.
 */

http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
