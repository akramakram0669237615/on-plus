<?php
declare(strict_types=1);
require __DIR__.'/../src/Database.php';
require __DIR__.'/../src/Auth.php';
require __DIR__.'/../src/Response.php';
require __DIR__.'/../src/Importer.php';

use App\{Database,Auth,Response,Importer};
$pdo=Database::pdo();
$method=$_SERVER['REQUEST_METHOD'];
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/')?:'/';

function out($x,$s=200){Response::json($x,$s);}
function input(): array { $x=json_decode(file_get_contents('php://input'),true); return is_array($x)?$x:$_POST; }
function rows($q,$args=[]){$s=Database::pdo()->prepare($q);$s->execute($args);return $s->fetchAll();}
function one($q,$args=[]){$s=Database::pdo()->prepare($q);$s->execute($args);return $s->fetch();}
function publicApi(){
 global $pdo,$path;
 if($path==='/api/health') out(['success'=>true,'status'=>'ok']);
 if($path==='/api/app/config') out(['success'=>true,'data'=>one('SELECT * FROM app_settings WHERE id=1')]);
 if($path==='/api/sidebar') out(['success'=>true,'data'=>rows('SELECT * FROM sidebar_items WHERE enabled=true ORDER BY position,id')]);
 if($path==='/api/categories') out(['success'=>true,'data'=>rows('SELECT * FROM categories WHERE enabled=true ORDER BY position,id')]);
 if($path==='/api/channels') out(['success'=>true,'data'=>rows('SELECT c.*,cat.name category_name FROM channels c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.enabled=true ORDER BY c.position,c.id')]);
 if(preg_match('#^/api/categories/(\\d+)/channels$#',$path,$m)) out(['success'=>true,'data'=>rows('SELECT * FROM channels WHERE category_id=? AND enabled=true ORDER BY position,id',[(int)$m[1]])]);
 if(preg_match('#^/api/channels/(\\d+)$#',$path,$m)){ $x=one('SELECT * FROM channels WHERE id=? AND enabled=true',[(int)$m[1]]); if(!$x)out(['success'=>false,'message'=>'Not found'],404);out(['success'=>true,'data'=>$x]);}
 if($path==='/api/matches'){ $st=$_GET['status']??null; $q='SELECT * FROM matches'.($st?' WHERE status=?':'').' ORDER BY match_date ASC NULLS LAST,id DESC'; out(['success'=>true,'data'=>rows($q,$st?[$st]:[])]);}
 if($path==='/api/notifications/startup') out(['success'=>true,'data'=>one('SELECT * FROM notifications WHERE enabled=true AND show_on_start=true AND (start_date IS NULL OR start_date<=NOW()) AND (end_date IS NULL OR end_date>=NOW()) ORDER BY id DESC LIMIT 1')?:null]);
 if($path==='/api/update/check'){ $u=one('SELECT * FROM app_updates WHERE enabled=true ORDER BY version_code DESC LIMIT 1');$cur=(int)($_GET['version_code']??0);out(['success'=>true,'data'=>['update_available'=>$u&&(int)$u['version_code']>$cur,'update_required'=>$u&&$u['update_type']==='force','latest_version'=>$u['version']??null,'message'=>$u['message']??null,'download_url'=>$u['download_url']??null]]);}
 if($path==='/api/home') out(['success'=>true,'data'=>rows('SELECT * FROM home_sections WHERE enabled=true ORDER BY position,id')]);
}

