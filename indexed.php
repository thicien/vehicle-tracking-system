<?php
/***************************************************************
 * All-in-One Vehicle Tracking Single File App
 * - Save as index.php
 * - Edit DB config below
 * - Visit ?action=setup once to create DB & seed admin
 ***************************************************************/
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

/*********************
 * CONFIG
 *********************/
define('DB_HOST','127.0.0.1');
define('DB_NAME','tracking');       // DB name (create DB or CREATE via setup)
define('DB_USER','root');           // DB user
define('DB_PASS','your_db_password'); // DB password

// Base URL for fetch calls (auto-detect)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Connect DB (PDO)
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    // if connecting fails, some routes (like setup) may still run with DB creation
    $pdo = null;
}

/*********************
 * Helpers
 *********************/
function json_input(){
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return $data ?: $_POST;
}
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
function require_auth(){
    if(empty($_SESSION['uid'])){
        header("Location: ?action=login");
        exit;
    }
}

/*********************
 * ROUTING
 *********************/
$action = $_GET['action'] ?? '';

/*************************************************
 * ACTION: setup
 * Creates DB tables and seeds an admin user.
 * Visit once then remove or protect this route.
 *************************************************/
if($action === 'setup') {
    // Try to create DB if not exists (requires DB user privileges)
    try {
        $tmp = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tmp->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch(Exception $e){
        // ignore
    }

    // Reconnect with DB
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch(Exception $e){
        die("Unable to connect to DB - check credentials in the file. Error: " . $e->getMessage());
    }

    // Create tables
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  password_hash VARCHAR(255),
  role ENUM('admin','manager') DEFAULT 'manager',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  reg_no VARCHAR(50) DEFAULT NULL,
  api_key VARCHAR(128) DEFAULT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS locations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  speed_kmh DECIMAL(6,2) DEFAULT NULL,
  heading DECIMAL(6,2) DEFAULT NULL,
  battery DECIMAL(5,2) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle_created (vehicle_id, created_at),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS alerts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  type VARCHAR(100),
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved TINYINT(1) DEFAULT 0
);
SQL;

    $pdo->exec($sql);

    // Seed admin user
    $adminEmail = 'admin@example.com';
    $adminPass = 'Admin123!'; // change after login
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);

    // Insert admin if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$adminEmail]);
    if(!$stmt->fetch()){
        $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?, 'admin')")->execute(['Admin', $adminEmail, $hash]);
    }

    // Insert sample vehicle with API key
    $sampleApiKey = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO vehicles (name,reg_no,api_key) VALUES (?,?,?)");
    $stmt->execute(['Vehicle 1', 'REG-001', $sampleApiKey]);

    echo "<h2>Setup completed</h2>";
    echo "<p>Admin user: <strong>$adminEmail</strong></p>";
    echo "<p>Admin password: <strong>$adminPass</strong> — change after login</p>";
    echo "<p>Sample Vehicle API key: <strong>$sampleApiKey</strong></p>";
    echo "<p>Visit <a href='?action=login'>Login</a></p>";
    exit;
}

/*************************************************
 * API endpoint: receive (device -> POST JSON)
 * Expects: vehicle_id, lat, lon, optional: speed, heading, battery
 * Or accepts api_key (preferred)
 *************************************************/
if($action === 'receive') {
    header('Content-Type: application/json; charset=utf-8');
    if(!$pdo) { http_response_code(500); echo json_encode(['error'=>'db']); exit; }
    $d = json_input();

    // prefer api_key auth if provided
    $vehicle_id = isset($d['vehicle_id']) ? (int)$d['vehicle_id'] : null;
    $api_key = $d['api_key'] ?? null;

    if ($api_key && !$vehicle_id) {
        // find vehicle id by api_key
        $s = $pdo->prepare("SELECT id FROM vehicles WHERE api_key=? AND active=1 LIMIT 1");
        $s->execute([$api_key]);
        $r = $s->fetch();
        if($r) $vehicle_id = $r['id'];
    }

    if(!$vehicle_id || !isset($d['lat']) || !isset($d['lon'])) {
        http_response_code(400);
        echo json_encode(['error'=>'missing vehicle_id or lat/lon']);
        exit;
    }

    // basic validation
    $lat = (float)$d['lat'];
    $lon = (float)$d['lon'];
    if($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        http_response_code(400);
        echo json_encode(['error'=>'invalid coordinates']);
        exit;
    }

    $speed = isset($d['speed']) ? (float)$d['speed'] : null;
    $heading = isset($d['heading']) ? (float)$d['heading'] : null;
    $battery = isset($d['battery']) ? (float)$d['battery'] : null;

    $stmt = $pdo->prepare("INSERT INTO locations (vehicle_id,latitude,longitude,speed_kmh,heading,battery,created_at) VALUES (?,?,?,?,?,?,NOW())");
    $stmt->execute([$vehicle_id, $lat, $lon, $speed, $heading, $battery]);

    // example alert: overspeed > 120
    if($speed !== null && $speed > 120) {
        $pdo->prepare("INSERT INTO alerts (vehicle_id,type,message) VALUES (?,?,?)")
            ->execute([$vehicle_id, 'overspeed', "Speed {$speed} km/h"]);
    }

    echo json_encode(['status'=>'ok']);
    exit;
}

