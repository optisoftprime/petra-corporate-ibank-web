<?php

/**
 * TOTP (Time-based One-Time Password) Manager
 * Pure PHP 7 implementation for 2FA authentication
 */
class TOTPManager
{
    private $secretLength;
    private $timeStep;
    private $digits;
    private $algorithm;
    private $issuer;

    /**
     * Constructor
     * 
     * @param int $secretLength Length of the secret key (default: 32)
     * @param int $timeStep Time step in seconds (default: 30)
     * @param int $digits Number of digits in TOTP code (default: 6)
     * @param string $algorithm Hash algorithm (default: 'sha1')
     * @param string $issuer Issuer name for QR code
     */
    public function __construct(
        int $secretLength = 32,
        int $timeStep = 30,
        int $digits = 6,
        string $algorithm = 'sha1',
        string $issuer = 'MyApp'
    ) {
        $this->secretLength = $secretLength;
        $this->timeStep = $timeStep;
        $this->digits = $digits;
        $this->algorithm = $algorithm;
        $this->issuer = $issuer;
    }

    /**
     * Generate a random secret key for enrollment
     * 
     * @return string Base32 encoded secret
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes($this->secretLength);
        return $this->base32Encode($bytes);
    }

    /**
     * Generate TOTP code for a given secret and timestamp
     * 
     * @param string $secret Base32 encoded secret
     * @param int|null $timestamp Unix timestamp (null for current time)
     * @return string TOTP code
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $timeCounter = intval($timestamp / $this->timeStep);
        $secretBinary = $this->base32Decode($secret);
        
        // Pack time counter as 64-bit big-endian
        $timeBytes = pack('N*', 0) . pack('N*', $timeCounter);
        
        // Generate HMAC
        $hash = hash_hmac($this->algorithm, $timeBytes, $secretBinary, true);
        
        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, $this->digits);
        
        return str_pad($code, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Validate a TOTP code against a secret
     * 
     * @param string $code User provided TOTP code
     * @param string $secret Base32 encoded secret
     * @param int $window Time window tolerance (default: 1, allows ±30 seconds)
     * @return bool True if valid, false otherwise
     */
    public function validateCode(string $code, string $secret, int $window = 1): bool
    {
        $currentTime = time();
        
        // Check current time and time windows
        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $currentTime + ($i * $this->timeStep);
            $expectedCode = $this->generateCode($secret, $testTime);
            
            if (hash_equals($code, $expectedCode)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the otpauth:// URL for manual entry or QR generation
     * 
     * @param string $secret Base32 encoded secret
     * @param string $accountName User account name/email
     * @param string|null $issuer Override default issuer
     * @return string OTP Auth URL
     */
    public function getOTPAuthURL(string $secret, string $accountName, ?string $issuer = null): string
    {
        $issuer = $issuer ?? $this->issuer;
        $label = rawurlencode($issuer . ':' . $accountName);
        
        $parameters = [
            'secret' => $secret,
            'issuer' => rawurlencode($issuer),
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->timeStep
        ];
        
        $queryString = http_build_query($parameters);
        return "otpauth://totp/{$label}?{$queryString}";
    }

    /**
     * Generate QR code URL using QR Server API
     * 
     * @param string $secret Base32 encoded secret
     * @param string $accountName User account name/email
     * @param string|null $issuer Override default issuer
     * @param int $size QR code size in pixels (default: 200)
     * @return string QR code URL
     */
    public function getQRCodeURL(string $secret, string $accountName, ?string $issuer = null, int $size = 200): string
    {
        $otpauthURL = $this->getOTPAuthURL($secret, $accountName, $issuer);
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($otpauthURL);
    }

    /**
     * Generate QR code URL using QuickChart API (alternative service)
     * 
     * @param string $secret Base32 encoded secret
     * @param string $accountName User account name/email
     * @param string|null $issuer Override default issuer
     * @param int $size QR code size in pixels (default: 200)
     * @return string QR code URL
     */
    public function getQRCodeURLAlternative(string $secret, string $accountName, ?string $issuer = null, int $size = 200): string
    {
        $otpauthURL = $this->getOTPAuthURL($secret, $accountName, $issuer);
        return "https://quickchart.io/qr?text=" . urlencode($otpauthURL) . "&size={$size}";
    }

    /**
     * Get multiple QR code service options
     * 
     * @param string $secret Base32 encoded secret
     * @param string $accountName User account name/email
     * @param string|null $issuer Override default issuer
     * @param int $size QR code size (default: 200)
     * @return array QR code service URLs
     */
    public function getQRCodeOptions(string $secret, string $accountName, ?string $issuer = null, int $size = 200): array
    {
        return [
            'primary' => $this->getQRCodeURL($secret, $accountName, $issuer, $size),
            'alternative' => $this->getQRCodeURLAlternative($secret, $accountName, $issuer, $size),
            'manual_url' => $this->getOTPAuthURL($secret, $accountName, $issuer)
        ];
    }

    /**
     * Complete enrollment process
     * 
     * @param string $accountName User account name/email
     * @param string|null $issuer Override default issuer
     * @return array Enrollment data with secret and QR code options
     */
    public function enroll(string $accountName, ?string $issuer = null): array
    {
        $secret = $this->generateSecret();
        $qrOptions = $this->getQRCodeOptions($secret, $accountName, $issuer);
        
        return [
            'secret' => $secret,
            'account_name' => $accountName,
            'issuer' => $issuer ?? $this->issuer,
            'qr_code_url' => $qrOptions['primary'],
            'qr_code_alternative' => $qrOptions['alternative'],
            'manual_entry_url' => $qrOptions['manual_url'],
            'manual_entry_key' => $secret,
            'backup_codes' => $this->generateBackupCodes()
        ];
    }

    /**
     * Generate backup codes for account recovery
     * 
     * @param int $count Number of backup codes (default: 10)
     * @return array Array of backup codes
     */
    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-character hex codes
        }
        return $codes;
    }

