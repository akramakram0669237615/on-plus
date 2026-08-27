<?php
namespace App;
final class Response {
  public static function json(mixed $d,int $s=200): never { http_response_code($s); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
}