/*************************************************
 * API endpoint: latest
 * Returns latest location per vehicle
 *************************************************/
if($action === 'latest') {
    header('Content-Type: application/json; charset=utf-8');
    if(!$pdo){ http_response_code(500); echo json_encode(['error'=>'db']); exit; }
    $sql = "
      SELECT l.*, v.name, v.reg_no, v.api_key
      FROM locations l
      JOIN vehicles v ON v.id = l.vehicle_id
      WHERE l.id IN (SELECT MAX(id) FROM locations GROUP BY vehicle_id)
      ORDER BY l.created_at DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();
    echo json_encode(['data'=>$rows]);
    exit;
}

/*************************************************
 * API endpoint: history?vehicle_id= &limit=
 *************************************************/
if($action === 'history') {
    header('Content-Type: application/json; charset=utf-8');
    if(!$pdo){ http_response_code(500); echo json_encode(['error'=>'db']); exit; }
    $vid = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
    $limit = isset($_GET['limit']) ? min(2000, (int)$_GET['limit']) : 500;
    if(!$vid){ echo json_encode(['data'=>[]]); exit; }
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE vehicle_id=? ORDER BY created_at ASC LIMIT ?");
    $stmt->execute([$vid, $limit]);
    echo json_encode(['data'=>$stmt->fetchAll()]);
    exit;
}

/*************************************************
 * API endpoint: vehicles CRUD (admin)
 * - GET ?action=vehicles => list
 * - POST ?action=vehicles_save to create/update (name, reg_no, id)
 *************************************************/
if($action === 'vehicles_save') {
    header('Content-Type: application/json; charset=utf-8');
    require_auth();
    if(!$pdo){ http_response_code(500); echo json_encode(['error'=>'db']); exit; }
    $d = json_input();
    $id = isset($d['id']) ? (int)$d['id'] : 0;
    $name = $d['name'] ?? '';
    $reg = $d['reg_no'] ?? '';
    if(!$name){ http_response_code(400); echo json_encode(['error'=>'name required']); exit; }

    if($id>0){
        $stmt = $pdo->prepare("UPDATE vehicles SET name=?, reg_no=? WHERE id=?");
        $stmt->execute([$name, $reg, $id]);
        echo json_encode(['status'=>'ok']);
    } else {
        $api_key = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO vehicles (name, reg_no, api_key) VALUES (?,?,?)");
        $stmt->execute([$name, $reg, $api_key]);
        echo json_encode(['status'=>'ok','api_key'=>$api_key]);
    }
    exit;
}
if($action === 'vehicles_list') {
    header('Content-Type: application/json; charset=utf-8');
    if(!$pdo){ http_response_code(500); echo json_encode(['error'=>'db']); exit; }
    $rows = $pdo->query("SELECT * FROM vehicles ORDER BY id DESC")->fetchAll();
    echo json_encode(['data'=>$rows]);
    exit;
}

/*************************************************
 * AUTH: login / logout
 *************************************************/
