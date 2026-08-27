<?php
namespace App;
use PDO;

/**
 * Imports public/authorized feed data into editable local records.
 * Direct media URLs may be imported, but secrets/DRM credentials are never copied.
 */
final class Importer {
  private const CATEGORIES_URL='https://bab-elmoshahd.online/api/index.php?path=categories';
  private const MATCHES_URL='https://bab-elmoshahd.online/api/index.php?path=matches&day=0';

  private static function fetch(string $url): array {
    $u=parse_url($url);
    if(($u['scheme']??'')!=='https') throw new \RuntimeException('Only HTTPS sources are allowed');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'OnPlusImporter/2.0']);
    $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if($body===false || $code<200 || $code>=300) throw new \RuntimeException('Import source failed: '.$err.' HTTP '.$code);
    $json=json_decode($body,true,512,JSON_THROW_ON_ERROR);
    if(isset($json['data']) && is_array($json['data'])) return $json['data'];
    return is_array($json)?$json:[];
  }

  private static function text($v): ?string { return is_scalar($v)?trim((string)$v):null; }
  private static function first(array $a,array $keys): mixed { foreach($keys as $k){ if(array_key_exists($k,$a) && $a[$k]!=='' && $a[$k]!==null) return $a[$k]; } return null; }
  private static function isUrl($v): ?string { $v=self::text($v); return $v && filter_var($v,FILTER_VALIDATE_URL)?$v:null; }

  // Preserve unknown useful fields while explicitly stripping credentials and DRM material.
  private static function sanitize(array $value): array {
    $secret='/^(key|keys|key_id|kid|license|license_url|drm|clearkey|token|authorization|cookie|headers|bearer|secret|password|username|credential|widevine|playready)$/i';
    $out=[];
    foreach($value as $k=>$v){
      if(is_string($k) && preg_match($secret,$k)) continue;
      if(is_array($v)) $out[$k]=self::sanitize($v);
      elseif(is_scalar($v) || $v===null) $out[$k]=$v;
    }
    return $out;
  }

  private static function streamUrl(array $a): ?string {
    $v=self::first($a,['stream_url','streamUrl','url','play_url','playUrl','source_url','sourceUrl','hls','hls_url','m3u8']);
    return self::isUrl($v);
  }

  public static function importCategories(PDO $pdo): array {
    $items=self::fetch(self::CATEGORIES_URL); $countC=0; $countCh=0;
    $pdo->beginTransaction();
    try {
      foreach($items as $item){
        if(!is_array($item)) continue;
        $external=self::text($item['id']??null); if(!$external) continue;
        $stmt=$pdo->prepare('INSERT INTO categories(external_id,name,image_url,description,position,enabled,source_url,raw_data,imported_at)
          VALUES(?,?,?,?,?,true,?,?,NOW())
          ON CONFLICT(external_id) DO UPDATE SET name=EXCLUDED.name,image_url=EXCLUDED.image_url,description=EXCLUDED.description,position=EXCLUDED.position,source_url=EXCLUDED.source_url,raw_data=EXCLUDED.raw_data,imported_at=NOW()
          RETURNING id');
        $stmt->execute([$external,(string)self::first($item,['name','title'])?:'غير مسمى',self::isUrl(self::first($item,['image','image_url','icon','logo'])),self::text(self::first($item,['description','desc'])),(int)(self::first($item,['order','position','sort'])??0),self::CATEGORIES_URL,json_encode(self::sanitize($item),JSON_UNESCAPED_UNICODE)]);
        $categoryId=(int)$stmt->fetchColumn(); $countC++;
        $channels=self::first($item,['channels','items','data']);
        if(!is_array($channels)) continue;
        foreach($channels as $ch){
          if(!is_array($ch)) continue;
          $cid=self::text($ch['id']??null); if(!$cid) $cid='cat-'.$external.'-'.substr(hash('sha256',json_encode($ch)),0,24);
          $q=$pdo->prepare('INSERT INTO channels(external_id,category_id,name,logo_url,stream_url,stream_type,description,enabled,position,is_live,source_url,raw_data,imported_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?, ?,NOW())
            ON CONFLICT(external_id) DO UPDATE SET category_id=EXCLUDED.category_id,name=EXCLUDED.name,logo_url=EXCLUDED.logo_url,stream_url=EXCLUDED.stream_url,stream_type=EXCLUDED.stream_type,description=EXCLUDED.description,position=EXCLUDED.position,is_live=EXCLUDED.is_live,source_url=EXCLUDED.source_url,raw_data=EXCLUDED.raw_data,imported_at=NOW(),updated_at=NOW()');
          $q->execute([$cid,$categoryId,(string)(self::first($ch,['name','title'])??'غير مسمى'),self::isUrl(self::first($ch,['logo','logo_url','image','icon'])),self::streamUrl($ch),self::text(self::first($ch,['streamType','stream_type','type','format'])),self::text(self::first($ch,['description','desc'])),true,(int)(self::first($ch,['order','position','sort'])??0),!empty(self::first($ch,['isLive','is_live','live'])),self::CATEGORIES_URL,json_encode(self::sanitize($ch),JSON_UNESCAPED_UNICODE)]);
          $countCh++;
        }
      }
      $pdo->commit();
    } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }
    return ['categories'=>$countC,'channels'=>$countCh];
  }

  public static function importMatches(PDO $pdo): array {
    $items=self::fetch(self::MATCHES_URL); $count=0;
    $pdo->beginTransaction();
    try {
      foreach($items as $m){
        if(!is_array($m)) continue;
        $id=self::text($m['id']??null); if(!$id) $id='match-'.substr(hash('sha256',json_encode($m)),0,24);
        $date=(string)(self::first($m,['date','match_date'])??''); $time=(string)(self::first($m,['time','match_time'])??'');
        $dt=null; try { $dt=(new \DateTime(trim($date.' '.$time),new \DateTimeZone('UTC')))->format('c'); } catch(\Throwable){}
        $status=!empty(self::first($m,['isLive','is_live','live']))?'live':((self::text($m['status']??null)==='finished')?'finished':'upcoming');
        $q=$pdo->prepare('INSERT INTO matches(external_id,home_team,away_team,home_logo,away_logo,competition,league_logo,match_date,status,score_home,score_away,stream_url,stream_type,source_url,raw_data,imported_at)
          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
          ON CONFLICT(external_id) DO UPDATE SET home_team=EXCLUDED.home_team,away_team=EXCLUDED.away_team,home_logo=EXCLUDED.home_logo,away_logo=EXCLUDED.away_logo,competition=EXCLUDED.competition,league_logo=EXCLUDED.league_logo,match_date=EXCLUDED.match_date,status=EXCLUDED.status,score_home=EXCLUDED.score_home,score_away=EXCLUDED.score_away,stream_url=EXCLUDED.stream_url,stream_type=EXCLUDED.stream_type,source_url=EXCLUDED.source_url,raw_data=EXCLUDED.raw_data,imported_at=NOW(),updated_at=NOW()');
        $q->execute([$id,(string)(self::first($m,['homeTeam','home_team'])??''),(string)(self::first($m,['awayTeam','away_team'])??''),self::isUrl(self::first($m,['homeLogo','home_logo'])),self::isUrl(self::first($m,['awayLogo','away_logo'])),self::text(self::first($m,['league','competition'])),self::isUrl(self::first($m,['leagueLogo','league_logo'])),$dt,$status,self::first($m,['homeScore','score_home']),self::first($m,['awayScore','score_away']),self::streamUrl($m),self::text(self::first($m,['streamType','stream_type','type'])),self::MATCHES_URL,json_encode(self::sanitize($m),JSON_UNESCAPED_UNICODE)]);
        $count++;
      }
      $pdo->commit();
    } catch(\Throwable $e){$pdo->rollBack();throw $e;}
    return ['matches'=>$count];
  }
}
