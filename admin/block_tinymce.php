<?php
// block_tinymce.php - This file prevents TinyMCE from loading
if (!headers_sent()) {
    header("Content-Security-Policy: script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline' 'unsafe-eval'; frame-src 'self'; connect-src 'self';");
}
?>
<!-- Block TinyMCE loading script - place this at the very top of the page -->
<script type="text/javascript">
    // Block specific TinyMCE CDNs
    const blockedDomains = ['tiny.cloud', 'tinymce.com', 'tinymce.min.js'];
    
    // Helper function to check if URL contains any blocked domains
    function isBlockedUrl(url) {
        if (!url) return false;
        return blockedDomains.some(domain => url.includes(domain));
    }
    
    // Block via document.write override
    const originalDocumentWrite = document.write;
    document.write = function() {
        // Convert all arguments to string and check if they contain TinyMCE
        const content = Array.from(arguments).join('');
        if (content.includes('tinymce') || content.includes('tinyMCE')) {
            console.log('[TinyMCE Blocker] Blocked document.write with TinyMCE content');
            return;
        }
        return originalDocumentWrite.apply(this, arguments);
    };
    
    // Block TinyMCE objects
    Object.defineProperties(window, {
        'tinymce': {
            configurable: false,
            writable: false,
            value: {
                init: function() { return false; },
                execCommand: function() { return false; },
                get activeEditor() { return null; },
                get editors() { return {}; }
            }
        },
        'tinyMCE': {
            configurable: false,
            writable: false,
            value: {
                init: function() { return false; },
                execCommand: function() { return false; },
                get activeEditor() { return null; },
                get editors() { return {}; }
            }
        }
    });
    
    // Block script tag creation or loading
    const originalCreateElement = document.createElement;
    document.createElement = function(tagName) {
        const element = originalCreateElement.call(document, tagName);
        if (tagName.toLowerCase() === 'script') {
            // Override script src setter to block TinyMCE
            const originalSrcDescriptor = Object.getOwnPropertyDescriptor(HTMLScriptElement.prototype, 'src');
            Object.defineProperty(element, 'src', {
                get: function() {
                    return originalSrcDescriptor.get.call(this);
                },
                set: function(value) {
                    if (isBlockedUrl(value)) {
                        console.log('[TinyMCE Blocker] Blocked script load:', value);
                        return;
                    }
                    return originalSrcDescriptor.set.call(this, value);
                }
            });
        }
        return element;
    };
    
    // Block any script loads via XMLHttpRequest
    const originalXhrOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url, ...rest) {
        if (isBlockedUrl(url)) {
            console.log('[TinyMCE Blocker] Blocked XMLHttpRequest:', url);
            throw new Error('Request blocked by TinyMCE blocker');
        }
        return originalXhrOpen.call(this, method, url, ...rest);
    };
    
    // Block TinyMCE via fetch API
    const originalFetch = window.fetch;
    window.fetch = function(resource, init) {
        if (typeof resource === 'string' && isBlockedUrl(resource)) {
            console.log('[TinyMCE Blocker] Blocked fetch request:', resource);
            return Promise.reject(new Error('Request blocked by TinyMCE blocker'));
        }
        return originalFetch.apply(this, arguments);
    };
    
    // Prepare fake TinyMCE initialization object
    window.tinyMCEPreInit = {
        suffix: '',
        base: '',
        query: ''
    };
    
    console.log('[TinyMCE Blocker] Active and blocking TinyMCE from loading');
    
    // Set up MutationObserver to remove any TinyMCE scripts that might get injected
    window.addEventListener('DOMContentLoaded', function() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type !== 'childList') return;
                
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeName === 'SCRIPT') {
                        const src = node.src || '';
                        if (isBlockedUrl(src)) {
                            console.log('[TinyMCE Blocker] Removed injected script:', src);
                            node.parentNode.removeChild(node);
                        }
                    }
                });
            });
        });
        
        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    });
</script>