if($action === 'login') {
    $error = '';
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $pass = $_POST['password'] ?? '';
        if($pdo){
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if($u && password_verify($pass, $u['password_hash'])) {
                $_SESSION['uid'] = $u['id'];
                $_SESSION['name'] = $u['name'];
                header("Location: index.php");
                exit;
            } else $error = "Invalid credentials";
        } else $error = 'DB connection not available';
    }
    // Render login form
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Login</title>
      <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-100 min-h-screen flex items-center justify-center">
      <div class="max-w-md w-full bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Vehicle Tracking - Login</h1>
        <?php if($error): ?>
          <div class="text-red-600 mb-3"><?= esc($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-3">
          <input name="email" type="email" required placeholder="Email" class="w-full border p-2 rounded" />
          <input name="password" type="password" required placeholder="Password" class="w-full border p-2 rounded" />
          <button class="w-full bg-blue-600 text-white p-2 rounded">Login</button>
        </form>
      </div>
    <!-- Code injected by live-server -->
<script>
	// <![CDATA[  <-- For SVG support
	if ('WebSocket' in window) {
		(function () {
			function refreshCSS() {
				var sheets = [].slice.call(document.getElementsByTagName("link"));
				var head = document.getElementsByTagName("head")[0];
				for (var i = 0; i < sheets.length; ++i) {
					var elem = sheets[i];
					var parent = elem.parentElement || head;
					parent.removeChild(elem);
					var rel = elem.rel;
					if (elem.href && typeof rel != "string" || rel.length == 0 || rel.toLowerCase() == "stylesheet") {
						var url = elem.href.replace(/(&|\?)_cacheOverride=\d+/, '');
						elem.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_cacheOverride=' + (new Date().valueOf());
					}
					parent.appendChild(elem);
				}
			}
			var protocol = window.location.protocol === 'http:' ? 'ws://' : 'wss://';
			var address = protocol + window.location.host + window.location.pathname + '/ws';
			var socket = new WebSocket(address);
			socket.onmessage = function (msg) {
				if (msg.data == 'reload') window.location.reload();
				else if (msg.data == 'refreshcss') refreshCSS();
			};
			if (sessionStorage && !sessionStorage.getItem('IsThisFirstTime_Log_From_LiveServer')) {
				console.log('Live reload enabled.');
				sessionStorage.setItem('IsThisFirstTime_Log_From_LiveServer', true);
			}
		})();
	}
	else {
		console.error('Upgrade your browser. This Browser is NOT supported WebSocket for Live-Reloading.');
	}
	// ]]>
</script>
</body>
    </html>
    <?php
    exit;
}

if($action === 'logout') {
    session_destroy();
    header("Location: ?action=login");
    exit;
}

/*************************************************
 * PROTECTED: All other pages require auth
 *************************************************/
if(empty($_SESSION['uid'])) {
    header("Location: ?action=login");
    exit;
}

/*************************************************
 * MAIN DASHBOARD (default)
 *************************************************/
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vehicle Tracking Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <style>#map{height:70vh}.small-map{height:40vh}</style>
</head>
<body class="bg-slate-50 min-h-screen">

<header class="bg-white shadow">
  <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
    <div>
      <h1 class="text-xl font-bold">Vehicle Tracking</h1>
      <div class="text-sm text-gray-500">Welcome, <?= esc($_SESSION['name'] ?? 'User') ?></div>
    </div>
    <div class="flex items-center gap-4">
      <a href="?action=vehicles" class="text-sm px-3 py-2 bg-gray-100 rounded">Vehicles</a>
      <a href="?action=logout" class="text-sm text-red-600">Logout</a>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto p-6">
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="col-span-3 bg-white p-4 rounded shadow">
      <div id="map"></div>
      <div class="mt-4 flex gap-2">
        <select id="vehicleSelect" class="border p-2 rounded">
          <option value="0">-- All Vehicles --</option>
        </select>
        <button id="btnTrack" class="px-3 py-2 bg-blue-600 text-white rounded">Track Vehicle</button>
        <button id="btnHistory" class="px-3 py-2 bg-gray-200 rounded">Show History</button>
      </div>
    </div>

    <div class="col-span-1 bg-white p-4 rounded shadow">
      <h2 class="font-semibold mb-3">Vehicles</h2>
      <div id="vehicleList" class="space-y-3 text-sm"></div>
      <h3 class="mt-4 font-semibold">Alerts</h3>
      <div id="alerts" class="text-sm text-red-600 mt-2"></div>
    </div>
  </div>

  <section id="historySection" class="mt-6 bg-white p-4 rounded shadow hidden">
    <h2 class="font-semibold mb-3">Vehicle History (Polyline)</h2>
    <div id="mapHistory" class="small-map"></div>
  </section>
</main>

<!-- Vehicles page modal or inline -->
<?php
// Vehicles page handling (simple CRUD UI) - route ?action=vehicles shows this portion
if($action === 'vehicles' || isset($_GET['action']) && $_GET['action']=='vehicles') {
    // nothing here — the JS will trigger opening the vehicles modal via fetch
}
?>

<script>
// Base url
const BASE = "<?= $baseUrl ?>";

// Map init
const map = L.map('map').setView([0,0], 2);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'&copy; OSM' }).addTo(map);
const markers = {};

