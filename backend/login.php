<?php
session_start();
include "db.php";

if (isset($_SESSION['sp_user'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if (isset($_GET['kicked'])) {
    $error = "Your session has ended or your account was suspended. Please sign in again.";
}
if (isset($_GET['registered'])) {
    $error = "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM login_accounts WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $u = mysqli_fetch_assoc($result);

        if ($u && password_verify($password, $u['password'])) {

            if (!$u['is_approved']) {
                $error = "Your account access has been paused by an admin.";
            } else {
                $upd = mysqli_prepare($conn, "UPDATE login_accounts SET last_login = NOW(), last_seen = NOW(), is_online = 1 WHERE id = ?");
                mysqli_stmt_bind_param($upd, "i", $u['id']);
                mysqli_stmt_execute($upd);

                $_SESSION['sp_user'] = [
                    'id'       => $u['id'],
                    'name'     => $u['name'],
                    'username' => $u['username'],
                    'role'     => $u['role'],
                ];
                header("Location: index.php");
                exit;
            }
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Planner — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --night:#0a1613;--glow:#14b8a6;--glow2:#5eead4;
  --gold:#ffb648;--white:#f1f7f4;--muted:rgba(190,225,213,.55);
  --border:rgba(94,234,212,.18);
}
html,body{height:100%;font-family:'Sora',sans-serif;background:var(--night);color:var(--white);overflow:hidden}
.stars{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.star{position:absolute;border-radius:50%;background:#fff;animation:twinkle var(--d) ease-in-out infinite alternate}
@keyframes twinkle{from{opacity:var(--a)}to{opacity:calc(var(--a)*.15)}}
.shoot{position:fixed;width:130px;height:1.5px;background:linear-gradient(to right,transparent,rgba(255,255,255,.9),transparent);border-radius:2px;animation:shoot var(--sd) linear var(--ss) infinite;opacity:0;pointer-events:none}
@keyframes shoot{0%{opacity:0;transform:translate(0,0) rotate(-28deg)}6%{opacity:1}35%{opacity:0;transform:translate(500px,250px) rotate(-28deg)}100%{opacity:0}}
#globe{position:fixed;inset:0;width:100%;height:100%;z-index:1;opacity:0;animation:fadeC 2.2s ease .2s forwards}
@keyframes fadeC{to{opacity:1}}
.scene{position:fixed;inset:0;z-index:10;display:flex;align-items:center;justify-content:center;overflow-y:auto;padding:30px 16px}
.card{width:420px;max-width:100%;background:rgba(10,22,19,.80);backdrop-filter:blur(32px);-webkit-backdrop-filter:blur(32px);border:1px solid var(--border);border-radius:26px;padding:50px 46px 46px;box-shadow:0 0 0 1px rgba(20,184,166,.07),0 40px 100px rgba(3,8,7,.65),inset 0 1px 0 rgba(255,255,255,.06);position:relative;overflow:hidden;animation:cardIn .9s cubic-bezier(.16,1,.3,1) .35s both}
@keyframes cardIn{from{opacity:0;transform:translateY(36px) scale(.96)}to{opacity:1;transform:none}}
.card::before{content:'';position:absolute;top:-90px;left:50%;transform:translateX(-50%);width:340px;height:220px;background:radial-gradient(ellipse,rgba(20,184,166,.15) 0%,transparent 70%);animation:gp 4s ease-in-out infinite;pointer-events:none}
@keyframes gp{0%,100%{opacity:.6;transform:translateX(-50%) scale(1)}50%{opacity:1;transform:translateX(-50%) scale(1.18)}}
.dot-deco{position:absolute;border-radius:50%;animation:df var(--dd) ease-in-out infinite alternate}
@keyframes df{from{transform:translate(0,0)}to{transform:translate(var(--tx),var(--ty))}}
.logo-row{display:flex;align-items:center;gap:13px;margin-bottom:34px;animation:fadeUp .6s ease .7s both}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.logo-ring{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#14b8a6,#0f3d37);border:1px solid rgba(94,234,212,.4);display:flex;align-items:center;justify-content:center;box-shadow:0 0 22px rgba(20,184,166,.45);animation:lp 3s ease-in-out infinite;flex-shrink:0;overflow:hidden;padding:2px}
@keyframes lp{0%,100%{box-shadow:0 0 22px rgba(20,184,166,.45)}50%{box-shadow:0 0 36px rgba(20,184,166,.75)}}
.logo-ring img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.logo-ring{position:relative}
.logo-ring::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:1px solid rgba(255,182,72,.35);pointer-events:none}
.logo-name{font-family:'Fraunces',serif;font-size:18px;font-weight:600;letter-spacing:-.2px}
.logo-sub{font-size:11.5px;color:var(--muted);margin-top:2px}
h2{font-family:'Fraunces',serif;font-size:30px;font-weight:600;letter-spacing:-.3px;line-height:1.2;margin-bottom:6px;background:linear-gradient(135deg,#f1f7f4 30%,#7fe0cf);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;animation:fadeUp .6s ease .8s both}
.sub{font-size:13.5px;color:var(--muted);margin-bottom:30px;animation:fadeUp .6s ease .9s both}
.alert{background:rgba(220,50,50,.12);border:1px solid rgba(220,80,80,.3);border-radius:11px;padding:11px 15px;font-size:13.5px;color:#ff9090;margin-bottom:20px;display:flex;align-items:center;gap:10px;animation:shake .4s ease}
.alert.kicked{background:rgba(255,100,0,.1);border-color:rgba(255,130,0,.35);color:#ffb870}
@keyframes shake{0%{transform:translateX(-6px)}25%{transform:translateX(6px)}50%{transform:translateX(-4px)}75%{transform:translateX(4px)}100%{transform:none}}
.alert-dot{width:18px;height:18px;flex-shrink:0;border-radius:50%;background:#e74c3c;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center}
.alert-dot.orange{background:#e67e22}
.field{margin-bottom:18px;animation:fadeUp .6s ease var(--fd) both}
label{display:block;font-size:12.5px;font-weight:500;color:rgba(172,224,208,.8);margin-bottom:8px;letter-spacing:.3px}
.iw{position:relative}
input{width:100%;padding:13px 16px;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px;color:var(--white);font-size:14px;font-family:'Sora',sans-serif;outline:none;transition:all .2s}
input:focus{border-color:rgba(94,234,212,.6);background:rgba(20,184,166,.06);box-shadow:0 0 0 4px rgba(20,184,166,.1)}
input::placeholder{color:rgba(172,224,208,.3)}
.tp{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:rgba(172,224,208,.5)}
.tp svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#14b8a6,#0d9488);border:none;border-radius:12px;color:#fff;font-size:14.5px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;margin-top:6px;box-shadow:0 10px 30px rgba(20,184,166,.4);transition:all .2s;position:relative;overflow:hidden;animation:fadeUp .6s ease 1.05s both}
.btn::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.14),transparent);opacity:0;transition:opacity .2s}
.btn:hover{transform:translateY(-2px);box-shadow:0 10px 36px rgba(20,184,166,.58)}
.btn:hover::after{opacity:1}
.btn:active{transform:scale(.98)}
.hr-row{display:flex;align-items:center;gap:12px;margin:22px 0 0}
.hr-row hr{flex:1;border:none;border-top:1px solid rgba(94,234,212,.12)}
.hr-row span{font-size:11.5px;color:var(--muted)}
.foot{text-align:center;font-size:13px;color:var(--muted);margin-top:18px;animation:fadeUp .6s ease 1.2s both}
.foot a{color:var(--glow2);font-weight:600;text-decoration:none;transition:color .2s}
.foot a:hover{color:var(--gold)}
.hint-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(20,184,166,.08);border:1px solid rgba(94,234,212,.2);border-radius:8px;padding:8px 12px;font-size:11px;color:rgba(120,220,195,.7);margin-top:14px;width:100%;justify-content:center;animation:fadeUp .6s ease 1.3s both;line-height:1.6}
.hint-badge b{color:rgba(195,230,215,.95)}
</style>
</head>
<body>
<div class="stars" id="stars"></div>
<div class="shoot" style="top:12%;left:8%;--sd:9s;--ss:0s"></div>
<div class="shoot" style="top:30%;left:55%;--sd:12s;--ss:4s"></div>
<div class="shoot" style="top:65%;left:2%;--sd:15s;--ss:8s"></div>
<canvas id="globe"></canvas>

<div class="scene">
  <div class="card">
    <div class="dot-deco" style="width:6px;height:6px;background:#ffb648;opacity:.55;top:16px;right:26px;--dd:3.2s;--tx:4px;--ty:-4px;box-shadow:0 0 8px #ffb648"></div>
    <div class="dot-deco" style="width:4px;height:4px;background:#5eead4;opacity:.4;bottom:55px;right:18px;--dd:4.5s;--tx:-3px;--ty:5px;box-shadow:0 0 6px #5eead4"></div>
    <div class="dot-deco" style="width:5px;height:5px;background:#14b8a6;opacity:.3;bottom:100px;left:16px;--dd:3.8s;--tx:4px;--ty:-4px"></div>

    <div class="logo-row">
      <div class="logo-ring">
        <img src="assets/img/logo.jpg" alt="School logo">
      </div>
      <div>
        <div class="logo-name">Student Planner</div>
        <div class="logo-sub">Plan smarter. Stress less.</div>
      </div>
    </div>

    <h2>Welcome back</h2>
    <p class="sub">Sign in to open your planner, tasks and schedule.</p>

    <?php if ($error): ?>
    <div class="alert<?php echo isset($_GET['kicked']) ? ' kicked' : ''; ?>">
      <div class="alert-dot<?php echo isset($_GET['kicked']) ? ' orange' : ''; ?>">!</div>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['registered']) && isset($_GET['pending'])): ?>
    <div class="alert" style="background:rgba(255,169,77,.1);border-color:rgba(255,169,77,.3);color:#ffc98a">
      <div class="alert-dot" style="background:#ffa94d">!</div>
      <span>Account created! Your admin request is awaiting approval from an existing admin.</span>
    </div>
    <?php elseif (isset($_GET['registered'])): ?>
    <div class="alert" style="background:rgba(46,207,122,.1);border-color:rgba(46,207,122,.3);color:#8ff2c0">
      <div class="alert-dot" style="background:#2ecf7a">✓</div>
      <span>Account created! Please sign in to continue.</span>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="field" style="--fd:1s">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter your username" autocomplete="username"
               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
      </div>
      <div class="field" style="--fd:1.05s">
        <label>Password</label>
        <div class="iw">
          <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password">
          <button type="button" class="tp" onclick="tpw()">
            <svg id="eye" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>
      <button class="btn" type="submit">Sign In</button>
    </form>

    <div class="hr-row"><hr><span>or</span><hr></div>
    <div class="foot">Don't have an account? <a href="Register.php">Create Account</a></div>
    <div class="hint-badge">Create account &nbsp;—&nbsp; <b></b> / <b></b> (after import, register your own instead)</div>
  </div>
</div>

<script>
function tpw(){
  const i=document.getElementById('password'),h=i.type==='password';
  i.type=h?'text':'password';
  document.getElementById('eye').innerHTML=h
    ?'<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    :'<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
const starsEl=document.getElementById('stars');
for(let i=0;i<200;i++){
  const s=document.createElement('div');s.className='star';
  const sz=Math.random()*2.2+.4,a=Math.random()*.8+.12;
  s.style.cssText=`width:${sz}px;height:${sz}px;top:${Math.random()*100}%;left:${Math.random()*100}%;--a:${a};--d:${(Math.random()*4+2).toFixed(1)}s;animation-delay:${(Math.random()*6).toFixed(1)}s`;
  starsEl.appendChild(s);
}
const canvas=document.getElementById('globe'),ctx=canvas.getContext('2d');
let W,H,cx,cy,R;
function resize(){W=canvas.width=window.innerWidth;H=canvas.height=window.innerHeight;cx=W/2;cy=H/2;R=Math.min(W,H)*.40;}
resize();window.addEventListener('resize',resize);
const cities=[{la:14.5,lo:121},{la:51.5,lo:-.12},{la:40.7,lo:-74},{la:35.7,lo:139.7},{la:48.9,lo:2.3},{la:-33.9,lo:151.2},{la:55.7,lo:37.6},{la:19.4,lo:-99.1},{la:-23.5,lo:-46.6},{la:1.3,lo:103.8},{la:28.6,lo:77.2},{la:-1.3,lo:36.8},{la:30,lo:31.2},{la:6.5,lo:3.4},{la:37.6,lo:-122.4},{la:59.9,lo:10.7},{la:41.9,lo:12.5},{la:34.7,lo:135.5},{la:43.7,lo:-79.4},{la:-34.6,lo:-58.4}];
const arcPairs=[[0,1],[1,2],[2,4],[4,6],[0,9],[9,7],[7,8],[2,14],[14,10],[5,9],[12,3],[13,11],[15,4],[16,1],[17,3],[18,2],[19,7]];
const arcs=arcPairs.map(([a,b])=>({a:cities[a],b:cities[b],prog:Math.random()*1.2,speed:.0025+Math.random()*.003}));
function v3(la,lo,rot){const phi=(90-la)*Math.PI/180,th=(lo+rot)*Math.PI/180;return{x:Math.sin(phi)*Math.cos(th),y:Math.cos(phi),z:Math.sin(phi)*Math.sin(th)};}
function proj(v){return{px:cx+v.x*R,py:cy-v.y*R,vis:v.z>-.05}}
function drawGrid(rot){ctx.lineWidth=.5;for(let la=-80;la<=80;la+=20){ctx.beginPath();let f=true;for(let lo=0;lo<=360;lo+=3){const p=proj(v3(la,lo,rot));if(p.vis){f?ctx.moveTo(p.px,p.py):ctx.lineTo(p.px,p.py);f=false;}else f=true;}ctx.strokeStyle='rgba(20,184,166,.07)';ctx.stroke();}for(let lo=0;lo<360;lo+=20){ctx.beginPath();let f=true;for(let la=-90;la<=90;la+=3){const p=proj(v3(la,lo,rot));if(p.vis){f?ctx.moveTo(p.px,p.py):ctx.lineTo(p.px,p.py);f=false;}else f=true;}ctx.strokeStyle='rgba(20,184,166,.07)';ctx.stroke();}}
function drawArcs(rot){arcs.forEach(a=>{a.prog=(a.prog+a.speed)%1.5;const pr=Math.min(a.prog,1);if(pr<.01)return;const vA=v3(a.a.la,a.a.lo,rot),vB=v3(a.b.la,a.b.lo,rot);ctx.beginPath();let st=false;for(let i=0;i<=50*pr;i++){const k=i/50,lx=vA.x+(vB.x-vA.x)*k,ly=vA.y+(vB.y-vA.y)*k,lz=vA.z+(vB.z-vA.z)*k;const len=Math.sqrt(lx*lx+ly*ly+lz*lz)||1,lift=1.05+.04*Math.sin(k*Math.PI);const p=proj({x:lx/len*lift,y:ly/len*lift,z:lz/len*lift});if(p.vis){st?ctx.lineTo(p.px,p.py):ctx.moveTo(p.px,p.py);st=true;}}ctx.strokeStyle=`rgba(94,234,212,${Math.min(pr*2,1)*.65})`;ctx.lineWidth=.9;ctx.stroke();const hx=vA.x+(vB.x-vA.x)*pr,hy=vA.y+(vB.y-vA.y)*pr,hz=vA.z+(vB.z-vA.z)*pr;const hl=Math.sqrt(hx*hx+hy*hy+hz*hz)||1;const hp=proj({x:hx/hl*1.07,y:hy/hl*1.07,z:hz/hl*1.07});if(hp.vis&&pr<.99){ctx.beginPath();ctx.arc(hp.px,hp.py,2.8,0,Math.PI*2);ctx.fillStyle='rgba(110,235,215,.95)';ctx.fill();}});}
function drawCities(rot){cities.forEach(c=>{const p=proj(v3(c.la,c.lo,rot));if(!p.vis)return;ctx.beginPath();ctx.arc(p.px,p.py,2.6,0,Math.PI*2);ctx.fillStyle='rgba(255,182,72,.92)';ctx.fill();ctx.beginPath();ctx.arc(p.px,p.py,5.5,0,Math.PI*2);ctx.strokeStyle='rgba(255,182,72,.22)';ctx.lineWidth=.7;ctx.stroke();});}
let rot=0;
function frame(){ctx.clearRect(0,0,W,H);rot+=.10;const atm=ctx.createRadialGradient(cx-R*.3,cy-R*.3,R*.08,cx,cy,R*1.2);atm.addColorStop(0,'rgba(20,184,166,.12)');atm.addColorStop(.65,'rgba(10,90,80,.06)');atm.addColorStop(1,'rgba(4,12,10,0)');ctx.beginPath();ctx.arc(cx,cy,R*1.2,0,Math.PI*2);ctx.fillStyle=atm;ctx.fill();const grd=ctx.createRadialGradient(cx-R*.3,cy-R*.32,R*.04,cx,cy,R);grd.addColorStop(0,'rgba(12,45,40,.78)');grd.addColorStop(.55,'rgba(8,30,26,.84)');grd.addColorStop(1,'rgba(6,16,14,.92)');ctx.beginPath();ctx.arc(cx,cy,R,0,Math.PI*2);ctx.fillStyle=grd;ctx.fill();ctx.save();ctx.beginPath();ctx.arc(cx,cy,R,0,Math.PI*2);ctx.clip();drawGrid(rot);drawArcs(rot);drawCities(rot);ctx.restore();ctx.beginPath();ctx.arc(cx,cy,R,0,Math.PI*2);ctx.strokeStyle='rgba(20,184,166,.20)';ctx.lineWidth=1;ctx.stroke();const sp=ctx.createRadialGradient(cx-R*.38,cy-R*.38,0,cx-R*.2,cy-R*.2,R*.52);sp.addColorStop(0,'rgba(185,232,215,.10)');sp.addColorStop(1,'transparent');ctx.beginPath();ctx.arc(cx,cy,R,0,Math.PI*2);ctx.fillStyle=sp;ctx.fill();requestAnimationFrame(frame);}
frame();
</script>
</body>
</html>
