<?php
// Audit-only: analyse route:list --json output for unauthenticated routes and throttles.
$raw = file_get_contents(__DIR__.'/../docs/audit/raw/static-routes.json');
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$start = strpos($raw, '[');
$routes = json_decode(substr($raw, $start), true);
if (! is_array($routes)) {
    fwrite(STDERR, "cannot parse json\n");
    exit(1);
}
$total = count($routes);
$unauth = [];
$throttled = [];
foreach ($routes as $r) {
    if (str_contains($r['uri'], 'storage')) {
        echo "STORAGE ROUTE: " . json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
    $mw = implode(',', (array) ($r['middleware'] ?? []));
    $line = sprintf('%-8s %-55s %s', $r['method'], '/' . ltrim($r['uri'], '/'), $mw);
    if (! str_contains($mw, 'Authenticate') && ! str_contains($mw, 'auth:')) {
        $unauth[] = $line;
    }
    if (str_contains($mw, 'Throttle') || str_contains($mw, 'throttle')) {
        $throttled[] = $line;
    }
}
echo "TOTAL ROUTES: $total\n\n== ROUTES WITHOUT AUTH MIDDLEWARE (".count($unauth)."):\n".implode("\n", $unauth)."\n\n";
echo '== ROUTES WITH THROTTLE ('.count($throttled)."):\n".implode("\n", $throttled)."\n";