    /**
     * Validate a backup code
     * 
     * @param string $code User provided backup code
     * @param array $validCodes Array of valid backup codes
     * @return bool True if valid backup code
     */
    public function validateBackupCode(string $code, array $validCodes): bool
    {
        $code = strtoupper(trim($code));
        return in_array($code, $validCodes, true);
    }

    /**
     * Get current time step for debugging
     * 
     * @return int Current time step
     */
    public function getCurrentTimeStep(): int
    {
        return intval(time() / $this->timeStep);
    }

    /**
     * Get remaining seconds in current time step
     * 
     * @return int Remaining seconds
     */
    public function getRemainingSeconds(): int
    {
        return $this->timeStep - (time() % $this->timeStep);
    }

    /**
     * Generate HTML for displaying QR code with fallbacks
     * 
     * @param string $secret Base32 encoded secret
     * @param string $accountName User account name/email
     * @param string|null $issuer Override default issuer
     * @return string HTML string with QR code and fallbacks
     */
    public function getEnrollmentHTML(string $secret, string $accountName, ?string $issuer = null): string
    {
        $qrOptions = $this->getQRCodeOptions($secret, $accountName, $issuer);
        $issuer = $issuer ?? $this->issuer;
        
        $html = '<div class="totp-enrollment">';
        $html .= '<h3>Set up Two-Factor Authentication</h3>';
        $html .= '<p>Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):</p>';
        
        // Primary QR code with fallback
        $html .= '<div class="qr-code-container">';
        $html .= '<img src="' . htmlspecialchars($qrOptions['primary']) . '" ';
        $html .= 'alt="TOTP QR Code" ';
        $html .= 'onerror="this.src=\'' . htmlspecialchars($qrOptions['alternative']) . '\'" ';
        $html .= 'style="border: 1px solid #ccc; padding: 10px; background: white;">';
        $html .= '</div>';
        
        // Manual entry option
        $html .= '<div class="manual-entry" style="margin-top: 20px;">';
        $html .= '<p><strong>Can\'t scan the QR code?</strong></p>';
        $html .= '<p>Enter this key manually in your authenticator app:</p>';
        $html .= '<div style="background: #f5f5f5; padding: 10px; font-family: monospace; word-break: break-all;">';
        $html .= '<strong>' . htmlspecialchars($secret) . '</strong>';
        $html .= '</div>';
        $html .= '<p><small>Account: ' . htmlspecialchars($accountName) . '<br>';
        $html .= 'Issuer: ' . htmlspecialchars($issuer) . '</small></p>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Base32 encode
     * 
     * @param string $data Binary data to encode
     * @return string Base32 encoded string
     */
    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $v <<= 8;
            $v += ord($data[$i]);
            $vbits += 8;
            
            while ($vbits >= 5) {
                $vbits -= 5;
                $output .= $alphabet[$v >> $vbits];
                $v &= ((1 << $vbits) - 1);
            }
        }
        
        if ($vbits > 0) {
            $v <<= (5 - $vbits);
            $output .= $alphabet[$v];
        }
        
        return $output;
    }

    /**
     * Base32 decode
     * 
     * @param string $data Base32 encoded string
     * @return string Binary data
     */
    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $alphabetFlipped = array_flip(str_split($alphabet));
        
        $data = strtoupper($data);
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            if (!isset($alphabetFlipped[$data[$i]])) {
                continue;
            }
            
            $v <<= 5;
            $v += $alphabetFlipped[$data[$i]];
            $vbits += 5;
            
            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr($v >> $vbits);
                $v &= ((1 << $vbits) - 1);
            }
        }
        
        return $output;
    }
}

?>