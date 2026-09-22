<?php

class Secure {
  // No default/bootstrap key. Keys are set per-session via setKeys(), which
  // includes/bootstrap.php calls once it has looked up the caller's session
  // and found the key minted for it at login (see User::validateOTP()).
  // Pre-session traffic (login, validateOTP itself, forgot_password) has no
  // shared secret to encrypt with yet and is sent as plain JSON over TLS.
  private $sKey = "";
  private $iv = "";

  public function setKeys($sKey, $iv) {
    $this->sKey = $sKey;
    $this->iv = $iv;
  }

  public function encrypt($plaintext)
  {
    $output = openssl_encrypt($plaintext,'aes-128-cbc',$this->sKey,OPENSSL_RAW_DATA,$this->iv);
    return base64_encode($output);
  }

  public function decrypt($data)
  {
    $output = openssl_decrypt(base64_decode($data),'aes-128-cbc',$this->sKey,OPENSSL_RAW_DATA,$this->iv);
    return $output;
  }

  function pkcs5_pad ($text, $blocksize)
  {
      $pad = $blocksize - (strlen($text) % $blocksize);
      return $text . str_repeat(chr($pad), $pad);
  }
}

?>
