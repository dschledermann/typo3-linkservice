<?php

class tx_linkservice_httpheadquery {
    // Request parts
    private $port = 80;
    private $host = "www.example.com";
    private $protocol = "http";
    private $path = "/";
    private $is_valid = true;
    private $fragment = '';

    // Response parts
    private $response_code = 0;
    private $location = '';

    // Settings
    public $http_timeout = 5;

    public function submitUrl($url) {
        // Reset out status
        $this->response_code = 0;
        $this->location = '';

        // Collect url parts and assemble in our usable parts
        $parts = parse_url($url);
        $this->host = $parts['host'];
        $this->path = $parts['path'];

        // We always have some port.
        if ($parts['port']) {
            $this->port = $parts['port'];
        }
        else {
            $this->port = 80;
        }

        // Query is just appended to URL
        if ($parts['query']) {
            $this->path .= '?'.$parts['query'];
        }

        // Fragment is not used in the request, but is saved to append to the final location, 
        // if it contains no fragment by itself.
        if ($parts['fragment']) {
            $this->fragment = '#'.$parts['fragment'];
        }
        else {
            $this->fragment = '';
        }

        $this->protocol = $parts['scheme'];

        try {
            // We will not handle urls using usernames/passwords
            if ($parts['password'] || $parts['username']) {
                throw new Exception('Username and password not supported in url');
            }

            // We are using curl to resolve our links
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->protocol . '://' . $this->host . $this->path);
            curl_setopt($ch, CURLOPT_USERAGENT, 'TYPO3CMS; crawler; linkservice; https://github.com/dschledermann/typo3-linkservice;');
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->http_timeout);
            curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($info['redirect_url']) {
                $this->setLocation($info['redirect_url']);
            }

            if ($info['http_code']) {
                $this->response_code = $info['http_code'];
            }

            // If we came here ... no errors :-)
            $this->is_valid = true;
        }
        catch (Exception $e) {
            echo "Linkservice error: ".$e->getMessage()."\n";
            $this->is_valid = false;
        }

        return $this->is_valid;
    }

    public function isPermanentRedirect() {
        return $this->is_valid && $this->response_code == 301 && $this->location;
    }

    public function getLocation() {
        $parts = parse_url($this->location);

        // If the new url contain a fragment, it will take precedence.
        if ($parts['fragment']) {
            return htmlentities($this->location);
        }
        // If no fragment if return, we trust in the old one.
        else {
            return htmlentities($this->location) . $this->fragment;
        }
    }

    public function getResponseCode() {
        return $this->response_code;
    }

    protected function setLocation($path) {
        $path = trim($path);

        // An absolute location
        if (preg_match('|^https?://|', $path)) {
            $this->location = $path;
        }
        // A relative location
        else if (preg_match('|^/|', $path)) {
            $this->location = 'http://'. $this->host . $path;
        }
        // An invalid location
        else {
            throw new Exception('Invalid location returned "'.$$path.'"');
        }
    }
}

