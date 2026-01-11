<?php
/* ================= CONFIG ================= */
$baseDir = __DIR__.'/uploads';
$relDir  = 'uploads';

/* weserv base */
$weservThumb = 'https://images.weserv.nl/?h=200&url=';
$weservView  = 'https://images.weserv.nl/?url=';

/* ================= PATH ================= */
$path = trim($_GET['path'] ?? '', '/');
$real = realpath("$baseDir/$path");
if (!$real || strpos($real, realpath($baseDir)) !== 0) {
	$path = '';
	$real = $baseDir;
}

/* ===== BACK / HOME ===== */
$hasParent  = ($path !== '');
$parentPath = $hasParent ? dirname($path) : '';
if ($parentPath === '.') $parentPath = '';

/* ================= ACTIONS ================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {

	// upload
	if (!empty($_FILES['files'])) {
		foreach ($_FILES['files']['tmp_name'] as $i=>$t) {
			if (is_uploaded_file($t)) {
				move_uploaded_file($t, "$real/".basename($_FILES['files']['name'][$i]));
			}
		}
		exit('OK');
	}

	// delete
	if (isset($_POST['delete'])) {
		$f = realpath("$baseDir/".$_POST['delete']);
		if ($f && strpos($f, realpath($baseDir))===0) {
			is_dir($f) ? rmdir($f) : unlink($f);
		}
		exit('OK');
	}

	// mkdir
	if (isset($_POST['mkdir'])) {
		mkdir("$real/".basename($_POST['mkdir']));
		exit('OK');
	}

	// rename
	if (isset($_POST['old'],$_POST['new'])) {
		$old = realpath("$baseDir/".$_POST['old']);
		$new = basename($_POST['new']);
		if ($old && strpos($old, realpath($baseDir))===0) {
			rename($old, dirname($old)."/$new");
		}
		exit('OK');
	}
}

/* ================= LIST ================= */
$dirs=$imgs=[];
foreach (array_diff(scandir($real),['.','..']) as $f) {
	if (is_dir("$real/$f")) $dirs[]=$f;
	elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i',$f)) $imgs[]=$f;
}

/* ================= BASE URL (FOR WESERV) ================= */
/* force HTTPS for weserv stability */
$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Gallery</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/spotlight.js/dist/css/spotlight.min.css">

<style>
body{margin:0;background:#111;color:#eee;font-family:sans-serif}
header{background:#000;padding:8px;display:flex;gap:8px;align-items:center}
button{background:#2196f3;border:0;color:#fff;padding:6px 10px;border-radius:4px;cursor:pointer}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;padding:10px}
.item{background:#222;border-radius:6px;overflow:hidden;position:relative}

img{width:100%;height:140px;object-fit:cover}

.folder{
	height:140px;
	display:flex;
	align-items:center;
	justify-content:center;
	font-size:48px;
	color:#2196f3;
}

.name{
	padding:4px 6px;
	background:#0008;
	font-size:13px;
	cursor:text;
	white-space:nowrap;
	overflow:hidden;
	text-overflow:ellipsis;
}

.overlay{
	position:absolute;inset:0;
	background:linear-gradient(to top,rgba(0,0,0,.7),transparent);
	opacity:0;transition:.2s;
	display:flex;align-items:flex-end;
	pointer-events:none;
}
.item:hover .overlay{opacity:1}
.overlay button{pointer-events:auto;margin:5px}
</style>
</head>
<body>

<header>
<?php if ($hasParent): ?>
<button onclick="go('<?= urlencode($parentPath) ?>')">⬅ Back</button>
<?php endif; ?>
<button onclick="go('')">🏠 Home</button>
<button onclick="upload()">Upload</button>
<button onclick="mkdir()">New Folder</button>
</header>

<div class="grid">

<?php foreach($dirs as $d): ?>
<div class="item">
<a href="?path=<?= urlencode(trim("$path/$d",'/')) ?>">
<div class="folder">📁</div>
</a>

<div class="name" contenteditable
onblur="renameItem('<?= "$path/$d" ?>',this.innerText)">
<?= htmlspecialchars($d) ?>
</div>

<div class="overlay">
<button onclick="del('<?= "$path/$d" ?>')">Delete</button>
</div>
</div>
<?php endforeach ?>

<?php foreach($imgs as $i):
$src = "$relDir/$path/$i";
$abs = $baseUrl.'/'.ltrim($src,'/');
$ver = @filemtime("$real/$i") ?: time();

/* weserv urls */
$thumbUrl = $weservThumb . urlencode($abs);
$viewUrl  = $weservView  . urlencode($abs);
?>
<div class="item">
<a class="spotlight" href="<?= $viewUrl ?>">
<img src="<?= $thumbUrl ?>"
onerror="this.src='<?= $src ?>'">
</a>

<div class="name" contenteditable
onblur="renameItem('<?= "$path/$i" ?>',this.innerText)">
<?= htmlspecialchars($i) ?>
</div>

<div class="overlay">
<button onclick="del('<?= "$path/$i" ?>')">Delete</button>
</div>
</div>
<?php endforeach ?>

</div>

<input id="file" type="file" multiple hidden>

<script src="https://cdn.jsdelivr.net/npm/spotlight.js/dist/spotlight.bundle.js"></script>

<script>
function go(p){
	location.href = p ? '?path='+p : '?';
}

function upload(){
	const file = document.getElementById('file');

	file.onchange = () => {
		const f = new FormData();
		for (const x of file.files) {
			f.append('files[]', x);
		}

		fetch(location.href, {
			method: 'POST',
		body: f
		}).then(() => location.reload());
	};

	file.click();
}


function del(p){
	if(confirm('Delete?'))
		fetch('',{
			method:'POST',
		headers:{'Content-Type':'application/x-www-form-urlencoded'},
		body:'delete='+encodeURIComponent(p)
		}).then(()=>location.reload());
}

function mkdir(){
	let n=prompt('Folder name');
	if(n)fetch('',{
		method:'POST',
		headers:{'Content-Type':'application/x-www-form-urlencoded'},
		body:'mkdir='+encodeURIComponent(n)
	}).then(()=>location.reload());
}

function renameItem(oldp,newn){
	newn=newn.trim();
	if(!newn)return;
	fetch('',{
		method:'POST',
	   headers:{'Content-Type':'application/x-www-form-urlencoded'},
	   body:'old='+encodeURIComponent(oldp)+'&new='+encodeURIComponent(newn)
	});
}
</script>
</body>
</html>
