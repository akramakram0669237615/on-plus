<?php
namespace App;
final class Auth {
  public static function start(): void {
    if(session_status()===PHP_SESSION_NONE){
      session_name('onplus_admin');
      session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')]);
      session_start();
    }
  }
  public static function requireAdmin(): void { self::start(); if(empty($_SESSION['admin_id'])) { header('Location: /admin/login'); exit; } }
  public static function csrf(): string { self::start(); return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
  public static function checkCsrf(string $v): bool { self::start(); return hash_equals($_SESSION['csrf']??'', $v); }
}
