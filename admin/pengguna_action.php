<?php
/** Server-side account management; no client-controlled role checks. */
require_once __DIR__ . '/../auth/session.php';
$actor=require_role('admin');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metode tidak diizinkan.');}
verify_csrf();$pdo=db();$action=(string)($_POST['action']??'');
function user_action_fail(string $message):void{flash('error',$message);redirect('admin/pengguna.php');}
try{
 if($action==='toggle'){
  $id=valid_nonnegative_int($_POST['id']??'');if(!$id||$id<1)throw new DomainException('Pengguna tidak valid.');
  if($id===(int)$actor['id'])throw new DomainException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
  db_transaction(function(PDO $pdo)use($id):void{$stmt=$pdo->prepare('SELECT role,is_active FROM users WHERE id=? FOR UPDATE');$stmt->execute([$id]);$target=$stmt->fetch();if(!$target)throw new DomainException('Pengguna tidak ditemukan.');$new=(int)$target['is_active']===1?0:1;if($target['role']==='admin'&&$new===0){$count=$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();if((int)$count<=1)throw new DomainException('Minimal satu Admin/Laboran aktif harus dipertahankan.');}$stmt=$pdo->prepare('UPDATE users SET is_active=? WHERE id=?');$stmt->execute([$new,$id]);});
  flash('success','Status akun berhasil diperbarui.');redirect('admin/pengguna.php');
 }
 if($action!=='save')throw new DomainException('Aksi tidak dikenal.');
 $id=valid_nonnegative_int($_POST['id']??'0');$full=trim((string)($_POST['full_name']??''));$username=trim((string)($_POST['username']??''));$role=(string)($_POST['role']??'');$type=(string)($_POST['user_type']??'');$identity=trim((string)($_POST['identity_no']??''));$password=(string)($_POST['password']??'');$active=($_POST['is_active']??'1')==='0'?0:1;
 if($id===null||$full===''||mb_strlen($full)>100)throw new DomainException('Nama lengkap wajib diisi dan maksimal 100 karakter.');
 if($username===''||mb_strlen($username)>50||!preg_match('/^[A-Za-z0-9._-]+$/',$username))throw new DomainException('Username wajib diisi; gunakan huruf, angka, titik, garis bawah, atau tanda hubung.');
 if(!in_array($role,['admin','peminjam'],true))throw new DomainException('Role pengguna tidak valid.');
 if(!in_array($type,['laboran','mahasiswa','dosen'],true))throw new DomainException('Jenis pengguna tidak valid.');
 if(($role==='admin'&&$type!=='laboran')||($role==='peminjam'&&$type==='laboran'))throw new DomainException('Jenis pengguna tidak sesuai dengan role yang dipilih.');
 if(mb_strlen($identity)>30)throw new DomainException('NIM/NIDN maksimal 30 karakter.');
 if($id===0&&$password==='')throw new DomainException('Password wajib diisi untuk akun baru.');
 if($id>0&&$password!==''&&strlen($password)<8)throw new DomainException('Password baru minimal 8 karakter.');
 $stmt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? AND id<>?');$stmt->execute([$username,$id]);if((int)$stmt->fetchColumn())throw new DomainException('Username sudah digunakan.');
 if($id>0){
  $currentStmt=$pdo->prepare('SELECT role,is_active FROM users WHERE id=?');$currentStmt->execute([$id]);$current=$currentStmt->fetch();if(!$current)throw new DomainException('Pengguna tidak ditemukan.');
  if($id===(int)$actor['id']&&$active===0)throw new DomainException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
  if($current['role']==='admin'&&((int)$current['is_active']===1)&&($role!=='admin'||$active===0)){$count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();if($count<=1)throw new DomainException('Minimal satu Admin/Laboran aktif harus dipertahankan.');}
  if($password!==''){$hash=password_hash($password,PASSWORD_DEFAULT);$stmt=$pdo->prepare('UPDATE users SET full_name=?,username=?,password_hash=?,role=?,identity_no=?,user_type=?,is_active=? WHERE id=?');$stmt->execute([$full,$username,$hash,$role,$identity?:null,$type,$active,$id]);}
  else{$stmt=$pdo->prepare('UPDATE users SET full_name=?,username=?,role=?,identity_no=?,user_type=?,is_active=? WHERE id=?');$stmt->execute([$full,$username,$role,$identity?:null,$type,$active,$id]);}
 }else{$hash=password_hash($password,PASSWORD_DEFAULT);$stmt=$pdo->prepare('INSERT INTO users(full_name,username,password_hash,role,identity_no,user_type,is_active) VALUES(?,?,?,?,?,?,1)');$stmt->execute([$full,$username,$hash,$role,$identity?:null,$type]);}
 flash('success',$id>0?'Data pengguna berhasil diperbarui.':'Pengguna baru berhasil dibuat.');redirect('admin/pengguna.php');
}catch(DomainException $e){user_action_fail($e->getMessage());}catch(PDOException $e){error_log('User update failed: '.$e->getMessage());user_action_fail($e->getCode()==='23000'?'Username sudah digunakan.':'Perubahan pengguna tidak berhasil disimpan.');}catch(Throwable $e){error_log('User update failed: '.$e->getMessage());user_action_fail('Perubahan pengguna tidak berhasil disimpan.');}
