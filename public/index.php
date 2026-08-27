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

 // Login
 if($path==='/admin/login'&&$method==='GET'){Auth::start();$c=Auth::csrf(); echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OnPlus Admin</title><style>body{margin:0;font-family:Arial;background:#0d1017;color:#fff;display:grid;place-items:center;height:100vh}.box{width:min(90vw,380px);background:#171c28;padding:28px;border-radius:18px;box-shadow:0 20px 60px #0008}input,button{width:100%;box-sizing:border-box;padding:13px;margin:7px 0;border-radius:10px;border:1px solid #2d3546;background:#0f1420;color:#fff}button{background:#6d5dfc;border:0;font-weight:bold;cursor:pointer}</style><div class="box"><h1>OnPlus</h1><form method="post"><input name="username" placeholder="اسم المستخدم" required><input type="password" name="password" placeholder="كلمة المرور" required><input type="hidden" name="csrf" value="'.htmlspecialchars($c).'"><button>تسجيل الدخول</button></form></div></html>';exit;}
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

 if($path==='/admin'){Auth::requireAdmin();$csrf=Auth::csrf();echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OnPlus Admin</title><style>
 *{box-sizing:border-box}body{margin:0;font-family:Arial;background:#0e1118;color:#e9ecf3}.top{height:64px;background:#171c28;display:flex;align-items:center;justify-content:space-between;padding:0 22px;position:sticky;top:0}.layout{display:grid;grid-template-columns:240px 1fr;min-height:calc(100vh - 64px)}aside{background:#141924;padding:18px}aside button{width:100%;text-align:right;background:transparent;border:0;color:#d9deeb;padding:12px;border-radius:9px;cursor:pointer}aside button:hover{background:#222a3a}.main{padding:24px;max-width:1400px;width:100%}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:15px}.card,.panel{background:#171c28;border:1px solid #252d3d;border-radius:14px;padding:18px}.panel{margin-top:20px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.btn{background:#6d5dfc;color:#fff;border:0;border-radius:9px;padding:10px 14px;cursor:pointer}.danger{background:#d94b63}.muted{color:#a9b0c0}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:10px;border-bottom:1px solid #293244;text-align:right}input,select,textarea{width:100%;padding:10px;background:#0e131d;border:1px solid #303a4d;color:#fff;border-radius:8px;margin:5px 0}dialog{width:min(94vw,700px);background:#171c28;color:#fff;border:1px solid #35415a;border-radius:14px}dialog::backdrop{background:#0009}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}@media(max-width:700px){.layout{grid-template-columns:1fr}aside{display:flex;overflow:auto;gap:5px}aside button{white-space:nowrap;width:auto}.main{padding:14px}.grid{grid-template-columns:1fr}}</style>
 <div class="top"><b>⚡ OnPlus Admin</b><a href="/admin/logout" style="color:#fff">خروج</a></div><div class="layout"><aside>
 <button onclick="showRes(\'dashboard\')">الرئيسية</button><button onclick="showRes(\'app_settings\')">إعدادات التطبيق</button><button onclick="showRes(\'sidebar_items\')">القائمة الجانبية</button><button onclick="showRes(\'categories\')">التصنيفات</button><button onclick="showRes(\'channels\')">القنوات</button><button onclick="showRes(\'matches\')">المباريات</button><button onclick="showRes(\'notifications\')">الإشعارات</button><button onclick="showRes(\'app_updates\')">التحديثات</button><button onclick="showRes(\'home_sections\')">الرئيسية الديناميكية</button><button onclick="showImport()">الاستيراد</button></aside>
 <main class="main"><h1 id="title">الرئيسية</h1><div id="app"></div></main></div><dialog id="dlg"><form method="dialog"><div id="form"></div><div class="actions"><button class="btn" type="button" onclick="save()">حفظ</button><button class="btn danger">إلغاء</button></div></form></dialog>
 <script>const csrf="'.htmlspecialchars($csrf).'";let res="dashboard",data=[],editing=null;const labels={sidebar_items:"القائمة الجانبية",categories:"التصنيفات",channels:"القنوات",matches:"المباريات",notifications:"الإشعارات",app_updates:"التحديثات",home_sections:"الرئيسية",app_settings:"إعدادات التطبيق"};
 const fields={sidebar_items:["title","icon_url","icon_type","target_type","target_value","position","enabled"],categories:["name","image_url","description","position","enabled"],channels:["category_id","name","logo_url","stream_url","stream_type","description","enabled","position","is_live"],matches:["home_team","away_team","home_logo","away_logo","competition","league_logo","match_date","status","score_home","score_away","stream_url","stream_type"],notifications:["title","message","image_url","type","action_type","action_value","enabled","show_on_start","start_date","end_date"],app_updates:["version","version_code","update_type","title","message","download_url","enabled"],home_sections:["title","type","image_url","position","enabled","config"],app_settings:["app_name","app_logo","primary_color","secondary_color","background_color","version","latest_version","update_required","update_message","update_url"]};
 async function api(u,opt={}){opt.headers={...(opt.headers||{}),"Content-Type":"application/json","X-CSRF-Token":csrf};let r=await fetch(u,opt);let j=await r.json();if(!r.ok)throw Error(j.message||"خطأ");return j}
 async function showRes(r){res=r;document.getElementById("title").textContent=labels[r]||r;if(r==="dashboard"){let a=await Promise.all(["channels","categories","matches","notifications"].map(x=>api("/admin/api/"+x)));document.getElementById("app").innerHTML="<div class=cards>"+a.map((x,i)=>"<div class=card><b>"+["القنوات","التصنيفات","المباريات","الإشعارات"][i]+"</b><h2>"+x.data.length+"</h2></div>").join("")+"</div>";return}let j=await api("/admin/api/"+r);data=Array.isArray(j.data)?j.data:[j.data];render()}
 function render(){let h="<div class=actions><button class=btn onclick=add()>+ إضافة</button><button class=btn onclick=showRes(\\\'"+res+"\\\')>تحديث</button></div><div class=panel><table><thead><tr><th>ID</th>"+fields[res].slice(0,4).map(x=>"<th>"+x+"</th>").join("")+"<th>إدارة</th></tr></thead><tbody>"+data.map(x=>"<tr><td>"+x.id+"</td>"+fields[res].slice(0,4).map(f=>"<td>"+String(x[f]??"").slice(0,80)+"</td>").join("")+"<td><button class=btn onclick=edit("+x.id+")>تعديل</button> <button class=\"btn danger\" onclick=del("+x.id+")>حذف</button></td></tr>").join("")+"</tbody></table></div>";document.getElementById("app").innerHTML=h}
 function add(){editing=null;openForm({})}function edit(id){editing=data.find(x=>x.id==id);openForm(editing)}function openForm(x){document.getElementById("form").innerHTML="<h2>"+(editing?"تعديل":"إضافة")+" "+labels[res]+"</h2><div class=grid>"+fields[res].map(f=>{let v=x?.[f]??"";if(typeof v==="object")v=JSON.stringify(v);return "<label>"+f+"<input data-f="+f+" value=\""+String(v).replace(/"/g,"&quot;")+"\"></label>"}).join("")+"</div>";document.getElementById("dlg").showModal()}
 async function save(){let o={};document.querySelectorAll("[data-f]").forEach(i=>{let v=i.value;if(["enabled","is_live","show_on_start","update_required"].includes(i.dataset.f))v=["true","1","yes","on"].includes(v.toLowerCase());if(["position","category_id","version_code","score_home","score_away"].includes(i.dataset.f)&&v!=="")v=Number(v);if(i.dataset.f==="config"){try{v=JSON.parse(v||"{}")}catch{}}o[i.dataset.f]=v});let u="/admin/api/"+res+(editing?"?id="+editing.id:"");await api(u,{method:editing?"PUT":"POST",body:JSON.stringify(o)});document.getElementById("dlg").close();showRes(res)}
 async function del(id){if(confirm("حذف هذا العنصر؟")){await api("/admin/api/"+res+"?id="+id,{method:"DELETE"});showRes(res)}}
 function showImport(){document.getElementById("title").textContent="استيراد البيانات";document.getElementById("app").innerHTML="<div class=panel><h2>استيراد من المصدر الخارجي</h2><p class=muted>يتم استيراد البيانات المتاحة مثل الأسماء والصور والوصف وروابط البث المباشرة إن كانت موجودة، مع حفظ بيانات إضافية قابلة للمراجعة. بيانات DRM أو كلمات المرور أو المفاتيح أو التوكنات لا تُستورد.</p><div class=actions><button class=btn onclick=imp(\\\'categories\\\')>استيراد التصنيفات والقنوات</button><button class=btn onclick=imp(\\\'matches\\\')>استيراد مباريات اليوم</button></div><pre id=result></pre></div>"}async function imp(x){try{let j=await api("/admin/import/"+x,{method:"POST",body:JSON.stringify({})});document.getElementById("result").textContent=JSON.stringify(j,null,2)}catch(e){alert(e.message)}}
 showRes("dashboard");</script></html>';exit;
 }
 out(['success'=>false,'message'=>'Not found'],404);
}catch(Throwable $e){error_log($e->getMessage());out(['success'=>false,'message'=>'Server error'],500);}