try {
 publicApi();

 // ============================================
 // شاشة تسجيل الدخول
 // ============================================
 if($path==='/admin/login'&&$method==='GET'){Auth::start();$c=Auth::csrf(); $html = <<<'HTML'
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OnPlus | تسجيل الدخول</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=El+Messiri:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#07080c;--card:#101319;--card2:#161a22;--line:#232838;--text:#eef0f4;--dim:#8890a4;
--accent1:#7c5cff;--accent2:#ff4d8d;--accent3:#20d5c4;--ok:#2ecc71;--err:#ff4757}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:'IBM Plex Sans Arabic',sans-serif;background:
radial-gradient(1200px 700px at 15% -10%, #241a4a44, transparent 60%),
radial-gradient(1000px 600px at 110% 10%, #ff4d8d22, transparent 55%),
var(--bg);color:var(--text);display:grid;place-items:center;padding:20px;overflow:hidden}
.blob{position:fixed;width:520px;height:520px;border-radius:50%;filter:blur(120px);opacity:.35;z-index:0}
.blob.a{background:var(--accent1);top:-180px;right:-160px}
.blob.b{background:var(--accent2);bottom:-200px;left:-160px}
.box{position:relative;z-index:1;width:min(92vw,400px);background:linear-gradient(180deg,var(--card),var(--card2));
border:1px solid var(--line);border-radius:24px;padding:38px 32px;box-shadow:0 30px 90px #000a,inset 0 1px 0 #ffffff0d;
backdrop-filter:blur(6px)}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:6px}
.logo{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,var(--accent1),var(--accent2));
display:grid;place-items:center;font-weight:700;font-size:20px;box-shadow:0 8px 24px #7c5cff55}
.brand h1{font-family:'El Messiri',sans-serif;font-size:22px;margin:0}
.sub{color:var(--dim);font-size:13px;margin:2px 0 26px}
label{display:block;font-size:12px;color:var(--dim);margin:0 2px 6px}
.field{position:relative;margin-bottom:16px}
input{width:100%;padding:13px 14px;background:#0b0e14;border:1px solid var(--line);border-radius:12px;
color:#fff;font-family:inherit;font-size:14px;transition:.2s border,.2s box-shadow}
input:focus{outline:none;border-color:var(--accent1);box-shadow:0 0 0 4px #7c5cff22}
button{width:100%;padding:14px;border:0;border-radius:12px;font-family:inherit;font-weight:700;font-size:15px;
color:#fff;cursor:pointer;background:linear-gradient(135deg,var(--accent1),var(--accent2));
box-shadow:0 10px 30px #7c5cff40;transition:.2s transform}
button:hover{transform:translateY(-2px)}
.err{color:var(--err);font-size:13px;min-height:18px;margin-top:-6px;margin-bottom:10px}
.foot{text-align:center;color:var(--dim);font-size:11px;margin-top:22px}
</style></head><body>
<div class="blob a"></div><div class="blob b"></div>
<div class="box">
  <div class="brand"><div class="logo">O+</div><h1>OnPlus</h1></div>
  <p class="sub">لوحة التحكم — سجّل الدخول للمتابعة</p>
  <form method="post">
    <div class="field"><label>اسم المستخدم</label><input name="username" required autocomplete="username"></div>
    <div class="field"><label>كلمة المرور</label><input type="password" name="password" required autocomplete="current-password"></div>
    <input type="hidden" name="csrf" value="__CSRF__">
    <button>دخول</button>
  </form>
  <div class="foot">OnPlus Admin Panel</div>
</div>
</body></html>
HTML;
echo str_replace('__CSRF__', htmlspecialchars($c), $html);
exit;}

 if($path==='/admin/login'&&$method==='POST'){Auth::start();if(!Auth::checkCsrf($_POST['csrf']??''))out(['success'=>false],419);$u=$_POST['username']??'';$a=one('SELECT * FROM admins WHERE username=? OR email=? LIMIT 1',[$u,$u]);if(!$a||!password_verify($_POST['password']??'',$a['password_hash'])){http_response_code(401);echo'بيانات غير صحيحة';exit;}session_regenerate_id(true);$_SESSION['admin_id']=$a['id'];header('Location: /admin');exit;}
 if($path==='/admin/logout'){Auth::start();session_destroy();header('Location: /admin/login');exit;}

 if(str_starts_with($path,'/admin/api/')){
   Auth::requireAdmin();
   $res=substr($path,11);
   $allowed=['sidebar_items','categories','channels','matches','notifications','app_updates','home_sections','app_settings'];
   if(!in_array($res,$allowed,true))out(['success'=>false,'message'=>'Unknown resource'],404);

   if($res==='import/categories' || $res==='import/matches'){}
   if($method==='GET'){ if($res==='app_settings')out(['success'=>true,'data'=>one('SELECT * FROM app_settings WHERE id=1')]); out(['success'=>true,'data'=>rows("SELECT * FROM $res ORDER BY id DESC")]);}
   $d=input();
   $allowedCols=[
    'sidebar_items'=>['title','icon_url','icon_type','target_type','target_value','position','enabled'],
    'categories'=>['name','image_url','description','position','enabled'],
    'channels'=>['category_id','name','logo_url','stream_url','stream_type','description','enabled','position','is_live'],
    'matches'=>['home_team','away_team','home_logo','away_logo','competition','league_logo','match_date','status','score_home','score_away','stream_url','stream_type'],
    'notifications'=>['title','message','image_url','type','action_type','action_value','enabled','show_on_start','start_date','end_date'],
    'app_updates'=>['version','version_code','update_type','title','message','download_url','enabled'],
    'home_sections'=>['title','type','image_url','position','enabled','config'],
    'app_settings'=>['app_name','app_logo','primary_color','secondary_color','background_color','version','latest_version','update_required','update_message','update_url']
   ];
   $cols=array_values(array_intersect(array_keys($d),$allowedCols[$res]));
   if(!$cols)out(['success'=>false,'message'=>'No valid fields'],400);
   if($res==='app_settings'){
     $sets=implode(',',array_map(fn($c)=>"$c=?", $cols));$pdo->prepare("UPDATE app_settings SET $sets,updated_at=NOW() WHERE id=1")->execute(array_map(fn($c)=>$d[$c],$cols));out(['success'=>true,'data'=>one('SELECT * FROM app_settings WHERE id=1')]);
   }
   if($method==='POST'){ $sql="INSERT INTO $res(".implode(',',$cols).') VALUES('.implode(',',array_fill(0,count($cols),'?')).') RETURNING *';$s=$pdo->prepare($sql);$s->execute(array_map(fn($c)=>$d[$c],$cols));out(['success'=>true,'data'=>$s->fetch()],201);}
   $id=(int)($_GET['id']??0); if(!$id)out(['success'=>false,'message'=>'id required'],400);
   if($method==='PUT'){$sets=implode(',',array_map(fn($c)=>"$c=?", $cols));$s=$pdo->prepare("UPDATE $res SET $sets WHERE id=? RETURNING *");$s->execute([...array_map(fn($c)=>$d[$c],$cols),$id]);out(['success'=>true,'data'=>$s->fetch()]);}
   if($method==='DELETE'){$pdo->prepare("DELETE FROM $res WHERE id=?")->execute([$id]);out(['success'=>true]);}
   out(['success'=>false,'message'=>'Method not allowed'],405);
 }

 if($path==='/admin/import/categories'&&$method==='POST'){Auth::requireAdmin();out(['success'=>true,'data'=>Importer::importCategories($pdo)]);}
 if($path==='/admin/import/matches'&&$method==='POST'){Auth::requireAdmin();out(['success'=>true,'data'=>Importer::importMatches($pdo)]);}

 // ============================================
 // لوحة التحكم الرئيسية
 // ============================================
 if($path==='/admin'){Auth::requireAdmin();$csrf=Auth::csrf(); $html = <<<'HTML'
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OnPlus | لوحة التحكم</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=El+Messiri:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{
 --bg:#07080c;--panel:#0e1119;--panel2:#141826;--line:#212636;--line2:#2a3143;
 --text:#eef0f5;--dim:#8891a6;--dim2:#5c6478;
 --accent1:#7c5cff;--accent2:#ff4d8d;--accent3:#20d5c4;
 --ok:#22c55e;--ok-bg:#22c55e1f;--off:#5c6478;--off-bg:#5c64781f;--err:#ff4757;
 --radius:16px;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
 font-family:'IBM Plex Sans Arabic',sans-serif;background:
 radial-gradient(900px 500px at 10% -10%,#241a4a3b,transparent 60%),
 radial-gradient(800px 500px at 100% 0%,#ff4d8d17,transparent 55%),
 var(--bg);color:var(--text);min-height:100vh}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:#2a3143;border-radius:10px}
::-webkit-scrollbar-track{background:transparent}

.topbar{height:66px;display:flex;align-items:center;justify-content:space-between;
 padding:0 24px;position:sticky;top:0;z-index:30;background:#0b0e16cc;backdrop-filter:blur(10px);
 border-bottom:1px solid var(--line)}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--accent1),var(--accent2));
 display:grid;place-items:center;font-weight:700;font-size:16px;box-shadow:0 6px 18px #7c5cff4d}
.brand h1{font-family:'El Messiri',sans-serif;font-size:19px;margin:0}
.topbar-right{display:flex;align-items:center;gap:14px}
.iconbtn{width:38px;height:38px;border-radius:11px;background:var(--panel2);border:1px solid var(--line);
 display:grid;place-items:center;color:var(--text);cursor:pointer;text-decoration:none;transition:.2s}
.iconbtn:hover{border-color:var(--accent1);color:var(--accent1)}
.hamb{display:none}

.layout{display:grid;grid-template-columns:252px 1fr;align-items:start}
aside{position:sticky;top:66px;height:calc(100vh - 66px);overflow-y:auto;padding:18px 14px;
 border-left:1px solid var(--line);background:#0b0e16aa}
.navgroup-title{font-size:11px;color:var(--dim2);padding:14px 12px 6px;letter-spacing:.5px}
.navbtn{display:flex;align-items:center;gap:12px;width:100%;text-align:right;background:transparent;
 border:0;color:var(--dim);padding:11px 12px;border-radius:12px;cursor:pointer;font-family:inherit;
 font-size:14px;margin-bottom:3px;transition:.15s}
.navbtn svg{flex:0 0 18px;width:18px;height:18px;opacity:.85}
.navbtn:hover{background:var(--panel2);color:var(--text)}
.navbtn.active{background:linear-gradient(90deg,#7c5cff26,transparent);color:#fff;box-shadow:inset 3px 0 0 var(--accent1)}

main{padding:26px 28px 60px;max-width:1300px;width:100%}
.pagehead{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.pagehead h2{font-family:'El Messiri',sans-serif;font-size:24px;margin:0}
.pagehead p{color:var(--dim);font-size:13px;margin:4px 0 0}

.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:6px}
.stat{background:linear-gradient(180deg,var(--panel),var(--panel2));border:1px solid var(--line);
 border-radius:var(--radius);padding:20px;position:relative;overflow:hidden}
.stat::after{content:"";position:absolute;inset:0;background:radial-gradient(120px 80px at 100% 0%,#7c5cff22,transparent)}
.stat .ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;margin-bottom:14px;
 background:linear-gradient(135deg,var(--accent1),var(--accent2));box-shadow:0 8px 20px #7c5cff40}
.stat .ic svg{width:20px;height:20px}
.stat .num{font-size:30px;font-weight:700;font-family:'El Messiri',sans-serif}
.stat .lbl{color:var(--dim);font-size:13px;margin-top:2px}

.actions{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 16px}
.btn{background:linear-gradient(135deg,var(--accent1),var(--accent2));color:#fff;border:0;border-radius:12px;
 padding:11px 18px;cursor:pointer;font-family:inherit;font-weight:600;font-size:13.5px;
 display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 20px #7c5cff33;transition:.2s transform}
.btn:hover{transform:translateY(-2px)}
.btn.ghost{background:var(--panel2);border:1px solid var(--line2);box-shadow:none;color:var(--text)}
.btn.danger{background:linear-gradient(135deg,#ff4757,#ff6b81);box-shadow:0 8px 20px #ff475733}
.btn.sm{padding:7px 12px;font-size:12.5px;border-radius:9px}

.panel{background:linear-gradient(180deg,var(--panel),var(--panel2));border:1px solid var(--line);
 border-radius:var(--radius);overflow:hidden}
.tablewrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:560px}
th{background:#0b0e16;color:var(--dim);font-weight:600;font-size:12px;text-align:right;padding:13px 14px;
 border-bottom:1px solid var(--line);white-space:nowrap;position:sticky;top:0}
td{padding:13px 14px;border-bottom:1px solid var(--line);color:var(--text);white-space:nowrap;
 max-width:220px;overflow:hidden;text-overflow:ellipsis}
tbody tr{transition:.15s}
tbody tr:hover{background:#7c5cff0d}
.badge{padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:600;display:inline-block}
.badge.on{background:var(--ok-bg);color:var(--ok)}
.badge.off{background:var(--off-bg);color:var(--off)}
.rowbtns{display:flex;gap:6px}
.empty{padding:60px 20px;text-align:center;color:var(--dim)}
.empty svg{width:46px;height:46px;opacity:.4;margin-bottom:10px}

dialog{width:min(94vw,640px);background:linear-gradient(180deg,var(--panel),var(--panel2));color:#fff;
 border:1px solid var(--line2);border-radius:20px;padding:0;box-shadow:0 30px 90px #000a}
dialog::backdrop{background:#000a;backdrop-filter:blur(3px)}
.dlg-head{padding:22px 26px 6px;font-family:'El Messiri',sans-serif;font-size:19px}
.dlg-body{padding:6px 26px 4px;max-height:60vh;overflow-y:auto}
.dlg-foot{padding:16px 26px 22px;display:flex;gap:10px;justify-content:flex-start}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;color:var(--dim);margin-bottom:6px}
input,select,textarea{width:100%;padding:11px 13px;background:#0b0e16;border:1px solid var(--line2);
 color:#fff;border-radius:11px;font-family:inherit;font-size:13.5px;transition:.2s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent1);box-shadow:0 0 0 3px #7c5cff22}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.switch{display:flex;align-items:center;gap:10px;padding:8px 0}

.toast-wrap{position:fixed;bottom:20px;left:20px;z-index:99;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--panel2);border:1px solid var(--line2);color:#fff;padding:13px 18px;border-radius:12px;
 font-size:13.5px;box-shadow:0 12px 30px #000a;display:flex;align-items:center;gap:10px;animation:up .25s ease}
.toast.err{border-color:#ff475766}
@keyframes up{from{transform:translateY(10px);opacity:0}to{transform:translateY(0);opacity:1}}

@media(max-width:900px){
 .layout{grid-template-columns:1fr}
 aside{position:fixed;inset:66px 0 0 0;transform:translateX(100%);transition:.25s;z-index:40;
  width:78vw;background:#0b0e16;border-left:1px solid var(--line)}
 aside.open{transform:translateX(0)}
 .hamb{display:grid;place-items:center}
 main{padding:18px}
}
</style></head><body>

<div class="topbar">
  <div class="brand"><div class="logo">O+</div><h1>OnPlus</h1></div>
  <div class="topbar-right">
    <button class="iconbtn hamb" onclick="document.getElementById('side').classList.toggle('open')" title="القائمة">☰</button>
    <a class="iconbtn" href="/admin/logout" title="تسجيل الخروج">⏻</a>
  </div>
</div>

<div class="layout">
  <aside id="side">
    <div class="navgroup-title">عام</div>
    <button class="navbtn active" data-r="dashboard" onclick="showRes('dashboard')">🏠 الرئيسية</button>
    <button class="navbtn" data-r="app_settings" onclick="showRes('app_settings')">⚙️ إعدادات التطبيق</button>
    <div class="navgroup-title">المحتوى</div>
    <button class="navbtn" data-r="sidebar_items" onclick="showRes('sidebar_items')">📑 القائمة الجانبية</button>
    <button class="navbtn" data-r="categories" onclick="showRes('categories')">🗂️ التصنيفات</button>
    <button class="navbtn" data-r="channels" onclick="showRes('channels')">📺 القنوات</button>
    <button class="navbtn" data-r="matches" onclick="showRes('matches')">⚽ المباريات</button>
    <button class="navbtn" data-r="home_sections" onclick="showRes('home_sections')">🧩 الرئيسية الديناميكية</button>
    <div class="navgroup-title">النظام</div>
    <button class="navbtn" data-r="notifications" onclick="showRes('notifications')">🔔 الإشعارات</button>
    <button class="navbtn" data-r="app_updates" onclick="showRes('app_updates')">🚀 التحديثات</button>
    <button class="navbtn" data-r="import" onclick="showImport()">⬇️ استيراد البيانات</button>
  </aside>

  <main>
    <div class="pagehead">
      <div><h2 id="title">الرئيسية</h2><p id="subtitle">نظرة عامة على منصة OnPlus</p></div>
    </div>
    <div id="app"></div>
  </main>
</div>

<dialog id="dlg">
  <div class="dlg-head" id="dlgTitle">إضافة</div>
  <div class="dlg-body"><div id="form" class="grid2"></div></div>
  <div class="dlg-foot">
    <button class="btn" onclick="save()">💾 حفظ</button>
    <button class="btn ghost" onclick="document.getElementById('dlg').close()">إلغاء</button>
  </div>
</dialog>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const csrf="__CSRF__";
let res="dashboard", data=[], editing=null;

const labels={sidebar_items:"القائمة الجانبية",categories:"التصنيفات",channels:"القنوات",matches:"المباريات",
 notifications:"الإشعارات",app_updates:"التحديثات",home_sections:"الرئيسية الديناميكية",app_settings:"إعدادات التطبيق"};

const fields={
 sidebar_items:["title","icon_url","icon_type","target_type","target_value","position","enabled"],
 categories:["name","image_url","description","position","enabled"],
 channels:["category_id","name","logo_url","stream_url","stream_type","description","enabled","position","is_live"],
 matches:["home_team","away_team","home_logo","away_logo","competition","league_logo","match_date","status","score_home","score_away","stream_url","stream_type"],
 notifications:["title","message","image_url","type","action_type","action_value","enabled","show_on_start","start_date","end_date"],
 app_updates:["version","version_code","update_type","title","message","download_url","enabled"],
 home_sections:["title","type","image_url","position","enabled","config"],
 app_settings:["app_name","app_logo","primary_color","secondary_color","background_color","version","latest_version","update_required","update_message","update_url"]
};
const boolFields=["enabled","is_live","show_on_start","update_required"];
const numFields=["position","category_id","version_code","score_home","score_away"];
const previewCols={sidebar_items:["title","target_type","position"],categories:["name","position"],
 channels:["name","stream_type","is_live"],matches:["home_team","away_team","status"],
 notifications:["title","type"],app_updates:["version","update_type"],home_sections:["title","type"]};

function toast(msg,isErr){
 const w=document.getElementById('toastWrap');
 const t=document.createElement('div');
 t.className='toast'+(isErr?' err':'');
 t.innerHTML=(isErr?'⚠️ ':'✅ ')+msg;
 w.appendChild(t);
 setTimeout(()=>t.remove(),3000);
}

async function api(u,opt={}){
 opt.headers={...(opt.headers||{}),"Content-Type":"application/json","X-CSRF-Token":csrf};
 let r=await fetch(u,opt); let j=await r.json();
 if(!r.ok) throw Error(j.message||"حدث خطأ");
 return j;
}

function setActive(r){
 document.querySelectorAll('.navbtn').forEach(b=>b.classList.toggle('active',b.dataset.r===r));
 document.getElementById('side').classList.remove('open');
}

async function showRes(r){
 res=r; setActive(r);
 document.getElementById("title").textContent=labels[r]||"الرئيسية";
 const app=document.getElementById("app");

 if(r==="dashboard"){
  document.getElementById("subtitle").textContent="نظرة عامة على منصة OnPlus";
  app.innerHTML='<div class="cards" id="cards"></div>';
  const keys=["channels","categories","matches","notifications"];
  const iconMap={channels:"📺",categories:"🗂️",matches:"⚽",notifications:"🔔"};
  const nameMap={channels:"القنوات",categories:"التصنيفات",matches:"المباريات",notifications:"الإشعارات"};
  const results=await Promise.all(keys.map(k=>api("/admin/api/"+k).catch(()=>({data:[]}))));
  document.getElementById("cards").innerHTML=results.map((x,i)=>`
   <div class="stat"><div class="ic">${iconMap[keys[i]]}</div>
    <div class="num">${x.data.length}</div><div class="lbl">${nameMap[keys[i]]}</div></div>`).join("");
  return;
 }

 document.getElementById("subtitle").textContent="إدارة "+labels[r];
 app.innerHTML='<div class="panel"><div class="empty">جارِ التحميل...</div></div>';
 const j=await api("/admin/api/"+r);
 data=Array.isArray(j.data)?j.data:(j.data?[j.data]:[]);
 render();
}

function render(){
 const cols=previewCols[res]||fields[res].slice(0,4);
 let rowsHtml=data.map(x=>`
  <tr>
   <td>#${x.id}</td>
   ${cols.map(f=>{
     let v=x[f];
     if(boolFields.includes(f)) return `<td><span class="badge ${v?'on':'off'}">${v?'مفعّل':'متوقف'}</span></td>`;
     return `<td>${String(v??'—').slice(0,60)}</td>`;
   }).join('')}
   <td class="rowbtns">
     <button class="btn ghost sm" onclick="edit(${x.id})">✏️ تعديل</button>
     <button class="btn danger sm" onclick="del(${x.id})">🗑️ حذف</button>
   </td>
  </tr>`).join('');

 document.getElementById("app").innerHTML=`
  <div class="actions">
   <button class="btn" onclick="add()">＋ إضافة عنصر جديد</button>
   <button class="btn ghost" onclick="showRes('${res}')">↻ تحديث</button>
  </div>
  <div class="panel"><div class="tablewrap">
   ${data.length? `<table><thead><tr><th>ID</th>${cols.map(c=>`<th>${c}</th>`).join('')}<th>إدارة</th></tr></thead>
    <tbody>${rowsHtml}</tbody></table>`
    : `<div class="empty">📭<br>لا توجد بيانات بعد — ابدأ بالإضافة</div>`}
  </div></div>`;
}

function add(){editing=null;openForm({})}
function edit(id){editing=data.find(x=>x.id==id);openForm(editing)}

function openForm(x){
 document.getElementById('dlgTitle').textContent=(editing?'تعديل ':'إضافة ')+labels[res];
 document.getElementById("form").innerHTML=fields[res].map(f=>{
  let v=x?.[f]??'';
  if(typeof v==='object'&&v!==null) v=JSON.stringify(v);
  if(boolFields.includes(f)){
   return `<div class="field switch" style="grid-column:span 2">
    <input type="checkbox" data-f="${f}" style="width:18px" ${v?'checked':''}>
    <label style="margin:0">${f}</label></div>`;
  }
  const span = ["description","stream_url","message","download_url","config"].includes(f) ? "grid-column:span 2" : "";
  const type = f==="match_date"?"datetime-local": (["start_date","end_date"].includes(f)?"datetime-local":"text");
  return `<div class="field" style="${span}"><label>${f}</label>
   <input data-f="${f}" type="${type}" value="${String(v).replace(/"/g,'&quot;')}"></div>`;
 }).join('');
 document.getElementById("dlg").showModal();
}

async function save(){
 let o={};
 document.querySelectorAll("[data-f]").forEach(i=>{
  let v = i.type==='checkbox' ? i.checked : i.value;
  if(numFields.includes(i.dataset.f) && v!=="") v=Number(v);
  if(i.dataset.f==="config"){ try{v=JSON.parse(v||"{}")}catch{} }
  o[i.dataset.f]=v;
 });
 try{
  let u="/admin/api/"+res+(editing?"?id="+editing.id:"");
  await api(u,{method:editing?"PUT":"POST",body:JSON.stringify(o)});
  document.getElementById("dlg").close();
  toast(editing?"تم التعديل بنجاح":"تمت الإضافة بنجاح");
  showRes(res);
 }catch(e){ toast(e.message,true); }
}

async function del(id){
 if(!confirm("هل أنت متأكد من حذف هذا العنصر؟")) return;
 try{
  await api("/admin/api/"+res+"?id="+id,{method:"DELETE"});
  toast("تم الحذف");
  showRes(res);
 }catch(e){ toast(e.message,true); }
}

function showImport(){
 setActive('import');
 document.getElementById("title").textContent="استيراد البيانات";
 document.getElementById("subtitle").textContent="جلب بيانات من مصدر خارجي";
 document.getElementById("app").innerHTML=`
  <div class="panel" style="padding:22px">
   <p style="color:var(--dim);line-height:2">
    يتم استيراد البيانات المتاحة مثل الأسماء والصور والوصف وروابط البث المباشرة إن كانت موجودة،
    مع حفظ بيانات إضافية قابلة للمراجعة. بيانات DRM أو كلمات المرور أو المفاتيح أو التوكنات لا تُستورد.
   </p>
   <div class="actions">
    <button class="btn" onclick="imp('categories')">⬇️ استيراد التصنيفات والقنوات</button>
    <button class="btn ghost" onclick="imp('matches')">⬇️ استيراد مباريات اليوم</button>
   </div>
   <pre id="result" style="background:#0b0e16;padding:16px;border-radius:12px;overflow:auto;font-size:12px;color:var(--dim)"></pre>
  </div>`;
}
async function imp(x){
 try{
  let j=await api("/admin/import/"+x,{method:"POST",body:JSON.stringify({})});
  document.getElementById("result").textContent=JSON.stringify(j,null,2);
  toast("اكتمل الاستيراد");
 }catch(e){ toast(e.message,true); }
}

showRes("dashboard");
</script>
</body></html>
HTML;
echo str_replace('__CSRF__', htmlspecialchars($csrf, ENT_QUOTES), $html);
exit;
 }
 out(['success'=>false,'message'=>'Not found'],404);
}catch(Throwable $e){error_log($e->getMessage());out(['success'=>false,'message'=>'Server error'],500);}

