<?php

/*
 * Copyright 2017 Joseph Mangmang.
 * Created 10-Mar-2017
 *
 */

class Security {

    private static $magicKey = 'foo';

    public static function encrypt($val) {
        return bin2hex(static::aes_encrypt($val));
    }

    public static function decrypt($val) {
        try {
            return static::aes_decrypt(hex2bin($val));
        } catch (Exception $exc) {
            return -1;
        }
    }

    private static function aes_encrypt($val) {
        $key = static::mysql_aes_key(static::$magicKey);
        $pad_value = 16 - (strlen($val) % 16);
        $val = str_pad($val, (16 * (floor(strlen($val) / 16) + 1)), chr($pad_value));
        return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $val, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB), MCRYPT_DEV_URANDOM));
    }

    private static function aes_decrypt($val) {
        $key = static::mysql_aes_key(static::$magicKey);
        $val = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $val, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB), MCRYPT_DEV_URANDOM));
        return $val; // rtrim($val, "..16");
    }

    private static function mysql_aes_key($key) {
        $new_key = str_repeat(chr(0), 16);
        for ($i = 0, $len = strlen($key); $i < $len; $i++) {
            $new_key[$i % 16] = $new_key[$i % 16] ^ $key[$i];
        }
        return $new_key;
    }

}
