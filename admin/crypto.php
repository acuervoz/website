<?php
/*
 * ECDSA signature helpers.
 *
 * Browsers' WebCrypto SubtleCrypto.sign({name:"ECDSA"}, ...) produces a raw
 * r||s signature (two fixed-length big-endian integers concatenated, per the
 * WebCrypto spec) — but PHP's openssl_verify() expects the DER encoding
 * OpenSSL itself produces (a SEQUENCE of two INTEGERs). This file converts
 * between the two so a browser-signed challenge can be verified here.
 */

function derEncodeUnsignedInt(string $bytes): string {
  $i = 0;
  while ($i < strlen($bytes) - 1 && ord($bytes[$i]) === 0) $i++;
  $bytes = substr($bytes, $i);
  if (ord($bytes[0]) & 0x80) $bytes = "\x00" . $bytes;
  return "\x02" . chr(strlen($bytes)) . $bytes;
}

function rawEcdsaSignatureToDer(string $raw): string {
  $half = intdiv(strlen($raw), 2);
  $r = derEncodeUnsignedInt(substr($raw, 0, $half));
  $s = derEncodeUnsignedInt(substr($raw, $half));
  $body = $r . $s;
  return "\x30" . chr(strlen($body)) . $body;
}

// Only used by the local self-test below (not part of the login path) to
// round-trip a normal openssl-produced DER signature back into raw form.
function derEcdsaSignatureToRaw(string $der, int $componentLen = 32): string {
  $pos = 0;
  if (ord($der[$pos++]) !== 0x30) throw new Exception('not a DER SEQUENCE');
  $pos++; // sequence length byte (short-form only, fine for P-256)
  $readInt = function() use ($der, &$pos, $componentLen) {
    if (ord($der[$pos++]) !== 0x02) throw new Exception('expected INTEGER');
    $len = ord($der[$pos++]);
    $bytes = substr($der, $pos, $len);
    $pos += $len;
    $bytes = ltrim($bytes, "\x00");
    return str_pad($bytes, $componentLen, "\x00", STR_PAD_LEFT);
  };
  $r = $readInt();
  $s = $readInt();
  return $r . $s;
}
