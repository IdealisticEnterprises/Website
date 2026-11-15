<?php
require '/var/www/.structure/library/idealistic_office/init.php';
IdealisticOfficeBrand::start();

header("Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms allow-modals");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$url = $_GET["url"] ?? null;

// 1. Βασικός έλεγχος εγκυρότητας URL
if (!$url || !is_url($url)) {
    fallback_redirect(1);
}

$parsedUrl = parse_url($url);
$host = $parsedUrl['host'] ?? null;

if (!$host || $host === 'idealistic.ai') {
    fallback_redirect(2);
}

// 2. Προστασία SSRF: Έλεγχος αν ΟΛΕΣ οι resolved IPs είναι public.
// gethostbyname() only returns a single (the first) A record and only IPv4,
// which allows a host with multiple A records (one public, one private) to
// slip through, and does nothing to stop DNS-rebinding between this check
// and the later fetch. We resolve every A/AAAA record here and reject if
// any of them is private/reserved.
$records = array_merge(
    dns_get_record($host, DNS_A) ?: [],
    dns_get_record($host, DNS_AAAA) ?: []
);

if (empty($records)) {
    fallback_redirect(3);
}

foreach ($records as $record) {
    $recordIp = $record['ip'] ?? $record['ipv6'] ?? null;

    if ($recordIp === null
        || !filter_var($recordIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        fallback_redirect(3); // Μπλοκάρισμα εσωτερικών IPs (127.0.0.1, 10.0.0.x, 169.254.169.254 κλπ)
    }
}

$html = IdealisticOfficeProcessor::getLinkContents($url);

// Check if the target website already has the Idealistic script installed
if (stripos($html, IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_SCRIPT_ID) !== false) {
    fallback_redirect(4);
}

$httpCode = IdealisticOfficeProcessor::getLastHttpCode();
$widgetScript = str_replace(
    IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER,
    IdealisticOfficeVariable::PORTAL_DEFAULT_FOR_TESTING,
    IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_WITH_DEFAULT_MESSAGE_AND_DOMAIN
);
$widgetScript = str_replace(
    IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_DEFAULT_MESSAGE_PLACEHOLDER,
    htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
    $widgetScript
);
$widgetScript = str_replace(
    IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_DEFAULT_DOMAIN_PLACEHOLDER,
    htmlspecialchars($host, ENT_QUOTES, 'UTF-8'),
    $widgetScript
);

// --- START OF PROMO BANNER & BLUR OVERLAY ADDITION ---
$companyUrl = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
$brand = IdealisticOfficeVariable::APPLICATION_NAME;
$promoBanner = <<<HTML
<style>
    /* Push the proxy site down slightly to avoid hiding its own header */
    html { margin-top: 40px !important; }
    
    /* The Blur Overlay using Transitions instead of Keyframes for reliability */
    #idealistic-blur-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.4) !important; 
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        z-index: 2147483645 !important; /* Just below the banner and widget */
        pointer-events: all !important; /* Blocks the user from clicking links on the blurred site */
        transition: opacity 1.5s ease-in-out, backdrop-filter 1.5s ease-in-out, -webkit-backdrop-filter 1.5s ease-in-out !important;
        opacity: 1;
    }
    
    #idealistic-promo-banner {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 40px;
        background: rgba(9, 9, 11, 0.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        color: #f4f4f5;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2147483647 !important; /* Fixed at highest level */
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 14px;
        font-weight: 500;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        box-sizing: border-box;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        text-decoration: none;
        transition: background-color 0.2s ease;
    }
    #idealistic-promo-banner:hover {
        background: rgba(24, 24, 27, 0.95);
    }
    #idealistic-promo-banner span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #ffffff;
    }
    #idealistic-promo-banner svg {
        width: 14px;
        height: 14px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
        transition: transform 0.2s ease;
    }
    #idealistic-promo-banner:hover svg {
        transform: translateX(3px);
    }
</style>

<!-- Full-screen glass overlay to blur the website -->
<div id="idealistic-blur-overlay"></div>

