<?php
session_start();
$cfgf = __DIR__ . '/config.php';
function h($s){return htmlspecialchars((string)$s, ENT_QUOTES);}
function ph($m){$d=preg_replace('/\D/','',(string)$m); if(strlen($d)>10)$d=substr($d,-10); return strlen($d)==10?$d:'';}
function db(){static $p;global $cfgf;if(!$p){$c=require $cfgf;$p=new PDO($c['dsn'],$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}return $p;}
function q($sql,$a=[]){$s=db()->prepare($sql);$s->execute($a);return $s;}
function go($u){header("Location: $u");exit;}
function page($t,$b){
 $u=$_SESSION['u']??null; $nav='';
 if($u){ $cs=q("SELECT id,name FROM companies WHERE active=1")->fetchAll(); $cur=(int)($_SESSION['cid']??0);
  $opt='<option value="0">All companies</option>'; foreach($cs as $c)$opt.='<option value="'.$c['id'].'"'.($cur==$c['id']?' selected':'').'>'.h($c['name']).'</option>';
  $nav='<nav><b>Anivarya CRM</b> <a href="?p=home">Dashboard</a><a href="?p=customers">Customers</a><a href="?p=orders">Orders</a><a href="?p=export">Ads audience</a><a href="?p=settings">Settings</a><form method="get" style="display:inline"><input type="hidden" name="p" value="switch"><select name="c" onchange="this.form.submit()">'.$opt.'</select></form><a href="?p=logout">Logout</a></nav>'; }
 echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($t).'</title><style>
body{font-family:system-ui,sans-serif;margin:0;background:#f4f6f8;color:#222}nav{background:#0b5d4b;color:#fff;padding:10px;display:flex;flex-wrap:wrap;gap:12px;align-items:center}nav a{color:#fff;text-decoration:none}
main{max-width:1000px;margin:auto;padding:14px}.card{background:#fff;border-radius:8px;padding:14px;margin:10px 0;box-shadow:0 1px 3px #0002;overflow-x:auto}
table{border-collapse:collapse;width:100%}td,th{padding:6px;border-bottom:1px solid #eee;text-align:left;font-size:14px}input,select,button{padding:8px;margin:3px;font-size:15px}button{background:#0b5d4b;color:#fff;border:0;border-radius:6px}
.bar{background:#0b5d4b;height:14px;border-radius:4px}.k{display:inline-block;margin:8px 18px 8px 0}.k b{font-size:24px;display:block}.m{background:#e7f5ee;padding:8px;border-radius:6px}
</style></head><body>'.$nav.'<main><h2>'.h($t).'</h2>'.(isset($_SESSION['m'])?'<div class="m">'.h($_SESSION['m']).'</div>':'').$b.'</main></body></html>'; unset($_SESSION['m']); exit;
}
function csrf(){return '<input type="hidden" name="t" value="'.($_SESSION['t']??($_SESSION['t']=bin2hex(random_bytes(16)))).'">';}
function upsert($cid,$r){
 $m=ph($r['mobile']??''); $e=strtolower(trim($r['email']??'')); if(!$m&&!$e) return 'skipped';
 $con=in_array(strtolower(trim($r['consent']??'')),['1','yes','y','true'])?1:0;
 $f=[trim($r['name']??''),$m,$e,trim($r['state']??''),trim($r['pincode']??''),trim($r['type']??'')?:'B2C',trim($r['source']??'')?:'manual'];
 $x=q("SELECT id FROM customers WHERE company_id=? AND ((mobile<>'' AND mobile=?) OR (email<>'' AND email=?))",[$cid,$m,$e])->fetch();
 if($x){ q("UPDATE customers SET name=COALESCE(NULLIF(?,''),name),mobile=COALESCE(NULLIF(?,''),mobile),email=COALESCE(NULLIF(?,''),email),state=COALESCE(NULLIF(?,''),state),pincode=COALESCE(NULLIF(?,''),pincode),type=?,source=?,consent=CASE WHEN ?=1 THEN 1 ELSE consent END WHERE id=?",array_merge($f,[$con,$x['id']])); return 'merged'; }
 q("INSERT INTO customers(company_id,name,mobile,email,state,pincode,type,source,consent,created) VALUES(?,?,?,?,?,?,?,?,?,?)",array_merge([$cid],$f,[$con,date('Y-m-d H:i:s')])); return 'added';
}
function csvrows($file){ $h=fopen($file,'r'); $hd=array_map(fn($x)=>strtolower(trim($x)),fgetcsv($h)?:[]); $o=[]; while(($r=fgetcsv($h))!==false){ if(count($r)==count($hd)) $o[]=array_combine($hd,$r);} return $o; }
function addorder($cid,$r){
 $m=ph($r['mobile']??''); $no=trim($r['order_no']??''); $ch=trim($r['channel']??'')?:'manual'; $amt=(float)($r['amount']??0);
 if($no&&q("SELECT 1 FROM orders WHERE company_id=? AND order_no=? AND channel=?",[$cid,$no,$ch])->fetch()) return 'duplicate';
 $cu=null; if($m){ $cu=q("SELECT id FROM customers WHERE company_id=? AND mobile=?",[$cid,$m])->fetch(); if(!$cu){upsert($cid,['mobile'=>$m,'name'=>$r['name']??'','source'=>$ch]); $cu=q("SELECT id FROM customers WHERE company_id=? AND mobile=?",[$cid,$m])->fetch();} }
 q("INSERT INTO orders(company_id,customer_id,order_no,channel,amount,odate) VALUES(?,?,?,?,?,?)",[$cid,$cu['id']??null,$no,$ch,$amt,trim($r['date']??'')?:date('Y-m-d')]); return 'added';
}
// ---------- installer ----------
if(!file_exists($cfgf)){
 if($_SERVER['REQUEST_METHOD']=='POST'){
  $sq=$_POST['host']==='sqlite'; $dsn=$sq?'sqlite:'.__DIR__.'/crm.sqlite':'mysql:host='.$_POST['host'].';dbname='.$_POST['db'].';charset=utf8mb4';
  try{ $p=new PDO($dsn,$_POST['dbuser']??'',$_POST['dbpass']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
   $ai=$sq?'INTEGER PRIMARY KEY AUTOINCREMENT':'INTEGER PRIMARY KEY AUTO_INCREMENT';
   foreach(["CREATE TABLE companies(id $ai,name VARCHAR(150),gstin VARCHAR(20) DEFAULT '',licence VARCHAR(60) DEFAULT '',active INT DEFAULT 1)",
    "CREATE TABLE users(id $ai,email VARCHAR(150),pass VARCHAR(255),role VARCHAR(30) DEFAULT 'admin')",
    "CREATE TABLE customers(id $ai,company_id INT,name VARCHAR(150),mobile VARCHAR(15),email VARCHAR(150),state VARCHAR(60),pincode VARCHAR(10),type VARCHAR(30),source VARCHAR(60),consent INT DEFAULT 0,created VARCHAR(20))",
    "CREATE TABLE orders(id $ai,company_id INT,customer_id INT NULL,order_no VARCHAR(60),channel VARCHAR(60),amount DECIMAL(12,2),odate VARCHAR(12))"] as $s)$p->exec($s);
   foreach(['Kanu Pharma','Win2age Pharma','Anivarya Products Private Limited'] as $n)$p->prepare("INSERT INTO companies(name) VALUES(?)")->execute([$n]);
   $p->prepare("INSERT INTO users(email,pass) VALUES(?,?)")->execute([strtolower($_POST['email']),password_hash($_POST['pw'],PASSWORD_DEFAULT)]);
   file_put_contents($cfgf,'<?php return '.var_export(['dsn'=>$dsn,'user'=>$_POST['dbuser']??'','pass'=>$_POST['dbpass']??''],true).';'); go('index.php');
  }catch(Throwable $e){ $err=$e->getMessage(); }
 }
 page('Install Anivarya CRM',($err??'').'<form method="post" class="card">Database host (type <b>sqlite</b> to test without MySQL)<br><input name="host" value="localhost"><br>Database name<br><input name="db"><br>DB user<br><input name="dbuser"><br>DB password<br><input name="dbpass" type="password"><hr>Admin email<br><input name="email" type="email" required><br>Admin password<br><input name="pw" type="password" minlength="8" required><br><button>Install</button></form>');
}
// ---------- auth ----------
$p=$_GET['p']??'home';
if(!isset($_SESSION['u'])){
 if($_SERVER['REQUEST_METHOD']=='POST'){ $u=q("SELECT * FROM users WHERE email=?",[strtolower($_POST['email'])])->fetch();
  if($u&&password_verify($_POST['pw'],$u['pass'])){session_regenerate_id(true);$_SESSION['u']=$u['email'];$_SESSION['cid']=0;go('index.php');} $_SESSION['m']='Wrong email or password'; }
 page('Login','<form method="post" class="card"><input name="email" type="email" placeholder="Email" required><br><input name="pw" type="password" placeholder="Password" required><br><button>Login</button></form>');
}
if($_SERVER['REQUEST_METHOD']=='POST'&&!hash_equals($_SESSION['t']??'x',$_POST['t']??'')) {http_response_code(400);die('Bad token, go back and retry');}
$cid=(int)($_SESSION['cid']??0); $w=$cid?"company_id=$cid":"1=1";
$cos=q("SELECT id,name FROM companies WHERE active=1")->fetchAll(); $cn=array_column($cos,'name','id');
$target=$cid?:(int)($cos[0]['id']??1);
switch($p){
case 'logout': session_destroy(); go('index.php');
case 'switch': $_SESSION['cid']=(int)($_GET['c']??0); go('index.php');
case 'customers':
 if($_SERVER['REQUEST_METHOD']=='POST'){
  if(!empty($_FILES['f']['tmp_name'])){ $s=[]; foreach(csvrows($_FILES['f']['tmp_name']) as $r){$k=upsert($target,$r);$s[$k]=($s[$k]??0)+1;} $_SESSION['m']='Import into '.$cn[$target].': '.json_encode($s); }
  else { $_SESSION['m']='Customer '.upsert($target,$_POST); }
  go('?p=customers'); }
 $s=trim($_GET['s']??''); $rows=q("SELECT * FROM customers WHERE $w AND (name LIKE ? OR mobile LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT 200",["%$s%","%$s%","%$s%"])->fetchAll();
 $t='<div class="card"><form method="get"><input type="hidden" name="p" value="customers"><input name="s" placeholder="Search name / mobile / email" value="'.h($s).'"><button>Search</button></form></div>
 <div class="card"><b>Add customer</b> (saved into: '.h($cn[$target]??'').' — pick a company at top)<form method="post">'.csrf().'<input name="name" placeholder="Name"><input name="mobile" placeholder="Mobile"><input name="email" placeholder="Email"><input name="state" placeholder="State"><input name="pincode" placeholder="Pincode"><select name="type"><option>B2C</option><option>B2B</option><option>D2C</option><option>Wholesale</option><option>Retail</option><option>Doctor</option><option>Chemist</option><option>Distributor</option></select><label><input type="checkbox" name="consent" value="1">Allowed to message</label><button>Save</button></form>
 <hr><b>Import CSV</b> — columns: name,mobile,email,state,pincode,type,source,consent<form method="post" enctype="multipart/form-data">'.csrf().'<input type="file" name="f" accept=".csv" required><button>Import</button></form> Same mobile or email is merged, never duplicated.</div><div class="card"><table><tr><th>Name<th>Mobile<th>Email<th>State<th>Type<th>Source<th>Msg OK<th>Company';
 foreach($rows as $r)$t.='<tr><td>'.h($r['name']).'<td>'.h($r['mobile']).'<td>'.h($r['email']).'<td>'.h($r['state']).'<td>'.h($r['type']).'<td>'.h($r['source']).'<td>'.($r['consent']?'Yes':'No').'<td>'.h($cn[$r['company_id']]??'');
 page('Customers',$t.'</table></div>');
case 'orders':
 if($_SERVER['REQUEST_METHOD']=='POST'){
  if(!empty($_FILES['f']['tmp_name'])){ $s=[]; foreach(csvrows($_FILES['f']['tmp_name']) as $r){$k=addorder($target,$r);$s[$k]=($s[$k]??0)+1;} $_SESSION['m']='Orders imported: '.json_encode($s); }
  else $_SESSION['m']='Order '.addorder($target,$_POST);
  go('?p=orders'); }
 $rows=q("SELECT o.*,c.name cname,c.mobile FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE ".($cid?"o.company_id=$cid":'1=1')." ORDER BY o.id DESC LIMIT 200")->fetchAll();
 $t='<div class="card"><b>Add order</b> (into '.h($cn[$target]??'').')<form method="post">'.csrf().'<input name="order_no" placeholder="Order no"><input name="mobile" placeholder="Customer mobile"><input name="channel" list="ch" placeholder="Channel"><datalist id="ch"><option>Website<option>Shopify<option>Amazon<option>Flipkart<option>1mg<option>Blinkit<option>Instamart<option>ONDC<option>Retail<option>Wholesale</datalist><input name="amount" type="number" step="0.01" placeholder="Amount"><input name="date" type="date"><button>Save</button></form><hr>
 <b>Import CSV</b> — columns: order_no,mobile,channel,amount,date,name<form method="post" enctype="multipart/form-data">'.csrf().'<input type="file" name="f" accept=".csv" required><button>Import</button></form></div><div class="card"><table><tr><th>Date<th>Order<th>Channel<th>Amount<th>Customer<th>Company';
 foreach($rows as $r)$t.='<tr><td>'.h($r['odate']).'<td>'.h($r['order_no']).'<td>'.h($r['channel']).'<td>'.number_format($r['amount'],2).'<td>'.h(($r['cname']?:'Marketplace/anonymous').' '.$r['mobile']).'<td>'.h($cn[$r['company_id']]??'');
 page('Orders',$t.'</table></div>');
case 'export':
 if(isset($_GET['dl'])){ header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=audience_'.($_GET['dl']).'.csv');
  $o=fopen('php://output','w'); fputcsv($o,['email','phone']);
  foreach(q("SELECT email,mobile FROM customers WHERE $w AND consent=1")->fetchAll() as $r) fputcsv($o,[$r['email']?hash('sha256',$r['email']):'',$r['mobile']?hash('sha256','91'.$r['mobile']):'']); exit; }
 page('Ads audience (Meta / Google)','<div class="card">Download customers who agreed to be messaged, with email and phone already SHA-256 hashed as ad platforms require. Upload in Meta Ads Manager (Audiences, Custom audience, Customer list) or Google Ads (Audience manager, Customer list). Current company filter applies.<br><br><a href="?p=export&dl=meta"><button>Meta file</button></a> <a href="?p=export&dl=google"><button>Google file</button></a></div>');
case 'settings':
 if($_SERVER['REQUEST_METHOD']=='POST'){
  if(!empty($_POST['new'])) q("INSERT INTO companies(name) VALUES(?)",[$_POST['new']]);
  foreach($_POST['n']??[] as $id=>$n) q("UPDATE companies SET name=?,gstin=?,licence=?,active=? WHERE id=?",[$n,$_POST['g'][$id],$_POST['l'][$id],isset($_POST['a'][$id])?1:0,(int)$id]);
  $_SESSION['m']='Saved'; go('?p=settings'); }
 $t='<form method="post" class="card">'.csrf().'<table><tr><th>Company name<th>GSTIN<th>Drug licence<th>Active';
 foreach(q("SELECT * FROM companies")->fetchAll() as $c)$t.='<tr><td><input name="n['.$c['id'].']" value="'.h($c['name']).'"><td><input name="g['.$c['id'].']" value="'.h($c['gstin']).'"><td><input name="l['.$c['id'].']" value="'.h($c['licence']).'"><td><input type="checkbox" name="a['.$c['id'].']" '.($c['active']?'checked':'').'>';
 page('Settings',$t.'</table>Add company: <input name="new"><button>Save all</button></form>');
default:
 $kc=q("SELECT COUNT(*) c FROM customers WHERE $w")->fetch()['c']; $ko=q("SELECT COUNT(*) c,COALESCE(SUM(amount),0) s FROM orders WHERE $w")->fetch();
 $rep=q("SELECT COUNT(*) c FROM (SELECT customer_id FROM orders WHERE $w AND customer_id IS NOT NULL GROUP BY customer_id HAVING COUNT(*)>1) x")->fetch()['c'];
 $ch=q("SELECT channel,COUNT(*) c,SUM(amount) s FROM orders WHERE $w GROUP BY channel ORDER BY s DESC")->fetchAll(); $mx=max(1,...array_column($ch,'s')?:[1]);
 $t='<div class="card"><span class="k"><b>'.$kc.'</b>Customers</span><span class="k"><b>'.$ko['c'].'</b>Orders</span><span class="k"><b>₹'.number_format($ko['s']).'</b>Revenue</span><span class="k"><b>'.$rep.'</b>Repeat buyers</span></div><div class="card"><b>Revenue by channel</b><table>';
 foreach($ch as $r)$t.='<tr><td>'.h($r['channel']).'<td style="width:55%"><div class="bar" style="width:'.round($r['s']/$mx*100).'%"></div><td>₹'.number_format($r['s']).' ('.$r['c'].')';
 page('Dashboard — '.($cid?$cn[$cid]:'All companies'),$t.'</table></div>');
}