// Utility: fetch JSON helper
async function fjson(path, opts={}) {
  const res = await fetch(path, opts);
  return await res.json();
}

// Load vehicles & latest
async function loadVehiclesAndLatest(){
  const vres = await fjson('?action=vehicles_list');
  const vehicles = vres.data || [];

  const select = document.getElementById('vehicleSelect');
  select.innerHTML = '<option value="0">-- All Vehicles --</option>';
  vehicles.forEach(v => {
    const o = document.createElement('option');
    o.value = v.id;
    o.text = v.name + (v.reg_no ? ' ('+v.reg_no+')' : '');
    select.appendChild(o);
  });

  // latest positions
  const latest = await fjson('?action=latest');
  renderLatest(latest.data || []);
  renderVehicleList(latest.data || []);
}

// Render latest markers
function renderLatest(data){
  // remove markers not in data? For simplicity update or create
  data.forEach(d => {
    const id = d.vehicle_id;
    const lat = parseFloat(d.latitude);
    const lon = parseFloat(d.longitude);
    const popup = `<strong>${d.name}</strong><br>${d.reg_no || ''}<br>${d.speed_kmh || ''} km/h<br>${d.created_at}`;

    if(markers[id]){
      markers[id].setLatLng([lat,lon]).setPopupContent(popup);
    } else {
      markers[id] = L.marker([lat,lon]).addTo(map).bindPopup(popup);
    }
  });

  // fit bounds
  const ms = Object.values(markers);
  if(ms.length){
    const group = L.featureGroup(ms);
    map.fitBounds(group.getBounds().pad(0.2));
  }
}

function renderVehicleList(data){
  const el = document.getElementById('vehicleList');
  el.innerHTML = '';
  data.forEach(d => {
    const div = document.createElement('div');
    div.className = 'p-2 border rounded flex justify-between items-center';
    div.innerHTML = `<div>
      <div class="font-semibold">${d.name || ('Vehicle '+d.vehicle_id)}</div>
      <div class="text-xs text-gray-500">${d.reg_no || ''}</div>
    </div>
    <div class="text-right">
      <div>${d.speed_kmh ? d.speed_kmh + ' km/h' : '-'}</div>
      <button class="mt-2 text-xs px-2 py-1 bg-blue-600 text-white rounded" onclick="center(${d.latitude},${d.longitude})">Center</button>
    </div>`;
    el.appendChild(div);
  });
}

function center(lat,lon){
  map.panTo([lat,lon]);
}

// Track / History controls
document.getElementById('btnTrack').addEventListener('click', ()=> {
  const v = document.getElementById('vehicleSelect').value;
  if(v === '0'){ alert('Select a vehicle to track'); return; }
  // center on latest marker
  const marker = markers[v];
  if(marker) map.panTo(marker.getLatLng());
});

document.getElementById('btnHistory').addEventListener('click', async ()=>{
  const v = document.getElementById('vehicleSelect').value;
  if(v === '0'){ alert('Choose vehicle'); return; }
  document.getElementById('historySection').classList.remove('hidden');
  // init history map
  const histMap = L.map('mapHistory').setView([0,0],2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'&copy; OSM' }).addTo(histMap);
  // fetch history
  const res = await fjson(`?action=history&vehicle_id=${v}&limit=1000`);
  const pts = (res.data || []).map(r => [parseFloat(r.latitude), parseFloat(r.longitude)]);
  if(pts.length === 0){ alert('No history'); return; }
  const poly = L.polyline(pts, {color:'blue'}).addTo(histMap);
  histMap.fitBounds(poly.getBounds().pad(0.2));
});

// Vehicles CRUD window (simple prompt-based)
async function registerVehicle(){
  const name = prompt('Vehicle name:');
  if(!name) return;
  const reg = prompt('Registration number (optional):', '');
  const res = await fjson('?action=vehicles_save', {
    method:'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({name, reg_no: reg})
  });
  if(res.api_key) alert('Vehicle created. API Key: ' + res.api_key);
  loadVehiclesAndLatest();
}

// quick button to add vehicle
const addBtn = document.createElement('button');
addBtn.innerText = 'Add Vehicle';
addBtn.className = 'ml-2 px-3 py-2 bg-green-600 text-white rounded text-sm';
addBtn.onclick = registerVehicle;
document.querySelector('header .max-w-7xl').appendChild(addBtn);

// Polling
loadVehiclesAndLatest();
setInterval(loadVehiclesAndLatest, 5000);
</script>

<!-- Code injected