<!-- Top Banner -->
<a href="{$companyUrl}" id="idealistic-promo-banner" target="_blank">
    <span>
        Get {$brand} widget for your website
        <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </span>
</a>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Trigger the Blur Fade-out after exactly 3 seconds
        setTimeout(function() {
            var overlay = document.getElementById('idealistic-blur-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
                overlay.style.backdropFilter = 'blur(0px)';
                overlay.style.webkitBackdropFilter = 'blur(0px)';
                overlay.style.pointerEvents = 'none'; // Unblocks the website underneath
                
                // Clean it up from the DOM completely after the fade finishes
                setTimeout(function() {
                    overlay.remove();
                }, 1500);
            }
        }, 3000);

        // 2. Auto-open the Chat Widget IMMEDIATELY (polls every 200ms until the widget loads)
        var attempts = 0;
        var checkInterval = setInterval(function() {
            attempts++;
            var clicked = false;
            
            // Try to find the widget iframe (if it uses one)
            var iframes = document.querySelectorAll('iframe');
            iframes.forEach(function(iframe) {
                if (iframe.src.includes('idealistic') || iframe.id.includes('idealistic')) {
                    try {
                        // Click the button inside the iframe
                        var innerDoc = iframe.contentDocument || iframe.contentWindow.document;
                        var btn = innerDoc.querySelector('button, .launcher, [role="button"], #idealistic-launcher');
                        if (btn) { btn.click(); clicked = true; }
                    } catch(e) {
                        // Fallback: send a postMessage to open it if cross-origin rules block the click
                        iframe.contentWindow.postMessage('idealistic-open', '*');
                        iframe.contentWindow.postMessage({ type: 'open' }, '*');
                        clicked = true;
                    }
                }
            });

            // Alternatively, if the widget is rendered in the main DOM:
            var mainDomElements = document.querySelectorAll('[id*="idealistic"], [class*="idealistic"]');
            mainDomElements.forEach(function(el) {
                var elId = (el.id || '').toLowerCase();
                var elClass = (el.className || '').toString().toLowerCase();
                
                // Look for common launcher names and click them
                if (elId.includes('launcher') || elId.includes('button') || elId.includes('toggle') ||
                    elClass.includes('launcher') || elClass.includes('button') || elClass.includes('toggle')) {
                    el.click();
                    clicked = true;
                }
            });
            
            // Stop checking once we successfully open it, or give up after 5 seconds
            if (clicked || attempts > 25) {
                clearInterval(checkInterval);
            }
        }, 200); 
    });
</script>
HTML;

// Attach the banner & overlay to the script injection variable
$widgetScript .= $promoBanner;
// --- END OF PROMO BANNER ADDITION ---

// 4. Ανίχνευση WAF / Bot Protection (Cloudflare, Datadome, Akamai κλπ)
// Ελέγχουμε τον κωδικό απάντησης ή κλασικά strings του Cloudflare που είδαμε στο screenshot
if ($httpCode === 403 || $httpCode === 503 ||
    stripos($html, 'security verification') !== false ||
    stripos($html, 'Cloudflare') !== false ||
    stripos($html, 'Attention Required! | Cloudflare') !== false) {

    // Επιστρέφουμε ένα όμορφο fallback UI αντί για τη σπασμένη σελίδα του Cloudflare
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en" data-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Preview Unavailable - {$brand}</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { background: #0d0d0f; color: #f4f4f5; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
            .container { max-width: 500px; padding: 40px; background: rgba(255, 255, 255, 0.035); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3); }
            h2 { font-weight: 700; margin-bottom: 15px; }
            p { color: #a1a1aa; line-height: 1.6; margin-bottom: 25px; }
            .badge { display: inline-block; background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 5px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.3); margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="badge">Strict Security Detected</div>
            <h2>Preview Blocked by Firewall</h2>
            <p>We couldn't generate a live preview because your website uses strict security protection (e.g., Cloudflare) that blocks our preview bot.</p>
            <p style="font-size: 0.9rem;"><strong>Don't worry!</strong> The {$brand} widget will work perfectly once you install the script directly on your website.</p>
            <!-- Κάνουμε inject το widget ακόμα και εδώ, ώστε να μπορεί να το δοκιμάσει στη "λευκή κόλλα"! -->
        </div>
        {$widgetScript}
    </body>
    </html>
HTML;
    exit;
}

