<?php
namespace App;
use PDO;
final class Database {
  private static ?PDO $pdo=null;
  public static function pdo(): PDO {
    if(self::$pdo) return self::$pdo;
    $url=getenv('DATABASE_URL');
    if(!$url) throw new \RuntimeException('DATABASE_URL is missing');
    $p=parse_url($url);
    if(!$p || empty($p['host']) || empty($p['path'])) throw new \RuntimeException('Invalid DATABASE_URL');
    parse_str($p['query']??'', $q);
    $dsn='pgsql:host='.$p['host'].';port='.($p['port']??5432).';dbname='.ltrim($p['path'],'/');
    if(isset($q['sslmode'])) $dsn.=';sslmode='.$q['sslmode'];
    self::$pdo=new PDO($dsn, rawurldecode($p['user']??''), rawurldecode($p['pass']??''), [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return self::$pdo;
  }
}