// 5. Απολύμανση HTML: αφαίρεση <script> και inline event-handlers πριν το echo.
//
// This page echoes a third party's raw HTML directly under this app's own
// origin (idealistic.ai). Without this step, any <script> or onX="" handler
// on the fetched page would execute with this origin's privileges in the
// visiting browser — able to read non-HttpOnly cookies/local storage and
// make authenticated requests using the visitor's live session. We strip
// script execution vectors with a real DOM parser (regex-based HTML
// sanitization is well known to be bypassable) before any further
// processing or output.
if (!empty($html)) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    foreach (iterator_to_array($xpath->query('//script')) as $node) {
        $node->parentNode?->removeChild($node);
    }
    // Also drop <link rel="import"> / <object>/<embed>, additional script vectors.
    foreach (iterator_to_array($xpath->query('//object | //embed')) as $node) {
        $node->parentNode?->removeChild($node);
    }

    foreach (iterator_to_array($xpath->query('//*[@*]')) as $node) {
        if (!($node instanceof DOMElement)) {
            continue;
        }
        foreach (iterator_to_array($node->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (str_starts_with($name, 'on') || $name === 'srcdoc') {
                $node->removeAttribute($attr->name);
                continue;
            }
            if (in_array($name, ['href', 'src', 'action', 'formaction', 'xlink:href', 'data', 'poster', 'background'], true)
                && preg_match('/^(javascript|vbscript|data):/i', preg_replace('/[\x00-\x20\x7F]+/', '', $attr->value))) {
                $node->removeAttribute($attr->name);
            }
        }
    }
    $sanitized = $dom->saveHTML();

    if (!empty($sanitized)) {
        $html = $sanitized;
    }
}

// 6. Μετασχηματισμός HTML: Διόρθωση Relative URLs
$baseUrl = $parsedUrl['scheme'] . '://' . $host;

// Διορθώνει src="..." και href="..." που ξεκινούν με / ή δεν έχουν scheme
$html = preg_replace_callback(
    '/(src|href)=["\'](?!\/\/|http|https|data:)([^"\']+)["\']/i',
    function ($matches) use ($baseUrl) {
        $path = $matches[2];
        // Αν ξεκινάει με /, κολλάει το base URL, αλλιώς προσθέτει / ενδιάμεσα
        $absoluteUrl = (strpos($path, '/') === 0) ? $baseUrl . $path : $baseUrl . '/' . $path;
        return $matches[1] . '="' . $absoluteUrl . '"';
    },
    $html
);

// Πρόσθετη διόρθωση για <base href="..."> αν υπάρχει
if (preg_match('/<base[^>]+href=["\']([^"\']+)["\']/i', $html, $baseMatch)) {
    // Αν υπάρχει ήδη base tag, το σεβόμαστε, αλλά το URL rewriting που κάναμε παραπάνω
    // ίσως συγκρουστεί. Το αφήνουμε ως έχει για απλότητα.
} else {
    // Inject <base> tag για να είμαστε σίγουροι ότι τα assets φορτώνουν σωστά
    $baseTag = '<base href="' . $baseUrl . '/">';
    $html = preg_replace('/<head>/i', '<head>' . $baseTag, $html, 1);
}


// 7. Εισαγωγή του Idealistic Chat Widget Script
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $widgetScript . '</body>', $html);
} else {
    $html .= $widgetScript;
}

// Εμφάνιση του τροποποιημένου HTML
$account = new Account(
    Account::IDEALISTIC_OFFICE_APPLICATION_ID
);
$account->getSession()->delete();
echo $html;

function fallback_redirect(int $number): void
{
    if (false) {
        var_dump($number);
    } else {
        require '/var/www/.structure/library/base/redirect.php';
        exit;
    }
}